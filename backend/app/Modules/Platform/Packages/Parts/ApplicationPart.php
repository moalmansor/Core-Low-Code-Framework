<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Access\ObjectPermissions;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\PackagePart;

/** Applications: key, names, icon, colour, data sharing and order. */
final class ApplicationPart implements PackagePart
{
    public function __construct(private readonly Translator $translator) {}

    public function type(): string
    {
        return 'application';
    }

    public function catalog(): array
    {
        return Application::query()->whereNull('deleted_at')->orderBy('sort_order')->orderBy('key')->get()
            ->map(fn (Application $a) => ['uuid' => strtolower($a->uuid), 'key' => $a->key, 'label' => $this->translator->labelOf($a->translationsFor('name'), $a->key)])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $a = Application::query()->where('uuid', $uuid)->whereNull('deleted_at')->first();
        if ($a === null) {
            return null;
        }

        return ['uuid' => strtolower($a->uuid), 'key' => $a->key, 'label' => $this->translator->labelOf($a->translationsFor('name'), $a->key), 'dependencies' => [], 'content' => [
            'name' => $a->translationsFor('name'), 'description' => $a->translationsFor('description'), 'icon' => $a->icon, 'color' => $a->color,
            'dataSharing' => $a->data_sharing_default, 'order' => $a->sort_order,
        ]];
    }

    public function exists(string $uuid): bool
    {
        return Application::query()->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return Application::query()->where('key', $key)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $c = $object['content'];
        $a = Application::withTrashed()->where('uuid', $object['uuid'])->first();
        $new = $a === null;
        $a ??= new Application(['status' => 'active', 'maintenance_mode' => false]);
        if ($new) {
            $a->uuid = $object['uuid'];
        }
        $a->fill(['key' => $object['key'], 'icon' => $c['icon'] ?? null, 'color' => $c['color'] ?? null, 'data_sharing_default' => $c['dataSharing'] ?? 'shared', 'sort_order' => (int) ($c['order'] ?? 0)]);
        $a->save();
        if ($a->trashed()) {
            $a->restore();
        }
        $a->setTranslations('name', (array) ($c['name'] ?? []));
        $a->setTranslations('description', (array) ($c['description'] ?? []));
        if ($new) {
            app(ObjectPermissions::class)->registerApplication($a->id, $a->uuid);
        }

        return [];
    }
}
