<?php

declare(strict_types=1);

use App\Infrastructure\Database\Naming;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Document templates can be saved as blueprints (specification §4.17,
 * §4.30, ADR-0046): the blueprint kinds gain `document_template`. The check
 * constraint is replaced with one listing the new kind, on MySQL and SQL
 * Server alike.
 */
return new class extends Migration
{
    private const KINDS = ['form', 'collection', 'workflow', 'view', 'action', 'notification', 'dashboard', 'application'];

    public function up(): void
    {
        $this->kinds([...self::KINDS, 'document_template']);
    }

    public function down(): void
    {
        DB::table('blueprints')->where('kind', 'document_template')->delete();
        $this->kinds(self::KINDS);
    }

    /** @param  list<string>  $kinds */
    private function kinds(array $kinds): void
    {
        $name = Naming::constraint('ck', 'blueprints', ['kind']);
        $list = implode(', ', array_map(static fn (string $k) => "'{$k}'", $kinds));
        DB::statement(DB::getDriverName() === 'mysql' ? "ALTER TABLE blueprints DROP CHECK {$name}" : "ALTER TABLE blueprints DROP CONSTRAINT {$name}");
        DB::statement("ALTER TABLE blueprints ADD CONSTRAINT {$name} CHECK (kind in ({$list}))");
    }
};
