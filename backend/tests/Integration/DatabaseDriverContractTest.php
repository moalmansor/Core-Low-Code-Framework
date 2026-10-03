<?php

declare(strict_types=1);

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\Specs\ColumnSpec;
use App\Infrastructure\Database\Specs\DdlOperation;
use App\Infrastructure\Database\Specs\ForeignKeySpec;
use App\Infrastructure\Database\Specs\IndexSpec;
use App\Infrastructure\Database\Specs\LogicalType;
use App\Infrastructure\Database\Specs\TableSpec;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The DatabaseDriver contract (architecture §9) exercised against the engine
 * under test. CI runs this file on MySQL 8 and SQL Server 2019; DDL is real,
 * so these tests manage their own scratch tables instead of a transaction.
 */

function driver(): DatabaseDriver
{
    return app(DatabaseDriver::class);
}

function runAll(array $statements): void
{
    foreach ($statements as $sql) {
        DB::statement($sql);
    }
}

beforeEach(function () {
    Schema::dropIfExists('zz_contract_child');
    Schema::dropIfExists('zz_contract_parent');
    runAll(driver()->createTable(new TableSpec('zz_contract_parent', [
        new ColumnSpec('id', LogicalType::Id),
        new ColumnSpec('code', LogicalType::Code, length: 32),
        new ColumnSpec('title', LogicalType::Str, length: 120),
        new ColumnSpec('payload', LogicalType::Json, nullable: true),
        new ColumnSpec('status', LogicalType::Enum, values: ['draft', 'live']),
        new ColumnSpec('amount', LogicalType::Decimal, nullable: true, precision: 20, scale: 10),
        new ColumnSpec('happened_at', LogicalType::DateTime, nullable: true),
        new ColumnSpec('external_ref', LogicalType::Code, nullable: true, length: 64),
    ], [
        new IndexSpec('uq_zz_contract_parent_code', ['code'], unique: true),
        new IndexSpec('uq_zz_contract_parent_external_ref', ['external_ref'], unique: true, nullableColumns: ['external_ref']),
    ])));
    runAll(driver()->createTable(new TableSpec('zz_contract_child', [
        new ColumnSpec('id', LogicalType::Id),
        new ColumnSpec('parent_id', LogicalType::BigInt, unsigned: true),
    ], [new IndexSpec('ix_zz_contract_child_parent_id', ['parent_id'])], [
        new ForeignKeySpec('fk_zz_contract_child_parent_id', 'parent_id', 'zz_contract_parent', onDelete: 'cascade'),
    ])));
});

afterEach(function () {
    Schema::dropIfExists('zz_contract_child');
    Schema::dropIfExists('zz_contract_parent');
});

it('names itself after the connection driver', function () {
    expect(driver()->name())->toBe(DB::getDriverName());
});

it('creates tables, indexes and foreign keys that introspection reports back', function () {
    expect(driver()->tables())->toContain('zz_contract_parent', 'zz_contract_child');
    $columns = collect(driver()->columns('zz_contract_parent'))->keyBy('name');
    expect($columns->keys()->all())->toContain('id', 'code', 'payload', 'status')
        ->and($columns['payload']['nullable'])->toBeTrue()
        ->and($columns['code']['nullable'])->toBeFalse();
    $indexes = collect(driver()->indexes('zz_contract_parent'))->keyBy('name');
    expect($indexes['uq_zz_contract_parent_code']['unique'])->toBeTrue();
    $fk = collect(driver()->foreignKeys('zz_contract_child'))->firstWhere('name', 'fk_zz_contract_child_parent_id');
    expect($fk['foreign_table'])->toBe('zz_contract_parent')->and($fk['on_delete'])->toBe('cascade');
});

it('enforces enum CHECK constraints and unique keys that allow several NULLs', function () {
    DB::table('zz_contract_parent')->insert(['code' => 'a', 'title' => 't', 'status' => 'draft']);
    DB::table('zz_contract_parent')->insert(['code' => 'b', 'title' => 't', 'status' => 'live']);
    expect(fn () => DB::table('zz_contract_parent')->insert(['code' => 'c', 'title' => 't', 'status' => 'bogus']))->toThrow(QueryException::class);
    expect(fn () => DB::table('zz_contract_parent')->insert(['code' => 'a', 'title' => 't', 'status' => 'live']))->toThrow(QueryException::class);
    DB::table('zz_contract_parent')->insert(['code' => 'x1', 'title' => 't', 'status' => 'live', 'external_ref' => 'r1']);
    expect(fn () => DB::table('zz_contract_parent')->insert(['code' => 'x2', 'title' => 't', 'status' => 'live', 'external_ref' => 'r1']))->toThrow(QueryException::class);
});

it('treats code columns as case-sensitive and text as case-insensitive', function () {
    DB::table('zz_contract_parent')->insert(['code' => 'Key', 'title' => 'Café Riyadh', 'status' => 'draft']);
    DB::table('zz_contract_parent')->insert(['code' => 'pct', 'title' => '50% off_[sale]!', 'status' => 'draft']);
    DB::table('zz_contract_parent')->insert(['code' => 'key', 'title' => 'other', 'status' => 'draft']); // distinct under binary collation
    expect(DB::table('zz_contract_parent')->where('code', 'KEY')->count())->toBe(0);
    $found = driver()->caseInsensitiveLike(DB::table('zz_contract_parent'), 'title', 'café riy')->count();
    expect($found)->toBe(1);
    // LIKE wildcards in the search term are literal.
    expect(driver()->caseInsensitiveLike(DB::table('zz_contract_parent'), 'title', '%')->count())->toBe(1);
    expect(driver()->caseInsensitiveLike(DB::table('zz_contract_parent'), 'title', 'off_[sale]!')->count())->toBe(1);
    expect(driver()->caseInsensitiveLike(DB::table('zz_contract_parent'), 'title', 'f_[')->count())->toBe(1);
    expect(driver()->caseInsensitiveLike(DB::table('zz_contract_parent'), 'title', 'o_f')->count())->toBe(0);
});

