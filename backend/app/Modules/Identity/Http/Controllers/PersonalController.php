<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\Flags\FeatureFlags;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\References;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pinned records and personal shortcuts (specification §4.36, ADR-0063).
 * Kept on the person's preferences; shown only while they may still see
 * what they point to.
 */
final class PersonalController extends Controller
{
    public const MAX_PINS = 50;

    public const MAX_SHORTCUTS = 20;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordScope $scope,
        private readonly AccessResolver $access,
        private readonly References $references,
        private readonly Translator $translator,
        private readonly FeatureFlags $flags,
    ) {}

    public function pins(): JsonResponse
    {
        $user = $this->user();
        $out = [];
        foreach ((array) ($user->preference()->first()->pinned_records ?? []) as $pin) {
            $form = Form::query()->where('uuid', (string) ($pin['form'] ?? ''))->first();
            $rt = $form === null ? null : $this->runtimes->forForm($form);
            if ($rt === null || ! $this->visible($user, $form)) {
                continue;
            }
            $row = $this->scope->apply(DB::table($rt->table)->where('uuid', (string) ($pin['record'] ?? ''))->whereNull('deleted_at'), $rt, $user, 'view')->first();
            if ($row === null) {
                continue;
            }
            $out[] = ['form' => strtolower($form->uuid), 'record' => strtolower((string) $row->uuid), 'title' => $this->references->recordTitle($rt->definition, $row),
                'form_name' => $this->translator->labelOf($form->translationsFor('name'), (string) $form->key)];
        }

        return response()->json(['data' => $out]);
    }

    public function pin(Request $request): JsonResponse
    {
        $user = $this->user();
        $data = $request->validate(['form' => ['required', 'uuid'], 'record' => ['required', 'uuid']]);
        $form = Form::query()->where('uuid', strtolower($data['form']))->first();
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        abort_if($rt === null || ! $this->visible($user, $form), 404);
        $exists = $this->scope->apply(DB::table($rt->table)->where('uuid', strtolower($data['record']))->whereNull('deleted_at'), $rt, $user, 'view')->exists();
        abort_unless($exists, 404);
        $preference = $user->preference()->firstOrNew();
        $pins = array_values(array_filter((array) ($preference->pinned_records ?? []), static fn ($p) => ($p['record'] ?? null) !== strtolower($data['record'])));
        if (count($pins) >= self::MAX_PINS) {
            throw ValidationException::withMessages(['record' => __('identity.too_many_pins', ['max' => self::MAX_PINS])]);
        }
        array_unshift($pins, ['form' => strtolower($data['form']), 'record' => strtolower($data['record'])]);
        $preference->forceFill(['pinned_records' => $pins])->save();

        return response()->json(null, 204);
    }

    public function unpin(string $record): JsonResponse
    {
        $preference = $this->user()->preference()->firstOrNew();
        $preference->forceFill(['pinned_records' => array_values(array_filter((array) ($preference->pinned_records ?? []), static fn ($p) => ($p['record'] ?? null) !== strtolower($record)))])->save();

        return response()->json(null, 204);
    }

    public function shortcuts(): JsonResponse
    {
        $user = $this->user();
        $out = [];
        foreach ((array) ($user->preference()->first()->shortcuts ?? []) as $s) {
            $form = Form::query()->where('uuid', (string) ($s['form'] ?? ''))->first();
            if ($form === null || $form->state !== 'published' || ! $this->visible($user, $form, ($s['action'] ?? 'list') === 'new' ? 'create' : 'view')) {
                continue;
            }
            $out[] = ['form' => strtolower($form->uuid), 'action' => $s['action'] ?? 'list', 'label' => $s['label'] ?? null,
                'form_name' => $this->translator->labelOf($form->translationsFor('name'), (string) $form->key)];
        }

        return response()->json(['data' => $out]);
    }

    public function saveShortcuts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shortcuts' => ['present', 'array', 'max:'.self::MAX_SHORTCUTS],
            'shortcuts.*.form' => ['required', 'uuid'],
            'shortcuts.*.action' => ['required', Rule::in(['list', 'new'])],
            'shortcuts.*.label' => ['nullable', 'string', 'max:80'],
        ]);
        $user = $this->user();
        $clean = [];
        foreach ($data['shortcuts'] as $i => $s) {
            $form = Form::query()->where('uuid', strtolower($s['form']))->first();
            if ($form === null || ! $this->visible($user, $form, $s['action'] === 'new' ? 'create' : 'view')) {
                throw ValidationException::withMessages(["shortcuts.{$i}.form" => __('identity.shortcut_form')]);
            }
            $label = trim((string) ($s['label'] ?? ''));
            $clean[] = ['form' => strtolower($s['form']), 'action' => $s['action'], 'label' => $label === '' ? null : $label];
        }
        $user->preference()->firstOrNew()->forceFill(['shortcuts' => $clean])->save();

        return $this->shortcuts();
    }

    private function visible(User $user, Form $form, string $ability = 'view'): bool
    {
        return $this->access->allows($user, 'form.'.strtolower((string) $form->uuid).'.'.$ability) && $this->flags->allows($user, 'form', (int) $form->id);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
