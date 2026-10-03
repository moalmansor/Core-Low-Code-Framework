<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;

/**
 * Truncation between engine tests (they run real DDL, which commits
 * implicitly on MySQL). Tables whose rows are written by migrations
 * themselves (the audit chain heads) survive. SQL Server refuses TRUNCATE on
 * a table referenced by a foreign key even while constraints are disabled,
 * so there rows are deleted instead.
 */
trait EngineTruncation
{
    use DatabaseTruncation {
        truncateTablesForConnection as truncateWithTruncate;
    }

    /** @var list<string> */
    protected array $exceptTables = ['migrations', 'audit_chain_heads'];

    protected function truncateTablesForConnection(ConnectionInterface $connection, ?string $name): void
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->truncateWithTruncate($connection, $name);

            return;
        }
        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();
        $except = $this->exceptTables($connection, $name);
        foreach ($this->getAllTablesForConnection($connection, $name) as $table) {
            if ($this->tableExistsIn($table, $except)) {
                continue;
            }
            $connection->statement('DELETE FROM '.$connection->getQueryGrammar()->wrapTable($table['schema_qualified_name']));
        }
        $connection->setEventDispatcher($dispatcher);
    }
}
