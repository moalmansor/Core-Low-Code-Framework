<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Monitoring\Models\ErrorGroup;
use App\Modules\Monitoring\Models\ErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Error monitoring console (specification §4.21). Requires View Errors. */
final class ErrorController extends Controller
{
    public function groups(Request $request): JsonResponse
    {
        Gate::authorize('system.view_errors');
        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'severity' => ['nullable', Rule::in(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])],
            'status' => ['nullable', Rule::in(['new', 'in_progress', 'resolved', 'ignored'])],
            'module' => ['nullable', 'string', 'max:48'],
            'user_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $q = ErrorGroup::query();
        if (! empty($f['from'])) {
            $q->where('last_seen_at', '>=', Carbon::parse($f['from'])->format('Y-m-d H:i:s.u'));
        }
        if (! empty($f['to'])) {
            $q->where('first_seen_at', '<=', Carbon::parse($f['to'])->endOfDay()->format('Y-m-d H:i:s.u'));
        }
        foreach (['severity', 'status', 'module'] as $field) {
            if (! empty($f[$field])) {
                $q->where($field, $f[$field]);
            }
        }
        if (! empty($f['user_id'])) {
            $q->whereIn('id', ErrorLog::query()->select('error_group_id')->where('user_id', $f['user_id']));
        }
        if (! empty($f['search'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['search']).'%';
            $q->where(static fn ($w) => $w->where('exception_class', 'like', $term)->orWhere('message_sample', 'like', $term));
        }

        return response()->json($q->orderByDesc('last_seen_at')->paginate((int) ($f['per_page'] ?? 25)));
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('system.view_errors');
        $group = ErrorGroup::query()->findOrFail($id);
        $logs = ErrorLog::query()->where('error_group_id', $group->id)->orderByDesc('occurred_at')->limit(50)->get();

        return response()->json(['data' => ['group' => $group, 'occurrences' => $logs]]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('system.view_errors');
        $group = ErrorGroup::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['new', 'in_progress', 'resolved', 'ignored'])],
            'assignee_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists(User::class, 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);
        if (($data['status'] ?? null) === 'resolved' && $group->status !== 'resolved') {
            $data['resolved_at'] = now();
            $data['resolved_by'] = Auth::id();
        } elseif (isset($data['status']) && $data['status'] !== 'resolved') {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }
        $group->fill($data)->save();

        return response()->json(['data' => $group->fresh()]);
    }

    public function byReference(string $code): JsonResponse
    {
        Gate::authorize('system.view_errors');
        $log = ErrorLog::query()->where('reference_code', $code)
            ->where('organization_id', app(\App\Modules\Core\Tenancy\TenantContext::class)->organizationId())->firstOrFail();

        return response()->json(['data' => $log]);
    }
}
