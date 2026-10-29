<?php

declare(strict_types=1);

namespace App\Modules\Extensions;

use App\Modules\Audit\AuditWriter;
use App\Modules\Extensions\Models\Extension;
use App\Modules\Extensions\Models\ExtensionVersion;
use App\Modules\Extensions\Sdk\ApiEndpoint;
use App\Modules\Extensions\Sdk\ApiRequest;
use App\Modules\Extensions\Sdk\HookContext;
use App\Modules\Extensions\Sdk\HookFailed;
use App\Modules\Extensions\Sdk\Records;
use App\Modules\Extensions\Sdk\ServerHook;
use App\Modules\Identity\Models\User;
use App\Modules\Monitoring\ErrorReporter;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Runs deployed server extensions (specification §4.19, ADR-0051).
 *
 * - Hooks of a form run at their point, in key order, inside the record's
 *   transaction: a failure rolls the whole operation back.
 * - Each run has the extension's time limit, enforced on every statement
 *   (ticks), so a loop cannot hold the request.
 * - `fail()` refuses the operation with the hook's message. Any other error
 *   or the time limit refuses it with a general message; the error goes to
 *   Error Monitoring and the Operations Center once the transaction is over
 *   (so the record of it is not rolled back with the save).
 * - A test run uses a version that need not be deployed, sends nothing out
 *   and rolls back everything it wrote.
 */
final class HookRunner
{
    public const POINTS = ['onLoad', 'beforeValidate', 'afterValidate', 'beforeSave', 'afterSave', 'beforeDelete', 'afterDelete', 'onStatusChange', 'onAction'];

    /** @var array<string, list<array{0: Extension, 1: ExtensionVersion}>> */
    private array $cache = [];

    /** @var list<array{ext: Extension, version: ExtensionVersion, point: string, record: string|null, error: Throwable}> */
    private array $failures = [];

    public function __construct(private readonly ExtensionStore $store) {}

    /** Forget loaded hook lists (after a deployment). */
    public function forget(): void
    {
        $this->cache = [];
    }

    public function has(FormRuntime $rt, string $point): bool
    {
        return $this->deployed($rt->form->organization_id, $rt->form->id, $point) !== [];
    }

    /**
     * Runs the form's hooks at a point.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $extra
     * @return array{values: array<string, mixed>, errors: array<string, list<string>>, changed: list<string>}
     */
    public function run(string $point, FormRuntime $rt, User $user, array $values, ?array $old, ?string $record, array $extra = []): array
    {
        $out = ['values' => $values, 'errors' => [], 'changed' => []];
        foreach ($this->deployed($rt->form->organization_id, $rt->form->id, $point) as [$ext, $version]) {
            $ctx = new HookContext($point, (string) $rt->form->key, $record, $out['values'], $old, self::who($user), $extra, false, new Records($user));
            try {
                $this->hook($ext, $version, $ctx);
            } catch (HookFailed $e) {
                throw new RecordException(422, 'extension_refused', $e->getMessage());
            } catch (Throwable $e) {
                $this->failed($ext, $version, $point, $record, $e);

                throw new RecordException(422, 'extension_failed', __('extensions.failed'));
            }
            $out['values'] = $ctx->values();
            $out['changed'] = array_values(array_unique([...$out['changed'], ...$ctx->changed()]));
            foreach ($ctx->errors() as $field => $messages) {
                $out['errors'][$field] = [...($out['errors'][$field] ?? []), ...$messages];
            }
        }

        return $out;
    }

    /**
     * A test run of one version in the sandbox: everything it writes is rolled back and nothing is sent.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $extra
     * @return array{ok: bool, values: array<string, mixed>, changed: list<string>, errors: array<string, list<string>>, logs: list<string>, refused: string|null, error: string|null, duration_ms: int, answer?: mixed}
     */
    public function test(Extension $ext, ExtensionVersion $version, User $user, string $point, string $formKey, array $values, ?array $old, ?string $record, array $extra = [], array $body = []): array
    {
        $started = microtime(true);
        $report = ['ok' => true, 'values' => $values, 'changed' => [], 'errors' => [], 'logs' => [], 'refused' => null, 'error' => null, 'duration_ms' => 0];
        DB::beginTransaction();
        try {
            if ($ext->type === 'api_endpoint') {
                $request = new ApiRequest('POST', [], $body, self::who($user), new Records($user), true);
                try {
                    $report['answer'] = $this->endpoint($ext, $version, $request);
                } finally {
                    $report['logs'] = $request->logs();
                }
            } else {
                $ctx = new HookContext($point, $formKey, $record, $values, $old, self::who($user), $extra, true, new Records($user));
                try {
                    $this->hook($ext, $version, $ctx);
                } finally {
                    $report = ['values' => $ctx->values(), 'changed' => $ctx->changed(), 'errors' => $ctx->errors(), 'logs' => $ctx->logs()] + $report;
                }
            }
        } catch (HookFailed $e) {
            $report['refused'] = $e->getMessage();
        } catch (Throwable $e) {
            $report['ok'] = false;
            $report['error'] = $e instanceof HookTimeout ? __('extensions.timed_out', ['ms' => $ext->timeout_ms]) : get_class($e).': '.mb_substr($e->getMessage(), 0, 1000).' (line '.max(1, $e->getLine() - ExtensionStore::HEADER_LINES).')';
        } finally {
            DB::rollBack();
        }
        $report['duration_ms'] = (int) round((microtime(true) - $started) * 1000);

        return $report;
    }

