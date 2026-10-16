<?php

declare(strict_types=1);

namespace App\Modules\Records\Templating;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Facades\DB;

/**
 * What a template designer offers and previews with, shared by email and
 * document templates: the placeholders of a form, and a record of it the
 * designer may see.
 */
final class TemplatePlaceholders
{
    public function __construct(
        private readonly Translator $translator,
        private readonly FormRuntimes $runtimes,
    ) {}

    /**
     * System values, the form's fields, fields of records linked from it (one
     * hop; longer paths can be typed), and the repeaters with their columns.
     * Encrypted fields are never offered.
     *
     * @return array{system: list<array<string, mixed>>, fields: list<array<string, mixed>>, repeaters: list<array<string, mixed>>}
     */
    public function catalog(?string $formUuid): array
    {
        $system = array_map(fn (string $s) => ['token' => $s, 'label' => __('templates.placeholder.'.ltrim($s, '@')), 'group' => 'system'], TemplateValues::SYSTEM);
        $form = $formUuid !== null && $formUuid !== '' ? Form::query()->where('uuid', strtolower($formUuid))->first() : null;
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

        return ['system' => $system, 'fields' => $fields, 'repeaters' => $repeaters];
    }

    /**
     * The form and, when one is named, a record of it the user may see (404 otherwise).
     *
     * @return array{0: FormRuntime|null, 1: int|null}
     */
    public function target(?string $formUuid, ?string $recordUuid, User $user): array
    {
        $form = $formUuid !== null && $formUuid !== '' ? Form::query()->where('uuid', strtolower($formUuid))->first() : null;
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null || $recordUuid === null || $recordUuid === '') {
            return [$rt, null];
        }
        abort_unless(app(AccessResolver::class)->allows($user, "form.{$rt->form->uuid}.view"), 404);
        $id = DB::table($rt->table)->where('uuid', strtolower($recordUuid))->whereNull('deleted_at')->value('id');
        abort_if($id === null || ! app(RecordScope::class)->allows($rt, $user, 'view', (int) $id), 404, __('records.not_found'));

        return [$rt, (int) $id];
    }
}
