<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Laravel infrastructure tables (cache, locks, queue bookkeeping) used unchanged
 * per architecture §10.21. Queues run on Redis/Horizon; `failed_jobs` and
 * `job_batches` are the database-backed failure and batch stores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $t): void {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration');
            Columns::index($t, ['expiration']);
        });

        Schema::create('cache_locks', function (Blueprint $t): void {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
            Columns::index($t, ['expiration']);
        });

        Schema::create('job_batches', function (Blueprint $t): void {
            $t->string('id')->primary();
            $t->string('name');
            $t->integer('total_jobs');
            $t->integer('pending_jobs');
            $t->integer('failed_jobs');
            $t->longText('failed_job_ids');
            $t->mediumText('options')->nullable();
            $t->integer('cancelled_at')->nullable();
            $t->integer('created_at');
            $t->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $t): void {
            $t->id();
            $t->string('uuid')->unique('uq_failed_jobs_uuid');
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            Columns::dt($t, 'failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
