<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Additional notification channels and their delivery log (architecture
 * §10.13, specification §4.33).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'type', ['email', 'in_app', 'sms', 'messaging']);
            Columns::code($t, 'provider', 48);
            $t->text('config')->nullable();
            $t->integer('rate_limit_per_minute')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_default')->default(false);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('notification_deliveries', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'notification_channel_id', 'notification_channels', nullable: false);
            Columns::fk($t, 'notification_rule_id', 'notification_rules');
            Columns::fk($t, 'user_id', 'users');
            $t->string('recipient', 255);
            Columns::json($t, 'payload');
            Columns::enum($t, 'status', ['pending', 'sent', 'failed', 'cancelled']);
            $t->smallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            Columns::dt($t, 'sent_at', nullable: true);
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['organization_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');

        Schema::dropIfExists('notification_channels');
    }
};
