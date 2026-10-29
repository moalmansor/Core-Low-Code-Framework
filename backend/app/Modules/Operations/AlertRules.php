<?php

declare(strict_types=1);

namespace App\Modules\Operations;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\EmailQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Threshold alerts of the Operations Center (specification §4.2, ADR-0045):
 * when a measure — failed submissions, jobs, e-mails or webhook calls in a
 * window, stuck e-mails, waiting jobs, errors, storage use, scheduler delay —
 * reaches its threshold, the members of the chosen roles get an e-mail
 * through the e-mail queue, at most once per cooldown.
 */
final class AlertRules
{
    /** Measures that can be watched today; `failed_integrations` comes with the integrations of Phase 5. */
    public const METRICS = ['failed_submissions', 'failed_jobs', 'failed_emails', 'stuck_emails', 'failed_webhooks', 'queue_size', 'error_rate', 'storage_usage', 'largest_table_mb', 'scheduler_lag'];

    /** Measures of the current state; the others count events inside the window. */
    public const INSTANT = ['stuck_emails', 'queue_size', 'storage_usage', 'largest_table_mb', 'scheduler_lag'];

    private const MAX_RULES = 50;

    public function __construct(
        private readonly Health $health,
        private readonly EmailMonitor $emails,
        private readonly EmailQueue $queue,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
        private readonly DatabaseDriver $driver,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(): array
    {
        $roles = DB::table('roles')->pluck('uuid', 'id');

        return DB::table('operations_alert_rules')->orderBy('id')->get()->map(static fn ($r) => [
            'uuid' => strtolower((string) $r->uuid), 'metric' => $r->metric, 'threshold' => (int) $r->threshold, 'window' => (int) $r->window_minutes,
            'roles' => array_values(array_filter(array_map(static fn ($id) => isset($roles[$id]) ? strtolower((string) $roles[$id]) : null, json_decode((string) $r->recipient_role_ids, true) ?: []))),
            'cooldown' => (int) $r->cooldown_minutes, 'active' => (bool) $r->is_active,
            'last_triggered_at' => $r->last_triggered_at === null ? null : Carbon::parse((string) $r->last_triggered_at, 'UTC')->toIso8601ZuluString(),
        ])->values()->all();
    }

    public function hash(): string
    {
        return DefinitionCompiler::hash(array_map(static fn ($r) => array_diff_key($r, ['last_triggered_at' => true]), $this->load()));
    }

    /** @param  list<mixed>  $rules */
    public function save(array $rules, int $userId): void
    {
        $clean = $this->validate($rules);
        DB::transaction(function () use ($clean, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            foreach ($clean as $r) {
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'metric' => $r['metric'], 'threshold' => $r['threshold'], 'window_minutes' => $r['window'],
                    'recipient_role_ids' => json_encode($r['role_ids']), 'cooldown_minutes' => $r['cooldown'], 'is_active' => $r['active'],
                ];
                $existing = DB::table('operations_alert_rules')->where('uuid', $r['uuid'])->first();
                if ($existing === null) {
                    DB::table('operations_alert_rules')->insert($row + ['uuid' => $r['uuid'], 'organization_id' => $this->tenant->organizationId(), 'created_at' => $now, 'created_by' => $userId]);
                } else {
                    DB::table('operations_alert_rules')->where('id', $existing->id)->update($row);
                }
                $kept[] = $r['uuid'];
            }
            DB::table('operations_alert_rules')->whereNotIn('uuid', $kept ?: ['00000000-0000-0000-0000-000000000000'])->delete();
            $this->audit->record('operations.alert_rules_saved', 'operations', null, null, null, ['rules' => count($clean)], $userId);
        });
    }

