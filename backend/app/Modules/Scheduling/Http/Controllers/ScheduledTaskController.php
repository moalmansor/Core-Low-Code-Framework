<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The scheduled-task manager (specification §4.31): everything scheduled
 * across the system — automation schedules, scheduled downloads and the
 * system's own jobs — with when it runs next and how it last ended. For
 * those who manage automations or operations.
 */
final class ScheduledTaskController extends Controller
{
    public function __construct(private readonly AccessResolver $access, private readonly Translator $translator) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->access->allows($user, 'system.manage_automations') || $this->access->allows($user, 'system.manage_operations'), 403);
        $rows = DB::table('scheduled_tasks')->orderBy('kind')->orderBy('next_run_at')->limit(1000)->get();
        $owners = $this->owners($rows->all());
        $iso = static fn ($at) => $at === null ? null : Carbon::parse((string) $at, 'UTC')->toIso8601ZuluString();

        return response()->json(['data' => $rows->map(fn ($t) => [
            'uuid' => strtolower((string) $t->uuid),
            'kind' => $t->kind,
            'name' => $owners[(int) $t->id]['name'] ?? $t->name,
            'owner' => $owners[(int) $t->id]['link'] ?? null,
            'cron' => $t->cron_expression,
            'timezone' => $t->timezone,
            'enabled' => (bool) $t->is_enabled,
            'next_run_at' => $t->kind === 'system' || $t->is_enabled ? $iso($t->next_run_at) : null,
            'last_run_at' => $iso($t->last_run_at),
            'last_status' => $t->last_status,
            'last_duration_ms' => $t->last_duration_ms === null ? null : (int) $t->last_duration_ms,
            'last_error' => $t->last_error,
        ])->values()->all()]);
    }

    /**
     * What each task belongs to, by its current (translated) name, with
     * where to manage it.
     *
     * @param  list<object>  $rows
     * @return array<int, array{name: string, link: array<string, string>}>
     */
    private function owners(array $rows): array
    {
        $out = [];
        $triggerIds = array_map(static fn ($t) => (int) $t->owner_id, array_filter($rows, static fn ($t) => $t->owner_type === 'automation_trigger'));
        $triggers = DB::table('automation_triggers')->whereIn('id', $triggerIds ?: [0])->get(['id', 'automation_id'])->keyBy('id');
        $automations = DB::table('automations')->whereIn('id', $triggers->pluck('automation_id')->all() ?: [0])->get(['id', 'uuid', 'key', 'form_id'])->keyBy('id');
        $autoNames = $this->translator->many('automation', $automations->keys()->map(static fn ($v) => (int) $v)->all(), ['name']);
        $scheduleIds = array_map(static fn ($t) => (int) $t->owner_id, array_filter($rows, static fn ($t) => $t->kind === 'download'));
        $schedules = DB::table('download_schedules')->whereIn('id', $scheduleIds ?: [0])->get(['id', 'download_profile_id'])->keyBy('id');
        $profiles = DB::table('download_profiles')->whereIn('id', $schedules->pluck('download_profile_id')->all() ?: [0])->get(['id', 'uuid', 'key', 'form_id'])->keyBy('id');
        $profileNames = $this->translator->many('download_profile', $profiles->keys()->map(static fn ($v) => (int) $v)->all(), ['name']);
        $formIds = [...$automations->pluck('form_id')->all(), ...$profiles->pluck('form_id')->all()];
        $forms = DB::table('forms')->whereIn('id', array_filter($formIds) ?: [0])->pluck('uuid', 'id');
        foreach ($rows as $t) {
            if ($t->owner_type === 'automation_trigger' && isset($triggers[$t->owner_id], $automations[$triggers[$t->owner_id]->automation_id])) {
                $a = $automations[$triggers[$t->owner_id]->automation_id];
                $out[(int) $t->id] = ['name' => (string) ($autoNames[(int) $a->id]['name'] ?? Translator::humanize((string) $a->key)),
                    'link' => ['type' => 'automation', 'uuid' => strtolower((string) $a->uuid), 'form' => strtolower((string) ($forms[$a->form_id] ?? ''))]];
            } elseif ($t->kind === 'download' && isset($schedules[$t->owner_id], $profiles[$schedules[$t->owner_id]->download_profile_id])) {
                $p = $profiles[$schedules[$t->owner_id]->download_profile_id];
                $out[(int) $t->id] = ['name' => (string) ($profileNames[(int) $p->id]['name'] ?? Translator::humanize((string) $p->key)),
                    'link' => ['type' => 'download_profile', 'uuid' => strtolower((string) $p->uuid), 'form' => strtolower((string) ($forms[$p->form_id] ?? ''))]];
            }
        }

        return $out;
    }
}
