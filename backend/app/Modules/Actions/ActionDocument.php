<?php

declare(strict_types=1);

namespace App\Modules\Actions;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Access\AccessCache;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Templating\BlockDesign;
use App\Modules\Records\Templating\TemplateValues;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The actions of a form (specification §4.15, architecture §14.9): built-in
 * operations placed where the admin wants them (duplicate, print, bulk
 * update, bulk status change, bulk delete) and custom actions made of
 * chained steps, each optionally conditional and either stopping or
 * continuing the chain when it fails. Each action has its own permission
 * (`action.{uuid}.run`, managed in the matrix), an optional availability
 * condition, confirmation, justification level and run mode. Saved whole
 * with a concurrency hash (ADR-0033). Webhook secrets and header values are
 * stored encrypted and never sent back: the screen sees only that one is set.
 */
final class ActionDocument
{
    public const PLACEMENTS = ['row', 'bulk', 'toolbar', 'view_page'];

    /** Built-in operations and where each can be placed. */
    public const BUILTINS = [
        'duplicate' => ['row', 'view_page'],
        'print' => ['row', 'view_page'],
        'bulk_update' => ['bulk', 'toolbar'],
        'bulk_status' => ['bulk', 'toolbar'],
        'bulk_delete' => ['bulk', 'toolbar'],
    ];

    public const STEPS = ['update_fields', 'change_status', 'send_email', 'send_notification', 'call_webhook', 'generate_document', 'create_linked_record', 'assign', 'run_download'];

    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const MAX_STEPS = 20;

