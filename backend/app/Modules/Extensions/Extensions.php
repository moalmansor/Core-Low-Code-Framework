<?php

declare(strict_types=1);

namespace App\Modules\Extensions;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Extensions\Models\Extension;
use App\Modules\Extensions\Models\ExtensionVersion;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The life of an extension (specification §4.19, architecture §19.13,
 * ADR-0051): write → test in the sandbox → submit → approve (by someone
 * else holding Approve code) → deploy; every version is kept, and rolling
 * back is deploying an earlier approved version.
 *
 * - Saving the code of a version that is still a draft (or was rejected, or
 *   waits for approval) replaces it and clears its test and submission.
 *   Saving after a version was approved starts the next version.
 * - Submitting needs a passed test of exactly the code being submitted.
 * - Nobody approves their own code: the author of the version and the last
 *   person who changed the extension are refused.
 * - Every step is audited.
 */
final class Extensions
{
    public const MIN_TIMEOUT = 50;

    public const MAX_TIMEOUT = 10_000;

    public function __construct(
        private readonly ExtensionStore $store,
        private readonly HookRunner $runner,
        private readonly AuditWriter $audit,
        private readonly AccessResolver $access,
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $records,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(User $user, array $data): Extension
    {
        $ext = new Extension;
        $ext->forceFill(['key' => $data['key'], 'type' => $data['type'], 'status' => 'draft'] + $this->settings($data, null));

        return DB::transaction(function () use ($ext, $user, $data): Extension {
            $ext->save();
            $this->newVersion($ext, $user, (string) $data['source'], $this->manifest($data));
            $this->audit->record('extension.created', 'config', null, 'extension', $ext->id, ['key' => $ext->key, 'type' => $ext->type], $user->id, null, $ext->form_id);

            return $ext->refresh();
        });
    }

    /** @param  array<string, mixed>  $data */
    public function save(Extension $ext, User $user, array $data): Extension
    {
        $this->notDisabled($ext);

        return DB::transaction(function () use ($ext, $user, $data): Extension {
            $ext->forceFill($this->settings($data, $ext));
            $current = $this->current($ext);
            $editable = $current !== null && $current->approved_at === null;
            $manifest = $this->manifest($data);
            if ($editable) {
                $checksum = $this->store->write($ext, $current, (string) $data['source'], $manifest);
                $changed = $checksum !== $current->checksum || $manifest !== $current->manifest;
                $current->forceFill(['checksum' => $checksum, 'manifest' => $manifest, 'submitted_by' => $user->id, 'rejected_reason' => null]
                    + ($changed ? ['test_status' => 'not_run', 'test_report' => null] : []))->save();
                $ext->status = $changed ? 'draft' : $ext->status;
            } else {
                $this->newVersion($ext, $user, (string) $data['source'], $manifest);
                $ext->status = 'draft';
            }
            $ext->save();
            $this->audit->record('extension.saved', 'config', null, 'extension', $ext->id, ['key' => $ext->key, 'version' => $this->current($ext)?->version], $user->id, null, $ext->form_id);

            return $ext->refresh();
        });
    }

    /**
     * Runs the current version in the sandbox against sample values or a real record, keeping the report.
     *
     * @param  array{record?: string|null, values?: array<string, mixed>|null, extra?: array<string, mixed>|null, body?: array<string, mixed>|null}  $input
     * @return array<string, mixed>
     */
    public function test(Extension $ext, User $user, array $input): array
    {
        $this->notDisabled($ext);
        $version = $this->current($ext) ?? throw ValidationException::withMessages(['extension' => __('extensions.no_version')]);
        if (! $ext->isServer()) {
            // Client code is compiled when saved; it runs only in the browser's sandboxed frame.
            $report = ['ok' => true, 'compiled' => true, 'bytes' => strlen($this->store->build($version, $ext)), 'logs' => [], 'duration_ms' => 0];
        } elseif ($ext->type === 'api_endpoint') {
            $report = $this->runner->test($ext, $version, $user, 'api', '', [], null, null, [], (array) ($input['body'] ?? []));
        } else {
            [$values, $old, $record, $formKey] = $this->sample($ext, $user, $input);
            $report = $this->runner->test($ext, $version, $user, (string) $ext->hook_point, $formKey, $values, $old, $record, (array) ($input['extra'] ?? []));
        }
        $report['checksum'] = $version->checksum;
        $report['at'] = now()->toIso8601String();
        $version->forceFill(['test_status' => $report['ok'] ? 'passed' : 'failed', 'test_report' => $report])->save();
        if (in_array($ext->status, ['draft', 'testing', 'rejected'], true)) {
            $ext->forceFill(['status' => $report['ok'] ? 'testing' : 'draft'])->save();
        }
        $this->audit->record('extension.tested', 'config', null, 'extension', $ext->id, ['key' => $ext->key, 'version' => $version->version, 'ok' => $report['ok']], $user->id, null, $ext->form_id);

        return $report;
    }

    public function submit(Extension $ext, User $user): void
    {
        $this->notDisabled($ext);
        $version = $this->current($ext);
        if ($version === null || $version->approved_at !== null || $version->test_status !== 'passed' || ($version->test_report['checksum'] ?? null) !== $version->checksum) {
            throw ValidationException::withMessages(['extension' => __('extensions.test_first')]);
        }
        $ext->forceFill(['status' => 'pending_approval'])->save();
        $this->audit->record('extension.submitted', 'config', null, 'extension', $ext->id, ['key' => $ext->key, 'version' => $version->version], $user->id, null, $ext->form_id);
    }

    public function approve(Extension $ext, User $user): void
    {
        $version = $this->pending($ext);
        if ($version->submitted_by === $user->id || $ext->updated_by === $user->id) {
            throw ValidationException::withMessages(['extension' => __('extensions.own_code')]);
        }
        $version->forceFill(['approved_by' => $user->id, 'approved_at' => now()])->save();
        $ext->forceFill(['status' => 'approved'])->save();
        $this->audit->record('extension.approved', 'security', null, 'extension', $ext->id, ['key' => $ext->key, 'version' => $version->version, 'checksum' => $version->checksum], $user->id, null, $ext->form_id);
    }

    public function reject(Extension $ext, User $user, string $reason): void
    {
        $version = $this->pending($ext);
        $version->forceFill(['rejected_reason' => mb_substr($reason, 0, 2000)])->save();
        $ext->forceFill(['status' => 'rejected'])->save();
        $this->audit->record('extension.rejected', 'config', null, 'extension', $ext->id, ['key' => $ext->key, 'version' => $version->version, 'reason' => mb_substr($reason, 0, 500)], $user->id, null, $ext->form_id);
    }

    /** Deploys an approved version: the current one, or an earlier one (a rollback). */
    public function deploy(Extension $ext, User $user, ?string $versionUuid): void
    {
        $version = $versionUuid === null ? $this->current($ext)
            : ExtensionVersion::query()->where('extension_id', $ext->id)->where('uuid', strtolower($versionUuid))->first();
        if ($version === null || $version->approved_at === null) {
            throw ValidationException::withMessages(['version' => __('extensions.approve_first')]);
        }
        // The files must still be exactly what was approved.
        $this->store->build($version, $ext);
        $previous = $ext->deployed_version_id;
        DB::transaction(function () use ($ext, $version): void {
            $version->forceFill(['deployed_at' => now()])->save();
            // The status follows the current version; deploying an earlier one changes only what runs.
            $status = $version->id === $ext->current_version_id && $ext->status !== 'disabled' ? 'deployed' : $ext->status;
            $ext->forceFill(['deployed_version_id' => $version->id, 'status' => $status])->save();
        });
        $this->runner->forget();
        $from = $previous === null ? null : ExtensionVersion::query()->find($previous)?->version;
        $this->audit->record($from !== null && $from > $version->version ? 'extension.rolled_back' : 'extension.deployed', 'security', [['field_key' => 'version', 'old' => $from, 'new' => $version->version]], 'extension', $ext->id, ['key' => $ext->key, 'checksum' => $version->checksum], $user->id, null, $ext->form_id);
    }

    public function disable(Extension $ext, User $user, bool $off): void
    {
        if ($off) {
            $ext->forceFill(['status' => 'disabled'])->save();
        } else {
            $current = $this->current($ext);
            $status = match (true) {
                $current === null => 'draft',
                $current->approved_at !== null => $ext->deployed_version_id === $current->id ? 'deployed' : 'approved',
                $current->test_status === 'passed' => 'testing',
                default => 'draft',
            };
            $ext->forceFill(['status' => $status])->save();
        }
        $this->runner->forget();
        $this->audit->record($off ? 'extension.disabled' : 'extension.enabled', 'security', null, 'extension', $ext->id, ['key' => $ext->key], $user->id, null, $ext->form_id);
    }

    public function delete(Extension $ext, User $user): void
    {
        if (ExtensionVersion::query()->where('extension_id', $ext->id)->whereNotNull('deployed_at')->exists()) {
            throw ValidationException::withMessages(['extension' => __('extensions.was_deployed')]);
        }
        DB::transaction(function () use ($ext): void {
            $ext->forceFill(['current_version_id' => null, 'deployed_version_id' => null])->save();
            ExtensionVersion::query()->where('extension_id', $ext->id)->delete();
            $ext->delete();
        });
        $this->audit->record('extension.deleted', 'config', null, 'extension', $ext->id, ['key' => $ext->key], $user->id, null, $ext->form_id);
    }

    public function current(Extension $ext): ?ExtensionVersion
    {
        return $ext->current_version_id === null ? null : ExtensionVersion::query()->find($ext->current_version_id);
    }

    public function source(Extension $ext, ?ExtensionVersion $version = null): string
    {
        $version ??= $this->current($ext);

        return $version === null ? '' : $this->store->source($version, $ext);
    }

    public function canApprove(User $user): bool
    {
        return $this->access->allows($user, 'system.approve_code');
    }

    /** @param  array<string, mixed>  $manifest */
    private function newVersion(Extension $ext, User $user, string $source, array $manifest): ExtensionVersion
    {
        $number = (int) ExtensionVersion::query()->where('extension_id', $ext->id)->max('version') + 1;
        $version = new ExtensionVersion;
        $version->forceFill(['uuid' => (string) Str::uuid7(), 'extension_id' => $ext->id, 'version' => $number, 'source_path' => '', 'checksum' => str_repeat('0', 64),
            'manifest' => $manifest, 'test_status' => 'not_run', 'submitted_by' => $user->id])->save();
        $version->checksum = $this->store->write($ext, $version, $source, $manifest);
        $version->save();
        $ext->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }

    private function notDisabled(Extension $ext): void
    {
        if ($ext->status === 'disabled') {
            throw ValidationException::withMessages(['extension' => __('extensions.disabled_first')]);
        }
    }

    private function pending(Extension $ext): ExtensionVersion
    {
        $version = $this->current($ext);
        if ($version === null || $ext->status !== 'pending_approval') {
            throw ValidationException::withMessages(['extension' => __('extensions.not_pending')]);
        }

        return $version;
    }

    /**
     * Type-specific settings, checked.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function settings(array $data, ?Extension $current): array
    {
        $type = $current->type ?? $data['type'];
        $formId = null;
        if (($data['form'] ?? null) !== null) {
            $formId = Form::query()->where('uuid', strtolower((string) $data['form']))->value('id');
        }
        $needsForm = in_array($type, ['server_hook', 'client_validator', 'form_script'], true);
        if ($needsForm && $formId === null) {
            throw ValidationException::withMessages(['form' => __('extensions.form_required')]);
        }
        if ($type === 'server_hook' && ! in_array($data['hook_point'] ?? null, HookRunner::POINTS, true)) {
            throw ValidationException::withMessages(['hook_point' => __('extensions.point_required')]);
        }
        if ($type === 'api_endpoint' && ! DB::table('permissions')->where('key', (string) ($data['permission'] ?? ''))->exists()) {
            throw ValidationException::withMessages(['permission' => __('extensions.permission_required')]);
        }

        return [
            'hook_point' => $type === 'server_hook' ? $data['hook_point'] : null,
            'form_id' => $needsForm ? (int) $formId : null,
            'timeout_ms' => max(self::MIN_TIMEOUT, min(self::MAX_TIMEOUT, (int) ($data['timeout_ms'] ?? 2000))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function manifest(array $data): array
    {
        return array_filter([
            'description' => isset($data['description']) ? mb_substr((string) $data['description'], 0, 2000) : null,
            'permission' => $data['permission'] ?? null,
            'label' => isset($data['label']) && is_array($data['label']) ? array_map(static fn ($v) => mb_substr((string) $v, 0, 255), $data['label']) : null,
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * The values a hook test starts from: a real record, or sample values.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null, 2: string|null, 3: string}
     */
    private function sample(Extension $ext, User $user, array $input): array
    {
        $form = Form::query()->find($ext->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($form === null || $rt === null) {
            throw ValidationException::withMessages(['form' => __('extensions.form_unpublished')]);
        }
        $values = is_array($input['values'] ?? null) ? $input['values'] : [];
        $record = null;
        $old = null;
        if (is_string($input['record'] ?? null) && Str::isUuid($input['record'])) {
            $found = $this->records->find($rt, strtolower($input['record']));
            if ($found === null) {
                throw ValidationException::withMessages(['record' => __('records.not_found')]);
            }
            $record = $found['uuid'];
            $old = $found['values'];
            $values = array_replace($found['values'], $values);
        }

        return [$values, $old, $record, (string) $form->key];
    }
}
