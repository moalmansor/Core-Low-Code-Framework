<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Workflow statuses and transitions (architecture §10.8, §19.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            $t->string('color', 16);
            $t->string('icon', 64)->nullable();
            $t->boolean('is_initial')->default(false);
            $t->boolean('is_final')->default(false);
            $t->integer('sort_order')->default(0);
            Columns::json($t, 'diagram_position', nullable: true);
            Columns::dt($t, 'archived_at', nullable: true);
            Columns::unique($t, ['form_id', 'key']);
        });

        Schema::create('transitions', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            Columns::fk($t, 'from_status_id', 'statuses');
            Columns::fk($t, 'to_status_id', 'statuses', nullable: false);
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::json($t, 'required_fields', nullable: true);
            Columns::enum($t, 'comment_level', ['none', 'optional', 'mandatory']);
            Columns::enum($t, 'attachments_level', ['none', 'optional', 'mandatory']);
            Columns::enum($t, 'approval_mode', ['none', 'all', 'any_n', 'quorum']);
            Columns::json($t, 'approval_config', nullable: true);
            Columns::enum($t, 'rejection_behavior', ['immediate', 'wait_all']);
            Columns::fk($t, 'rejection_status_id', 'statuses');
            $t->boolean('confirmation')->default(false);
            Columns::json($t, 'button_style', nullable: true);
            $t->integer('sort_order')->default(0);
            Columns::json($t, 'diagram_edge', nullable: true);
            Columns::unique($t, ['form_id', 'key']);
            Columns::index($t, ['form_id', 'from_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transitions');
        Schema::dropIfExists('statuses');
    }
};
