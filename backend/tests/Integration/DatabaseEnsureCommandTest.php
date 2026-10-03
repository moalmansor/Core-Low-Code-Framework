<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * `db:ensure` runs in the container entrypoint before migrations. It creates
 * the configured database with the collation of architecture §9 when missing
 * and leaves an existing one untouched. CI runs this on MySQL and SQL Server.
 */

function ensureConnection(): string
{
    return (string) config('database.default');
}

function databaseCollation(string $database): ?string
{
    if (DB::connection()->getDriverName() === 'sqlsrv') {
        return DB::selectOne('SELECT collation_name AS c FROM sys.databases WHERE name = ?', [$database])?->c;
    }

    return DB::selectOne('SELECT DEFAULT_COLLATION_NAME AS c FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$database])?->c;
}

function dropScratchDatabase(string $database): void
{
    if (DB::connection()->getDriverName() === 'sqlsrv') {
        DB::statement("IF DB_ID('{$database}') IS NOT NULL DROP DATABASE [{$database}]");
    } else {
        DB::statement("DROP DATABASE IF EXISTS `{$database}`");
    }
}

it('leaves an existing database untouched', function () {
    $database = (string) config('database.connections.'.ensureConnection().'.database');

    $this->artisan('db:ensure', ['--timeout' => 0])
        ->expectsOutput("Database [{$database}] exists.")
        ->assertExitCode(0);
});

it('creates a missing database with the prescribed collation', function () {
    $connection = ensureConnection();
    $original = (string) config("database.connections.{$connection}.database");
    $scratch = 'lcf_ensure_'.bin2hex(random_bytes(4));
    dropScratchDatabase($scratch);

    try {
        config(["database.connections.{$connection}.database" => $scratch]);
        $this->artisan('db:ensure', ['--timeout' => 0])
            ->expectsOutput("Created database [{$scratch}].")
            ->assertExitCode(0);
        // A second run is a no-op.
        $this->artisan('db:ensure', ['--timeout' => 0])
            ->expectsOutput("Database [{$scratch}] exists.")
            ->assertExitCode(0);
    } finally {
        config(["database.connections.{$connection}.database" => $original]);
        DB::purge($connection);
    }

    $expected = DB::connection()->getDriverName() === 'sqlsrv' ? 'Arabic_100_CI_AI_SC' : 'utf8mb4_0900_ai_ci';
    expect(databaseCollation($scratch))->toBe($expected);
    dropScratchDatabase($scratch);
});

it('refuses a database name that is not a plain identifier', function () {
    $connection = ensureConnection();
    $original = (string) config("database.connections.{$connection}.database");

    try {
        config(["database.connections.{$connection}.database" => 'lcf]; DROP DATABASE x; --']);
        $this->artisan('db:ensure', ['--timeout' => 0])
            ->expectsOutput('DB_DATABASE must start with a letter and contain only letters, digits, and underscores.')
            ->assertExitCode(1);
    } finally {
        config(["database.connections.{$connection}.database" => $original]);
    }
});