    public function __construct(
        private readonly Translator $translator,
        private readonly ObjectPermissions $permissions,
        private readonly OwnedConditions $conditions,
        private readonly FormRuntimes $runtimes,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly BlockDesign $design,
        private readonly AuditWriter $audit,
        private readonly AccessCache $cache,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('actions')->where('form_id', $form->id)->orderBy('sort_order')->orderBy('id')->get();
        $ids = $rows->pluck('id')->map(static fn ($v) => (int) $v)->all();
        $steps = DB::table('action_steps')->whereIn('action_id', $ids ?: [0])->orderBy('sort_order')->orderBy('id')->get()->groupBy('action_id');
        $asts = $this->conditions->many([...$rows->pluck('condition_id')->all(), ...$steps->flatten()->pluck('condition_id')->all()]);
        $tr = $this->translator->allMany('action', $ids);

        return $rows->map(fn ($a) => [
            'uuid' => strtolower((string) $a->uuid),
            'key' => $a->key,
            'kind' => $a->kind,
            'builtin' => $a->builtin,
            'i18n' => [
                'label' => (object) ($tr[(int) $a->id]['label'] ?? []),
                'confirmation' => (object) ($tr[(int) $a->id]['confirmation_text'] ?? []),
                'success' => (object) ($tr[(int) $a->id]['success_message'] ?? []),
            ],
            'placements' => json_decode((string) $a->placements, true) ?: [],
            'condition' => $a->condition_id === null ? null : ($asts[(int) $a->condition_id] ?? null),
            'confirm' => (bool) $a->requires_confirmation,
            'runMode' => $a->run_mode,
            'queueThreshold' => $a->queue_threshold === null ? null : (int) $a->queue_threshold,
            'maxRecords' => $a->max_records === null ? null : (int) $a->max_records,
            'justification' => $a->justification_level,
            'icon' => $a->icon,
            'color' => $a->color,
            'active' => (bool) $a->is_active,
            'steps' => ($steps[$a->id] ?? collect())->map(fn ($s) => [
                'uuid' => strtolower((string) $s->uuid),
                'type' => $s->type,
                'config' => $this->presentConfig($s->type, json_decode((string) $s->config, true) ?: []),
                'condition' => $s->condition_id === null ? null : ($asts[(int) $s->condition_id] ?? null),
                'onFailure' => $s->on_failure,
            ])->values()->all(),
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /**
     * A step's stored configuration, as the server uses it (secrets decrypted).
     *
     * @return array<string, mixed>
     */
    public static function runtimeConfig(object $step): array
    {
        $config = json_decode((string) $step->config, true) ?: [];
        if ($step->type === 'call_webhook') {
            $config['secret'] = isset($config['secret']) ? Crypt::decryptString((string) $config['secret']) : null;
            $config['headers'] = array_map(static fn (array $h) => ['name' => $h['name'], 'value' => Crypt::decryptString((string) $h['value'])], (array) ($config['headers'] ?? []));
        }

        return $config;
    }

    /** @param  list<array<string, mixed>>  $actions */
    public function save(Form $form, array $actions, int $userId): void
    {
        $rt = $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['actions' => __('actions.publish_first')]);
        }
        $actions = $this->validate($form, $rt, $actions);
        DB::transaction(function () use ($form, $actions, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            $tr = [];
            foreach ($actions as $i => $a) {
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'key' => $a['key'], 'kind' => $a['kind'], 'builtin' => $a['builtin'],
                    'placements' => json_encode($a['placements']), 'requires_confirmation' => $a['confirm'], 'run_mode' => $a['runMode'],
                    'queue_threshold' => $a['queueThreshold'], 'max_records' => $a['maxRecords'], 'justification_level' => $a['justification'],
                    'icon' => $a['icon'], 'color' => $a['color'], 'sort_order' => $i, 'is_active' => $a['active'],
                ];
                $existing = DB::table('actions')->where('form_id', $form->id)->where('uuid', $a['uuid'])->first();
                if ($existing === null) {
                    $id = (int) DB::table('actions')->insertGetId($row + [
                        'uuid' => $a['uuid'], 'organization_id' => $form->organization_id, 'form_id' => $form->id, 'created_at' => $now, 'created_by' => $userId,
                    ]);
                    $conditionId = null;
                } else {
                    $id = (int) $existing->id;
                    DB::table('actions')->where('id', $id)->update($row);
                    $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
                }
                $newCondition = $this->conditions->put($form->id, 'action', $id, $a['condition'], $conditionId);
                if ($newCondition !== $conditionId) {
                    DB::table('actions')->where('id', $id)->update(['condition_id' => $newCondition]);
                }
                $this->permissions->registerAction($id, $a['uuid']);
                $tr[$id] = ['label' => $a['i18n']['label'], 'confirmation_text' => $a['i18n']['confirmation'], 'success_message' => $a['i18n']['success']];
                $this->saveSteps($form, $id, $a['steps'], $now);
                $kept[] = $id;
            }
            $this->translator->syncObjects('action', $tr);
            foreach (DB::table('actions')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->get(['id', 'uuid', 'condition_id']) as $gone) {
                $this->removeSteps((int) $gone->id, []);
                $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
                $this->translator->forget('action', (int) $gone->id);
                $this->permissions->forget('action.'.strtolower((string) $gone->uuid));
                // Runs and justification rules keep their history; they no longer point at the action.
                DB::table('bulk_operations')->where('action_id', $gone->id)->update(['action_id' => null]);
                DB::table('justification_rules')->where('action_id', $gone->id)->delete();
                DB::table('actions')->where('id', $gone->id)->delete();
            }
            $this->audit->record('actions.saved', 'config', null, 'form', $form->id, ['actions' => count($actions)], $userId);
            $this->cache->bump();
        });
    }

    /** @param  list<array<string, mixed>>  $steps */
    private function saveSteps(Form $form, int $actionId, array $steps, string $now): void
    {
        $kept = [];
        foreach ($steps as $j => $s) {
            $existing = DB::table('action_steps')->where('action_id', $actionId)->where('uuid', $s['uuid'])->first();
            $config = $s['type'] === 'call_webhook' ? $this->sealWebhook($s['config'], $existing) : $s['config'];
            $row = ['updated_at' => $now, 'sort_order' => $j, 'type' => $s['type'], 'config' => json_encode($config, JSON_UNESCAPED_UNICODE), 'on_failure' => $s['onFailure']];
            if ($existing === null) {
                $id = (int) DB::table('action_steps')->insertGetId($row + ['uuid' => $s['uuid'], 'action_id' => $actionId, 'created_at' => $now, 'condition_id' => null]);
                $conditionId = null;
            } else {
                $id = (int) $existing->id;
                DB::table('action_steps')->where('id', $id)->update($row);
                $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
            }
            $newCondition = $this->conditions->put($form->id, 'action_step', $id, $s['condition'], $conditionId);
            if ($newCondition !== $conditionId) {
                DB::table('action_steps')->where('id', $id)->update(['condition_id' => $newCondition]);
            }
            $kept[] = $id;
        }
        $this->removeSteps($actionId, $kept);
    }

