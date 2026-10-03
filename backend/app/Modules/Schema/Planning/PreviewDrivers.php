<?php

declare(strict_types=1);

namespace App\Modules\Schema\Planning;

use App\Infrastructure\Database\Connections\MySqlConnection;
use App\Infrastructure\Database\Connections\SqlServerConnection;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\Drivers\MySqlDriver;
use App\Infrastructure\Database\Drivers\SqlServerDriver;
use RuntimeException;

/**
 * Drivers for both engines over connections that never connect, used to
 * render each migration step's SQL for MySQL and SQL Server in the impact
 * analysis (`migration_steps.sql_preview`) whichever engine is installed.
 *
 * @internal
 */
final class PreviewDrivers
{
    /** @var array<string, DatabaseDriver> */
    private array $drivers = [];

    /** @return array<string, DatabaseDriver> engine => driver */
    public function all(): array
    {
        if ($this->drivers !== []) {
            return $this->drivers;
        }
        $refuse = static fn () => throw new RuntimeException('Preview connections never connect.');
        $mysql = new class($refuse, 'preview', '', ['driver' => 'mysql', 'name' => 'preview_mysql']) extends MySqlConnection
        {
            public function isMaria()
            {
                return false;
            }

            public function getServerVersion(): string
            {
                return '8.4.0';
            }
        };
        $sqlsrv = new class($refuse, 'preview', '', ['driver' => 'sqlsrv', 'name' => 'preview_sqlsrv']) extends SqlServerConnection
        {
            public function getServerVersion(): string
            {
                return '15.0.0';
            }
        };
        $mysql->useDefaultSchemaGrammar();
        $sqlsrv->useDefaultSchemaGrammar();

        return $this->drivers = ['mysql' => new MySqlDriver($mysql), 'sqlsrv' => new SqlServerDriver($sqlsrv)];
    }
}
