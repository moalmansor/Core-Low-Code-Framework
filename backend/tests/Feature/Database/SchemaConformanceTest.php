<?php

declare(strict_types=1);

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use Illuminate\Support\Facades\DB;

/*
 * Every table the migrations create must match the ERD in docs/architecture.md
 * §10 on the engine under test: columns, native types, nullability, named
 * indexes, unique constraints, foreign keys, and CHECK constraints. Columns
 * whose referenced tables arrive in later phases are listed as deferred
 * (ADR-0021) and must not exist yet.
 */

const DEFERRED_COLUMNS = [
    'organizations' => ['theme_id'],
    'roles' => ['access_policy_id'],
    'sessions' => ['external_user_id', 'trusted_device_id', 'impersonation_session_id'],
    'files' => ['external_user_id'],
    'applications' => ['theme_id', 'home_screen_id'],
    'field_access_rules' => ['status_id'],
    'submission_journal' => ['external_user_id', 'import_job_id'],
];

const FRAMEWORK_TABLES = ['migrations', 'cache', 'cache_locks', 'job_batches', 'failed_jobs'];

/** @return array<string, array{columns: array<string, array{mysql: string, sqlsrv: string, nullable: bool}>, objects: list<string>, checks: list<string>}> */
function erdTables(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }
    $doc = (string) file_get_contents(base_path('../docs/architecture.md'));
    $erd = substr($doc, (int) strpos($doc, '## 10. ERD'), (int) strpos($doc, '## 11. Physical') - (int) strpos($doc, '## 10. ERD'));
    preg_match_all('/\n\*\*`([a-z0-9_]+)`\*\*[^\n]*\n\n\| Column/', $erd, $m, PREG_OFFSET_CAPTURE);
    $tables = [];
    foreach ($m[1] as $i => [$name, $offset]) {
        $end = $m[0][$i + 1][1] ?? strlen($erd);
        $section = substr($erd, $offset, $end - $offset);
        $section = substr($section, 0, ($p = strpos($section, "\n### ")) !== false ? $p : strlen($section));
        $columns = [];
        foreach (explode("\n", $section) as $line) {
            if (str_starts_with($line, '| `')) {
                $cells = array_map('trim', preg_split('/(?<!\\\\)\|/', trim($line, '|')));
                $columns[trim($cells[0], '`')] = ['mysql' => $cells[1], 'sqlsrv' => $cells[2], 'nullable' => $cells[3] === 'NULL'];
            }
        }
        preg_match_all('/- \*\*(?:Unique|Index|Foreign key):\*\* `([a-z0-9_]+)`/', $section, $objects);
        preg_match_all('/- \*\*Check(?: \(([A-Za-z ]+)\))?:\*\* `([a-z0-9_]+)`/', $section, $checks, PREG_SET_ORDER);
        $tables[$name] = [
            'columns' => $columns,
            'objects' => $objects[1],
            'checks' => array_map(static fn (array $c): array => ['name' => $c[2], 'engine' => $c[1] ?? ''], $checks),
        ];
    }

    return $tables;
}

function normalizeType(string $type): string
{
    return strtoupper((string) preg_replace('/\s+/', '', $type));
}

