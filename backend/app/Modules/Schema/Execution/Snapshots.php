<?php

declare(strict_types=1);

namespace App\Modules\Schema\Execution;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Schema\Models\SchemaSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-publish snapshots (architecture §12.5): the previous definition, the
 * introspected physical schema of the affected tables, and — before any
 * destructive or data-changing step — an encrypted logical backup of those
 * tables on the private disk. Location and retention are shown to the admin.
 */
final class Snapshots
{
    public const DISK = 'local';

    public function __construct(private readonly DatabaseDriver $driver, private readonly SettingsService $settings) {}

    /**
     * @param  array<string, mixed>|null  $definition
     * @param  list<string>  $tables  affected tables that exist
     * @return array{metadata: SchemaSnapshot, physical: SchemaSnapshot, data: SchemaSnapshot|null}
     */
    public function take(int $formId, int $planId, ?array $definition, array $tables, bool $withData): array
    {
        $dir = sprintf('schema-snapshots/%d/%s', $formId, Carbon::now('UTC')->format('Ymd\THis').'-'.$planId);
        $metadata = $this->store($formId, $planId, 'metadata', $dir.'/definition.json.enc', (string) json_encode($definition, JSON_UNESCAPED_UNICODE), []);
        $physical = [];
        foreach ($tables as $t) {
            $physical[$t] = ['columns' => $this->driver->columns($t), 'indexes' => $this->driver->indexes($t), 'foreign_keys' => $this->driver->foreignKeys($t)];
        }
        $phys = $this->store($formId, $planId, 'physical_schema', $dir.'/schema.json.enc', (string) json_encode($physical, JSON_UNESCAPED_UNICODE), array_fill_keys($tables, null));
        $data = null;
        if ($withData && $tables !== []) {
            $result = $this->driver->logicalBackup($tables, self::DISK, $dir.'/data');
            $storage = Storage::disk(self::DISK);
            foreach ($storage->files($dir.'/data') as $file) {
                $storage->put($file.'.enc', Crypt::encryptString((string) $storage->get($file)));
                $storage->delete($file);
            }
            $data = SchemaSnapshot::query()->create([
                'form_id' => $formId, 'migration_plan_id' => $planId, 'kind' => 'data_backup', 'tables' => $result->rowsPerTable,
                'disk' => self::DISK, 'path' => $dir.'/data', 'size_bytes' => $result->bytes, 'checksum' => $result->checksum,
                'expires_at' => $this->expiry(),
            ]);
        }

        return ['metadata' => $metadata, 'physical' => $phys, 'data' => $data];
    }

    /**
     * Restores a data backup into the given tables (repair screen): rows are
     * re-inserted by id where missing and overwritten where present.
     */
    public function restore(SchemaSnapshot $snapshot): void
    {
        $storage = Storage::disk($snapshot->disk);
        foreach (array_keys($snapshot->tables) as $table) {
            $file = $snapshot->path.'/'.$table.'.jsonl.enc';
            if (! $storage->exists($file) || ! in_array($table, $this->driver->tables(), true)) {
                continue;
            }
            $columns = array_column($this->driver->columns($table), 'name');
            $lines = explode("\n", trim(Crypt::decryptString((string) $storage->get($file))));
            foreach (array_chunk(array_filter($lines), 200) as $chunk) {
                DB::transaction(function () use ($chunk, $table, $columns): void {
                    foreach ($chunk as $line) {
                        $row = array_intersect_key(json_decode($line, true, 512, JSON_THROW_ON_ERROR), array_flip($columns));
                        $id = $row['id'] ?? null;
                        if ($id === null) {
                            continue;
                        }
                        $q = DB::table($table)->where('id', $id);
                        if ($q->exists()) {
                            $q->update(array_diff_key($row, ['id' => true]));
                        } else {
                            DB::table($table)->insert($row);
                        }
                    }
                });
            }
        }
        $snapshot->forceFill(['restored_at' => Carbon::now('UTC')])->save();
    }

    /** @return array<string, mixed>|null */
    public function definition(SchemaSnapshot $snapshot): ?array
    {
        return json_decode(Crypt::decryptString((string) Storage::disk($snapshot->disk)->get($snapshot->path)), true);
    }

    /** @param  array<string, mixed>  $tables */
    private function store(int $formId, int $planId, string $kind, string $path, string $content, array $tables): SchemaSnapshot
    {
        $encrypted = Crypt::encryptString($content);
        Storage::disk(self::DISK)->put($path, $encrypted);

        return SchemaSnapshot::query()->create([
            'form_id' => $formId, 'migration_plan_id' => $planId, 'kind' => $kind, 'tables' => $tables, 'disk' => self::DISK,
            'path' => $path, 'size_bytes' => strlen($encrypted), 'checksum' => hash('sha256', $content), 'expires_at' => $this->expiry(),
        ]);
    }

    private function expiry(): Carbon
    {
        $days = (int) ($this->settings->get('schema', 'snapshot_retention_days') ?? 30);

        return Carbon::now('UTC')->addDays(max(1, $days));
    }
}
