<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Connections;

use App\Infrastructure\Database\Grammars\MySqlSchemaGrammar;
use Illuminate\Database\MySqlConnection as BaseConnection;

final class MySqlConnection extends BaseConnection
{
    protected function getDefaultSchemaGrammar()
    {
        return new MySqlSchemaGrammar($this);
    }
}
