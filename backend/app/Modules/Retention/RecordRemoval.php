<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Takes a record out of its form completely, and puts it back (specification
 * §4.26): a snapshot holds the main row, its repeater rows, its link rows and
 * its files (metadata and content), so a record archived to cold storage can
 * be restored under the id it had, with everything that belonged to it.
 */
final class RecordRemoval
{
    public const FORMAT = 'lcf.archived-record.v1';

    public function __construct(private readonly DatabaseDriver $driver) {}

    /**
     * The record and everything that belongs to it; null when it is gone.
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(FormRuntime $rt, int $id): ?array
    {
        $row = DB::table($rt->table)->where('id', $id)->first();
        if ($row === null) {
            return null;
        }
        $children = [];
        $childIds = [];
        foreach ($rt->repeaters as $rep) {
            $rows = DB::table($rep['table'])->where('parent_id', $id)->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all();
            $children[$rep['table']] = $rows;
            array_push($childIds, ...array_map(static fn ($r) => (int) $r['id'], $rows));
        }
        $pivots = [];
        foreach ($rt->pivots as $pivot) {
            $pivots[$pivot] = DB::table($pivot)->whereIn('source_id', [$id, ...$childIds])->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all();
        }
        $files = DB::table('files')->where('form_id', $rt->form->id)->where('record_id', $id)->whereNull('deleted_at')->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all();

        return [
            'format' => self::FORMAT, 'form' => strtolower($rt->form->uuid), 'form_key' => $rt->form->key, 'table' => $rt->table,
            'record' => (array) $row, 'children' => $children, 'pivots' => $pivots, 'files' => $files,
        ];
    }

    /** Removes the record, its repeater and link rows; its files are marked deleted. Run inside a transaction. */
    public function remove(FormRuntime $rt, int $id, string $now): void
    {
        $childIds = [];
        foreach ($rt->repeaters as $rep) {
            array_push($childIds, ...DB::table($rep['table'])->where('parent_id', $id)->pluck('id')->map(static fn ($v) => (int) $v)->all());
        }
        foreach ($rt->pivots as $pivot) {
            DB::table($pivot)->whereIn('source_id', [$id, ...$childIds])->delete();
        }
        foreach ($rt->repeaters as $rep) {
            DB::table($rep['table'])->where('parent_id', $id)->delete();
        }
        DB::table('files')->where('form_id', $rt->form->id)->where('record_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);
        DB::table($rt->table)->where('id', $id)->delete();
    }

    /**
     * Copies the record's file contents next to its archive, so a restore does
     * not depend on the live file store keeping them.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed> the snapshot with each file's archived path
     */
    public function archiveFiles(array $snapshot, string $disk, string $directory): array
    {
        foreach ($snapshot['files'] as $i => $f) {
            $source = Storage::disk((string) $f['disk']);
            if (! $source->exists((string) $f['path'])) {
                $snapshot['files'][$i]['archived_path'] = null;

                continue;
            }
            $target = $directory.'/files/'.$f['uuid'];
            Storage::disk($disk)->writeStream($target, $source->readStream((string) $f['path']));
            $snapshot['files'][$i]['archived_path'] = $target;
        }

        return $snapshot;
    }

    /**
     * Puts an archived record back under its own id, with its repeater rows,
     * links and files. Columns the form no longer has are left out; a record
     * whose id is taken meanwhile is refused.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function restore(FormRuntime $rt, array $snapshot, string $disk): void
    {
        if (($snapshot['format'] ?? null) !== self::FORMAT || strtolower((string) $snapshot['form']) !== strtolower($rt->form->uuid)) {
            throw new RuntimeException(__('retention.restore_wrong_form'));
        }
        $record = (array) $snapshot['record'];
        if (DB::table($rt->table)->where('id', $record['id'])->orWhere('uuid', $record['uuid'])->exists()) {
            throw new RuntimeException(__('retention.restore_exists'));
        }
        $this->driver->insertKeepingIds($rt->table, [$this->fit($rt->table, $record)]);
        foreach ((array) $snapshot['children'] as $table => $rows) {
            if (in_array($table, array_column($rt->repeaters, 'table'), true) && $rows !== []) {
                $this->driver->insertKeepingIds((string) $table, array_map(fn ($r) => $this->fit((string) $table, (array) $r), $rows));
            }
        }
        foreach ((array) $snapshot['pivots'] as $table => $rows) {
            if (in_array($table, $rt->pivots, true) && $rows !== []) {
                $this->driver->insertKeepingIds((string) $table, array_map(fn ($r) => $this->fit((string) $table, (array) $r), $rows));
            }
        }
        foreach ((array) $snapshot['files'] as $f) {
            $live = Storage::disk((string) $f['disk']);
            if (! $live->exists((string) $f['path']) && ($f['archived_path'] ?? null) !== null && Storage::disk($disk)->exists((string) $f['archived_path'])) {
                $live->writeStream((string) $f['path'], Storage::disk($disk)->readStream((string) $f['archived_path']));
            }
            DB::table('files')->where('id', $f['id'])->update(['deleted_at' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fit(string $table, array $row): array
    {
        $columns = array_flip(array_column($this->driver->columns($table), 'name'));

        return array_intersect_key($row, $columns);
    }
}
