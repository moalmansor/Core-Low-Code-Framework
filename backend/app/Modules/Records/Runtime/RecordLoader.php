<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Values\ArrayRecord;
use App\Expressions\Values\Value;
use App\Modules\Core\I18n\Translator;
use Illuminate\Support\Facades\DB;

/**
 * Loads referenced records for relation paths in expressions, memoized per
 * evaluation batch. Forms and collections load their current values; users,
 * roles and departments expose a fixed set of properties.
 */
final class RecordLoader
{
    /** @var array<string, Value> */
    private array $memo = [];

    public function __construct(private readonly FormRuntimes $runtimes, private readonly RecordStore $store, private readonly Translator $translator) {}

    public function reference(FormRuntime $rt, array $field, string $uuid): Value
    {
        $storage = $rt->type($field)?->storage;
        $key = ($storage ?? '').':'.($rt->targetFormUuid($field) ?? '').':'.$uuid;
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = match ($storage) {
            'user' => $this->user($uuid),
            'role' => $this->named('roles', 'role', $uuid, 'key'),
            'department' => $this->named('departments', 'department', $uuid, 'code'),
            default => $this->formRecord($rt->targetFormUuid($field), $uuid),
        };
    }

    private function formRecord(?string $formUuid, string $uuid): Value
    {
        $target = $formUuid === null ? null : $this->runtimes->forUuid($formUuid);
        if ($target === null) {
            return Value::null();
        }
        $record = $this->store->find($target, $uuid);
        if ($record === null) {
            return Value::null();
        }

        return Value::record(ValuesRecord::forRecord($target, $record['values'], $this, $uuid));
    }

    private function user(string $uuid): Value
    {
        $u = DB::table('users')->where('uuid', $uuid)->first(['id', 'name', 'email', 'department_id']);
        if ($u === null) {
            return Value::null();
        }

        return Value::record(new ArrayRecord([
            'name' => Value::text((string) $u->name),
            'email' => $u->email === null ? Value::null() : Value::text((string) $u->email),
            'department' => $u->department_id === null ? Value::null() : Value::text((string) DB::table('departments')->where('id', $u->department_id)->value('code')),
        ], (string) $u->name, strtolower((string) $uuid)));
    }

    private function named(string $table, string $type, string $uuid, string $codeColumn): Value
    {
        $row = DB::table($table)->where('uuid', $uuid)->first(['id', $codeColumn]);
        if ($row === null) {
            return Value::null();
        }
        $name = $this->translator->get($type, (int) $row->id, 'name') ?? (string) $row->{$codeColumn};

        return Value::record(new ArrayRecord(['code' => Value::text((string) $row->{$codeColumn}), 'name' => Value::text($name)], $name, $uuid));
    }
}
