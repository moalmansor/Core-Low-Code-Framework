<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Connections;

use App\Infrastructure\Database\Grammars\SqlServerSchemaGrammar;
use Illuminate\Database\SqlServerConnection as BaseConnection;

class SqlServerConnection extends BaseConnection
{
    protected function getDefaultSchemaGrammar()
    {
        return new SqlServerSchemaGrammar($this);
    }
}
