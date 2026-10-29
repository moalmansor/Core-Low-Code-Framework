<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\PackagePart;
use App\Modules\Reports\Dashboards;
use App\Modules\Reports\Models\Dashboard;
use Illuminate\Support\Facades\DB;

/** Dashboards (ADR-0053): widgets and their layout; the reports they show travel with them, roles by key. */
final class DashboardPart implements PackagePart
{
    public function __construct(private readonly Dashboards $dashboards, private readonly Translator $translator) {}

    public function type(): string
    {
        return 'dashboard';
    }

    public function catalog(): array
    {
        return Dashboard::query()->orderBy('key')->get()
            ->map(fn (Dashboard $d) => ['uuid' => strtolower($d->uuid), 'key' => $d->key, 'label' => $this->translator->labelOf($d->translationsFor('name'), $d->key)])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $d = Dashboard::query()->where('uuid', $uuid)->first();
        if ($d === null) {
            return null;
        }
        $doc = $this->dashboards->present($d);
        $deps = array_values(array_unique(array_filter([$doc['application'], ...array_column($doc['widgets'], 'report')])));

        return ['uuid' => strtolower($d->uuid), 'key' => $d->key, 'label' => (string) $doc['label'], 'dependencies' => $deps,
            'content' => array_diff_key($doc, array_flip(['uuid', 'key', 'hash', 'label']))];
    }

    public function exists(string $uuid): bool
    {
        return Dashboard::query()->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return Dashboard::query()->where('key', $key)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $existing = Dashboard::query()->where('uuid', $object['uuid'])->first();
        $c = $object['content'];
        $notes = [];
        // A role the package names that does not exist here is dropped from the widget (the widget stays).
        foreach ($c['widgets'] as &$w) {
            $kept = array_values(array_filter((array) ($w['roles'] ?? []), static fn ($r) => DB::table('roles')->where('uuid', $r)->exists()));
            if (count($kept) !== count((array) ($w['roles'] ?? []))) {
                $notes[] = __('packages.skipped_subject', ['form' => (string) $object['label'], 'what' => __('packages.subject.role')]);
            }
            $w['roles'] = $kept;
        }
        unset($w);
        $p = $this->dashboards->check(['uuid' => $object['uuid'], 'key' => $object['key']] + $c, $existing);
        $this->dashboards->save($existing, $p, $user);

        return array_values(array_unique($notes));
    }
}
