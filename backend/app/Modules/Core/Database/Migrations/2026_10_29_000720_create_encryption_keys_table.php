<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-organization data keys (architecture §7.5, §10 `encryption_keys`):
 * envelope encryption of encrypted field values and their blind indexes,
 * each key wrapped with the application master key, rotated with keys:rotate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encryption_keys', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::code($t, 'key_id', 64);
            $t->text('wrapped_key');
            $t->string('kms_key_ref', 255)->nullable();
            Columns::code($t, 'algorithm', 32);
            Columns::enum($t, 'purpose', ['fields', 'files', 'secrets', 'blind_index']);
            Columns::enum($t, 'status', ['active', 'retiring', 'retired']);
            Columns::dt($t, 'rotated_at', nullable: true);
            Columns::unique($t, ['key_id']);
            Columns::index($t, ['organization_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encryption_keys');
    }
};
