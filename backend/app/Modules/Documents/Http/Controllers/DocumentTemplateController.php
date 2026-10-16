<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Core\I18n\Translator;
use App\Modules\Documents\DocumentRenderer;
use App\Modules\Documents\DocumentTemplates;
use App\Modules\Documents\GeneratedDocuments;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Documents\PdfConverter;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Templating\HtmlPreviews;
use App\Modules\Records\Templating\TemplatePlaceholders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Document templates (specification §4.17): list, upload a Word template,
 * design one from blocks with a live preview, and generate a document from
 * a real record to check it. Needs "Manage document templates"; previews and
 * test documents show only records and fields the administrator may see.
 */
final class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplates $templates,
        private readonly Translator $translator,
        private readonly TemplatePlaceholders $placeholders,
    ) {}

    public function index(Request $request, PdfConverter $converter): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $q = DocumentTemplate::query()->orderBy('key');
        if ($request->filled('form')) {
            $formId = DB::table('forms')->where('uuid', strtolower((string) $request->query('form')))->value('id');
            // A form's actions and rules may use its own templates and the general ones.
            $q->where(fn ($w) => $w->whereNull('form_id')->orWhere('form_id', $formId));
        }
        $forms = Form::query()->get()->keyBy('id');

        return response()->json([
            'data' => $q->get()->map(fn (DocumentTemplate $t) => [
                'uuid' => strtolower($t->uuid), 'key' => $t->key, 'type' => $t->type, 'active' => $t->is_active, 'formats' => $t->output_formats,
                'name' => $this->translator->labelOf($t->translationsFor('name'), $t->key),
                'form' => $t->form_id === null ? null : strtolower((string) $forms->get($t->form_id)?->uuid),
                'form_name' => $t->form_id === null ? null : $this->translator->labelOf($forms->get($t->form_id)?->translationsFor('name'), (string) $forms->get($t->form_id)?->key),
                'used_by' => $this->templates->usedBy($t),
            ])->values(),
            'meta' => ['pdf_from_word' => $converter->available()],
        ]);
    }

    public function show(string $template): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');

        return response()->json(['data' => $this->templates->present($this->find($template))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $t = $this->templates->save(null, $request->validate($this->rules()), $this->user()->id);

        return response()->json(['data' => $this->templates->present($t)], 201);
    }

    public function update(Request $request, string $template): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $t = $this->find($template);
        $data = $request->validate($this->rules() + ['base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->templates->present($t)['hash'], $data['base_hash'])) {
            return response()->json(['message' => __('documents.changed'), 'code' => 'template_changed'], 409);
        }

        return response()->json(['data' => $this->templates->present($this->templates->save($t, $data, $this->user()->id))]);
    }

    public function destroy(string $template): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $this->templates->delete($this->find($template), $this->user()->id);

        return response()->json(null, 204);
    }

    /** Checks an uploaded Word template and keeps it until a template claims it. */
    public function upload(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $data = $request->validate(['file' => ['required', 'file'], 'form' => ['sometimes', 'nullable', 'uuid']]);

        return response()->json(['data' => $this->templates->upload($request->file('file'), $data['form'] ?? null)], 201);
    }

    /** A designed template (saved or not) as a page, for a real record, in a chosen language. */
    public function preview(Request $request, DocumentRenderer $renderer): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $data = $request->validate([
            'form' => ['sometimes', 'nullable', 'uuid'], 'record' => ['sometimes', 'nullable', 'uuid'],
            'design' => ['required', 'array'], 'design.blocks' => ['present', 'array'], 'design.style' => ['sometimes', 'array'],
            'design.title' => ['sometimes', 'array'], 'design.header' => ['sometimes', 'array'], 'design.footer' => ['sometimes', 'array'],
            'paper' => ['sometimes', 'array'], 'name' => ['sometimes', 'array'],
            'locale' => ['sometimes', Rule::in($this->translator->enabledLocales())],
        ]);
        [$rt, $recordId] = $this->placeholders->target($data['form'] ?? null, $data['record'] ?? null, $this->user());
        $errors = [];
        $designed = $this->templates->design($data['design'], $rt?->form, $rt, $errors);
        $paper = array_replace_recursive(DocumentTemplates::DEFAULT_PAPER, array_intersect_key((array) ($data['paper'] ?? []), DocumentTemplates::DEFAULT_PAPER));
        if (! in_array($paper['size'], DocumentTemplates::PAPER_SIZES, true)) {
            $errors['paper.size'][] = __('validation.in', ['attribute' => 'size']);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $locale = $data['locale'] ?? app()->getLocale();
        $html = $renderer->preview($designed, $paper, $rt, $recordId, [$this->user()], $locale, (string) ($this->translator->pick((array) ($data['name'] ?? []), $locale) ?? ''));

        return response()->json(['data' => ['url' => app(HtmlPreviews::class)->put($html)]]);
    }

    /** Generates the saved template for a record, as the current user sees it, and offers the file. */
    public function generate(Request $request, string $template, DocumentRenderer $renderer, GeneratedDocuments $documents): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');
        $t = $this->find($template);
        $data = $request->validate([
            'record' => ['sometimes', 'nullable', 'uuid'],
            'format' => ['required', Rule::in($t->output_formats)],
            'locale' => ['sometimes', Rule::in($this->translator->enabledLocales())],
        ]);
        $formUuid = $t->form_id === null ? null : (string) DB::table('forms')->where('id', $t->form_id)->value('uuid');
        [$rt, $recordId] = $this->placeholders->target($formUuid, $data['record'] ?? null, $this->user());
        try {
            $out = $renderer->render($t, $rt, $recordId, [$this->user()], $data['locale'] ?? app()->getLocale(), $data['format'], $this->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('documents.generate_failed')], 422);
        }
        $file = $documents->store($out, GeneratedDocuments::OWNER_DOWNLOAD, $rt?->form->id, $recordId, $this->user()->id);

        return response()->json(['data' => [
            'name' => $file->original_name, 'size' => $file->size_bytes,
            'url' => URL::temporarySignedRoute('files.download', now()->addMinutes(5), ['file' => $file->uuid]),
        ]], 201);
    }

    public function placeholders(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_document_templates');

        return response()->json(['data' => $this->placeholders->catalog($request->filled('form') ? (string) $request->query('form') : null)]);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:48', 'regex:/^[a-z][a-z0-9_]*$/'],
            'type' => ['required', Rule::in(['docx', 'html'])],
            'form' => ['sometimes', 'nullable', 'uuid'],
            'name' => ['required', 'array'],
            'design' => ['sometimes', 'nullable', 'array'], 'design.blocks' => ['sometimes', 'array'], 'design.style' => ['sometimes', 'array'],
            'design.title' => ['sometimes', 'array'], 'design.header' => ['sometimes', 'array'], 'design.footer' => ['sometimes', 'array'],
            'files' => ['sometimes', 'nullable', 'array'],
            'formats' => ['required', 'array'],
            'paper' => ['sometimes', 'array'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    private function find(string $uuid): DocumentTemplate
    {
        return DocumentTemplate::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
