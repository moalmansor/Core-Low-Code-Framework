<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Calendars\WorkingCalendar;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Values\Value;
use App\Modules\Identity\Models\User;
use App\Modules\Reference\WorkingCalendars;
use Illuminate\Support\Facades\DB;

/**
 * Server evaluation of a form's rules (specification §4.7: "every rule is
 * evaluated both on the client and on the server"): defaults on create,
 * formulas in dependency order, and conditions with their effects
 * (visibility, enablement, read-only, required, set/clear value, messages,
 * block submit). Elements inside a repeater are evaluated once per row with
 * row scope.
 */
final class RuleRuntime
{
    public const MAX_PASSES = 5;

    public function __construct(private readonly ExpressionContext $userContext, private readonly RecordLoader $loader) {}

    /**
     * @param  array<string, mixed>  $values  API values (main fields and repeater rows)
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $params  URL parameters for `url_param` defaults
     * @return array{values: array<string, mixed>, state: RuleState}
     */
    public function run(FormRuntime $rt, array $values, ?array $old, string $mode, User $user, bool $applyDefaults, array $params = []): array
    {
        $ctx = $this->context($rt, $mode, $user, $params);
        if ($applyDefaults) {
            $values = $this->defaults($rt, $values, $ctx->with(['record' => ValuesRecord::forRecord($rt, $values, $this->loader)]), $user, $params);
        }
        $state = new RuleState;
        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $before = json_encode($values);
            $state = new RuleState;
            $values = $this->formulas($rt, $values, $ctx, $old);
            $values = $this->conditions($rt, $values, $ctx, $old, $state);
            if (json_encode($values) === $before) {
                break;
            }
        }

