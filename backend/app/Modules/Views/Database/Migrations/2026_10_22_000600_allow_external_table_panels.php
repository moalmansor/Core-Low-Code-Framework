<?php

declare(strict_types=1);

use App\Infrastructure\Database\Naming;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * View Mode panels can show rows of an external data source (specification
 * §4.33, ADR-0057): the panel types gain `external_table`. The check
 * constraint is replaced with one listing the new type, on MySQL and SQL
 * Server alike.
 */
return new class extends Migration
{
    private const TYPES = ['tabs', 'tab', 'section', 'related_table', 'derived_fields', 'summary_widget', 'status_timeline', 'comments', 'attachments', 'form_body', 'html'];

    public function up(): void
    {
        $this->types([...self::TYPES, 'external_table']);
    }

    public function down(): void
    {
        DB::table('view_panels')->where('type', 'external_table')->delete();
        $this->types(self::TYPES);
    }

    /** @param  list<string>  $types */
    private function types(array $types): void
    {
        $name = Naming::constraint('ck', 'view_panels', ['type']);
        $list = implode(', ', array_map(static fn (string $k) => "'{$k}'", $types));
        DB::statement(DB::getDriverName() === 'mysql' ? "ALTER TABLE view_panels DROP CHECK {$name}" : "ALTER TABLE view_panels DROP CONSTRAINT {$name}");
        DB::statement("ALTER TABLE view_panels ADD CONSTRAINT {$name} CHECK (type in ({$list}))");
    }
};
