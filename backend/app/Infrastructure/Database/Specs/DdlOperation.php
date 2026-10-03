<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

enum DdlOperation: string
{
    case CreateTable = 'create_table';
    case AddColumn = 'add_column';
    case RenameColumn = 'rename_column';
    case AlterColumn = 'alter_column';
    case AddIndex = 'add_index';
    case DropIndex = 'drop_index';
    case AddForeignKey = 'add_foreign_key';
    case DropForeignKey = 'drop_foreign_key';
}
