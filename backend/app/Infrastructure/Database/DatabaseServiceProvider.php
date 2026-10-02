<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Database\Connections\MySqlConnection;
use App\Infrastructure\Database\Connections\SqlServerConnection;
use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\Drivers\MySqlDriver;
use App\Infrastructure\Database\Drivers\SqlServerDriver;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('mysql', static fn ($pdo, $database, $prefix, $config) => new MySqlConnection($pdo, $database, $prefix, $config));
        Connection::resolverFor('sqlsrv', static fn ($pdo, $database, $prefix, $config) => new SqlServerConnection($pdo, $database, $prefix, $config));

        $this->app->bind(DatabaseDriver::class, static function ($app): DatabaseDriver {
            /** @var DatabaseManager $db */
            $db = $app->make('db');
            $connection = $db->connection();

            return match ($connection->getDriverName()) {
                'mysql' => new MySqlDriver($connection),
                'sqlsrv' => new SqlServerDriver($connection),
                default => throw new RuntimeException('Unsupported database driver ['.$connection->getDriverName().']; use mysql or sqlsrv.'),
            };
        });
    }

    public function boot(): void
    {
        Blueprint::macro('check', function (string $name, string $expression) {
            /** @var Blueprint $this */
            return $this->addCommand('check', ['index' => $name, 'expression' => $expression]);
        });

        /*
         * @param list<string> $columns
         * @param list<string> $nullable
         */
        Blueprint::macro('filteredUnique', function (string $name, array $columns, array $nullable) {
            /** @var Blueprint $this */
            return $this->addCommand('filteredUnique', ['index' => $name, 'columns' => $columns, 'nullable' => $nullable]);
        });
    }
}
