<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Parts;

use App\Modules\Core\I18n\Translator;
use App\Modules\Downloads\DownloadProfiles;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\PackagePart;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Shared download profiles (personal ones stay with their owner). */
final class DownloadProfilePart implements PackagePart
{
    public function __construct(private readonly DownloadProfiles $profiles, private readonly Translator $translator) {}

    public function type(): string
    {
        return 'download_profile';
    }

    public function catalog(): array
    {
        $rows = DB::table('download_profiles')->where('is_personal', false)->orderBy('key')->get(['id', 'uuid', 'key']);
        $names = $this->translator->many('download_profile', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all(), ['name']);

        return $rows->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'key' => (string) $r->key, 'label' => $names[(int) $r->id]['name'] ?? Translator::humanize((string) $r->key)])->values()->all();
    }

    public function export(string $uuid): ?array
    {
        $p = DB::table('download_profiles')->where('uuid', $uuid)->where('is_personal', false)->first();
        if ($p === null) {
            return null;
        }
        // The key travels on the object (it may be imported under another one).
        $doc = array_diff_key($this->profiles->load($p), ['key' => true, 'uuid' => true]);
        $form = strtolower((string) DB::table('forms')->where('id', $p->form_id)->value('uuid'));

        return ['uuid' => strtolower((string) $p->uuid), 'key' => (string) $p->key, 'label' => $this->translator->labelOf((array) ($doc['i18n']['name'] ?? []), (string) $p->key),
            'dependencies' => [$form], 'content' => ['form' => $form, 'profile' => $doc]];
    }

    public function exists(string $uuid): bool
    {
        return DB::table('download_profiles')->where('uuid', $uuid)->exists();
    }

    public function keyTaken(string $key, string $uuid): bool
    {
        return DB::table('download_profiles')->where('key', $key)->where('is_personal', false)->where('uuid', '!=', $uuid)->exists();
    }

    public function apply(array $object, User $user, array $options): array
    {
        $form = Form::query()->where('uuid', $object['content']['form'])->first() ?? throw new RuntimeException(__('packages.missing_form'));
        $existing = DB::table('download_profiles')->where('uuid', $object['uuid'])->first();
        $doc = ['key' => $object['key'], 'uuid' => $object['uuid']] + (array) $object['content']['profile'];
        $this->profiles->save($form, $existing, $doc, $user, false);

        return [];
    }
}
