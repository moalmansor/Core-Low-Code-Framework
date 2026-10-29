<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\PackagePart;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\ReportDefinitions;

/** Reports (ADR-0053): the definition as the builder edits it; the base form travels with it. */
final class ReportPart implements PackagePart
{
    public function __construct(private readonly ReportDefinitions $definitions, private readonly Translator $translator) {}

    public function type(): string
    {
        return 'report';
    }

    public function catalog(): array
    {
        return Report::query()->orderBy('key')->get()
            ->map(fn (Report $r) => ['uuid' => strtolower($r->uuid), 'key' => $r->key, 'label' => $this->translator->labelOf($r->translationsFor('name'), $r->key)])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $r = Report::query()->where('uuid', $uuid)->first();
        if ($r === null) {
            return null;
        }
        $doc = $this->definitions->present($r);
        $content = array_diff_key($doc, array_flip(['uuid', 'key', 'hash', 'label', 'form_name']));

        return ['uuid' => strtolower($r->uuid), 'key' => $r->key, 'label' => (string) $doc['label'],
            'dependencies' => array_values(array_filter([$doc['form'], $doc['application']])), 'content' => $content];
    }

    public function exists(string $uuid): bool
    {
        return Report::query()->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return Report::query()->where('key', $key)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $existing = Report::query()->where('uuid', $object['uuid'])->first();
        $p = $this->definitions->check(['uuid' => $object['uuid'], 'key' => $object['key']] + $object['content'], $existing);
        $this->definitions->save($existing, $p, $user);

        return [];
    }
}
