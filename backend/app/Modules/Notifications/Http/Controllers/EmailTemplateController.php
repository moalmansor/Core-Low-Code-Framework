<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\EmailQueue;
use App\Modules\Notifications\EmailTemplates;
use App\Modules\Notifications\HtmlPreviews;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\TemplateRenderer;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Email templates (specification §4.16): list, edit with the visual
 * designer, preview with a real record (as a chosen recipient), send a test
 * to yourself, and the placeholders a template may use. Needs the
 * "Manage notifications" permission.
 */
final class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplates $templates,
        private readonly TemplateRenderer $renderer,
        private readonly Translator $translator,
        private readonly FormRuntimes $runtimes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $q = EmailTemplate::query()->orderBy('key');
        if ($request->filled('form')) {
            $formId = DB::table('forms')->where('uuid', strtolower((string) $request->query('form')))->value('id');
            // A form's rules may use its own templates and the general ones.
            $q->where(fn ($w) => $w->whereNull('form_id')->orWhere('form_id', $formId));
        }
        $forms = DB::table('forms')->pluck('uuid', 'id')->all();

        return response()->json(['data' => $q->get()->map(fn (EmailTemplate $t) => [
            'uuid' => strtolower($t->uuid), 'key' => $t->key, 'active' => $t->is_active,
            'subject' => $this->translator->labelOf($t->translationsFor('subject'), $t->key),
            'form' => $t->form_id === null ? null : strtolower((string) ($forms[$t->form_id] ?? '')),
            'form_name' => $t->form_id === null ? null : $this->translator->labelOf(Form::query()->find($t->form_id)?->translationsFor('name'), (string) DB::table('forms')->where('id', $t->form_id)->value('key')),
            'used_by' => DB::table('notification_rules')->where('email_template_id', $t->id)->count(),
        ])->values()]);
    }

    public function show(string $template): JsonResponse
    {
        Gate::authorize('system.manage_notifications');

        return response()->json(['data' => $this->templates->present($this->find($template))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $t = $this->templates->save(null, $request->validate($this->rules()), (int) Auth::id());

        return response()->json(['data' => $this->templates->present($t)], 201);
    }

    public function update(Request $request, string $template): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $t = $this->find($template);
        $data = $request->validate($this->rules() + ['base_hash' => ['required', 'string', 'size:64']]);
        $current = $this->templates->present($t);
        if (! hash_equals($current['hash'], $data['base_hash'])) {
            return response()->json(['message' => __('notifications.changed_elsewhere'), 'code' => 'template_changed', 'data' => $current], 409);
        }

        return response()->json(['data' => $this->templates->present($this->templates->save($t, $data, (int) Auth::id()))]);
    }

    public function destroy(string $template): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $t = $this->find($template);
        abort_if(DB::table('notification_rules')->where('email_template_id', $t->id)->exists(), 409, __('notifications.template_in_use'));
        DB::transaction(function () use ($t): void {
            DB::table('email_queue')->where('email_template_id', $t->id)->update(['email_template_id' => null]);
            $this->translator->forget('email_template', $t->id);
            $t->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Renders a design (saved or not) for a real record, as the current user
     * or as a chosen recipient, in a chosen language.
     */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $data = $request->validate($this->rules(false) + [
            'record' => ['sometimes', 'nullable', 'uuid'],
            'as_user' => ['sometimes', 'nullable', 'uuid'],
            'locale' => ['sometimes', Rule::in($this->translator->enabledLocales())],
        ]);
        [$rt, $recordId] = $this->target($data);
        $form = $rt?->form;
        $design = $this->templates->validate($form, (array) ($data['design'] ?? []), (array) ($data['subject'] ?? []), (array) ($data['preheader'] ?? []));
        $viewer = isset($data['as_user']) ? User::query()->where('uuid', strtolower($data['as_user']))->firstOrFail() : $this->user();
        $out = $this->renderer->render($design, (array) ($data['subject'] ?? []), (array) ($data['preheader'] ?? []), $rt, $recordId, [$viewer], $data['locale'] ?? app()->getLocale(), $viewer->name);

        return response()->json(['data' => ['subject' => $out['subject'], 'html' => $out['html'], 'preheader' => $out['preheader'], 'url' => app(HtmlPreviews::class)->put($out['html'])]]);
    }

    /** Sends the template, rendered for a record as the current user, to the current user. */
    public function test(Request $request, string $template, EmailQueue $queue): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $t = $this->find($template);
        $data = $request->validate(['record' => ['sometimes', 'nullable', 'uuid'], 'locale' => ['sometimes', Rule::in($this->translator->enabledLocales())]]);
        $data['form'] = $t->form_id === null ? null : DB::table('forms')->where('id', $t->form_id)->value('uuid');
        [$rt, $recordId] = $this->target($data);
        $user = $this->user();
        $locale = $data['locale'] ?? app()->getLocale();
        $out = $this->renderer->render($t->design, $t->translationsFor('subject'), $t->translationsFor('preheader'), $rt, $recordId, [$user], $locale, $user->name);
        $id = $queue->enqueue([
            'source' => 'test', 'locale' => $locale, 'to' => [(string) $user->email], 'subject' => $out['subject'], 'html' => $out['html'],
            'template_id' => $t->id, 'form_id' => $rt?->form->id, 'record_id' => $recordId,
        ]);

        return response()->json(['data' => ['status' => DB::table('email_queue')->where('id', $id)->value('status'), 'to' => $user->email]], 202);
    }

    /**
     * What a template may show: system values, the form's fields, fields of
     * records linked from it (one hop; longer paths can be typed), and the
     * repeaters with their columns.
     */
    public function placeholders(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_notifications');
        $system = array_map(fn (string $s) => ['token' => $s, 'label' => __('notifications.placeholder.'.ltrim($s, '@')), 'group' => 'system'], TemplateRenderer::SYSTEM);
        $form = $request->filled('form') ? Form::query()->where('uuid', strtolower((string) $request->query('form')))->first() : null;
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $fields = [];
        $repeaters = [];
        if ($rt !== null) {
            foreach ($rt->mainFields() as $f) {
                if (! $rt->isStored($f) || ($f['flags']['encrypted'] ?? false)) {
                    continue;
                }
                $label = $this->translator->labelOf($f['i18n']['label'] ?? null, (string) $f['key']);
                $fields[] = ['token' => $f['key'], 'label' => $label, 'group' => 'form', 'user' => $rt->targetTable($f) === 'users', 'date' => in_array($rt->type($f)?->storage, ['date', 'datetime'], true), 'file' => in_array($rt->type($f)?->storage, ['file', 'files'], true)];
                $target = ($t = $rt->targetFormUuid($f)) === null ? null : $this->runtimes->forUuid($t);
                foreach ($target?->mainFields() ?? [] as $g) {
                    if ($target->isStored($g) && ! ($g['flags']['encrypted'] ?? false)) {
                        $fields[] = ['token' => $f['key'].'.'.$g['key'], 'label' => $label.' › '.$this->translator->labelOf($g['i18n']['label'] ?? null, (string) $g['key']), 'group' => 'linked', 'user' => $target->targetTable($g) === 'users', 'date' => false, 'file' => false];
                    }
                }
            }
            foreach ($rt->repeaters as $uuid => $rep) {
                $repeaters[] = [
                    'key' => $rep['group']['key'], 'label' => $this->translator->labelOf($rep['group']['i18n']['title'] ?? null, (string) $rep['group']['key']),
                    'columns' => array_map(fn ($f) => ['key' => $f['key'], 'label' => $this->translator->labelOf($f['i18n']['label'] ?? null, (string) $f['key'])], $rt->rowFields($uuid)),
                ];
            }
        }

        return response()->json(['data' => ['system' => $system, 'fields' => $fields, 'repeaters' => $repeaters]]);
    }

    /**
     * The form and a record of it the current user may see.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: FormRuntime|null, 1: int|null}
     */
    private function target(array $data): array
    {
        $form = isset($data['form']) ? Form::query()->where('uuid', strtolower((string) $data['form']))->first() : null;
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null || ! isset($data['record'])) {
            return [$rt, null];
        }
        abort_unless(app(AccessResolver::class)->allows($this->user(), "form.{$rt->form->uuid}.view"), 404);
        $id = DB::table($rt->table)->where('uuid', strtolower((string) $data['record']))->whereNull('deleted_at')->value('id');
        abort_if($id === null || ! app(RecordScope::class)->allows($rt, $this->user(), 'view', (int) $id), 404, __('records.not_found'));

        return [$rt, (int) $id];
    }

    /** @return array<string, mixed> */
    private function rules(bool $withKey = true): array
    {
        return ($withKey ? [
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,47}$/'],
            'application' => ['sometimes', 'nullable', 'uuid'],
            'active' => ['sometimes', 'boolean'],
        ] : []) + [
            'form' => ['sometimes', 'nullable', 'uuid'],
            'design' => ['required', 'array'],
            'design.blocks' => ['present', 'array', 'max:100'],
            'design.style' => ['sometimes', 'array'],
            'subject' => ['required', 'array'],
            'subject.*' => ['nullable', 'string', 'max:255'],
            'preheader' => ['sometimes', 'array'],
            'preheader.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function find(string $uuid): EmailTemplate
    {
        return EmailTemplate::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
