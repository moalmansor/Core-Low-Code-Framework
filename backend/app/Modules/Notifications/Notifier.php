<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Documents\DocumentRenderer;
use App\Modules\Documents\GeneratedDocuments;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Channels\ChannelDeliveries;
use App\Modules\Notifications\Channels\ChannelProviders;
use App\Modules\Notifications\Jobs\FireNotificationRule;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Support\BatchMemo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends what notification rules ask for (architecture §19.14). Each event
 * of a record (created, updated, field changed, condition became true,
 * status changed, SLA warning or escalation, approval requested, assigned)
 * finds the form's active rules for it; a rule whose condition holds
 * resolves its recipients for To, CC and BCC, applies their channel
 * preferences (unless the rule is mandatory), and writes the email queue
 * rows (one message per language, rendered so that no recipient sees a
 * field they may not) and the in-app notifications. Built-in notices (work
 * assigned to you, SLA escalations) reach the people concerned without a
 * rule.
 */
final class Notifier
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RuleRuntime $rules,
        private readonly OwnedConditions $conditions,
        private readonly TemplateRenderer $renderer,
        private readonly EmailQueue $emails,
        private readonly InAppNotifier $inApp,
        private readonly Translator $translator,
        private readonly DocumentRenderer $documents,
        private readonly GeneratedDocuments $generated,
        private readonly Recipients $recipientList,
        private readonly ChannelDeliveries $deliveries,
    ) {}

    /**
     * The rules of a form for a trigger, filtered by the event's details.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(string $trigger, array $event): void
    {
        $rt = $this->runtimes->forUuid((string) ($event['form'] ?? ''));
        if ($rt === null) {
            return;
        }
        $recordId = isset($event['record']) ? DB::table($rt->table)->where('uuid', strtolower((string) $event['record']))->value('id') : null;
        if ($recordId === null) {
            return;
        }
        $rules = app(BatchMemo::class)->remember("notification_rules.{$rt->form->id}.{$trigger}", static fn () => DB::table('notification_rules')
            ->where('form_id', $rt->form->id)->where('trigger', $trigger)->where('is_active', true)->orderBy('id')->get());
        foreach ($rules as $rule) {
            if (! $this->matches($rule, $rt, $trigger, $event)) {
                continue;
            }
            $this->fire($rule, $rt, (int) $recordId);
        }
    }

    /** @param  array<string, mixed>  $event */
    private function matches(object $rule, FormRuntime $rt, string $trigger, array $event): bool
    {
        if ($trigger === 'field_changed') {
            $key = $rt->fields[strtolower((string) DB::table('fields')->where('id', $rule->field_id)->value('uuid'))]['key'] ?? null;
            if ($key === null || ! in_array($key, (array) ($event['changed'] ?? []), true)) {
                return false;
            }
        }
        if ($trigger === 'status_changed') {
            foreach (['from_status_id' => 'from', 'to_status_id' => 'to'] as $col => $field) {
                if ($rule->{$col} !== null && strtolower((string) DB::table('statuses')->where('id', $rule->{$col})->value('uuid')) !== strtolower((string) ($event[$field] ?? ''))) {
                    return false;
                }
            }
        }
        if ($trigger === 'condition_met') {
            // The pipeline noted which conditions turned true with this save.
            return in_array(strtolower((string) $rule->uuid), (array) ($event['notification_conditions'] ?? []), true);
        }

        return true;
    }

    /** Fires a rule for a record: at once, or after its delay. */
    public function fire(object $rule, FormRuntime $rt, int $recordId): void
    {
        if ((int) ($rule->delay_minutes ?? 0) > 0) {
            FireNotificationRule::dispatch((int) $rule->id, $rt->form->uuid, $recordId)->delay(now()->addMinutes((int) $rule->delay_minutes));

            return;
        }
        $this->deliver($rule, $rt, $recordId);
    }

    /** Checks the rule's condition now and sends to its recipients. */
    public function deliver(object $rule, FormRuntime $rt, int $recordId): void
    {
        $record = $this->store->findById($rt, $recordId);
        if ($record === null) {
            return;
        }
        if ($rule->trigger !== 'condition_met' && $rule->condition_id !== null) {
            $ast = $this->conditions->ast((int) $rule->condition_id);
            if ($ast !== null && ! $this->rules->holds($rt, $ast, $record['values'], null, 'view')) {
                return;
            }
        }
        $this->send($rule, $rt, $recordId, $record, ['form_id' => $rt->form->id, 'record_id' => $recordId, 'rule_id' => (int) $rule->id, 'source' => 'rule']);
    }

    /**
     * An action's "send an email" or "notify" step (§4.15): the same
     * recipients, per-language rendering and attachments as a rule, sent now
     * on the actor's behalf. Addresses outside the system see what the actor
     * may see.
     *
     * @param  array{channels: list<string>, template: string|null, recipients: array<string, mixed>, attachments?: array<string, mixed>|null, respectPreferences?: bool}  $spec
     */
    public function sendForAction(array $spec, FormRuntime $rt, int $recordId, User $actor, string $actionUuid, string $title, string $source = 'action'): void
    {
        $record = $this->store->findById($rt, $recordId);
        if ($record === null) {
            return;
        }
        $template = $spec['template'] === null ? null : EmailTemplate::query()->where('uuid', strtolower($spec['template']))->first();
        $rule = (object) [
            'id' => null, 'uuid' => $actionUuid, 'trigger' => $source, 'condition_id' => null,
            'channels' => json_encode($spec['channels']), 'recipients' => json_encode($spec['recipients']),
            'respect_user_preferences' => $spec['respectPreferences'] ?? true, 'email_template_id' => $template?->id,
            'attachments' => ($spec['attachments'] ?? null) === null ? null : json_encode($spec['attachments']), 'created_by' => $actor->id,
        ];
        $this->send($rule, $rt, $recordId, $record, ['form_id' => $rt->form->id, 'record_id' => $recordId, 'rule_id' => null, 'source' => $source, 'title' => $title]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{form_id: int, record_id: int, rule_id: int|null, source: string, title?: string}  $context
     */
    private function send(object $rule, FormRuntime $rt, int $recordId, array $record, array $context): void
    {
        $channels = json_decode((string) $rule->channels, true) ?: [];
        $who = $this->recipients($rt, $recordId, json_decode((string) $rule->recipients, true) ?: []);
        $respect = (bool) $rule->respect_user_preferences;
        $template = $rule->email_template_id === null ? null : EmailTemplate::query()->find($rule->email_template_id);
        if ($template !== null && ! $template->is_active) {
            $template = null;
        }
        $link = '/app/'.$rt->form->uuid.'/'.$record['uuid'];
        if (in_array('in_app', $channels, true)) {
            foreach (array_unique([...$who['to'], ...$who['cc'], ...$who['bcc']]) as $userId) {
                $user = User::query()->find($userId);
                if ($user === null || ($respect && ! $this->wants($userId, 'in_app'))) {
                    continue;
                }
                $locale = $this->localeOf($userId);
                [$title, $body] = $template !== null
                    ? $this->titleAndBody($template, $rt, $recordId, [$user], $locale)
                    : [$context['title'] ?? $this->defaultTitle($rule, $rt, $record, $locale), null];
                $direct = in_array($context['source'], ['action', 'automation'], true);
                $this->inApp->notifyText($userId, $direct ? $context['source'] : 'rule.'.$rule->trigger, $title, $body, $link, [$direct ? $context['source'] : 'rule' => strtolower((string) $rule->uuid)], $context);
            }
        }
        if (in_array('email', $channels, true) && $template !== null) {
            $this->email($rule, $template, $rt, $recordId, $who, $respect, $context);
        }
        $others = array_values(array_diff($channels, NotificationRules::CHANNELS));
        if ($others !== []) {
            $this->channels($others, $rule, $template, $rt, $recordId, $record, $who, $respect, $context, $link);
        }
    }

    /**
     * SMS and messaging channels (ADR-0056). SMS goes to the mobile number of
     * each user recipient who has one (and has not turned SMS off, unless the
     * rule is mandatory), rendered in their language with their access. A
     * messaging channel posts once to its conversation, rendered in the
     * default language with the rule author's access, like a message to an
     * outside address. The text is the template's subject and preheader with
     * the record link; without a template, the rule's default title.
     *
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $record
     * @param  array{to: list<int>, cc: list<int>, bcc: list<int>, static: array{to: list<string>, cc: list<string>, bcc: list<string>}}  $who
     * @param  array{form_id: int, record_id: int, rule_id: int|null, source: string, title?: string}  $context
     */
    private function channels(array $keys, object $rule, ?EmailTemplate $template, FormRuntime $rt, int $recordId, array $record, array $who, bool $respect, array $context, string $link): void
    {
        $url = rtrim((string) config('app.url'), '/').$link;
        $render = function (array $viewers, string $locale) use ($template, $rt, $recordId, $rule, $record, $context): array {
            if ($template !== null && $viewers !== []) {
                [$title, $body] = $this->titleAndBody($template, $rt, $recordId, $viewers, $locale);

                return [$title, (string) $body];
            }

            return [$context['title'] ?? $this->defaultTitle($rule, $rt, $record, $locale), ''];
        };
        foreach (NotificationChannel::query()->whereIn('key', $keys)->where('is_active', true)->get() as $channel) {
            if (ChannelProviders::isPerRecipient($channel->type)) {
                foreach (array_unique([...$who['to'], ...$who['cc'], ...$who['bcc']]) as $userId) {
                    $user = User::query()->find($userId);
                    $phone = $user === null ? '' : preg_replace('/[^0-9+]/', '', (string) $user->phone);
                    if ($user === null || preg_match('/^\+[1-9][0-9]{6,14}$/', (string) $phone) !== 1 || ($respect && ! $this->wants($userId, 'sms'))) {
                        continue;
                    }
                    [$title, $text] = $render([$user], $this->localeOf($userId));
                    $this->deliveries->queue($channel, $userId, ['to' => (string) $phone, 'title' => $title, 'text' => $text, 'link' => $url], $context);
                }

                continue;
            }
            $author = $rule->created_by === null ? null : User::query()->find($rule->created_by);
            [$title, $text] = $render($author === null ? [] : [$author], $this->translator->defaultLocale());
            $this->deliveries->queue($channel, null, ['to' => (string) (((array) $channel->config)['to'] ?? $channel->key), 'title' => $title, 'text' => $text, 'link' => $url], $context);
        }
    }

    /**
     * One message per language: the users of that language in To, CC and
     * BCC as configured, addresses outside the system with the default
     * language (rendered with the rule author's access).
     *
     * @param  array{to: list<int>, cc: list<int>, bcc: list<int>, static: array{to: list<string>, cc: list<string>, bcc: list<string>}}  $who
     * @param  array{form_id: int, record_id: int, rule_id: int|null, source: string, title?: string}  $context
     */
    private function email(object $rule, EmailTemplate $template, FormRuntime $rt, int $recordId, array $who, bool $respect, array $context): void
    {
        $groups = [];
        $default = $this->translator->defaultLocale();
        $taken = [];
        foreach (['to', 'cc', 'bcc'] as $slot) {
            foreach ($who[$slot] as $userId) {
                if (isset($taken[$userId]) || ($respect && ! $this->wants($userId, 'email'))) {
                    continue;
                }
                $user = User::query()->find($userId);
                if ($user === null || $user->email === null || $user->email === '') {
                    continue;
                }
                $taken[$userId] = true;
                $locale = $this->localeOf($userId);
                $groups[$locale][$slot][] = $user->email;
                $groups[$locale]['viewers'][] = $user;
            }
            foreach ($who['static'][$slot] as $address) {
                $groups[$default][$slot][] = $address;
                $groups[$default]['static'] = true;
            }
        }
        $author = $rule->created_by === null ? null : User::query()->find($rule->created_by);
        // Every message and its documents are prepared before any is queued: a document that
        // fails to generate fails the whole delivery, which retries without sending anyone twice.
        $messages = [];
        foreach ($groups as $locale => $g) {
            $viewers = $g['viewers'] ?? [];
            if (($g['static'] ?? false) === true) {
                if ($author === null) {
                    continue;
                }
                $viewers[] = $author;
            }
            if (($g['to'] ?? []) === [] && ($g['cc'] ?? []) === [] && ($g['bcc'] ?? []) === []) {
                continue;
            }
            $out = $this->renderer->render($template->design, $template->translationsFor('subject'), $template->translationsFor('preheader'), $rt, $recordId, $viewers, (string) $locale,
                count($viewers) === 1 ? $viewers[0]->name : null);
            $messages[] = [
                'source' => $context['source'], 'locale' => (string) $locale, 'to' => array_values(array_unique($g['to'] ?? [])),
                'cc' => array_values(array_unique($g['cc'] ?? [])), 'bcc' => array_values(array_unique($g['bcc'] ?? [])),
                'subject' => $out['subject'], 'html' => $out['html'], 'rule_id' => $context['rule_id'], 'template_id' => $template->id,
                'form_id' => $context['form_id'], 'record_id' => $context['record_id'],
                'attachments' => [...$this->attachments($rule, $rt, $recordId), ...$this->documents($rule, $rt, $recordId, $viewers, (string) $locale)],
            ];
        }
        foreach ($messages as $m) {
            $this->emails->enqueue($m);
        }
    }

    /** @return list<int> files of the record's file fields named by the rule */
    private function attachments(object $rule, FormRuntime $rt, int $recordId): array
    {
        $spec = $rule->attachments === null ? null : json_decode((string) $rule->attachments, true);
        if (! is_array($spec) || ($spec['fields'] ?? []) === []) {
            return [];
        }
        $record = $this->store->findById($rt, $recordId);
        $uuids = [];
        foreach ((array) $spec['fields'] as $key) {
            foreach ((array) ($record['values'][$key] ?? []) as $u) {
                if (is_string($u)) {
                    $uuids[] = strtolower($u);
                }
            }
        }

        return $uuids === [] ? [] : DB::table('files')->whereIn('uuid', $uuids)->whereNull('deleted_at')->pluck('id')->map(static fn ($v) => (int) $v)->all();
    }

    /**
     * The rule's documents, generated for this message's readers in its language.
     *
     * @param  list<User>  $viewers
     * @return list<int> file ids
     */
    private function documents(object $rule, FormRuntime $rt, int $recordId, array $viewers, string $locale): array
    {
        $spec = $rule->attachments === null ? null : json_decode((string) $rule->attachments, true);
        $ids = [];
        foreach (is_array($spec) ? (array) ($spec['documents'] ?? []) : [] as $d) {
            $template = DocumentTemplate::query()->where('uuid', (string) ($d['template'] ?? ''))->where('is_active', true)->first();
            if ($template === null || ! in_array($d['format'] ?? null, $template->output_formats, true)) {
                continue;
            }
            $out = $this->documents->render($template, $rt, $recordId, $viewers, $locale, (string) $d['format']);
            $ids[] = $this->generated->store($out, GeneratedDocuments::OWNER_EMAIL, $rt->form->id, $recordId, null)->id;
        }

        return $ids;
    }

    /**
     * @param  list<User>  $viewers
     * @return array{0: string, 1: string|null}
     */
    private function titleAndBody(EmailTemplate $template, FormRuntime $rt, int $recordId, array $viewers, string $locale): array
    {
        $out = $this->renderer->render($template->design, $template->translationsFor('subject'), $template->translationsFor('preheader'), $rt, $recordId, $viewers, $locale, $viewers[0]->name ?? null);

        return [$out['subject'], $out['preheader'] === '' ? null : mb_substr($out['preheader'], 0, 1000)];
    }

    /** @param  array<string, mixed>  $record */
    private function defaultTitle(object $rule, FormRuntime $rt, array $record, string $locale): string
    {
        $name = $this->translator->get('notification_rule', (int) $rule->id, 'name', $locale) ?? $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key);
        $number = $record['system']['record_number'] ?? null;

        return (string) __($number === null ? 'notifications.default_title' : 'notifications.default_title_numbered', ['name' => $name, 'number' => $number], $locale);
    }

    /**
     * Users (by slot) and outside addresses a rule's recipients name for a
     * record, resolved now (Recipients).
     *
     * @param  array<string, mixed>  $spec
     * @return array{to: list<int>, cc: list<int>, bcc: list<int>, static: array{to: list<string>, cc: list<string>, bcc: list<string>}}
     */
    public function recipients(FormRuntime $rt, int $recordId, array $spec): array
    {
        return $this->recipientList->resolve($rt, $recordId, $spec);
    }

    private function wants(int $userId, string $channel): bool
    {
        $prefs = json_decode((string) (DB::table('user_preferences')->where('user_id', $userId)->value('notification_channels') ?? ''), true);

        return ! is_array($prefs) || ($prefs[$channel] ?? true) !== false;
    }

    public function localeOf(int $userId): string
    {
        $locale = DB::table('user_preferences')->where('user_id', $userId)->value('locale');

        return is_string($locale) && in_array($locale, $this->translator->enabledLocales(), true) ? $locale : $this->translator->defaultLocale();
    }

    /**
     * Work given to people (an assignment, an approval to decide): an in-app
     * notice to each of them, with no rule needed.
     *
     * @param  array<string, mixed>  $event
     */
    public function assigned(array $event): void
    {
        $rt = $this->runtimes->forUuid((string) ($event['form'] ?? ''));
        if ($rt === null) {
            return;
        }
        $record = DB::table($rt->table)->where('uuid', strtolower((string) ($event['record'] ?? '')))->first(['id', 'uuid', 'record_number']);
        if ($record === null) {
            return;
        }
        $approval = ($event['source'] ?? null) === 'approval';
        foreach ((array) ($event['recipients'] ?? []) as $uuid) {
            $userId = DB::table('users')->where('uuid', strtolower((string) $uuid))->value('id');
            if ($userId === null || ! $this->wants((int) $userId, 'in_app')) {
                continue;
            }
            $locale = $this->localeOf((int) $userId);
            $form = $this->translator->get('form', $rt->form->id, 'name', $locale) ?? $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key);
            $this->inApp->notifyText((int) $userId, $approval ? 'approval.requested' : 'record.assigned',
                (string) __($approval ? 'notifications.system.approval' : 'notifications.system.assigned', ['form' => $form, 'number' => $record->record_number ?? ''], $locale),
                null, '/app/'.$rt->form->uuid.'/'.strtolower((string) $record->uuid), null, ['form_id' => $rt->form->id, 'record_id' => (int) $record->id]);
        }
    }

    /**
     * An SLA escalation that notifies: an in-app notice and an email to the
     * people it names.
     *
     * @param  array<string, mixed>  $event
     */
    public function escalated(array $event): void
    {
        $rt = $this->runtimes->forUuid((string) ($event['form'] ?? ''));
        if ($rt === null || ($event['action'] ?? null) !== 'notify') {
            return;
        }
        $record = DB::table($rt->table)->where('uuid', strtolower((string) ($event['record'] ?? '')))->first(['id', 'uuid', 'record_number']);
        if ($record === null) {
            return;
        }
        $link = '/app/'.$rt->form->uuid.'/'.strtolower((string) $record->uuid);
        foreach ((array) ($event['recipients'] ?? []) as $uuid) {
            $user = User::query()->where('uuid', strtolower((string) $uuid))->first();
            if ($user === null) {
                continue;
            }
            $locale = $this->localeOf($user->id);
            $form = $this->translator->get('form', $rt->form->id, 'name', $locale) ?? $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key);
            $title = (string) __('notifications.system.sla_escalation', ['form' => $form, 'number' => $record->record_number ?? ''], $locale);
            if ($this->wants($user->id, 'in_app')) {
                $this->inApp->notifyText($user->id, 'sla.escalation', $title, null, $link, null, ['form_id' => $rt->form->id, 'record_id' => (int) $record->id]);
            }
            if ($this->wants($user->id, 'email') && $user->email !== null) {
                $url = rtrim((string) config('app.url'), '/').$link;
                $this->emails->enqueue([
                    'source' => 'system', 'locale' => $locale, 'to' => [$user->email], 'subject' => $title,
                    'html' => '<!doctype html><html lang="'.e($locale).'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"><body style="font-family:Segoe UI,Tahoma,Arial,sans-serif"><p>'.e($title).'</p><p><a href="'.e($url).'">'.e((string) __('notifications.system.open_record', [], $locale)).'</a></p></body></html>',
                    'form_id' => $rt->form->id, 'record_id' => (int) $record->id,
                ]);
            }
        }
    }

    /**
     * Reminders relative to a date field (e.g. three days before expiry):
     * each record is reminded once per date value, when its moment passes.
     */
    public function reminders(): int
    {
        $sent = 0;
        $now = Carbon::now('UTC');
        foreach (DB::table('notification_rules')->where('trigger', 'scheduled_reminder')->where('is_active', true)->get() as $rule) {
            $schedule = json_decode((string) $rule->schedule, true);
            $form = DB::table('forms')->where('id', $rule->form_id)->first(['uuid', 'organization_id']);
            $rt = $form === null ? null : $this->runtimes->forUuid((string) $form->uuid);
            $field = $rt === null ? null : ($rt->fields[strtolower((string) ($schedule['dateField'] ?? ''))] ?? null);
            $column = $field === null ? null : ($rt->column($field['uuid'])['name'] ?? null);
            if ($column === null) {
                continue;
            }
            app(TenantContext::class)->set((int) $form->organization_id);
            $offset = (int) ($schedule['offsetMinutes'] ?? 0);
            // The date value d is due when d + offset <= now: look at the last week of due moments.
            $upper = $now->copy()->subMinutes($offset);
            $lower = $upper->copy()->subDays(7);
            $rows = DB::table($rt->table)->whereNull('deleted_at')->whereNotNull($column)
                ->where($column, '>', $lower->format('Y-m-d H:i:s'))->where($column, '<=', $upper->format('Y-m-d H:i:s'))
                ->orderBy('id')->limit(1000)->get(['id', $column.' as due']);
            foreach ($rows as $row) {
                $moment = Carbon::parse((string) $row->due, 'UTC')->addMinutes($offset)->format('Y-m-d H:i:s.u');
                $done = DB::table('email_queue')->where('notification_rule_id', $rule->id)->where('record_id', $row->id)->where('created_at', '>=', $moment)->exists()
                    || DB::table('in_app_notifications')->where('notification_rule_id', $rule->id)->where('record_id', $row->id)->where('created_at', '>=', $moment)->exists()
                    // The channel log keeps the record in its payload as its last key (MySQL's JSON type writes a space after the colon).
                    || DB::table('notification_deliveries')->where('notification_rule_id', $rule->id)->where('created_at', '>=', $moment)
                        ->where(static fn ($q) => $q->where('payload', 'like', '%"record_id":'.(int) $row->id.'}')->orWhere('payload', 'like', '%"record_id": '.(int) $row->id.'}'))->exists();
                if (! $done) {
                    $this->deliver($rule, $rt, (int) $row->id);
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * Notes, during a save, which condition rules of the form became true
     * (held after the save, not before), for the `record.saved` event.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    /**
     * The active "condition met" rules of a form.
     *
     * @return Collection<int, \stdClass>
     */
    public static function conditionRules(int $formId): Collection
    {
        return app(BatchMemo::class)->remember('notification_rules.condition_met.'.$formId, static fn () => DB::table('notification_rules')
            ->where('form_id', $formId)->where('trigger', 'condition_met')->where('is_active', true)->whereNotNull('condition_id')->get(['uuid', 'condition_id']));
    }

    /** Whether the form has any active notification rule (so its record events are worth queueing). */
    public static function formHasRules(string $formUuid): bool
    {
        return app(BatchMemo::class)->remember('notification_rules.any.'.strtolower($formUuid), static fn () => DB::table('notification_rules')
            ->join('forms', 'forms.id', '=', 'notification_rules.form_id')->where('forms.uuid', strtolower($formUuid))
            ->where('notification_rules.is_active', true)->exists());
    }

    public function conditionsMet(FormRuntime $rt, ?array $old, array $new): array
    {
        $met = [];
        foreach (self::conditionRules($rt->form->id) as $r) {
            $ast = $this->conditions->ast((int) $r->condition_id);
            if ($ast === null) {
                continue;
            }
            $now = $this->rules->holds($rt, $ast, $new, null, 'view');
            $before = $old !== null && $this->rules->holds($rt, $ast, $old, null, 'view');
            if ($now && ! $before) {
                $met[] = strtolower((string) $r->uuid);
            }
        }

        return $met === [] ? [] : ['notification_conditions' => $met];
    }
}
