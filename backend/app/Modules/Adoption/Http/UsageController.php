<?php

declare(strict_types=1);

namespace App\Modules\Adoption\Http;

use App\Http\Controllers\Controller;
use App\Modules\Adoption\UsageRecorder;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Usage insight (specification §4.37, ADR-0066): the screens report views,
 * starts and abandons; holders of "Manage forms" read how forms are used, so
 * that the builder can be improved on evidence.
 */
final class UsageController extends Controller
{
    public function __construct(private readonly UsageRecorder $usage) {}

    /** A batch of events from the screens. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:50'],
            'events.*.metric' => ['required', Rule::in(UsageRecorder::CLIENT_METRICS)],
            'events.*.form' => ['required', 'uuid'],
        ]);
        $this->usage->fromClient($this->user(), $data['events']);

        return response()->json(null, 204);
    }

    public function forms(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        [$from, $to] = $this->range($request);

        return response()->json(['data' => ['from' => $from, 'to' => $to, 'tracking' => $this->usage->mode(), 'forms' => $this->usage->forms($from, $to)]]);
    }

    public function form(Request $request, string $form): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        [$from, $to] = $this->range($request);
        $id = Str::isUuid($form) ? DB::table('forms')->where('uuid', strtolower($form))->value('id') : null;
        abort_if($id === null, 404);

        return response()->json(['data' => ['from' => $from, 'to' => $to] + $this->usage->form((int) $id, $from, $to)]);
    }

    /** @return array{0: string, 1: string} the last 30 days unless asked otherwise; at most a year */
    private function range(Request $request): array
    {
        $data = $request->validate(['from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = Carbon::parse($data['to'] ?? Carbon::now('UTC')->toDateString(), 'UTC');
        $from = Carbon::parse($data['from'] ?? $to->copy()->subDays(29)->toDateString(), 'UTC');
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
