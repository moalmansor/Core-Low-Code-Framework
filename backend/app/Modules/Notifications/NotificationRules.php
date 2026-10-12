<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Assignment\Membership;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Views\RelationPaths;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A form's notification rules as one document (specification §4.16): what
 * triggers each rule (record created or updated, status changed from / to,
 * a field changed, a condition became true, a reminder relative to a date
 * field, SLA warning or escalation, approval requested, record assigned),
 * an optional condition, the channels, the template, and who receives it in
 * To, CC and BCC.
 */
final class NotificationRules
{
    public const TRIGGERS = ['record_created', 'record_updated', 'status_changed', 'field_changed', 'condition_met', 'scheduled_reminder', 'sla_warning', 'sla_escalation', 'approval_requested', 'assigned'];

    public const CHANNELS = ['email', 'in_app'];

    public const RECIPIENTS = ['user', 'role', 'department', 'field_user', 'creator', 'linked_record_users', 'static'];

    public function __construct(
        private readonly OwnedConditions $conditions,
        private readonly Membership $members,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly Translator $translator,
        private readonly FormRuntimes $runtimes,
        private readonly RelationPaths $paths,
        private readonly AuditWriter $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('notification_rules')->where('form_id', $form->id)->orderBy('id')->get();
        $statuses = DB::table('statuses')->whereIn('id', $rows->pluck('from_status_id')->merge($rows->pluck('to_status_id'))->filter()->all())->pluck('uuid', 'id')->all();
        $fields = DB::table('fields')->whereIn('id', $rows->pluck('field_id')->filter()->all())->pluck('uuid', 'id')->all();
        $templates = DB::table('email_templates')->whereIn('id', $rows->pluck('email_template_id')->filter()->all())->pluck('uuid', 'id')->all();
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());
        $names = $this->translator->allMany('notification_rule', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $lower = static fn ($v) => $v === null ? null : strtolower((string) $v);

        return $rows->map(fn ($r) => [
            'uuid' => strtolower((string) $r->uuid),
            'key' => $r->key,
            'name' => (object) ($names[(int) $r->id]['name'] ?? []),
            'trigger' => $r->trigger,
            'fromStatus' => $lower($statuses[$r->from_status_id] ?? null),
            'toStatus' => $lower($statuses[$r->to_status_id] ?? null),
            'field' => $lower($fields[$r->field_id] ?? null),
            'condition' => $r->condition_id === null ? null : ($asts[(int) $r->condition_id] ?? null),
            'schedule' => $r->schedule === null ? null : json_decode((string) $r->schedule, true),
            'template' => $lower($templates[$r->email_template_id] ?? null),
            'channels' => json_decode((string) $r->channels, true),
            'recipients' => json_decode((string) $r->recipients, true),
            'attachments' => $r->attachments === null ? null : json_decode((string) $r->attachments, true),
            'delayMinutes' => $r->delay_minutes === null ? null : (int) $r->delay_minutes,
            'respectPreferences' => (bool) $r->respect_user_preferences,
            'active' => (bool) $r->is_active,
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @param  list<array<string, mixed>>  $rules */
    public function save(Form $form, array $rules, int $userId): void
    {
        $resolved = $this->validate($form, $rules);
        DB::transaction(function () use ($form, $rules, $resolved, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            foreach ($rules as $i => $r) {
                $ids = $resolved[$i];
                $existing = DB::table('notification_rules')->where('form_id', $form->id)->where('uuid', strtolower($r['uuid']))->first();
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'key' => $r['key'], 'trigger' => $r['trigger'],
                    'from_status_id' => $ids['from'], 'to_status_id' => $ids['to'], 'field_id' => $ids['field'],
                    'schedule' => $ids['schedule'] === null ? null : json_encode($ids['schedule']),
                    'email_template_id' => $ids['template'],
                    'channels' => json_encode(array_values(array_unique($r['channels']))),
                    'recipients' => json_encode($ids['recipients'], JSON_UNESCAPED_UNICODE),
                    'attachments' => $ids['attachments'] === null ? null : json_encode($ids['attachments']),
                    'delay_minutes' => $r['delayMinutes'] ?? null,
                    'respect_user_preferences' => (bool) ($r['respectPreferences'] ?? true),
                    'is_active' => (bool) ($r['active'] ?? true),
                ];
                if ($existing === null) {
                    $id = (int) DB::table('notification_rules')->insertGetId($row + ['uuid' => strtolower($r['uuid']), 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId, 'form_id' => $form->id, 'condition_id' => null]);
                    $conditionId = null;
                } else {
                    $id = (int) $existing->id;
                    DB::table('notification_rules')->where('id', $id)->update($row);
                    $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
                }
                $new = $this->conditions->put($form->id, 'notification_rule', $id, $r['condition'] ?? null, $conditionId);
                if ($new !== $conditionId) {
                    DB::table('notification_rules')->where('id', $id)->update(['condition_id' => $new]);
                }
                $this->translator->putMany('notification_rule', $id, 'name', array_map(static fn ($v) => is_string($v) ? $v : null, (array) ($r['name'] ?? [])));
                $kept[] = $id;
            }
            foreach (DB::table('notification_rules')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->get(['id', 'condition_id']) as $gone) {
                // Emails and notifications already sent by a removed rule keep their history; the link is cleared.
                DB::table('email_queue')->where('notification_rule_id', $gone->id)->update(['notification_rule_id' => null]);
                DB::table('in_app_notifications')->where('notification_rule_id', $gone->id)->update(['notification_rule_id' => null]);
                DB::table('notification_rules')->where('id', $gone->id)->delete();
                $this->translator->forget('notification_rule', (int) $gone->id);
                $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
            }
            $this->audit->record('notifications.rules_saved', 'config', null, 'form', $form->id, ['rules' => count($rules)], $userId);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>> resolved ids per rule
     */
    private function validate(Form $form, array $rules): array
    {
        $errors = [];
        $out = [];
        $draft = $this->drafts->normalize($this->drafts->load($form));
        $resolver = $this->draftValidator->resolver($draft);
        $rt = $this->runtimes->forForm($form);
        $seen = [];
        $keys = [];
        $default = $this->translator->defaultLocale();
        foreach ($rules as $i => $r) {
            $p = "rules.{$i}";
            $ids = ['from' => null, 'to' => null, 'field' => null, 'schedule' => null, 'template' => null, 'recipients' => ['to' => [], 'cc' => [], 'bcc' => []], 'attachments' => null];
            if (! is_array($r) || ! is_string($r['uuid'] ?? null) || ! Str::isUuid($r['uuid']) || isset($seen[strtolower($r['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);
                $out[] = $ids;

                continue;
            }
            $seen[strtolower($r['uuid'])] = true;
            if (DB::table('notification_rules')->where('uuid', strtolower($r['uuid']))->where('form_id', '!=', $form->id)->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $key = $r['key'] ?? null;
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
                $errors["{$p}.key"][] = __('notifications.bad_key');
            } elseif (isset($keys[$key])) {
                $errors["{$p}.key"][] = __('notifications.key_taken');
            }
            $keys[(string) $key] = true;
            if (trim((string) ($r['name'][$default] ?? '')) === '') {
                $errors["{$p}.name.{$default}"][] = __('notifications.name_required');
            }
            $trigger = $r['trigger'] ?? null;
            if (! in_array($trigger, self::TRIGGERS, true)) {
                $errors["{$p}.trigger"][] = __('validation.in', ['attribute' => 'trigger']);
            }
            foreach (['fromStatus' => 'from', 'toStatus' => 'to'] as $in => $slot) {
                if (($r[$in] ?? null) !== null) {
                    $sid = DB::table('statuses')->where('form_id', $form->id)->where('uuid', strtolower((string) $r[$in]))->whereNull('archived_at')->value('id');
                    $sid === null ? $errors["{$p}.{$in}"][] = __('notifications.unknown_status') : $ids[$slot] = (int) $sid;
                }
            }
            if (($r['field'] ?? null) !== null || $trigger === 'field_changed') {
                $fid = is_string($r['field'] ?? null) ? DB::table('fields')->where('form_id', $form->id)->where('uuid', strtolower($r['field']))->whereNull('archived_at')->value('id') : null;
                $fid === null ? $errors["{$p}.field"][] = __('notifications.field_required') : $ids['field'] = (int) $fid;
            }
            $condition = $r['condition'] ?? null;
            if ($condition !== null) {
                try {
                    TypeChecker::check((array) $condition, $resolver, 'boolean');
                } catch (StaticError $e) {
                    $errors["{$p}.condition"][] = $e->getMessage();
                }
            } elseif ($trigger === 'condition_met') {
                $errors["{$p}.condition"][] = __('notifications.condition_required');
            }
            if ($trigger === 'scheduled_reminder') {
                $s = (array) ($r['schedule'] ?? []);
                $dateField = $rt === null ? null : ($rt->fields[strtolower((string) ($s['dateField'] ?? ''))] ?? null);
                if ($dateField === null || ! in_array($rt->type($dateField)?->storage, ['date', 'datetime'], true)) {
                    $errors["{$p}.schedule.dateField"][] = __('notifications.date_field_required');
                } else {
                    $ids['schedule'] = ['dateField' => $dateField['uuid'], 'offsetMinutes' => max(-525600, min(525600, (int) ($s['offsetMinutes'] ?? 0)))];
                }
            }
            $channels = array_values(array_intersect((array) ($r['channels'] ?? []), self::CHANNELS));
            if ($channels === [] || count($channels) !== count((array) ($r['channels'] ?? []))) {
                $errors["{$p}.channels"][] = __('notifications.channels_required');
            }
            if (($r['template'] ?? null) !== null) {
                $tpl = DB::table('email_templates')->where('uuid', strtolower((string) $r['template']))->first();
                if ($tpl === null || ($tpl->form_id !== null && (int) $tpl->form_id !== $form->id)) {
                    $errors["{$p}.template"][] = __('notifications.unknown_template');
                } else {
                    $ids['template'] = (int) $tpl->id;
                }
            } elseif (in_array('email', $channels, true)) {
                $errors["{$p}.template"][] = __('notifications.template_required');
            }
            foreach (['to', 'cc', 'bcc'] as $slot) {
                foreach (array_values((array) ($r['recipients'][$slot] ?? [])) as $j => $item) {
                    $resolvedItem = $this->recipient($rt, (array) $item);
                    $resolvedItem === null ? $errors["{$p}.recipients.{$slot}.{$j}"][] = __('notifications.bad_recipient') : $ids['recipients'][$slot][] = $resolvedItem;
                }
            }
            if ($ids['recipients']['to'] === []) {
                $errors["{$p}.recipients.to"][] = __('notifications.recipients_required');
            }
            $att = $r['attachments'] ?? null;
            if (is_array($att) && (($att['fields'] ?? []) !== [] || ($att['documents'] ?? []) !== [])) {
                $fileFields = [];
                foreach ((array) ($att['fields'] ?? []) as $k) {
                    $uuid = $rt?->keys[$k] ?? null;
                    if ($uuid === null || ! in_array($rt->type($rt->fields[$uuid])?->storage, ['file', 'files'], true)) {
                        $errors["{$p}.attachments.fields"][] = __('notifications.bad_attachment_field');
                    } else {
                        $fileFields[] = $k;
                    }
                }
                $documents = [];
                foreach (array_values((array) ($att['documents'] ?? [])) as $j => $d) {
                    $doc = is_array($d) && is_string($d['template'] ?? null) ? DocumentTemplate::query()->where('uuid', strtolower($d['template']))->first() : null;
                    if ($doc === null || ! $doc->is_active || ($doc->form_id !== null && $doc->form_id !== $rt?->form->id)) {
                        $errors["{$p}.attachments.documents.{$j}"][] = __('notifications.bad_attachment_document');

                        continue;
                    }
                    $format = (string) ($d['format'] ?? '');
                    if (! in_array($format, $doc->output_formats, true)) {
                        $errors["{$p}.attachments.documents.{$j}"][] = __('notifications.bad_attachment_format');

                        continue;
                    }
                    $item = ['template' => strtolower($doc->uuid), 'format' => $format];
                    if (in_array($item, $documents, true)) {
                        $errors["{$p}.attachments.documents.{$j}"][] = __('notifications.duplicate_document');

                        continue;
                    }
                    $documents[] = $item;
                }
                if (count($documents) > 5) {
                    $errors["{$p}.attachments.documents"][] = __('notifications.too_many_documents');
                }
                $ids['attachments'] = ['fields' => $fileFields, 'documents' => $documents];
            }
            $delay = $r['delayMinutes'] ?? null;
            if ($delay !== null && (! is_int($delay) || $delay < 0 || $delay > 43200)) {
                $errors["{$p}.delayMinutes"][] = __('notifications.bad_delay');
            }
            $out[] = $ids;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * A recipient as stored: `{type, ref}` with a uuid (user, role,
     * department), a field key or relation path (field users), an address
     * (static), or nothing (creator).
     *
     * @param  array<string, mixed>  $item
     * @return array{type: string, ref: string|null}|null
     */
    private function recipient(?FormRuntime $rt, array $item): ?array
    {
        $type = $item['type'] ?? null;
        $ref = isset($item['ref']) && is_string($item['ref']) ? trim($item['ref']) : null;

        return match ($type) {
            'user', 'role', 'department' => $ref !== null && $this->members->idOf($type, strtolower($ref)) !== null ? ['type' => $type, 'ref' => strtolower($ref)] : null,
            'creator' => ['type' => 'creator', 'ref' => null],
            'static' => $ref !== null && filter_var($ref, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($ref) <= 255 ? ['type' => 'static', 'ref' => mb_strtolower($ref)] : null,
            'field_user', 'linked_record_users' => $rt !== null && $ref !== null && $this->userPath($rt, $ref, $type === 'linked_record_users') ? ['type' => $type, 'ref' => $ref] : null,
            default => null,
        };
    }

    /** A field key (or relation path) that ends in a user field. */
    private function userPath(FormRuntime $rt, string $ref, bool $linked): bool
    {
        $segments = explode('.', $ref);
        if ($linked !== (count($segments) > 1)) {
            return false;
        }
        $r = $this->paths->resolve($rt, $segments);
        if ($r === null || $r['field'] === null) {
            return false;
        }

        return ($r['rt']->targetTable($r['field']) ?? null) === 'users';
    }
}