    /** The current value of a measure (counted over the window for event measures). */
    public function measure(string $metric, int $windowMinutes): int
    {
        $since = Carbon::now('UTC')->subMinutes(max(1, $windowMinutes))->format('Y-m-d H:i:s.u');

        return match ($metric) {
            'failed_submissions' => DB::table('submission_journal')->where('status', 'failed')->whereNotNull('error_trace')->where('updated_at', '>=', $since)->count(),
            'failed_jobs' => DB::table('import_jobs')->where('status', 'failed')->where('updated_at', '>=', $since)->count()
                + DB::table('export_jobs')->where('status', 'failed')->where('updated_at', '>=', $since)->count()
                + DB::table('download_jobs')->where('status', 'failed')->where('updated_at', '>=', $since)->count()
                + DB::table('bulk_operations')->where('dry_run', false)->where('status', 'failed')->where('updated_at', '>=', $since)->count()
                + DB::table('automation_runs')->whereIn('status', ['failed', 'partially_failed'])->where('is_test', false)->where('updated_at', '>=', $since)->count()
                + DB::table('failed_jobs')->where('failed_at', '>=', $since)->count(),
            'failed_emails' => DB::table('email_queue')->where('status', 'failed')->where('updated_at', '>=', $since)->count(),
            'stuck_emails' => $this->emails->stuckCount(),
            'failed_webhooks' => DB::table('audit_logs')->whereIn('event', ['action.webhook_called', 'automation.webhook_called'])->where('occurred_at', '>=', $since)->where('meta', 'like', '%"error":"%')->count(),
            'queue_size' => array_sum(array_map('intval', $this->health->queueSizes())),
            'error_rate' => DB::table('error_logs')->where('occurred_at', '>=', $since)->count(),
            'storage_usage' => (int) ($this->health->storage()['used_percent'] ?? 0),
            // Table growth (§4.26): the largest table of the database, in MB; the alert names it.
            'largest_table_mb' => intdiv($this->largestTable()[1], 1048576),
            'scheduler_lag' => ($last = $this->health->schedulerLastRun()) === null ? 0 : (int) $last->diffInMinutes(Carbon::now('UTC')),
            default => 0,
        };
    }

    /**
     * The largest table and its size in bytes.
     *
     * @return array{0: string, 1: int}
     */
    public function largestTable(): array
    {
        $sizes = $this->driver->tableSizes();
        arsort($sizes);
        $table = (string) array_key_first($sizes);

        return [$table, (int) ($sizes[$table] ?? 0)];
    }

