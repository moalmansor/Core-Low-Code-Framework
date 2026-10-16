<?php

declare(strict_types=1);

namespace App\Modules\Automation;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\FileStore;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The "file arrives in a watched folder" trigger (specification §4.31,
 * ADR-0043). Folders live under the `watched` disk, whose root the operator
 * mounts; admins name a folder inside it. Each new file (left alone for a
 * little while, so it is not still being copied) is checked like an upload
 * (type, size, malware scan), becomes a record of the automation's form with
 * the file in the chosen field — saved as the run-as user, with their
 * permissions — and starts a run for that record. Processed files move to
 * `processed/`, rejected ones to `failed/` with a note saying why.
 */
final class WatchedFolders
{
    public const DISK = 'watched';

    private const SETTLE_SECONDS = 30;

    private const PER_TICK = 50;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly FileStore $files,
        private readonly RecordPipeline $pipeline,
        private readonly AutomationEngine $engine,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
        private readonly Translator $translator,
    ) {}

    public function scan(): int
    {
        $taken = 0;
        $triggers = DB::table('automation_triggers')->join('automations', 'automations.id', '=', 'automation_triggers.automation_id')
            ->where('automation_triggers.type', 'watched_folder')->where('automations.is_enabled', true)->get(['automation_triggers.*']);
        foreach ($triggers as $t) {
            $a = DB::table('automations')->where('id', $t->automation_id)->first();
            if ($a === null || $t->watched_path === null) {
                continue;
            }
            $this->tenant->set((int) $a->organization_id);
            $taken += $this->folder($a, $t);
        }

        return $taken;
    }

    private function folder(object $a, object $t): int
    {
        $disk = Storage::disk(self::DISK);
        $folder = (string) $t->watched_path;
        if (! $disk->directoryExists($folder)) {
            return 0;
        }
        $form = Form::query()->find($a->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $config = json_decode((string) ($t->config ?? 'null'), true) ?: [];
        $field = $rt === null ? null : ($rt->fields[(string) ($config['fileField'] ?? '')] ?? null);
        $user = $a->run_as_user_id === null ? null : User::query()->find((int) $a->run_as_user_id);
        if ($rt === null || $field === null || $user === null || $user->status !== 'active') {
            return 0;
        }
        $taken = 0;
        foreach ($disk->files($folder) as $path) {
            $name = basename($path);
            if ($taken >= self::PER_TICK || str_starts_with($name, '.') || time() - $disk->lastModified($path) < self::SETTLE_SECONDS) {
                continue;
            }
            $taken++;
            $previous = Auth::user();
            Auth::setUser($user);
            try {
                $file = $this->files->storeUpload(new UploadedFile($disk->path($path), $name, null, null, true),
                    ($field['validation']['file']['types'] ?? null) ?: null, $field['validation']['file']['maxSizeKb'] ?? null);
                $values = [$field['key'] => $rt->type($field)?->storage === 'files' ? [$file->uuid] : $file->uuid];
                $nameField = $rt->fields[(string) ($config['nameField'] ?? '')] ?? null;
                if ($nameField !== null) {
                    $values[$nameField['key']] = mb_substr($name, 0, 255);
                }
                $key = 'watch-'.substr(hash('sha256', $t->uuid.'|'.$path.'|'.$disk->lastModified($path).'|'.$disk->size($path)), 0, 40);
                $created = $this->pipeline->create($rt, $user, $values, [], $key, 'automation');
                $this->move($disk, $path, "{$folder}/processed");
                $this->engine->fileArrived($a, $created['uuid'], $created['id'], $name);
            } catch (ValidationException|RecordException $e) {
                $reason = $this->reason($e, $rt);
                $this->reject($disk, $path, $folder, $reason);
                $this->audit->record('automation.file_rejected', 'operations', null, 'automation', (int) $a->id, ['key' => $a->key, 'file' => $name, 'reason' => mb_substr($reason, 0, 300)], $user->id, null, (int) $a->form_id);
            } catch (Throwable $e) {
                report($e);
                $this->reject($disk, $path, $folder, __('automations.crashed'));
            } finally {
                $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
            }
        }
        if ($taken > 0) {
            DB::table('automation_triggers')->where('id', $t->id)->update(['last_fired_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        }

        return $taken;
    }

    /** Why a file was not accepted, naming the fields that were not valid. */
    private function reason(ValidationException|RecordException $e, FormRuntime $rt): string
    {
        if ($e instanceof ValidationException) {
            return implode(' ', array_merge(...array_values(array_map(static fn ($m) => array_map('strval', (array) $m), $e->errors()))));
        }
        $lines = [$e->getMessage()];
        foreach ((array) ($e->payload['errors'] ?? []) as $key => $messages) {
            $field = isset($rt->keys[$key]) ? ($rt->fields[$rt->keys[$key]] ?? null) : null;
            $label = $field === null ? (string) $key : $this->translator->labelOf((array) ($field['i18n']['label'] ?? []), (string) $key);
            $lines[] = $label.': '.implode(' ', array_map('strval', (array) $messages));
        }

        return implode("\n", $lines);
    }

    private function reject(Filesystem $disk, string $path, string $folder, string $reason): void
    {
        $moved = $this->move($disk, $path, "{$folder}/failed");
        $disk->put($moved.'.error.txt', $reason."\n");
    }

    /** Moves a file into a sub-folder, never over another file of the same name. */
    private function move(Filesystem $disk, string $path, string $into): string
    {
        $target = $into.'/'.Carbon::now('UTC')->format('Ymd-His').'-'.basename($path);
        for ($n = 1; $disk->exists($target); $n++) {
            $target = $into.'/'.Carbon::now('UTC')->format('Ymd-His')."-{$n}-".basename($path);
        }
        $disk->move($path, $target);

        return $target;
    }
}
