<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Number sequences for record numbers and auto-number fields (architecture
 * §10.15, specification §4.34). Closes forms.numbering_sequence_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'scope', ['form', 'shared']);
            Columns::fk($t, 'form_id', 'forms', index: false);
            Columns::fk($t, 'field_id', 'fields');
            $t->string('pattern', 255);
            $t->string('prefix', 32)->nullable();
            $t->smallInteger('padding');
            $t->integer('step');
            Columns::enum($t, 'reset_period', ['never', 'daily', 'monthly', 'yearly']);
            Columns::enum($t, 'calendar', ['gregorian', 'hijri']);
            Columns::code($t, 'period_key', 16);
            $t->bigInteger('current_value');
            Columns::dt($t, 'last_adjusted_at', nullable: true);
            Columns::unique($t, ['organization_id', 'key']);
            Columns::index($t, ['form_id', 'field_id']);
        });

        Schema::table('forms', function (Blueprint $t): void {
            Columns::foreign($t, 'numbering_sequence_id', 'number_sequences');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $t): void {
            $t->dropForeign('fk_forms_numbering_sequence_id');
        });
        Schema::dropIfExists('number_sequences');
    }
};
