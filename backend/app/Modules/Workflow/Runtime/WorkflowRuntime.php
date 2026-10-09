<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Runtime;

use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Support\Facades\DB;

/**
 * The published workflow of a form version indexed for the runtime: statuses
 * and transitions by uuid with their row ids, the initial status, and the SLA
 * rules of each status. A form without statuses has no workflow.
 */
final class WorkflowRuntime
{
    /** @var array<string, array<string, mixed>> status uuid => status (with `id`) */
    public array $statuses = [];

    /** @var array<int, string> status id => uuid (archived statuses included) */
    public array $statusUuid = [];

    /** @var array<string, array<string, mixed>> transition uuid => transition (with `id`, `fromId`, `toId`) */
    public array $transitions = [];

    /** @var array<string, list<array<string, mixed>>> status uuid => SLA rules (with `id`) */
    public array $sla = [];

    public ?string $initial = null;

    /**
     * The workflow of a runtime, built once per request or job (the cache
     * lives in a scoped container binding, never across requests).
     */
    public static function for(FormRuntime $rt): self
    {
        /** @var WorkflowRuntimes $cache */
        $cache = app(WorkflowRuntimes::class);

        return $cache->memo[$rt->form->id.':'.$rt->versionId] ??= new self($rt);
    }

    public function __construct(FormRuntime $rt)
    {
        $wf = $rt->definition['workflow'] ?? [];
        $statusIds = DB::table('statuses')->where('form_id', $rt->form->id)->get(['id', 'uuid']);
        $byUuid = [];
        foreach ($statusIds as $s) {
            $byUuid[strtolower((string) $s->uuid)] = (int) $s->id;
            $this->statusUuid[(int) $s->id] = strtolower((string) $s->uuid);
        }
        foreach ($wf['statuses'] ?? [] as $s) {
            $this->statuses[$s['uuid']] = $s + ['id' => $byUuid[$s['uuid']] ?? null];
            if ($s['initial'] ?? false) {
                $this->initial = $s['uuid'];
            }
        }
        $transitionIds = DB::table('transitions')->where('form_id', $rt->form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        foreach ($wf['transitions'] ?? [] as $t) {
            $this->transitions[$t['uuid']] = $t + [
                'id' => $transitionIds[$t['uuid']] ?? null,
                'fromId' => $t['from'] === null ? null : ($byUuid[$t['from']] ?? null),
                'toId' => $byUuid[$t['to']] ?? null,
            ];
        }
        $slaIds = DB::table('sla_rules')->where('form_id', $rt->form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        foreach ($wf['sla'] ?? [] as $r) {
            if (($r['active'] ?? true) && isset($slaIds[$r['uuid']])) {
                $this->sla[$r['status']][] = $r + ['id' => $slaIds[$r['uuid']]];
            }
        }
    }

    public function enabled(): bool
    {
        return $this->statuses !== [];
    }

    /** @return array<string, mixed>|null */
    public function status(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $uuid = $this->statusUuid[$id] ?? null;

        return $uuid === null ? null : ($this->statuses[$uuid] ?? null);
    }

    public function initialId(): ?int
    {
        return $this->initial === null ? null : $this->statuses[$this->initial]['id'];
    }

    /**
     * Transitions that leave the given status (or any status).
     *
     * @return list<array<string, mixed>>
     */
    public function from(?int $statusId): array
    {
        $uuid = $statusId === null ? null : ($this->statusUuid[$statusId] ?? null);

        return array_values(array_filter($this->transitions, static fn (array $t): bool => $t['from'] === null || ($uuid !== null && $t['from'] === $uuid)));
    }

    /**
     * Name of a status in the request locale (falls back to its key).
     *
     * @param  array<string, mixed>  $status
     */
    public static function label(array $status, string $field = 'name'): string
    {
        $names = (array) ($status['i18n'][$field] ?? []);

        return (string) ($names[app()->getLocale()] ?? $names[config('app.fallback_locale', 'en')] ?? (reset($names) ?: ($status['key'] ?? '')));
    }
}