it('stores Arabic text, microsecond datetimes and exact decimals', function () {
    DB::table('zz_contract_parent')->insert(['code' => 'ar', 'title' => 'طلب شراء — تجريبي', 'status' => 'draft', 'amount' => '1234567890.0123456789', 'happened_at' => '2026-03-01 10:11:12.123456']);
    $row = DB::table('zz_contract_parent')->where('code', 'ar')->first();
    expect($row->title)->toBe('طلب شراء — تجريبي')
        ->and(rtrim((string) $row->amount, '0'))->toBe('1234567890.0123456789')
        ->and(substr((string) $row->happened_at, 0, 26))->toBe('2026-03-01 10:11:12.123456');
});

it('extracts and filters JSON values with validated paths', function () {
    DB::table('zz_contract_parent')->insert(['code' => 'j1', 'title' => 't', 'status' => 'draft', 'payload' => json_encode(['customer' => ['city' => 'Jeddah'], 'tags' => ['a', 'b']])]);
    DB::table('zz_contract_parent')->insert(['code' => 'j2', 'title' => 't', 'status' => 'draft', 'payload' => json_encode(['customer' => ['city' => 'Riyadh'], 'tags' => ['c']])]);
    $grammar = DB::connection()->getQueryGrammar();
    $city = DB::table('zz_contract_parent')->where('code', 'j1')->selectRaw(driver()->jsonExtract('payload', '$.customer.city')->getValue($grammar).' as city')->value('city');
    expect(trim((string) $city, '"'))->toBe('Jeddah');
    expect(driver()->jsonContains(DB::table('zz_contract_parent'), 'payload', '$.tags', 'c')->pluck('code')->all())->toBe(['j2']);
    expect(fn () => driver()->jsonExtract('payload', "$.x') or 1=1 --"))->toThrow(InvalidArgumentException::class);
    expect(fn () => driver()->jsonExtract('payload; drop table x', '$.a'))->toThrow(InvalidArgumentException::class);
});

it('truncates dates for grouping', function () {
    DB::table('zz_contract_parent')->insert(['code' => 'd1', 'title' => 't', 'status' => 'draft', 'happened_at' => '2026-03-17 10:11:12']);
    $month = DB::table('zz_contract_parent')->where('code', 'd1')->selectRaw(driver()->dateTrunc('happened_at', 'month')->getValue(DB::connection()->getQueryGrammar()).' as m')->value('m');
    expect(substr((string) $month, 0, 10))->toBe('2026-03-01');
});

it('adds, renames and re-types columns and drops indexes and foreign keys', function () {
    runAll(driver()->addColumn('zz_contract_parent', new ColumnSpec('note', LogicalType::Str, nullable: true, length: 50)));
    runAll(driver()->renameColumn('zz_contract_parent', 'note', 'remark'));
    runAll(driver()->alterColumnType('zz_contract_parent', new ColumnSpec('remark', LogicalType::Str, nullable: true, length: 200)));
    expect(collect(driver()->columns('zz_contract_parent'))->pluck('name')->all())->toContain('remark')->not->toContain('note');
    runAll(driver()->addIndex('zz_contract_parent', new IndexSpec('ix_zz_contract_parent_title', ['title'])));
    runAll(driver()->dropIndex('zz_contract_parent', 'ix_zz_contract_parent_title'));
    runAll(driver()->dropForeignKey('zz_contract_child', 'fk_zz_contract_child_parent_id'));
    expect(driver()->foreignKeys('zz_contract_child'))->toBe([]);
});

it('reports online DDL support, impact estimates, and limits', function () {
    $impact = driver()->estimateDdlImpact('zz_contract_parent', DdlOperation::AddColumn);
    expect($impact->rows)->toBeGreaterThanOrEqual(0)->and($impact->estimatedMs)->toBeGreaterThanOrEqual(50);
    expect(driver()->supportsOnlineDdl(DdlOperation::AddIndex)->online)->toBeBool();
    expect(driver()->ddlIsTransactional())->toBe(DB::getDriverName() === 'sqlsrv');
    // Generated names are capped below the smaller engine limit (MySQL: 64).
    expect(driver()->maxIdentifierLength())->toBeLessThanOrEqual(64);
});

it('acquires and releases named locks', function () {
    expect(driver()->namedLock('contract-test', 1))->toBeTrue();
    driver()->releaseNamedLock('contract-test');
    expect(driver()->namedLock('contract-test', 1))->toBeTrue();
    driver()->releaseNamedLock('contract-test');
});

it('skips rows locked by another transaction', function () {
    DB::table('zz_contract_parent')->insert(['code' => 'q1', 'title' => 't', 'status' => 'draft']);
    DB::transaction(function () {
        $rows = driver()->skipLocked(DB::table('zz_contract_parent')->where('code', 'q1'))->get();
        expect($rows)->toHaveCount(1);
    });
});

it('maps every logical type to a native type', function (LogicalType $type) {
    $spec = new ColumnSpec('c', $type, length: 20, precision: 10, scale: 2, values: ['a']);
    expect(driver()->nativeType($spec))->toBeString()->not->toBe('');
})->with(LogicalType::cases());