    /** @param  list<int>  $kept */
    private function removeSteps(int $actionId, array $kept): void
    {
        foreach (DB::table('action_steps')->where('action_id', $actionId)->whereNotIn('id', $kept ?: [0])->get(['id', 'condition_id']) as $gone) {
            DB::table('action_steps')->where('id', $gone->id)->delete();
            $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
        }
    }

    /**
     * Encrypts a webhook's secret and header values; a value left empty keeps
     * what was stored (the screen never receives it back).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function sealWebhook(array $config, ?object $existing): array
    {
        $old = $existing === null ? [] : (json_decode((string) $existing->config, true) ?: []);
        $oldHeaders = [];
        foreach ((array) ($old['headers'] ?? []) as $h) {
            $oldHeaders[strtolower((string) $h['name'])] = $h['value'];
        }
        $headers = [];
        foreach ($config['headers'] as $h) {
            $value = $h['value'] ?? null;
            $sealed = is_string($value) && $value !== '' ? Crypt::encryptString($value) : ($oldHeaders[strtolower($h['name'])] ?? null);
            if ($sealed !== null) {
                $headers[] = ['name' => $h['name'], 'value' => $sealed];
            }
        }
        $secret = $config['secret'] ?? null;
        $config['headers'] = $headers;
        $config['secret'] = is_string($secret) && $secret !== '' ? Crypt::encryptString($secret) : ($config['keepSecret'] ? ($old['secret'] ?? null) : null);
        unset($config['keepSecret']);

        return $config;
    }

    /**
     * A step's configuration for the screen: webhook secrets and header
     * values are replaced by whether they are set.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function presentConfig(string $type, array $config): array
    {
        if ($type !== 'call_webhook') {
            return $config;
        }
        $config['headers'] = array_map(static fn (array $h) => ['name' => $h['name'], 'value' => null, 'set' => true], (array) ($config['headers'] ?? []));
        $config['secretSet'] = ($config['secret'] ?? null) !== null;
        $config['secret'] = null;

        return $config;
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    private function validate(Form $form, FormRuntime $rt, array $actions): array
    {
        $errors = [];
        $out = [];
        $resolver = $this->draftValidator->resolver($this->drafts->normalize($this->drafts->load($form)));
        $seen = [];
        $keys = [];
        $default = $this->translator->defaultLocale();
        foreach (array_values($actions) as $i => $a) {
            $p = "actions.{$i}";
            if (! is_array($a) || ! is_string($a['uuid'] ?? null) || ! Str::isUuid($a['uuid']) || isset($seen[strtolower($a['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $uuid = strtolower($a['uuid']);
            $seen[$uuid] = true;
            if (DB::table('actions')->where('uuid', $uuid)->where('form_id', '!=', $form->id)->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $key = (string) ($a['key'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key) !== 1) {
                $errors["{$p}.key"][] = __('actions.bad_key');
            } elseif (isset($keys[$key])) {
                $errors["{$p}.key"][] = __('actions.key_taken');
            }
            $keys[$key] = true;
            $label = $this->texts($a['i18n']['label'] ?? [], 120);
            if (($label[$default] ?? '') === '') {
                $errors["{$p}.i18n.label.{$default}"][] = __('actions.label_required');
            }
            $kind = $a['kind'] ?? null;
            $builtin = $kind === 'builtin' ? ($a['builtin'] ?? null) : null;
            if (! in_array($kind, ['builtin', 'custom'], true) || ($kind === 'builtin' && ! isset(self::BUILTINS[$builtin]))) {
                $errors["{$p}.kind"][] = __('validation.in', ['attribute' => 'kind']);
            }
            $placements = array_values(array_unique(array_filter((array) ($a['placements'] ?? []), 'is_string')));
            $allowed = $kind === 'builtin' && isset(self::BUILTINS[$builtin]) ? self::BUILTINS[$builtin] : self::PLACEMENTS;
            if ($placements === [] || array_diff($placements, $allowed) !== []) {
                $errors["{$p}.placements"][] = __('actions.bad_placements');
            }
            $condition = $a['condition'] ?? null;
            if ($condition !== null) {
                try {
                    TypeChecker::check((array) $condition, $resolver, 'boolean');
                } catch (StaticError $e) {
                    $errors["{$p}.condition"][] = $e->getMessage();
                }
            }
            $runMode = $a['runMode'] ?? 'auto';
            if (! in_array($runMode, ['sync', 'queued', 'auto'], true)) {
                $errors["{$p}.runMode"][] = __('validation.in', ['attribute' => 'runMode']);
            }
            foreach (['queueThreshold' => [1, 10000], 'maxRecords' => [1, 100000]] as $field => [$min, $max]) {
                $v = $a[$field] ?? null;
                if ($v !== null && (! is_int($v) || $v < $min || $v > $max)) {
                    $errors["{$p}.{$field}"][] = __('actions.bad_number', ['min' => $min, 'max' => $max]);
                }
            }
            $justification = $a['justification'] ?? 'not_required';
            if (! in_array($justification, ['not_required', 'optional', 'mandatory'], true)) {
                $errors["{$p}.justification"][] = __('validation.in', ['attribute' => 'justification']);
            }
            $icon = $a['icon'] ?? null;
            if ($icon !== null && preg_match('/^pi-[a-z0-9-]{1,40}$/', (string) $icon) !== 1) {
                $errors["{$p}.icon"][] = __('validation.in', ['attribute' => 'icon']);
            }
            $color = $a['color'] ?? null;
            if ($color !== null && ! in_array($color, ['primary', 'secondary', 'success', 'warn', 'danger', 'info'], true)) {
                $errors["{$p}.color"][] = __('validation.in', ['attribute' => 'color']);
            }
            $steps = [];
            if ($kind === 'custom') {
                $rawSteps = array_values((array) ($a['steps'] ?? []));
                if ($rawSteps === []) {
                    $errors["{$p}.steps"][] = __('actions.steps_required');
                }
                if (count($rawSteps) > self::MAX_STEPS) {
                    $errors["{$p}.steps"][] = __('actions.too_many_steps', ['max' => self::MAX_STEPS]);
                }
                $stepSeen = [];
                foreach ($rawSteps as $j => $s) {
                    $sp = "{$p}.steps.{$j}";
                    if (! is_array($s) || ! is_string($s['uuid'] ?? null) || ! Str::isUuid($s['uuid']) || isset($stepSeen[strtolower($s['uuid'])])) {
                        $errors["{$sp}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                        continue;
                    }
                    $stepSeen[strtolower($s['uuid'])] = true;
                    $type = $s['type'] ?? null;
                    if (! in_array($type, self::STEPS, true)) {
                        $errors["{$sp}.type"][] = __('validation.in', ['attribute' => 'type']);

                        continue;
                    }
                    $sc = $s['condition'] ?? null;
                    if ($sc !== null) {
                        try {
                            TypeChecker::check((array) $sc, $resolver, 'boolean');
                        } catch (StaticError $e) {
                            $errors["{$sp}.condition"][] = $e->getMessage();
                        }
                    }
                    $steps[] = [
                        'uuid' => strtolower($s['uuid']), 'type' => $type,
                        'config' => $this->stepConfig($form, $rt, $resolver, $type, (array) ($s['config'] ?? []), "{$sp}.config", $errors),
                        'condition' => is_array($sc) ? $sc : null,
                        'onFailure' => ($s['onFailure'] ?? 'stop') === 'continue' ? 'continue' : 'stop',
                    ];
                }
            }
            $out[] = [
                'uuid' => $uuid, 'key' => $key, 'kind' => $kind, 'builtin' => $builtin, 'placements' => $placements,
                'i18n' => ['label' => $label, 'confirmation' => $this->texts($a['i18n']['confirmation'] ?? [], 500), 'success' => $this->texts($a['i18n']['success'] ?? [], 255)],
                'condition' => is_array($condition) ? $condition : null, 'confirm' => (bool) ($a['confirm'] ?? false), 'runMode' => $runMode,
                'queueThreshold' => $a['queueThreshold'] ?? null, 'maxRecords' => $a['maxRecords'] ?? null, 'justification' => $justification,
                'icon' => $icon, 'color' => $color, 'active' => (bool) ($a['active'] ?? true), 'steps' => $steps,
            ];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * Checks one step's configuration against the form; returns it normalized.
     *
     * @param  array<string, mixed>  $c
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array<string, mixed>
     */
    public function stepConfig(Form $form, FormRuntime $rt, mixed $resolver, string $type, array $c, string $p, array &$errors): array
    {
        $expr = function (mixed $ast, string $path) use ($resolver, &$errors): ?array {
            if (! is_array($ast)) {
                $errors[$path][] = __('actions.value_required');

                return null;
            }
            try {
                TypeChecker::check($ast, $resolver, null);
            } catch (StaticError $e) {
                $errors[$path][] = $e->getMessage();
            }

            return $ast;
        };

        return match ($type) {
            'update_fields' => (function () use ($rt, $c, $p, $expr, &$errors): array {
                $set = [];
                foreach (array_values((array) ($c['set'] ?? [])) as $k => $item) {
                    $field = $rt->fields[strtolower((string) ($item['field'] ?? ''))] ?? null;
                    if ($field === null || ! $rt->isStored($field) || isset($rt->fieldRepeater[$field['uuid']]) || $field['type'] === 'formula') {
                        $errors["{$p}.set.{$k}.field"][] = __('actions.bad_field');

                        continue;
                    }
                    $set[] = ['field' => $field['uuid'], 'value' => $expr($item['value'] ?? null, "{$p}.set.{$k}.value")];
                }
                if ($set === [] && ! isset($errors["{$p}.set"])) {
                    $errors["{$p}.set"][] = __('actions.fields_required');
                }

                return ['set' => $set];
            })(),
            'change_status' => (function () use ($rt, $c, $p, &$errors): array {
                $t = strtolower((string) ($c['transition'] ?? ''));
                if (! Str::isUuid($t) || ! DB::table('transitions')->where('form_id', $rt->form->id)->where('uuid', $t)->whereNull('archived_at')->exists()) {
                    $errors["{$p}.transition"][] = __('actions.unknown_transition');
                }

                return ['transition' => $t];
            })(),
            'send_email', 'send_notification' => (function () use ($rt, $c, $p, $type, &$errors): array {
                $tpl = $c['template'] ?? null;
                $template = is_string($tpl) && Str::isUuid($tpl) ? EmailTemplate::query()->where('uuid', strtolower($tpl))->first() : null;
                if (($type === 'send_email' || $tpl !== null) && ($template === null || ($template->form_id !== null && $template->form_id !== $rt->form->id))) {
                    $errors["{$p}.template"][] = __('actions.unknown_template');
                }
                $recipients = ['to' => [], 'cc' => [], 'bcc' => []];
                foreach ($type === 'send_email' ? ['to', 'cc', 'bcc'] : ['to'] as $slot) {
                    foreach (array_values((array) ($c['recipients'][$slot] ?? [])) as $k => $r) {
                        $ok = $this->recipientOk($rt, $r, $type === 'send_email');
                        $ok ? $recipients[$slot][] = ['type' => $r['type'], 'ref' => $r['ref'] ?? null] : $errors["{$p}.recipients.{$slot}.{$k}"][] = __('actions.bad_recipient');
                    }
                }
                if ($recipients['to'] === []) {
                    $errors["{$p}.recipients.to"][] = __('actions.recipients_required');
                }
                $documents = [];
                foreach ($type === 'send_email' ? array_values((array) ($c['documents'] ?? [])) : [] as $k => $d) {
                    $doc = is_string($d['template'] ?? null) && Str::isUuid($d['template']) ? DocumentTemplate::query()->where('uuid', strtolower($d['template']))->first() : null;
                    if ($doc === null || ($doc->form_id !== null && $doc->form_id !== $rt->form->id) || ! in_array($d['format'] ?? null, $doc->output_formats, true)) {
                        $errors["{$p}.documents.{$k}"][] = __('actions.unknown_document');

                        continue;
                    }
                    $documents[] = ['template' => strtolower($doc->uuid), 'format' => (string) $d['format']];
                }

                return ['template' => $template === null ? null : strtolower($template->uuid), 'recipients' => $recipients, 'documents' => $documents];
            })(),
            'call_webhook' => (function () use ($rt, $c, $p, &$errors): array {
                $url = trim((string) ($c['url'] ?? ''));
                // Placeholders are filled (URL-encoded) at run time; the rest must be a plain address.
                $plain = (string) preg_replace(TemplateValues::TOKEN, 'x', $url);
                if (preg_match('#^https?://[^\s"<>{}]+$#i', $plain) !== 1 || mb_strlen($url) > 2000) {
                    $errors["{$p}.url"][] = __('templates.bad_url');
                }
                $this->design->checkText($url, $rt, "{$p}.url", $errors);
                $method = strtoupper((string) ($c['method'] ?? 'POST'));
                if (! in_array($method, self::METHODS, true)) {
                    $errors["{$p}.method"][] = __('validation.in', ['attribute' => 'method']);
                }
                $headers = [];
                foreach (array_values((array) ($c['headers'] ?? [])) as $k => $h) {
                    $name = trim((string) ($h['name'] ?? ''));
                    if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $name) !== 1 || in_array(strtolower($name), ['host', 'content-length', 'x-lcf-signature', 'x-lcf-timestamp'], true)) {
                        $errors["{$p}.headers.{$k}.name"][] = __('actions.bad_header');

                        continue;
                    }
                    $value = $h['value'] ?? null;
                    if ($value !== null && (! is_string($value) || mb_strlen($value) > 2000 || preg_match('/[\r\n]/', $value) === 1)) {
                        $errors["{$p}.headers.{$k}.value"][] = __('actions.bad_header');
                    }
                    $headers[] = ['name' => $name, 'value' => is_string($value) && $value !== '' ? $value : null];
                }
                if (count($headers) > 20) {
                    $errors["{$p}.headers"][] = __('actions.too_many_headers');
                }
                $body = (string) ($c['body'] ?? '');
                if (mb_strlen($body) > 20000) {
                    $errors["{$p}.body"][] = __('validation.max.string', ['attribute' => 'body', 'max' => 20000]);
                }
                $this->design->checkText($body, $rt, "{$p}.body", $errors);
                $format = in_array($c['format'] ?? 'json', ['json', 'form', 'text'], true) ? (string) ($c['format'] ?? 'json') : 'json';
                $secret = $c['secret'] ?? null;
                if ($secret !== null && (! is_string($secret) || mb_strlen($secret) > 255)) {
                    $errors["{$p}.secret"][] = __('actions.bad_secret');
                }

                return ['url' => $url, 'method' => $method, 'headers' => $headers, 'body' => $body, 'format' => $format, 'secret' => $secret, 'keepSecret' => (bool) ($c['secretSet'] ?? false) && ($secret === null || $secret === '')];
            })(),
            'generate_document' => (function () use ($rt, $c, $p, &$errors): array {
                $doc = is_string($c['template'] ?? null) && Str::isUuid($c['template']) ? DocumentTemplate::query()->where('uuid', strtolower($c['template']))->first() : null;
                if ($doc === null || ($doc->form_id !== null && $doc->form_id !== $rt->form->id)) {
                    $errors["{$p}.template"][] = __('actions.unknown_document');
                } elseif (! in_array($c['format'] ?? null, $doc->output_formats, true)) {
                    $errors["{$p}.format"][] = __('notifications.bad_attachment_format');
                }
                $field = isset($c['attachToField']) ? ($rt->fields[strtolower((string) $c['attachToField'])] ?? null) : null;
                if (isset($c['attachToField']) && ($field === null || ! in_array($rt->type($field)?->storage, ['file', 'files'], true) || isset($rt->fieldRepeater[$field['uuid']]))) {
                    $errors["{$p}.attachToField"][] = __('actions.bad_file_field');
                }

                return ['template' => $doc === null ? null : strtolower($doc->uuid), 'format' => (string) ($c['format'] ?? 'pdf'), 'attachToField' => $field['uuid'] ?? null];
            })(),
            'run_download' => (function () use ($rt, $c, $p, &$errors): array {
                // A shared profile of this form offered on the record page; the runner still needs its permission.
                $profile = is_string($c['profile'] ?? null) && Str::isUuid($c['profile']) ? DB::table('download_profiles')->where('uuid', strtolower($c['profile']))->where('form_id', $rt->form->id)
                    ->where('is_personal', false)->whereNull('deleted_at')->first() : null;
                if ($profile === null || ! in_array('record_view', json_decode((string) $profile->available_in, true) ?: [], true)) {
                    $errors["{$p}.profile"][] = __('actions.unknown_download');
                } elseif (! in_array($c['format'] ?? null, json_decode((string) $profile->formats, true) ?: [], true)) {
                    $errors["{$p}.format"][] = __('notifications.bad_attachment_format');
                }

                return ['profile' => $profile === null ? null : strtolower((string) $profile->uuid), 'format' => (string) ($c['format'] ?? 'xlsx')];
            })(),
            'create_linked_record' => (function () use ($rt, $c, $p, $expr, &$errors): array {
                $target = is_string($c['form'] ?? null) && Str::isUuid($c['form']) ? $this->runtimes->forUuid(strtolower($c['form'])) : null;
                if ($target === null) {
                    $errors["{$p}.form"][] = __('actions.unknown_form');

                    return ['form' => null, 'linkField' => null, 'prefill' => [], 'open' => false];
                }
                $link = $target->fields[strtolower((string) ($c['linkField'] ?? ''))] ?? null;
                if ($link === null || $target->targetFormUuid($link) !== $rt->form->uuid || isset($target->fieldRepeater[$link['uuid']])) {
                    $errors["{$p}.linkField"][] = __('actions.bad_link_field');
                }
                $prefill = [];
                foreach (array_values((array) ($c['prefill'] ?? [])) as $k => $item) {
                    $field = $target->fields[strtolower((string) ($item['field'] ?? ''))] ?? null;
                    if ($field === null || ! $target->isStored($field) || isset($target->fieldRepeater[$field['uuid']]) || $field['uuid'] === ($link['uuid'] ?? null)) {
                        $errors["{$p}.prefill.{$k}.field"][] = __('actions.bad_field');

                        continue;
                    }
                    $prefill[] = ['field' => $field['uuid'], 'value' => $expr($item['value'] ?? null, "{$p}.prefill.{$k}.value")];
                }

                return ['form' => $target->form->uuid, 'linkField' => $link['uuid'] ?? null, 'prefill' => $prefill, 'open' => (bool) ($c['open'] ?? true)];
            })(),
            'assign' => (function () use ($c, $p, &$errors): array {
                $t = $c['to']['type'] ?? null;
                $ref = $c['to']['uuid'] ?? null;
                $table = match ($t) {
                    'user' => 'users', 'role' => 'roles', 'department' => 'departments', default => null,
                };
                if ($table === null || ! is_string($ref) || ! Str::isUuid($ref) || ! DB::table($table)->where('uuid', strtolower($ref))->exists()) {
                    $errors["{$p}.to"][] = __('actions.bad_assignee');
                }

                return ['to' => ['type' => $t, 'uuid' => is_string($ref) ? strtolower($ref) : null]];
            })(),
            default => [],
        };
    }

    private function recipientOk(FormRuntime $rt, mixed $r, bool $email): bool
    {
        if (! is_array($r)) {
            return false;
        }
        $ref = $r['ref'] ?? null;
        // Checked for shape first: SQL Server rejects a non-UUID compared with a uniqueidentifier column.
        $uuid = is_string($ref) && Str::isUuid($ref);

        return match ($r['type'] ?? null) {
            'user' => $uuid && DB::table('users')->where('uuid', strtolower($ref))->exists(),
            'role' => $uuid && DB::table('roles')->where('uuid', strtolower($ref))->exists(),
            'department' => $uuid && DB::table('departments')->where('uuid', strtolower($ref))->exists(),
            'field_user' => is_string($ref) && isset($rt->keys[$ref]) && $rt->targetTable($rt->fields[$rt->keys[$ref]]) === 'users',
            'creator', 'actor' => true,
            'linked_record_users' => is_string($ref) && preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,3}$/', $ref) === 1,
            'static' => $email && is_string($ref) && filter_var($ref, FILTER_VALIDATE_EMAIL) !== false,
            default => false,
        };
    }

    /** @return array<string, string> */
    private function texts(mixed $map, int $max): array
    {
        $out = [];
        foreach ((array) $map as $locale => $text) {
            if (is_string($text) && trim($text) !== '' && in_array($locale, $this->translator->enabledLocales(), true)) {
                $out[(string) $locale] = mb_substr($text, 0, $max);
            }
        }

        return $out;
    }
}
