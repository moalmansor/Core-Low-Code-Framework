<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Calendars\Civil;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Values\Value;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Support\Facades\DB;

/**
 * The API shape of records: values filtered by the reader's field access,
 * display titles of referenced records, file metadata, system information,
 * and the record title (form title template).
 */
final class RecordPresenter
{
    public function __construct(private readonly References $refs, private readonly RecordLoader $loader) {}

    /**
     * @param  array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}  $record
     * @param  array<string, string>  $access  field uuid => level
     * @return array<string, mixed>
     */
    public function present(FormRuntime $rt, array $record, array $access, bool $withTitles = true): array
    {
        return $this->many($rt, [$record], $access, $withTitles)[0];
    }

    /**
     * @param  list<array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}>  $records
     * @param  array<string, string>  $access
     * @return list<array<string, mixed>>
     */
    public function many(FormRuntime $rt, array $records, array $access, bool $withTitles = true): array
    {
        $hidden = array_flip(array_keys(array_filter($access, static fn ($l) => $l === 'hidden')));
        $titles = [];
        $files = [];
        if ($withTitles) {
            $refUuids = [];
            $fileUuids = [];
            foreach ($records as $r) {
                $this->collect($rt, $rt->mainFields(), $r['values'], $refUuids, $fileUuids, $hidden);
                foreach ($rt->repeaters as $repUuid => $rep) {
                    foreach ($r['values'][$rep['group']['key']] ?? [] as $row) {
                        $this->collect($rt, $rt->rowFields($repUuid), $row, $refUuids, $fileUuids, $hidden);
                    }
                }
            }
            foreach ($refUuids as $fieldUuid => $uuids) {
                $titles[$fieldUuid] = $this->refs->titles($rt, $rt->fields[$fieldUuid], array_values(array_unique($uuids)));
            }
            if ($fileUuids !== []) {
                foreach (StoredFile::query()->whereIn('uuid', array_values(array_unique($fileUuids)))->get() as $file) {
                    $files[$file->uuid] = ['uuid' => $file->uuid, 'name' => $file->original_name, 'size' => $file->size_bytes, 'mime' => $file->mime_type, 'width' => $file->width, 'height' => $file->height];
                }
            }
        }
        $userIds = [];
        foreach ($records as $r) {
            $userIds[] = $r['system']['created_by'];
            $userIds[] = $r['system']['updated_by'];
        }
        $users = DB::table('users')->whereIn('id', array_filter($userIds))->pluck('name', 'id')->all();
        $versions = DB::table('form_versions')->whereIn('id', array_unique(array_map(static fn ($r) => $r['system']['form_version_id'], $records)))->pluck('version_number', 'id')->all();
        $template = $rt->definition['form']['titleTemplate'] ?? null;
        $wf = WorkflowRuntime::for($rt);
        $titlesByKey = [];
        foreach ($titles as $fieldUuid => $map) {
            $titlesByKey[$rt->fields[$fieldUuid]['key']] = $map;
        }

        $out = [];
        foreach ($records as $r) {
            $values = $r['values'];
            foreach ($rt->mainFields() as $f) {
                if (isset($hidden[$f['uuid']])) {
                    unset($values[$f['key']]);
                }
            }
            foreach ($rt->repeaters as $repUuid => $rep) {
                if (! isset($values[$rep['group']['key']])) {
                    continue;
                }
                foreach ($values[$rep['group']['key']] as $i => $row) {
                    foreach ($rt->rowFields($repUuid) as $f) {
                        if (isset($hidden[$f['uuid']])) {
                            unset($values[$rep['group']['key']][$i][$f['key']]);
                        }
                    }
                }
            }
            $title = null;
            if ($template !== null) {
                $ctx = Context::now()->with(['record' => ValuesRecord::forRecord($rt, $r['values'], $this->loader)]);
                $result = Evaluator::evaluate($template, $ctx)->value;
                $title = self::text($result);
            }
            $out[] = [
                'uuid' => $r['uuid'],
                'row_version' => $r['row_version'],
                'title' => $title ?? $r['system']['record_number'] ?? null,
                'values' => $values,
                'references' => $titlesByKey === [] ? (object) [] : $titlesByKey,
                'files' => $files === [] ? (object) [] : $this->filesOf($rt, $r['values'], $files),
                'system' => [
                    'record_number' => $r['system']['record_number'],
                    'version' => $versions[$r['system']['form_version_id']] ?? null,
                    'created_at' => $r['system']['created_at'],
                    'created_by' => $users[$r['system']['created_by']] ?? null,
                    'updated_at' => $r['system']['updated_at'],
                    'updated_by' => $users[$r['system']['updated_by']] ?? null,
                    'deleted_at' => $r['system']['deleted_at'],
                    'status' => self::status($wf, $r['system']['status_id'] ?? null),
                    'status_changed_at' => $r['system']['status_changed_at'] ?? null,
                ],
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function status(WorkflowRuntime $wf, ?int $statusId): ?array
    {
        $s = $wf->status($statusId);

        return $s === null ? null : ['uuid' => $s['uuid'], 'key' => $s['key'], 'name' => WorkflowRuntime::label($s), 'color' => $s['color'], 'icon' => $s['icon'] ?? null, 'final' => (bool) ($s['final'] ?? false)];
    }

    public static function text(Value $v): ?string
    {
        return match ($v->type) {
            'null' => null,
            'text' => $v->data,
            'number', 'duration' => $v->data->toString(),
            'boolean' => $v->data ? 'true' : 'false',
            'date' => Civil::formatDate($v->data),
            'datetime' => Civil::formatDatetime($v->data),
            'time' => Civil::formatTime($v->data),
            'record' => $v->data->title(),
            default => implode(', ', array_filter(array_map(self::text(...), $v->items()), static fn ($s) => $s !== null)),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<array-key, list<string>>  $refUuids
     * @param  list<string>  $fileUuids
     */
    private function collect(FormRuntime $rt, array $fields, array $values, array &$refUuids, array &$fileUuids, array $hidden): void
    {
        foreach ($fields as $f) {
            if (isset($hidden[$f['uuid']])) {
                continue;
            }
            $v = $values[$f['key']] ?? null;
            if ($v === null) {
                continue;
            }
            $storage = $rt->type($f)?->storage;
            if (in_array($storage, ['file', 'files'], true)) {
                $fileUuids = [...$fileUuids, ...array_values(array_filter((array) $v, 'is_string'))];
            } elseif ($rt->targetTable($f) !== null) {
                $refUuids[$f['uuid']] = [...($refUuids[$f['uuid']] ?? []), ...array_values(array_filter((array) $v, 'is_string'))];
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function filesOf(FormRuntime $rt, array $values, array $files): array
    {
        $out = [];
        array_walk_recursive($values, static function ($v) use (&$out, $files): void {
            if (is_string($v) && isset($files[$v])) {
                $out[$v] = $files[$v];
            }
        });

        return $out;
    }
}
