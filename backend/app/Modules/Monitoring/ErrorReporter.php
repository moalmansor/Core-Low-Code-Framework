<?php

declare(strict_types=1);

namespace App\Modules\Monitoring;

use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Monitoring\Models\ErrorGroup;
use App\Modules\Monitoring\Models\ErrorLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Captures exceptions (specification §4.21, architecture §19.15). The secondary
 * sink (a JSON log file on its own path) is written first, so failures that take
 * the database down are still recorded; the database write follows and groups
 * duplicates by fingerprint.
 */
final class ErrorReporter
{
    private bool $reporting = false;

    public function __construct(
        private readonly Masker $masker,
        private readonly CorrelationId $correlation,
        private readonly TenantContext $tenant,
    ) {}

    /** @return string the reference code shown to the user */
    public function report(Throwable $e, ?string $severity = null): string
    {
        $reference = 'E-'.strtoupper(Str::random(10));
        if ($this->reporting) {
            return $reference; // never recurse while reporting
        }
        $this->reporting = true;
        try {
            $record = $this->buildRecord($e, $reference, $severity ?? $this->severity($e));
            Log::channel('error_sink')->error($record['exception_class'], $record);
            $this->persist($record, $e);
        } catch (Throwable) {
            // The secondary sink already has the record (or the disk is gone);
            // nothing else can be done without risking a loop.
        } finally {
            $this->reporting = false;
        }

        return $reference;
    }

    /** @return array<string, mixed> */
    private function buildRecord(Throwable $e, string $reference, string $severity): array
    {
        $request = app()->bound('request') && ! app()->runningInConsole() ? app(Request::class) : null;
        $message = $e instanceof QueryException
            ? 'SQLSTATE['.$e->getCode().'] query failed: '.$e->getSql() // SQL without bound values
            : $this->masker->text($e->getMessage());
        $user = Auth::user();

        return [
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'organization_id' => $this->safeOrganizationId(),
            'reference_code' => $reference,
            'severity' => $severity,
            'module' => $this->module($e),
            'exception_class' => $e::class,
            'message' => mb_substr($message, 0, 4000),
            'file' => $this->relativePath($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $this->masker->text(mb_substr($e->getTraceAsString(), 0, 60000)),
            'request' => $request === null ? null : [
                'method' => $request->method(),
                'route' => $request->route()?->uri(),
                'url' => $this->masker->url($request->fullUrl()),
                'payload' => $this->masker->payload($request->except(['password', 'password_confirmation', 'current_password'])),
                'headers' => $this->masker->headers($request->headers->all()),
            ],
            'user_id' => $user?->getAuthIdentifier(),
            'role_keys' => $user !== null && method_exists($user, 'roleKeys') ? $user->roleKeys() : null,
            'environment' => (string) config('app.env'),
            'release' => config('app.release'),
            'correlation_id' => $this->correlation->get(),
            'fingerprint' => $this->fingerprint($e),
        ];
    }

    /** @param array<string, mixed> $record */
    private function persist(array $record, Throwable $e): void
    {
        $orgId = $record['organization_id'] ?? null;
        if ($orgId === null) {
            return; // no tenant can be determined (e.g. before installation)
        }
        $alert = false;
        $group = DB::transaction(function () use ($record, $orgId, &$alert): ErrorGroup {
            /** @var ErrorGroup|null $group */
            $group = ErrorGroup::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)->where('fingerprint', $record['fingerprint'])
                ->lockForUpdate()->first();
            if ($group === null) {
                $group = new ErrorGroup;
                $group->forceFill([
                    'organization_id' => $orgId,
                    'fingerprint' => $record['fingerprint'],
                    'exception_class' => $record['exception_class'],
                    'message_sample' => $record['message'],
                    'module' => $record['module'],
                    'severity' => $record['severity'],
                    'first_seen_at' => $record['occurred_at'],
                    'last_seen_at' => $record['occurred_at'],
                    'occurrences' => 1,
                    'status' => 'new',
                ]);
                $group->saveQuietly();
                $alert = true;
            } else {
                $regressed = $group->status === 'resolved';
                $group->forceFill([
                    'last_seen_at' => $record['occurred_at'],
                    'occurrences' => $group->occurrences + 1,
                    'status' => $regressed ? 'new' : $group->status,
                    'message_sample' => $record['message'],
                ])->saveQuietly();
                $alert = $regressed;
            }
            $row = collect($record)->except(['fingerprint'])->all();
            $row['error_group_id'] = $group->id;
            $row['request'] = $row['request'] === null ? null : json_encode($row['request'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $row['role_keys'] = $row['role_keys'] === null ? null : json_encode($row['role_keys']);
            ErrorLog::query()->insert($row);

            return $group;
        });
        if ($alert) {
            app(ErrorAlerts::class)->maybeAlert($group, $record['reference_code']);
        }
    }

    private function fingerprint(Throwable $e): string
    {
        $message = $e instanceof QueryException ? $e->getSql() : $e->getMessage();
        $normalized = (string) preg_replace(
            ['/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '/\d+/', '/(["\']).*?\1/'],
            ['{uuid}', '{n}', '{s}'],
            $message,
        );
        $frames = array_slice(array_map(
            fn (array $f): string => $this->relativePath((string) ($f['file'] ?? '')).':'.($f['function'] ?? ''),
            array_filter($e->getTrace(), static fn (array $f): bool => isset($f['file']) && ! str_contains((string) $f['file'], '/vendor/')),
        ), 0, 3);

        return hash('sha256', $e::class.'|'.$normalized.'|'.implode('|', $frames));
    }

    private function severity(Throwable $e): string
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500 ? 'error' : 'warning';
        }

        return $e instanceof \Error ? 'critical' : 'error';
    }

    private function module(Throwable $e): ?string
    {
        foreach ([$e->getFile(), ...array_column($e->getTrace(), 'file')] as $file) {
            if (is_string($file) && preg_match('#/app/Modules/([A-Za-z]+)/#', $file, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function safeOrganizationId(): ?int
    {
        try {
            return $this->tenant->organizationId();
        } catch (Throwable) {
            return null;
        }
    }
}
