<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The notifications center: a person's own in-app notifications, newest
 * first, with an unread count, marking one or all as read.
 */
final class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['unread' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1', 'max:1000']]);
        $q = DB::table('in_app_notifications')->where('user_id', Auth::id());
        if ($data['unread'] ?? false) {
            $q->whereNull('read_at');
        }
        $page = (int) ($data['page'] ?? 1);
        $rows = (clone $q)->orderByDesc('id')->forPage($page, 20)->get();

        return response()->json([
            'data' => $rows->map(static fn (object $n) => [
                'uuid' => strtolower((string) $n->uuid), 'type' => $n->type, 'title' => $n->title, 'body' => $n->body, 'link' => $n->link,
                'read' => $n->read_at !== null, 'created_at' => Carbon::parse($n->created_at, 'UTC')->toIso8601ZuluString(),
            ])->values(),
            'meta' => ['unread' => $this->unread(), 'total' => (clone $q)->count(), 'page' => $page],
        ]);
    }

    public function count(): JsonResponse
    {
        return response()->json(['data' => ['unread' => $this->unread()]]);
    }

    public function read(string $notification): JsonResponse
    {
        DB::table('in_app_notifications')->where('uuid', strtolower($notification))->where('user_id', Auth::id())->whereNull('read_at')
            ->update(['read_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);

        return response()->json(['data' => ['unread' => $this->unread()]]);
    }

    public function readAll(): JsonResponse
    {
        DB::table('in_app_notifications')->where('user_id', Auth::id())->whereNull('read_at')->update(['read_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);

        return response()->json(['data' => ['unread' => 0]]);
    }

    private function unread(): int
    {
        return DB::table('in_app_notifications')->where('user_id', Auth::id())->whereNull('read_at')->count();
    }
}
