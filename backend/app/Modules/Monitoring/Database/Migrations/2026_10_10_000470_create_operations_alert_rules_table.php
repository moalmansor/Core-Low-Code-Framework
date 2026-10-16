<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Operations Center threshold alerts (architecture §10.21, §19.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_alert_rules', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::enum($t, 'metric', ['failed_submissions', 'failed_jobs', 'failed_emails', 'stuck_emails', 'failed_webhooks', 'failed_integrations', 'queue_size', 'error_rate', 'storage_usage', 'scheduler_lag']);
            $t->integer('threshold');
            $t->integer('window_minutes');
            Columns::json($t, 'recipient_role_ids');
            $t->integer('cooldown_minutes');
            $t->boolean('is_active')->default(true);
            Columns::dt($t, 'last_triggered_at', nullable: true);
            Columns::index($t, ['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_alert_rules');
    }
};