    /**
     * Calls a deployed API endpoint. It reads only: whatever it writes is rolled back.
     *
     * @return array<string, mixed>
     */
    public function call(Extension $ext, ApiRequest $request): array
    {
        $version = $ext->deployed_version_id === null ? null : ExtensionVersion::query()->find($ext->deployed_version_id);
        if ($version === null || $ext->status === 'disabled') {
            throw new RecordException(404, 'not_found', __('extensions.not_deployed'));
        }
        DB::beginTransaction();
        try {
            return $this->endpoint($ext, $version, $request);
        } catch (HookFailed $e) {
            throw new RecordException(422, 'extension_refused', $e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();
            $this->failed($ext, $version, 'api', null, $e);
            $this->flush();

            throw new RecordException(500, 'extension_failed', __('extensions.failed'));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    /** Writes the failures kept during a rolled-back operation: Error Monitoring and the audit log (Operations Center). */
    public function flush(): void
    {
        if (DB::transactionLevel() > 0) {
            return;
        }
        $failures = $this->failures;
        $this->failures = [];
        foreach ($failures as $f) {
            $reference = null;
            try {
                $reference = app(ErrorReporter::class)->report($f['error']);
            } catch (Throwable) {
                // Error Monitoring has its own secondary sink; the audit entry below still records it.
            }
            app(AuditWriter::class)->record('extension.failed', 'operations', null, 'extension', $f['ext']->id, [
                'key' => $f['ext']->key, 'version' => $f['version']->version, 'point' => $f['point'], 'record' => $f['record'], 'reference' => $reference,
                'error' => mb_substr($f['error'] instanceof HookTimeout ? __('extensions.timed_out', ['ms' => $f['ext']->timeout_ms]) : $f['error']->getMessage(), 0, 500),
            ], null, null, $f['ext']->form_id);
        }
    }

    /** @return array{uuid: string, name: string, email: string|null, roles: list<string>} */
    public static function who(User $user): array
    {
        return [
            'uuid' => strtolower((string) $user->uuid), 'name' => (string) $user->name, 'email' => $user->email,
            'roles' => DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_roles.user_id', $user->id)->pluck('roles.key')->map(static fn ($k) => (string) $k)->values()->all(),
        ];
    }

    private function hook(Extension $ext, ExtensionVersion $version, HookContext $ctx): void
    {
        $class = $this->store->load($version, $ext);
        $hook = new $class;
        if (! $hook instanceof ServerHook) {
            throw new RuntimeException(__('extensions.not_a_hook'));
        }
        $this->limited($ext->timeout_ms, static fn () => $hook->handle($ctx));
    }

    /** @return array<string, mixed> */
    private function endpoint(Extension $ext, ExtensionVersion $version, ApiRequest $request): array
    {
        $class = $this->store->load($version, $ext);
        $endpoint = new $class;
        if (! $endpoint instanceof ApiEndpoint) {
            throw new RuntimeException(__('extensions.not_an_endpoint'));
        }

        return $this->limited($ext->timeout_ms, static fn () => $endpoint->handle($request));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $run
     * @return T
     */
    private function limited(int $timeoutMs, callable $run): mixed
    {
        $deadline = microtime(true) + max(10, $timeoutMs) / 1000;
        $tick = static function () use ($deadline): void {
            if (microtime(true) > $deadline) {
                throw new HookTimeout;
            }
        };
        register_tick_function($tick);
        try {
            return $run();
        } finally {
            unregister_tick_function($tick);
        }
    }

    private function failed(Extension $ext, ExtensionVersion $version, string $point, ?string $record, Throwable $e): void
    {
        $this->failures[] = ['ext' => $ext, 'version' => $version, 'point' => $point, 'record' => $record, 'error' => $e];
        if (count($this->failures) === 1) {
            // Written once the request (or job) has finished, whatever it rolled back.
            app()->terminating(fn () => $this->flush());
        }
    }

    /** @return list<array{0: Extension, 1: ExtensionVersion}> */
    private function deployed(int $org, int $formId, string $point): array
    {
        $key = "{$org}:{$formId}:{$point}";
        if (! isset($this->cache[$key])) {
            $exts = Extension::query()->withoutGlobalScope('organization')->where('organization_id', $org)->where('type', 'server_hook')
                ->where('form_id', $formId)->where('hook_point', $point)->whereNotNull('deployed_version_id')->where('status', '!=', 'disabled')->orderBy('key')->get();
            $versions = ExtensionVersion::query()->whereIn('id', $exts->pluck('deployed_version_id'))->get()->keyBy('id');
            $this->cache[$key] = $exts->map(static fn (Extension $e) => [$e, $versions[$e->deployed_version_id]])->values()->all();
        }

        return $this->cache[$key];
    }
}
