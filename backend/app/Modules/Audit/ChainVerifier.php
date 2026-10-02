<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Walks each hash chain and reports breaks (specification §4.20): a missing or
 * reordered sequence number, a prev_hash that does not match the previous
 * entry, or a hash that does not match the entry's content.
 */
final class ChainVerifier
{
    /**
     * @return array{verified: int, breaks: list<array{chain_id: int, chain_seq: int, id: int, reason: string}>}
     */
    public function verify(bool $full = false): array
    {
        $breaks = [];
        $verified = 0;
        foreach (DB::table('audit_chain_heads')->orderBy('chain_id')->get() as $head) {
            $chain = (int) $head->chain_id;
            $startSeq = $full ? 0 : (int) $head->last_verified_seq;
            $prevHash = str_repeat('0', 64);
            if ($startSeq > 0) {
                $prevHash = (string) DB::table('audit_logs')->where('chain_id', $chain)->where('chain_seq', $startSeq)->value('hash');
            }
            $expectedSeq = $startSeq + 1;
            $lastGood = $startSeq;
            $chainOk = true;
            DB::table('audit_logs')->where('chain_id', $chain)->where('chain_seq', '>', $startSeq)
                ->orderBy('chain_seq')->chunk(1000, function ($rows) use (&$prevHash, &$expectedSeq, &$lastGood, &$breaks, &$verified, &$chainOk, $chain): bool {
                    foreach ($rows as $row) {
                        $entry = (array) $row;
                        $reason = null;
                        if ((int) $entry['chain_seq'] !== $expectedSeq) {
                            $reason = 'sequence gap or reorder';
                        } elseif ($entry['prev_hash'] !== $prevHash) {
                            $reason = 'previous-hash mismatch';
                        } else {
                            $entry['changes'] = $entry['changes'] === null ? null : json_decode((string) $entry['changes'], true, 512, JSON_THROW_ON_ERROR);
                            $entry['meta'] = $entry['meta'] === null ? null : json_decode((string) $entry['meta'], true, 512, JSON_THROW_ON_ERROR);
                            $entry['occurred_at'] = self::normalizeTime((string) $entry['occurred_at']);
                            foreach (['chain_id', 'chain_seq', 'organization_id', 'object_id', 'form_id', 'record_id', 'actor_user_id', 'subject_user_id', 'on_behalf_of_user_id', 'external_user_id', 'impersonation_session_id', 'justification_id'] as $intField) {
                                $entry[$intField] = $entry[$intField] === null ? null : (int) $entry[$intField];
                            }
                            if (AuditWriter::hash($prevHash, $entry) !== $row->hash) {
                                $reason = 'content hash mismatch';
                            }
                        }
                        if ($reason !== null) {
                            $breaks[] = ['chain_id' => $chain, 'chain_seq' => (int) $row->chain_seq, 'id' => (int) $row->id, 'reason' => $reason];
                            $chainOk = false;

                            return false; // stop this chain at the first break
                        }
                        $prevHash = (string) $row->hash;
                        $expectedSeq++;
                        $lastGood = (int) $row->chain_seq;
                        $verified++;
                    }

                    return true;
                });
            if ($chainOk) {
                DB::table('audit_chain_heads')->where('chain_id', $chain)->update([
                    'last_verified_seq' => $lastGood,
                    'last_verified_at' => now()->format('Y-m-d H:i:s.u'),
                ]);
            }
        }

        return ['verified' => $verified, 'breaks' => $breaks];
    }

    /** Both engines return DATETIME(6) values; normalise to the writer's format. */
    public static function normalizeTime(string $value): string
    {
        return \Illuminate\Support\Carbon::parse($value, 'UTC')->format('Y-m-d H:i:s.u');
    }
}
