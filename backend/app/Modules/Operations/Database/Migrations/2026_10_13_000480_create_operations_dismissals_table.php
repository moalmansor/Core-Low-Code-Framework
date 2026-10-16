<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Failed jobs an operator dismissed from the Operations Center, with the
 * reason (architecture §10.21, ADR-0045). The failed row itself is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_dismissals', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::enum($t, 'kind', ['import', 'export', 'download', 'action', 'automation', 'scheduled_task', 'webhook', 'queue_job']);
            $t->unsignedBigInteger('reference_id');
            $t->text('reason');
            Columns::fk($t, 'dismissed_by', 'users');
            Columns::dt($t, 'dismissed_at');
            Columns::unique($t, ['kind', 'reference_id']);
            Columns::index($t, ['organization_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_dismissals');
    }
};
