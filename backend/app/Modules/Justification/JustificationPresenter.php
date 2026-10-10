<?php

declare(strict_types=1);

namespace App\Modules\Justification;

use App\Modules\Records\Models\StoredFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Justifications as shown in the history timeline, the audit log and the
 * record's justifications list (specification §4.24): who, when, which
 * fields, the reason code, the text, the note and the attachments. Callers
 * show them only to holders of View Justifications.
 */
final class JustificationPresenter
{
    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('justifications')->whereIn('id', $ids)->get();

        return $this->present($rows->all());
    }

    /**
     * @param  list<object>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function present(array $rows): array
    {
        $users = DB::table('users')->whereIn('id', array_filter([...array_column($rows, 'user_id'), ...array_column($rows, 'on_behalf_of_user_id')]))->pluck('name', 'id')->all();
        $codes = DB::table('justification_reason_codes')->whereIn('id', array_filter(array_column($rows, 'reason_code_id')))->pluck('code', 'id')->all();
        $files = [];
        $attachments = DB::table('justification_attachments')->whereIn('justification_id', array_column($rows, 'id'))->get();
        $stored = StoredFile::query()->whereIn('id', $attachments->pluck('file_id')->all())->get()->keyBy('id');
        foreach ($attachments as $a) {
            $f = $stored[$a->file_id] ?? null;
            if ($f !== null) {
                $files[(int) $a->justification_id][] = ['uuid' => $f->uuid, 'name' => $f->original_name, 'size' => $f->size_bytes, 'mime' => $f->mime_type];
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = [
                'uuid' => strtolower((string) $r->uuid),
                'context' => $r->context,
                'reason_text' => $r->reason_text,
                'reason_code' => $r->reason_code_id === null && $r->reason_code_record_id === null ? null : [
                    'code' => $codes[$r->reason_code_id] ?? null, 'label' => $r->reason_code_label_snapshot,
                ],
                'note' => $r->note,
                'changed_fields' => json_decode((string) $r->changed_fields, true) ?: [],
                'affected_count' => (int) $r->affected_count,
                'by' => $users[$r->user_id] ?? null,
                'on_behalf_of' => $r->on_behalf_of_user_id === null ? null : ($users[$r->on_behalf_of_user_id] ?? null),
                'at' => Carbon::parse($r->created_at, 'UTC')->toIso8601ZuluString(),
                'attachments' => $files[(int) $r->id] ?? [],
                'content_hash' => $r->content_hash,
            ];
        }

        return $out;
    }
}
