<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\External\Models\AccessToken;
use App\Modules\Forms\Definition\ClientDefinition;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\EmailQueue;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Workflow\Runtime\WorkflowEngine;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tokenized single-record links (specification §4.32, ADR-0058).
 *
 * - A person who can act on a record sends someone without an account a
 *   link to that one record with one action: view it, approve or reject it
 *   through a chosen workflow transition, or complete one section of it.
 * - The link carries a 256-bit random token; only its SHA-256 is stored.
 *   It expires, allows a set number of uses, and can be revoked.
 * - Whatever the recipient does is done as the person who sent the link,
 *   through the same pipeline as the UI: their permissions, record scope,
 *   field access, rules, validation, workflow requirements and
 *   justification rules all apply at the moment of use, and the record's
 *   version is checked so nothing is overwritten. Every opening and use is
 *   audited with the link and the recipient's address.
 */
final class AccessLinks
{
    public const ACTIONS = ['view', 'approve', 'reject', 'complete_section'];

    /** Group types a "complete a section" link can point at. */
    private const SECTION_TYPES = ['section', 'fieldset', 'card', 'tab', 'step', 'panel', 'accordion'];

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordScope $scope,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordPipeline $pipeline,
        private readonly RecordPresenter $presenter,
        private readonly ClientDefinition $client,
        private readonly WorkflowEngine $workflow,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly EmailQueue $emails,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * What a link to this record can do for this person: actions, the
     * transitions they may perform now, and the sections they may edit.
     *
     * @return array{actions: list<string>, transitions: list<array{uuid: string, label: string}>, sections: list<array{key: string, label: string}>}
     */
    public function choices(FormRuntime $rt, User $issuer, int $recordId): array
    {
        $status = DB::table($rt->table)->where('id', $recordId)->value('status_id');
        $status = $status === null ? null : (int) $status;
        $wf = WorkflowRuntime::for($rt);
        $transitions = [];
        foreach ($wf->from($status) as $t) {
            if ($t['toId'] !== null && $this->pipeline->permitted($rt, $issuer, ['transition.'.$t['uuid'].'.perform']) !== false) {
                $transitions[] = ['uuid' => $t['uuid'], 'label' => $this->translator->labelOf($t['i18n']['name'] ?? null, Translator::humanize((string) $t['key']))];
            }
        }
        $sections = [];
        $edit = $this->scope->allows($rt, $issuer, 'edit', $recordId) ? $this->fieldAccess->resolve($issuer, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit', $status) : null;
        if ($edit !== null && ($edit['modes']['edit'] ?? false)) {
            foreach ($rt->definition['groups'] as $g) {
                if (in_array($g['type'], self::SECTION_TYPES, true) && $this->sectionFields($rt, $g['uuid'], $edit['fields']) !== []) {
                    $sections[] = ['key' => (string) $g['key'], 'label' => $this->translator->labelOf($g['i18n']['title'] ?? null, Translator::humanize((string) $g['key']))];
                }
            }
        }
        $actions = ['view'];
        if ($transitions !== []) {
            array_push($actions, 'approve', 'reject');
        }
        if ($sections !== []) {
            $actions[] = 'complete_section';
        }

        return ['actions' => $actions, 'transitions' => $transitions, 'sections' => $sections];
    }

    /**
     * Issues a link. Returns the link (shown once) and the stored token.
     *
     * @param  array<string, mixed>  $p  action, transition, section, recipient, days, uses
     * @return array{url: string, token: AccessToken}
     */
    public function issue(FormRuntime $rt, User $issuer, int $recordId, array $p, string $locale): array
    {
        if (! $this->scope->allows($rt, $issuer, 'view', $recordId)) {
            throw new RecordException(404, 'not_found', __('records.not_found'));
        }
        $choices = $this->choices($rt, $issuer, $recordId);
        $action = (string) ($p['action'] ?? '');
        $errors = [];
        if (! in_array($action, $choices['actions'], true)) {
            $errors['action'] = __('external.link_action_unavailable');
        }
        $transitionId = null;
        if (in_array($action, ['approve', 'reject'], true)) {
            $uuid = is_string($p['transition'] ?? null) ? strtolower($p['transition']) : '';
            if (! in_array($uuid, array_column($choices['transitions'], 'uuid'), true)) {
                $errors['transition'] = __('external.link_transition_unavailable');
            } else {
                $transitionId = (int) DB::table('transitions')->where('form_id', $rt->form->id)->where('uuid', $uuid)->value('id');
            }
        }
        $section = null;
        if ($action === 'complete_section') {
            $section = is_string($p['section'] ?? null) ? $p['section'] : '';
            if (! in_array($section, array_column($choices['sections'], 'key'), true)) {
                $errors['section'] = __('external.link_section_unavailable');
            }
        }
        $recipient = null;
        if (($p['recipient'] ?? null) !== null && $p['recipient'] !== '') {
            $recipient = app(VerificationCodes::class)->normalize('email', $p['recipient']);
            if ($recipient === null) {
                $errors['recipient'] = __('external.recipient_invalid');
            }
        }
        $days = filter_var($p['days'] ?? 7, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
        $uses = filter_var($p['uses'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($days === false) {
            $errors['days'] = __('external.link_days_invalid');
        }
        if ($uses === false) {
            $errors['uses'] = __('external.link_uses_invalid');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = new AccessToken;
        $token->forceFill([
            'token_hash' => hash('sha256', $plain), 'purpose' => 'record_action', 'form_id' => $rt->form->id, 'record_id' => $recordId,
            'action' => $action, 'section_key' => $section, 'transition_id' => $transitionId, 'recipient_email' => $recipient,
            'max_uses' => (int) $uses, 'uses' => 0, 'expires_at' => Carbon::now('UTC')->addDays((int) $days), 'created_by' => $issuer->id,
        ])->save();
        $url = url('/x/t/'.$plain);
        $this->audit->record('external.link_issued', 'access', null, 'access_token', $token->id,
            ['action' => $action, 'recipient' => $recipient, 'expires_at' => $token->expires_at->toIso8601ZuluString(), 'uses' => $token->max_uses], $issuer->id, null, $rt->form->id, $recordId);
        if ($recipient !== null) {
            $title = $this->recordTitle($rt, $recordId);
            $this->emails->enqueue([
                'source' => 'system', 'locale' => $locale, 'to' => [$recipient], 'form_id' => $rt->form->id, 'record_id' => $recordId,
                'subject' => __('external.link_subject', ['name' => $issuer->name, 'record' => $title], $locale),
                'html' => '<p dir="'.(str_starts_with($locale, 'ar') ? 'rtl' : 'ltr').'">'.e(__('external.link_body', ['name' => $issuer->name, 'record' => $title, 'date' => $token->expires_at->toDateString()], $locale))
                    .'</p><p><a href="'.e($url).'">'.e(__('external.link_open', [], $locale)).'</a></p>',
            ]);
        }

        return ['url' => $url, 'token' => $token];
    }

    /** The token behind a link, in its organization; null when unknown. */
    public function find(string $plain, string $purpose = 'record_action'): ?AccessToken
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $plain) !== 1) {
            return null;
        }
        /** @var AccessToken|null $t */
        $t = AccessToken::query()->withoutGlobalScope('organization')->where('token_hash', hash('sha256', $plain))->where('purpose', $purpose)->first();
        if ($t !== null) {
            $this->tenant->set($t->organization_id);
        }

        return $t;
    }

    /** Why a link cannot be used, or null when it can. */
    public function state(AccessToken $t): ?string
    {
        return match (true) {
            $t->revoked_at !== null => 'revoked',
            $t->expires_at->isPast() => 'expired',
            $t->uses >= $t->max_uses => 'used',
            default => null,
        };
    }

    /**
     * What the recipient sees: the record as the sender sees it (files left
     * out), or the one section to complete, and the action's details.
     *
     * @return array<string, mixed>
     */
    public function page(AccessToken $t, string $ip): array
    {
        [$rt, $issuer, $found] = $this->context($t);
        $status = $found['system']['status_id'] ?? null;
        $mode = $t->action === 'complete_section' ? 'edit' : 'view';
        $levels = $this->fieldAccess->resolve($issuer, $rt->form->id, $rt->form->uuid, $rt->definition, $mode, $status);
        foreach ($rt->definition['fields'] as $f) {
            $type = $rt->type($f);
            if ($type === null || $type->category === 'file' || ($t->action === 'complete_section' && ! isset($this->sectionFields($rt, $this->sectionUuid($rt, (string) $t->section_key), $levels['fields'])[$f['uuid']]))) {
                $levels['fields'][$f['uuid']] = 'hidden';
            }
        }
        $levels = ExternalForms::hideEmptyGroups($rt, $levels);
        $presented = $this->presenter->present($rt, $found, $levels['fields']);
        $definition = $this->client->build($rt->definition, $levels, $mode);
        $definition['relations'] = [];
        $wf = WorkflowRuntime::for($rt);
        $transition = null;
        if ($t->transition_id !== null) {
            $uuid = strtolower((string) DB::table('transitions')->where('id', $t->transition_id)->value('uuid'));
            $tr = $wf->transitions[$uuid] ?? null;
            $transition = $tr === null ? null : ['label' => $this->translator->labelOf($tr['i18n']['name'] ?? null, Translator::humanize((string) $tr['key'])), 'comment' => $tr['comment'] ?? 'none'];
        }
        if ($t->action === 'view') {
            $this->consume($t, $ip);
        }
        $this->audit->record('external.link_opened', 'access', null, 'access_token', $t->id, ['action' => $t->action, 'recipient' => $t->recipient_email], $issuer->id, null, $rt->form->id, $t->record_id);

        return [
            'action' => $t->action, 'form_name' => $this->formName($rt), 'title' => $presented['title'] ?? null,
            'status' => $this->workflow->statusPayload($wf, $status === null ? null : (int) $status),
            'sender' => $issuer->name, 'expires_at' => $t->expires_at->toIso8601ZuluString(), 'uses_left' => max(0, $t->max_uses - $t->uses),
            'transition' => $transition, 'definition' => $definition + ['user' => self::nobody()],
            'values' => $presented['values'], 'references' => $presented['references'], 'row_version' => $found['row_version'],
        ];
    }

    /**
     * Performs the link's action as its sender.
     *
     * @param  array<string, mixed>  $input  values, row_version, comment
     * @return array<string, mixed>
     */
    public function perform(AccessToken $t, array $input, string $ip): array
    {
        [$rt, $issuer, $found] = $this->context($t);
        if ($t->action === 'view') {
            throw new RecordException(422, 'invalid', __('external.link_view_only'));
        }
        $version = filter_var($input['row_version'] ?? null, FILTER_VALIDATE_INT);
        if ($version === false) {
            throw new RecordException(422, 'invalid', __('records.invalid'));
        }
        $this->consume($t, $ip);
        $key = 'xt-'.$t->id.'-'.$t->uses;
        $previous = Auth::user();
        Auth::setUser($issuer);
        try {
            if ($t->action === 'complete_section') {
                $levels = $this->fieldAccess->resolve($issuer, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit', $found['system']['status_id'] ?? null);
                $keys = [];
                foreach ($this->sectionFields($rt, $this->sectionUuid($rt, (string) $t->section_key), $levels['fields']) as $f) {
                    $keys[$f['key']] = true;
                }
                $this->pipeline->update($rt, $issuer, $found['uuid'], (int) $version, array_intersect_key((array) ($input['values'] ?? []), $keys), $key, 'external');
            } else {
                $uuid = strtolower((string) DB::table('transitions')->where('id', $t->transition_id)->value('uuid'));
                $comment = is_string($input['comment'] ?? null) ? $input['comment'] : null;
                $this->pipeline->transition($rt, $issuer, $found['uuid'], $uuid, (int) $version, $comment === null ? [] : ['comment' => $comment], $key, 'external');
            }
        } catch (RecordException $e) {
            // The use is given back: nothing changed.
            DB::table('access_tokens')->where('id', $t->id)->where('uses', '>', 0)->update(['uses' => DB::raw('uses - 1')]);
            throw $e;
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
        $this->audit->record('external.link_used', 'access', null, 'access_token', $t->id, ['action' => $t->action, 'recipient' => $t->recipient_email], $issuer->id, null, $rt->form->id, $t->record_id);

        return ['done' => true];
    }

    public function revoke(AccessToken $t, User $actor): void
    {
        if ($t->revoked_at === null) {
            $t->forceFill(['revoked_at' => Carbon::now('UTC'), 'revoked_by' => $actor->id])->save();
            $this->audit->record('external.link_revoked', 'access', null, 'access_token', $t->id, ['action' => $t->action], $actor->id, null, $t->form_id, $t->record_id);
        }
    }

    /** @return array<string, mixed> */
    public function present(AccessToken $t): array
    {
        return [
            'uuid' => strtolower($t->uuid), 'action' => $t->action, 'purpose' => $t->purpose, 'recipient' => $t->recipient_email,
            'transition' => $t->transition_id === null ? null : $this->transitionLabel($t->transition_id),
            'section' => $t->section_key, 'uses' => $t->uses, 'max_uses' => $t->max_uses,
            'expires_at' => $t->expires_at->toIso8601ZuluString(), 'created_at' => $t->created_at?->toIso8601ZuluString(),
            'created_by' => $t->created_by === null ? null : DB::table('users')->where('id', $t->created_by)->value('name'),
            'state' => $this->state($t) ?? 'active',
        ];
    }

    /** Counts one use; a link used up meanwhile is refused. */
    private function consume(AccessToken $t, string $ip): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $ok = DB::table('access_tokens')->where('id', $t->id)->whereNull('revoked_at')->where('expires_at', '>', $now)->whereColumn('uses', '<', 'max_uses')
            ->update(['uses' => DB::raw('uses + 1'), 'last_used_at' => $now, 'last_used_ip' => mb_substr($ip, 0, 45), 'updated_at' => $now]);
        if ($ok === 0) {
            throw new RecordException(410, 'link_gone', __('external.link_state.used'));
        }
        $t->uses++;
    }

    /** @return array{0: FormRuntime, 1: User, 2: array<string, mixed>} */
    private function context(AccessToken $t): array
    {
        $form = Form::query()->find($t->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $issuer = $t->created_by === null ? null : User::query()->whereKey($t->created_by)->where('status', 'active')->first();
        $uuid = $rt === null ? null : DB::table($rt->table)->where('id', $t->record_id)->value('uuid');
        $found = $uuid === null || $rt === null ? null : $this->store->find($rt, strtolower((string) $uuid));
        // The sender must still be able to see the record; otherwise the link shows nothing.
        if ($rt === null || $issuer === null || $found === null || ! $this->scope->allows($rt, $issuer, 'view', $t->record_id)) {
            throw new RecordException(410, 'link_gone', __('external.link_state.unavailable'));
        }

        return [$rt, $issuer, $found];
    }

    /**
     * Fields of a section the person may edit, by uuid.
     *
     * @param  array<string, string>  $levels
     * @return array<string, array<string, mixed>>
     */
    private function sectionFields(FormRuntime $rt, ?string $groupUuid, array $levels): array
    {
        if ($groupUuid === null) {
            return [];
        }
        $out = [];
        foreach ($rt->mainFields() as $f) {
            $type = $rt->type($f);
            if ($type === null || ! $type->isStored() || $type->calculated || $type->category === 'file' || ! in_array($levels[$f['uuid']] ?? 'hidden', ['editable', 'required'], true)) {
                continue;
            }
            for ($g = $f['group'] ?? null, $seen = []; $g !== null && ! isset($seen[$g]); $g = $rt->groups[$g]['parent'] ?? null) {
                $seen[$g] = true;
                if ($g === $groupUuid) {
                    $out[$f['uuid']] = $f;
                    break;
                }
            }
        }

        return $out;
    }

    private function sectionUuid(FormRuntime $rt, string $key): ?string
    {
        foreach ($rt->groups as $uuid => $g) {
            if ($g['key'] === $key) {
                return $uuid;
            }
        }

        return null;
    }

    private function recordTitle(FormRuntime $rt, int $recordId): string
    {
        $number = DB::table($rt->table)->where('id', $recordId)->value('record_number');

        return $number === null ? $this->formName($rt) : $this->formName($rt).' '.$number;
    }

    private function formName(FormRuntime $rt): string
    {
        return $this->translator->many('form', [$rt->form->id], ['name'])[$rt->form->id]['name'] ?? Translator::humanize((string) $rt->form->key);
    }

    private function transitionLabel(int $id): string
    {
        $row = DB::table('transitions')->where('id', $id)->first(['id', 'key']);

        return $row === null ? '' : $this->translator->labelOf($this->translator->all('transition', (int) $row->id, 'name'), Translator::humanize((string) $row->key));
    }

    /** @return array<string, mixed> */
    public static function nobody(): array
    {
        return ['id' => null, 'uuid' => null, 'name' => null, 'email' => null, 'roles' => [], 'role_uuids' => [], 'department' => null, 'department_uuid' => null, 'departments' => [], 'attributes' => (object) []];
    }
}
