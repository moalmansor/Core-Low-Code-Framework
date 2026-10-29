<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Parallel imports (architecture §20.3, ADR-0072): an insert-mode import runs
 * its 500-row batches as separate queued jobs. A batch's row here is written
 * in the batch's own transaction with the batch's counts, so a batch runs
 * once however often its job is delivered, and the import's totals are the
 * sums of its batches; the import finishes when every batch has its row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_job_batches', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'import_job_id', 'import_jobs', nullable: false, index: false, onDelete: 'cascade');
            $t->integer('batch_no');
            Columns::enum($t, 'outcome', ['committed', 'cancelled', 'time_limit']);
            $t->integer('processed_rows')->default(0);
            $t->integer('created_count')->default(0);
            $t->integer('updated_count')->default(0);
            $t->integer('error_count')->default(0);
            Columns::dt($t, 'created_at');
            Columns::unique($t, ['import_job_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_job_batches');
    }
};