    /** Checks every active rule and alerts the roles of those that reached their threshold. Returns how many alerted. */
    public function evaluate(): int
    {
        $sent = 0;
        $now = Carbon::now('UTC');
        foreach (DB::table('operations_alert_rules')->where('is_active', true)->orderBy('id')->get() as $rule) {
            if ($rule->last_triggered_at !== null && Carbon::parse((string) $rule->last_triggered_at, 'UTC')->addMinutes((int) $rule->cooldown_minutes)->isAfter($now)) {
                continue;
            }
            $value = $this->measure((string) $rule->metric, (int) $rule->window_minutes);
            if ($value < (int) $rule->threshold) {
                continue;
            }
            $recipients = User::query()->where('status', 'active')
                ->whereHas('roles', static fn ($q) => $q->whereIn('roles.id', json_decode((string) $rule->recipient_role_ids, true) ?: [0]))->get(['id', 'email']);
            $locales = DB::table('user_preferences')->whereIn('user_id', $recipients->pluck('id')->all() ?: [0])->pluck('locale', 'user_id');
            $enabled = $this->translator->enabledLocales();
            $byLocale = $recipients->groupBy(fn (User $u) => in_array($locales[$u->id] ?? null, $enabled, true) ? (string) $locales[$u->id] : $this->translator->defaultLocale());
            foreach ($byLocale as $locale => $group) {
                $vars = ['measure' => __('operations.metric.'.$rule->metric, [], (string) $locale), 'value' => $value, 'threshold' => (int) $rule->threshold, 'window' => (int) $rule->window_minutes];
                if ($rule->metric === 'largest_table_mb') {
                    $vars['measure'] .= ' ('.$this->largestTable()[0].')';
                }
                $this->queue->enqueue([
                    'source' => 'alert', 'locale' => (string) $locale, 'to' => $group->pluck('email')->filter()->values()->all(),
                    'subject' => __('operations.alert.subject', $vars, (string) $locale),
                    'html' => '<p>'.e(__(in_array($rule->metric, self::INSTANT, true) ? 'operations.alert.body_now' : 'operations.alert.body', $vars, (string) $locale)).'</p>'
                        .'<p><a href="'.e(rtrim((string) config('app.url'), '/').'/admin/operations').'">'.e(__('operations.alert.open', [], (string) $locale)).'</a></p>',
                ]);
            }
            DB::table('operations_alert_rules')->where('id', $rule->id)->update(['last_triggered_at' => $now->format('Y-m-d H:i:s.u')]);
            $this->audit->record('operations.alert_triggered', 'operations', null, 'operations_alert_rule', (int) $rule->id, ['metric' => $rule->metric, 'value' => $value, 'threshold' => (int) $rule->threshold, 'recipients' => $recipients->count()]);
            $sent++;
        }

        return $sent;
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<array{uuid: string, metric: string, threshold: int, window: int, role_ids: list<int>, cooldown: int, active: bool}>
     */
    private function validate(array $rules): array
    {
        $errors = [];
        $out = [];
        $seen = [];
        if (count($rules) > self::MAX_RULES) {
            $errors['rules'][] = __('operations.too_many_rules', ['max' => self::MAX_RULES]);
        }
        foreach (array_values($rules) as $i => $r) {
            $p = "rules.{$i}";
            if (! is_array($r) || ! is_string($r['uuid'] ?? null) || ! Str::isUuid($r['uuid']) || isset($seen[strtolower($r['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $seen[strtolower($r['uuid'])] = true;
            $metric = in_array($r['metric'] ?? null, self::METRICS, true) ? (string) $r['metric'] : null;
            if ($metric === null) {
                $errors["{$p}.metric"][] = __('operations.bad_metric');
            }
            $threshold = filter_var($r['threshold'] ?? null, FILTER_VALIDATE_INT);
            $max = $metric === 'storage_usage' ? 100 : 1000000;
            if ($threshold === false || $threshold < 1 || $threshold > $max) {
                $errors["{$p}.threshold"][] = __('operations.bad_threshold', ['max' => $max]);
            }
            $window = filter_var($r['window'] ?? 60, FILTER_VALIDATE_INT);
            if ($window === false || $window < 5 || $window > 10080) {
                $errors["{$p}.window"][] = __('operations.bad_window');
            }
            $cooldown = filter_var($r['cooldown'] ?? 60, FILTER_VALIDATE_INT);
            if ($cooldown === false || $cooldown < 5 || $cooldown > 10080) {
                $errors["{$p}.cooldown"][] = __('operations.bad_cooldown');
            }
            $roleUuids = array_values(array_filter((array) ($r['roles'] ?? []), static fn ($u) => is_string($u) && Str::isUuid($u)));
            $roleIds = DB::table('roles')->whereIn('uuid', array_map('strtolower', $roleUuids) ?: ['00000000-0000-0000-0000-000000000000'])->pluck('id')->map(static fn ($v) => (int) $v)->all();
            if ($roleIds === [] || count($roleIds) !== count((array) ($r['roles'] ?? []))) {
                $errors["{$p}.roles"][] = __('operations.roles_required');
            }
            $out[] = ['uuid' => strtolower($r['uuid']), 'metric' => (string) $metric, 'threshold' => (int) $threshold, 'window' => (int) $window, 'role_ids' => $roleIds, 'cooldown' => (int) $cooldown, 'active' => (bool) ($r['active'] ?? true)];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
