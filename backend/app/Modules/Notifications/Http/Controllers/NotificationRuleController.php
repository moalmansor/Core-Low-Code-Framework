<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Forms\Models\Form;
use App\Modules\Notifications\NotificationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * A form's notification rules, read and saved as one document with
 * optimistic concurrency (a document changed elsewhere is a conflict, never
 * overwritten). Needs "Manage forms" or "Manage notifications".
 */
final class NotificationRuleController extends Controller
{
    public function show(Form $form, NotificationRules $rules): JsonResponse
    {
        $this->authorizeAny();

        return response()->json(['data' => ['rules' => $rules->load($form), 'hash' => $rules->hash($form)]]);
    }

    public function save(Request $request, Form $form, NotificationRules $rules): JsonResponse
    {
        $this->authorizeAny();
        $data = $request->validate(['rules' => ['present', 'array', 'max:200'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($rules->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('notifications.changed_elsewhere'), 'code' => 'rules_changed', 'data' => ['rules' => $rules->load($form), 'hash' => $rules->hash($form)]], 409);
        }
        $rules->save($form, array_values(json_decode((string) json_encode($request->input('rules')), true)), (int) Auth::id());

        return $this->show($form, $rules);
    }

    private function authorizeAny(): void
    {
        $access = app(AccessResolver::class);
        $user = Auth::user();
        abort_unless($user !== null && ($access->allows($user, 'system.manage_forms') || $access->allows($user, 'system.manage_notifications')), 403, __('records.forbidden'));
    }
}
