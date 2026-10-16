<?php

declare(strict_types=1);

namespace App\Modules\Assignment\Http\Controllers;

use App\Modules\Assignment\Membership;
use App\Modules\Assignment\Models\Queue;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Work queues (specification §4.25): an admin chooses, per role or
 * department queue, which forms appear in My Work and with which columns
 * (field paths of the form, including linked forms' fields).
 */
final class QueueController extends Controller
{
    public function __construct(private readonly Translator $translator, private readonly Membership $members, private readonly AuditWriter $audit) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => Queue::query()->orderBy('key')->get()->map(fn (Queue $q) => $this->present($q))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate($this->rules(true));
        $queue = DB::transaction(function () use ($data): Queue {
            $queue = new Queue;
            $this->fill($queue, $data);

            return $queue;
        });

        return response()->json(['data' => $this->present($queue)], 201);
    }

    public function update(Request $request, string $queue): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $row = Queue::query()->where('uuid', strtolower($queue))->firstOrFail();
        $data = $request->validate($this->rules(false) + ['base_updated_at' => ['required', 'string', 'max:40']]);
        // Saved whole by an editor: a change made elsewhere since it was loaded is never overwritten.
        if ($this->stamp($row) !== $data['base_updated_at']) {
            return response()->json(['message' => __('assignment.queue_changed'), 'code' => 'queue_changed', 'data' => $this->present($row)], 409);
        }
        DB::transaction(fn () => $this->fill($row, $data));

        return response()->json(['data' => $this->present($row->refresh())]);
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Queue $queue, array $data): void
    {
        $type = $data['type'] ?? $queue->type;
        $subject = isset($data['subject']) ? $this->members->idOf($type, strtolower($data['subject'])) : ($type === 'role' ? $queue->role_id : $queue->department_id);
        abort_if($subject === null, 422, __('assignment.target_required'));
        $queue->fill([
            'key' => $data['key'] ?? $queue->key, 'type' => $type,
            'role_id' => $type === 'role' ? $subject : null, 'department_id' => $type === 'department' ? $subject : null,
            'claim_timeout_minutes' => array_key_exists('claim_timeout_minutes', $data) ? $data['claim_timeout_minutes'] : $queue->claim_timeout_minutes,
            'is_active' => $data['is_active'] ?? ($queue->is_active ?? true),
        ])->save();
        if (isset($data['name'])) {
            $queue->setTranslations('name', $data['name']);
        }
        if (isset($data['forms'])) {
            DB::table('queue_forms')->where('queue_id', $queue->id)->delete();
            foreach (array_values($data['forms']) as $i => $f) {
                $formId = DB::table('forms')->where('uuid', strtolower($f['form']))->value('id');
                abort_if($formId === null, 422, __('validation.exists', ['attribute' => "forms.{$i}.form"]));
                DB::table('queue_forms')->insert(['queue_id' => $queue->id, 'form_id' => $formId, 'columns' => json_encode(array_values($f['columns'] ?? [])), 'sort_order' => $i]);
            }
        }
        $this->audit->record('assignment.queue_saved', 'config', null, 'queue', $queue->id, ['key' => $queue->key]);
    }

    /** @return array<string, list<mixed>> */
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        $default = $this->translator->defaultLocale();

        return [
            'key' => [$req, 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/', Rule::unique('queues', 'key')->ignore(request()->route('queue'), 'uuid')],
            'name' => [$req, 'array'],
            'name.'.$default => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'type' => [$req, Rule::in(['role', 'department'])],
            'subject' => [$req, 'uuid'],
            'claim_timeout_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,525600'],
            'is_active' => ['sometimes', 'boolean'],
            'forms' => ['sometimes', 'array', 'max:100'],
            'forms.*.form' => ['required', 'uuid'],
            'forms.*.columns' => ['sometimes', 'array', 'max:12'],
            'forms.*.columns.*' => ['array', 'min:1', 'max:4'],
            'forms.*.columns.*.*' => ['string', 'regex:/^[a-z][a-z0-9_]{0,47}$/'],
        ];
    }

    /** @return array<string, mixed> */
    private function present(Queue $q): array
    {
        $forms = DB::table('queue_forms')->join('forms', 'forms.id', '=', 'queue_forms.form_id')->where('queue_id', $q->id)->orderBy('queue_forms.sort_order')
            ->get(['forms.uuid', 'forms.key', 'forms.id', 'queue_forms.columns']);

        return [
            'uuid' => strtolower($q->uuid), 'key' => $q->key, 'name' => $q->translate('name') ?? Translator::humanize((string) $q->key), 'names' => $q->translationsFor('name'),
            'type' => $q->type, 'subject' => $this->members->uuidOf($q->type, (int) ($q->type === 'role' ? $q->role_id : $q->department_id)),
            'claim_timeout_minutes' => $q->claim_timeout_minutes, 'is_active' => $q->is_active,
            'forms' => $forms->map(fn ($f) => ['form' => strtolower((string) $f->uuid), 'key' => $f->key, 'name' => $this->translator->get('form', (int) $f->id, 'name') ?? Translator::humanize((string) $f->key), 'columns' => json_decode((string) $f->columns, true) ?: []])->values(),
            'updated_at' => $this->stamp($q),
        ];
    }

    private function stamp(Queue $q): ?string
    {
        return $q->updated_at === null ? null : Carbon::parse($q->updated_at)->format('Y-m-d\\TH:i:s.u\\Z');
    }
}
