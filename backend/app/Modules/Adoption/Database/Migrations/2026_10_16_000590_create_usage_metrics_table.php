<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Usage insight (architecture §10.24, specification §4.37).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_metrics', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            $t->date('day');
            Columns::enum($t, 'metric', ['form_view', 'form_start', 'form_submit', 'form_abandon', 'field_left_empty', 'page_view', 'search']);
            Columns::fk($t, 'form_id', 'forms', index: false);
            Columns::fk($t, 'field_id', 'fields');
            Columns::fk($t, 'user_id', 'users');
            Columns::hash($t, 'dims_hash');
            $t->integer('count');
            Columns::index($t, ['form_id', 'day']);
            Columns::unique($t, ['day', 'dims_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_metrics');
    }
};
