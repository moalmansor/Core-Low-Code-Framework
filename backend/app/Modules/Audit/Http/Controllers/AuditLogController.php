<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\AuditWriter;
use App\Modules\Audit\ChainVerifier;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Core\Tenancy\TenantContext;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Audit log viewer (specification §4.20): filter, inspect, export, verify. */
final class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.view_audit_log');
        $filters = $this->validated($request);
        $page = $this->query($filters)->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(min(100, (int) ($filters['per_page'] ?? 25)));

        return response()->json($page);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        Gate::authorize('system.view_audit_log');
        $entry = AuditLog::query()->where('organization_id', app(TenantContext::class)->organizationId())->findOrFail($id);

        return response()->json(['data' => $entry]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('system.view_audit_log');
        $filters = $this->validated($request);
        $query = $this->query($filters)->orderBy('occurred_at')->orderBy('id');
        $count = (clone $query)->count();
        abort_if($count > 100_000, 422, __('ui.audit.export_too_large'));
        app(AuditWriter::class)->record('audit.exported', 'export', meta: ['filters' => $filters, 'rows' => $count]);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Arabic in spreadsheet software
            fputcsv($out, ['id', 'occurred_at', 'event', 'category', 'object_type', 'object_id', 'actor_user_id', 'ip_address', 'correlation_id', 'changes', 'hash'], escape: '');
            $query->chunk(1000, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, array_map([Csv::class, 'cell'], [
                        $row->id, $row->occurred_at?->format('Y-m-d H:i:s.u'), $row->event, $row->category, $row->object_type,
                        $row->object_id, $row->actor_user_id, $row->ip_address, $row->correlation_id,
                        $row->changes === null ? '' : json_encode($row->changes, JSON_UNESCAPED_UNICODE), $row->hash,
                    ]), escape: '');
                }
            });
            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function verify(ChainVerifier $verifier): JsonResponse
    {
        Gate::authorize('system.view_audit_log');
        $result = $verifier->verify(full: true);
        app(AuditWriter::class)->record('audit.chain_verified', 'security', meta: ['verified' => $result['verified'], 'breaks' => count($result['breaks'])]);

        return response()->json(['data' => $result]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'event' => ['nullable', 'string', 'max:48'],
            'category' => ['nullable', 'string', 'max:32'],
            'object_type' => ['nullable', 'string', 'max:48'],
            'object_id' => ['nullable', 'integer'],
            'actor_user_id' => ['nullable', 'integer'],
            'correlation_id' => ['nullable', 'string', 'max:36'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<AuditLog>
     */
    private function query(array $f): Builder
    {
        $q = AuditLog::query()->where('organization_id', app(TenantContext::class)->organizationId());
        if (! empty($f['from'])) {
            $q->where('occurred_at', '>=', Carbon::parse($f['from'])->format('Y-m-d H:i:s.u'));
        }
        if (! empty($f['to'])) {
            $q->where('occurred_at', '<=', Carbon::parse($f['to'])->endOfDay()->format('Y-m-d H:i:s.u'));
        }
        foreach (['event', 'category', 'object_type', 'object_id', 'actor_user_id', 'correlation_id'] as $field) {
            if (isset($f[$field]) && $f[$field] !== '') {
                $q->where($field, $f[$field]);
            }
        }

        return $q;
    }
}