/** @return array<string, array{type: string, nullable: bool}> */
function liveColumns(string $table): array
{
    $out = [];
    if (DB::getDriverName() === 'mysql') {
        $rows = DB::select('select column_name as name, column_type as type, is_nullable as nullable, character_set_name as charset, collation_name as coll, extra as extra
            from information_schema.columns where table_schema = database() and table_name = ?', [$table]);
        foreach ($rows as $r) {
            $type = strtoupper($r->type);
            if ($r->charset === 'ascii') {
                $type .= ' CHARACTER SET ascii COLLATE '.$r->coll;
            }
            if (str_contains((string) $r->extra, 'auto_increment')) {
                $type .= ' AUTO_INCREMENT';
            }
            $out[$r->name] = ['type' => $type, 'nullable' => $r->nullable === 'YES'];
        }

        return $out;
    }
    $rows = DB::select('select c.name, t.name as type, c.max_length, c.precision, c.scale, c.is_nullable, c.is_identity, c.collation_name
        from sys.columns c join sys.types t on t.user_type_id = c.user_type_id where c.object_id = object_id(?)', [$table]);
    foreach ($rows as $r) {
        $type = strtoupper($r->type);
        $type .= match ($r->type) {
            'nvarchar', 'nchar' => '('.((int) $r->max_length === -1 ? 'MAX' : intdiv((int) $r->max_length, 2)).')',
            'varchar', 'char', 'varbinary' => '('.((int) $r->max_length === -1 ? 'MAX' : (int) $r->max_length).')',
            'datetime2' => '('.(int) $r->scale.')',
            'decimal', 'numeric' => '('.(int) $r->precision.','.(int) $r->scale.')',
            default => '',
        };
        if (in_array($r->type, ['varchar', 'char'], true) && $r->collation_name === 'Latin1_General_100_BIN2') {
            $type .= ' COLLATE Latin1_General_100_BIN2';
        }
        if ((int) $r->is_identity === 1) {
            $type .= ' IDENTITY(1,1)';
        }
        $out[$r->name] = ['type' => $type, 'nullable' => (int) $r->is_nullable === 1];
    }

    return $out;
}

/** @return list<string> names of non-primary indexes, unique constraints and foreign keys */
function liveObjects(string $table): array
{
    if (DB::getDriverName() === 'mysql') {
        $indexes = DB::select("select distinct index_name as name from information_schema.statistics where table_schema = database() and table_name = ? and index_name <> 'PRIMARY'", [$table]);
        $fks = DB::select("select constraint_name as name from information_schema.table_constraints where table_schema = database() and table_name = ? and constraint_type = 'FOREIGN KEY'", [$table]);
    } else {
        $indexes = DB::select('select name from sys.indexes where object_id = object_id(?) and is_primary_key = 0 and name is not null', [$table]);
        $fks = DB::select('select name from sys.foreign_keys where parent_object_id = object_id(?)', [$table]);
    }

    return array_values(array_unique(array_map(static fn ($r): string => (string) $r->name, [...$indexes, ...$fks])));
}

/** @return list<string> */
function liveChecks(string $table): array
{
    $rows = DB::getDriverName() === 'mysql'
        ? DB::select("select constraint_name as name from information_schema.table_constraints where table_schema = database() and table_name = ? and constraint_type = 'CHECK'", [$table])
        : DB::select('select name from sys.check_constraints where parent_object_id = object_id(?)', [$table]);

    return array_map(static fn ($r): string => (string) $r->name, $rows);
}

it('creates only tables that the ERD defines', function () {
    $erd = erdTables();
    $live = array_diff(app(DatabaseDriver::class)->tables(), FRAMEWORK_TABLES);
    expect(array_values(array_diff($live, array_keys($erd))))->toBe([]);
    expect(count($live))->toBeGreaterThanOrEqual(22);
});

it('matches the ERD for every created table', function () {
    $driver = DB::getDriverName();
    $erd = erdTables();
    $problems = [];
    $checked = 0;
    foreach (array_diff(app(DatabaseDriver::class)->tables(), FRAMEWORK_TABLES) as $table) {
        $spec = $erd[$table];
        $deferred = DEFERRED_COLUMNS[$table] ?? [];
        $live = liveColumns($table);
        foreach ($spec['columns'] as $column => $c) {
            if (in_array($column, $deferred, true)) {
                if (isset($live[$column])) {
                    $problems[] = "{$table}.{$column}: deferred column exists";
                }

                continue;
            }
            if (! isset($live[$column])) {
                $problems[] = "{$table}.{$column}: missing";

                continue;
            }
            $checked++;
            $expected = $c[$driver];
            if (normalizeType($live[$column]['type']) !== normalizeType($expected)) {
                $problems[] = "{$table}.{$column}: type {$live[$column]['type']} ≠ ERD {$expected}";
            }
            if ($live[$column]['nullable'] !== $c['nullable']) {
                $problems[] = "{$table}.{$column}: nullability differs from the ERD";
            }
        }
        foreach (array_diff(array_keys($live), array_keys($spec['columns'])) as $extra) {
            $problems[] = "{$table}.{$extra}: not in the ERD";
        }

        $want = array_filter($spec['objects'], static function (string $name) use ($deferred): bool {
            foreach ($deferred as $column) {
                if (str_contains($name.'_', '_'.$column.'_')) {
                    return false;
                }
            }

            return true;
        });
        $have = liveObjects($table);
        foreach (array_diff($want, $have) as $missing) {
            $problems[] = "{$table}: missing {$missing}";
        }
        foreach (array_diff($have, $want) as $extra) {
            $problems[] = "{$table}: unexpected {$extra}";
        }

        $wantChecks = array_column(array_filter($spec['checks'], static fn (array $c): bool => $c['engine'] === ''
            || ($c['engine'] === 'SQL Server' && $driver === 'sqlsrv')
            || ($c['engine'] === 'MySQL' && $driver === 'mysql')), 'name');
        $haveChecks = liveChecks($table);
        foreach (array_diff($wantChecks, $haveChecks) as $missing) {
            $problems[] = "{$table}: missing check {$missing}";
        }
        foreach (array_diff($haveChecks, $wantChecks) as $extra) {
            $problems[] = "{$table}: unexpected check {$extra}";
        }
    }
    expect($problems)->toBe([])->and($checked)->toBeGreaterThan(250);
});