        return ['values' => $values, 'state' => $state];
    }

    public function context(FormRuntime $rt, string $mode, User $user, array $params = []): Context
    {
        $calendar = null;
        $calendarId = $rt->form->business_calendar_id ?? ($user->department_id === null ? null : DB::table('departments')->where('id', $user->department_id)->value('business_calendar_id'));
        $calendarId ??= DB::table('business_calendars')->where('organization_id', $rt->form->organization_id)->where('is_default', true)->value('id');
        if ($calendarId !== null) {
            $calendar = app(WorkingCalendars::class)->expressionCalendar((int) $calendarId);
        }
        $params = array_map(static fn ($v) => is_scalar($v) ? Value::text((string) $v) : Value::null(), $params);

        return Context::now(config('app.timezone', 'UTC'))->with([
            'mode' => $mode,
            'form' => $rt->form->key,
            'locale' => app()->getLocale(),
            'user' => $this->userContext->user($user),
            'params' => $params,
            'calendar' => $calendar instanceof WorkingCalendar ? $calendar : null,
        ]);
    }

    /** @param  array<string, mixed>  $values */
    private function defaults(FormRuntime $rt, array $values, Context $ctx, User $user, array $params): array
    {
        foreach ($rt->mainFields() as $f) {
            if (($values[$f['key']] ?? null) !== null || ! $rt->isStored($f)) {
                continue;
            }
            $v = $this->defaultValue($rt, $f, $values, $ctx, $user, $params);
            if ($v !== null) {
                $values[$f['key']] = $v;
            }
        }
        foreach ($rt->repeaters as $repUuid => $rep) {
            $key = $rep['group']['key'];
            if (! array_key_exists($key, $values)) {
                $values[$key] = array_fill(0, (int) ($rep['group']['repeater']['defaultRows'] ?? 0), []);
            }
            foreach ($values[$key] as $i => $row) {
                foreach ($rt->rowFields($repUuid) as $f) {
                    if (($row[$f['key']] ?? null) === null) {
                        $v = $this->defaultValue($rt, $f, $values, $ctx->withRow(new ValuesRecord($rt, is_array($row) ? $row : [], $rep['fields'], null, null, $this->loader, false)), $user, $params);
                        if ($v !== null) {
                            $values[$key][$i][$f['key']] = $v;
                        }
                    }
                }
            }
        }

        return $values;
    }

    private function defaultValue(FormRuntime $rt, array $f, array $values, Context $ctx, User $user, array $params): mixed
    {
        $d = $f['behavior']['default'] ?? null;
        if (! is_array($d)) {
            // Static options marked as defaults.
            $defaults = $f['options']['defaults'] ?? [];
            foreach ($f['options']['static'] ?? [] as $o) {
                if ($o['default'] ?? false) {
                    $defaults[] = $o['value'];
                }
            }
            if ($defaults === []) {
                return null;
            }

            return in_array($rt->type($f)?->storage, ['multi_choice'], true) ? array_values(array_unique($defaults)) : $defaults[0];
        }
        $storage = $rt->type($f)?->storage;
        $codec = app(ValueCodec::class);
        try {
            return match ($d['kind']) {
                'static' => $codec->normalize($rt, $f, $d['value'] ?? null),
                'current_user' => $storage === 'user' ? strtolower((string) $user->uuid) : $user->name,
                'current_department' => $user->department_id === null ? null
                    : ($storage === 'department' ? strtolower((string) DB::table('departments')->where('id', $user->department_id)->value('uuid')) : (string) DB::table('departments')->where('id', $user->department_id)->value('code')),
                'now' => ValueBridge::toApi($rt, $f, Value::datetime($ctx->now)),
                'today' => ValueBridge::toApi($rt, $f, Value::date($ctx->today)),
                'url_param' => isset($d['param'], $params[$d['param']]) ? $codec->normalize($rt, $f, $params[$d['param']]) : null,
                'field' => isset($d['field'], $rt->fields[$d['field']]) ? ($values[$rt->fields[$d['field']]['key']] ?? null) : null,
                'formula' => isset($d['expr']) ? ValueBridge::toApi($rt, $f, Evaluator::evaluate($d['expr'], $ctx)->value) : null,
                'reference' => isset($d['reference']['path']) ? ValueBridge::toApi($rt, $f, Evaluator::evaluate(['k' => 'ref', 'scope' => 'record', 'path' => $d['reference']['path']], $ctx)->value) : null,
                default => null,
            };
        } catch (InvalidValue) {
            return null;
        }
    }

    /** Formula fields (and calculated display values) recomputed from the current values. */
    private function formulas(FormRuntime $rt, array $values, Context $ctx, ?array $old): array
    {
        $record = ValuesRecord::forRecord($rt, $values, $this->loader);
        $oldRecord = $old === null ? null : ValuesRecord::forRecord($rt, $old, $this->loader);
        $base = $ctx->with(['record' => $record, 'old' => $oldRecord]);
        foreach ($rt->mainFields() as $f) {
            $ast = $f['behavior']['formula'] ?? null;
            if ($ast === null || ! $rt->isStored($f)) {
                continue;
            }
            $values[$f['key']] = ValueBridge::toApi($rt, $f, Evaluator::evaluate($ast, $base)->value);
        }
        foreach ($rt->repeaters as $repUuid => $rep) {
            $key = $rep['group']['key'];
            foreach ($values[$key] ?? [] as $i => $row) {
                $rowCtx = $base->withRow(new ValuesRecord($rt, is_array($row) ? $row : [], $rep['fields'], null, null, $this->loader, false));
                foreach ($rt->rowFields($repUuid) as $f) {
                    if (($ast = $f['behavior']['formula'] ?? null) !== null) {
                        $values[$key][$i][$f['key']] = ValueBridge::toApi($rt, $f, Evaluator::evaluate($ast, $rowCtx)->value);
                    }
                }
            }
        }

        return $values;
    }

    /** Evaluates active conditions and applies their effects to values and state. */
    private function conditions(FormRuntime $rt, array $values, Context $ctx, ?array $old, RuleState $state): array
    {
        $record = ValuesRecord::forRecord($rt, $values, $this->loader);
        $oldRecord = $old === null ? null : ValuesRecord::forRecord($rt, $old, $this->loader);
        $base = $ctx->with(['record' => $record, 'old' => $oldRecord]);
        $conditions = $rt->definition['conditions'];
        usort($conditions, static fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        foreach ($conditions as $c) {
            if (! ($c['active'] ?? true)) {
                continue;
            }
            $repeater = $this->repeaterOfOwner($rt, $c['owner']);
            if ($repeater === null) {
                $holds = Evaluator::evaluate($c['when'], $base)->value->data === true;
                $values = $this->apply($rt, $holds ? $c['effects'] : ($c['else'] ?? []), $values, $base, $state, null, $c);

                continue;
            }
            $key = $rt->repeaters[$repeater]['group']['key'];
            foreach ($values[$key] ?? [] as $i => $row) {
                $rowCtx = $base->withRow(new ValuesRecord($rt, is_array($row) ? $row : [], $rt->repeaters[$repeater]['fields'], null, null, $this->loader, false));
                $holds = Evaluator::evaluate($c['when'], $rowCtx)->value->data === true;
                $values = $this->apply($rt, $holds ? $c['effects'] : ($c['else'] ?? []), $values, $rowCtx, $state, [$key, $i], $c);
            }
        }

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $effects
     * @param  array{0: string, 1: int}|null  $row
     */
    private function apply(FormRuntime $rt, array $effects, array $values, Context $ctx, RuleState $state, ?array $row, array $condition): array
    {
        foreach ($effects as $e) {
            $target = $e['target'] ?? null;
            $targetUuid = $target['uuid'] ?? null;
            $field = $target !== null && $target['type'] === 'field' ? ($rt->fields[$targetUuid] ?? null) : null;
            $inRow = $field !== null && $row !== null && isset($rt->fieldRepeater[$field['uuid']]);
            switch ($e['effect']) {
                case 'show':
                case 'enable':
                    $state->set($target, $e['effect'] === 'show' ? 'hidden' : 'disabled', false, $inRow ? $row : null);
                    break;
                case 'hide':
                case 'disable':
                case 'read_only':
                case 'require':
                    $flag = ['hide' => 'hidden', 'disable' => 'disabled', 'read_only' => 'readOnly', 'require' => 'required'][$e['effect']];
                    $state->set($target, $flag, true, $inRow ? $row : null);
                    break;
                case 'set_value':
                    if ($field !== null) {
                        $v = ValueBridge::toApi($rt, $field, Evaluator::evaluate($e['value'], $ctx)->value);
                        $values = $this->assign($values, $field, $v, $inRow ? $row : null);
                    }
                    break;
                case 'clear_value':
                    if ($field !== null) {
                        $values = $this->assign($values, $field, null, $inRow ? $row : null);
                    }
                    break;
                case 'show_message':
                    $state->messages[] = ['severity' => $e['severity'] ?? 'info', 'message' => $e['message'] ?? [], 'condition' => $condition['uuid']];
                    break;
                case 'block_submit':
                    $state->blocks[] = ['message' => $e['message'] ?? [], 'condition' => $condition['uuid']];
                    break;
                default:
                    // reload_options and trigger_action are client-side behaviours.
                    break;
            }
        }

        return $values;
    }

    /** @param  array{0: string, 1: int}|null  $row */
    private function assign(array $values, array $field, mixed $value, ?array $row): array
    {
        if ($row !== null) {
            $values[$row[0]][$row[1]][$field['key']] = $value;
        } else {
            $values[$field['key']] = $value;
        }

        return $values;
    }

    private function repeaterOfOwner(FormRuntime $rt, array $owner): ?string
    {
        return match ($owner['type']) {
            'field' => $rt->fieldRepeater[$owner['uuid']] ?? null,
            'group' => isset($rt->repeaters[$owner['uuid']]) ? null : $this->groupRepeater($rt, $owner['uuid']),
            default => null,
        };
    }

    private function groupRepeater(FormRuntime $rt, string $groupUuid): ?string
    {
        $g = $rt->groups[$groupUuid] ?? null;
        $seen = [];
        while ($g !== null && ! isset($seen[$g['uuid']])) {
            $seen[$g['uuid']] = true;
            $parent = $g['parent'] === null ? null : ($rt->groups[$g['parent']] ?? null);
            if ($parent !== null && $parent['type'] === 'repeater') {
                return $parent['uuid'];
            }
            $g = $parent;
        }

        return null;
    }
}
