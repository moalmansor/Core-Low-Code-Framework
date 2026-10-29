<?php

declare(strict_types=1);

namespace App\Modules\Experience\Themes;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Experience\Models\Theme;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use App\Support\Json\Canonical;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Themes (specification §4.28, ADR-0055): one per application at most, and
 * one organization default; the default applies everywhere an application
 * has no theme of its own. Contrast is checked on every save; errors refuse
 * the theme, warnings must be acknowledged. Every save bumps the version so
 * browsers fetch the new stylesheet.
 */
final class Themes
{
    /** Artwork a theme can carry, and where it is used. */
    public const ASSETS = ['logo', 'logo_dark', 'favicon', 'login_background', 'email_header', 'email_footer'];

    private const TEXTS = ['name', 'login_welcome', 'support_contact', 'browser_title'];

    public function __construct(
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly FileStore $files,
    ) {}

    /** @return array<string, mixed> */
    public function present(Theme $t): array
    {
        $doc = [
            'uuid' => strtolower($t->uuid), 'key' => $t->key,
            'application' => $t->application_id === null ? null : strtolower((string) Application::query()->whereKey($t->application_id)->value('uuid')),
            'default' => $t->is_default,
            'tokens' => ['colors' => (object) [], 'legal_links' => []] + $t->tokens,
            'custom_css' => $t->custom_css,
            'i18n' => array_combine(self::TEXTS, array_map(static fn (string $f) => (object) $t->translationsFor($f), self::TEXTS)),
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['label'] = $this->translator->labelOf($t->translationsFor('name'), $t->key);
        $doc['version'] = $t->version;
        $doc['assets'] = $this->assets($t);
        $doc['contrast'] = ThemeTokens::report($t->tokens);

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?Theme $existing): array
    {
        $errors = [];
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
            $errors['key'][] = __('experience.bad_key');
        } elseif (Theme::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('experience.key_taken');
        }
        $texts = [];
        foreach (self::TEXTS as $f) {
            $texts[$f] = self::i18n($d['i18n'][$f] ?? [], $f === 'login_welcome' ? 1000 : 255);
        }
        if ($texts['name'] === []) {
            $errors['i18n.name'][] = __('experience.name_required');
        }
        $application = null;
        if (($d['application'] ?? null) !== null) {
            $application = Str::isUuid((string) $d['application']) ? Application::query()->where('uuid', strtolower((string) $d['application']))->first() : null;
            if ($application === null) {
                $errors['application'][] = __('experience.unknown_application');
            } elseif (Theme::query()->where('application_id', $application->id)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
                $errors['application'][] = __('experience.app_has_theme');
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $tokens = ThemeTokens::normalize((array) ($d['tokens'] ?? []));
        $css = CustomCss::compile(is_string($d['custom_css'] ?? null) ? $d['custom_css'] : null);
        $report = ThemeTokens::report($tokens);
        if (ThemeTokens::errors($report) > 0) {
            $failed = array_map(static fn (array $r) => __('experience.contrast_check.'.$r['check']).' ('.__('experience.mode.'.$r['mode']).', '.$r['ratio'].':1)', array_values(array_filter($report, static fn (array $r) => $r['level'] === 'error')));
            throw ValidationException::withMessages(['tokens' => __('experience.contrast_failed', ['checks' => implode('; ', $failed)])]);
        }
        if (ThemeTokens::warnings($report) > 0 && ! (bool) ($d['acknowledge_warnings'] ?? false)) {
            throw ValidationException::withMessages(['acknowledge_warnings' => __('experience.contrast_warnings')]);
        }

        return [
            'key' => $key, 'texts' => $texts, 'application' => $application, 'tokens' => $tokens,
            'custom_css' => is_string($d['custom_css'] ?? null) && trim($d['custom_css']) !== '' ? $d['custom_css'] : null,
            'compiled' => $css, 'report' => $report,
        ];
    }

    /** @param  array<string, mixed>  $p  from check() */
    public function save(?Theme $existing, array $p, User $actor): Theme
    {
        return DB::transaction(function () use ($existing, $p, $actor): Theme {
            $t = $existing ?? new Theme;
            if ($existing === null) {
                $t->uuid = (string) Str::uuid7();
                $t->version = 0;
                $t->is_default = false;
                $t->is_preset = false;
            }
            $previousApp = $existing?->application_id;
            $t->forceFill([
                'key' => $p['key'], 'application_id' => $p['application']?->id, 'tokens' => $p['tokens'],
                'custom_css' => $p['custom_css'], 'custom_css_compiled' => $p['compiled'], 'contrast_report' => $p['report'],
                'version' => $t->version + 1,
            ])->save();
            foreach ($p['texts'] as $field => $values) {
                $t->setTranslations($field, $values);
            }
            if ($previousApp !== null && $previousApp !== $t->application_id) {
                DB::table('applications')->where('id', $previousApp)->where('theme_id', $t->id)->update(['theme_id' => null]);
            }
            if ($t->application_id !== null) {
                DB::table('applications')->where('id', $t->application_id)->update(['theme_id' => $t->id]);
            }
            $this->audit->record($existing === null ? 'theme.created' : 'theme.updated', 'config', null, 'theme', $t->id,
                ['key' => $t->key, 'version' => $t->version, 'warnings' => ThemeTokens::warnings($p['report'])], $actor->id);

            return $t->refresh();
        });
    }

    /** Makes the theme the organization's default (it applies where an application has none). */
    public function makeDefault(Theme $t, User $actor): void
    {
        abort_if($t->application_id !== null, 422, __('experience.app_theme_not_default'));
        DB::transaction(function () use ($t, $actor): void {
            Theme::query()->where('is_default', true)->update(['is_default' => false]);
            $t->forceFill(['is_default' => true])->save();
            DB::table('organizations')->where('id', app(TenantContext::class)->organizationId())->update(['theme_id' => $t->id]);
            $this->audit->record('theme.default', 'config', null, 'theme', $t->id, ['key' => $t->key], $actor->id);
        });
    }

    /** Back to the product theme: no default theme at all. */
    public function clearDefault(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            Theme::query()->where('is_default', true)->update(['is_default' => false]);
            DB::table('organizations')->where('id', app(TenantContext::class)->organizationId())->update(['theme_id' => null]);
            $this->audit->record('theme.default_cleared', 'config', null, 'theme', null, [], $actor->id);
        });
    }

    /** Resets the tokens and custom CSS to the product theme, keeping texts and artwork. */
    public function reset(Theme $t, User $actor): Theme
    {
        $t->forceFill(['tokens' => ['colors' => (object) [], 'legal_links' => $t->tokens['legal_links'] ?? []], 'custom_css' => null, 'custom_css_compiled' => null,
            'contrast_report' => ThemeTokens::report([]), 'version' => $t->version + 1])->save();
        $this->audit->record('theme.reset', 'config', null, 'theme', $t->id, ['key' => $t->key], $actor->id);

        return $t->refresh();
    }

    public function delete(Theme $t, User $actor): void
    {
        DB::transaction(function () use ($t, $actor): void {
            DB::table('applications')->where('theme_id', $t->id)->update(['theme_id' => null]);
            DB::table('organizations')->where('theme_id', $t->id)->update(['theme_id' => null]);
            DB::table('external_forms')->where('theme_id', $t->id)->update(['theme_id' => null]);
            DB::table('theme_assets')->where('theme_id', $t->id)->delete();
            $this->audit->record('theme.deleted', 'config', null, 'theme', $t->id, ['key' => $t->key], $actor->id);
            $t->delete();
        });
    }

    /**
     * A portable copy: tokens, custom CSS and texts. Artwork stays behind; it
     * is uploaded again where the theme is imported.
     *
     * @return array<string, mixed>
     */
    public function export(Theme $t): array
    {
        $doc = $this->present($t);

        return ['format' => 'lcf-theme', 'version' => 1, 'key' => $t->key, 'tokens' => $doc['tokens'], 'custom_css' => $t->custom_css, 'i18n' => $doc['i18n']];
    }

    /**
     * @param  array<string, mixed>  $file
     *
     * @throws ValidationException
     */
    public function import(array $file, User $actor): Theme
    {
        if (($file['format'] ?? null) !== 'lcf-theme' || (int) ($file['version'] ?? 0) !== 1) {
            throw ValidationException::withMessages(['file' => __('experience.not_a_theme')]);
        }
        $key = (string) ($file['key'] ?? 'imported');
        $base = preg_match('/^[a-z][a-z0-9_]{1,40}$/', $key) === 1 ? $key : 'imported';
        $candidate = $base;
        for ($i = 2; Theme::query()->where('key', $candidate)->exists(); $i++) {
            $candidate = $base.'_'.$i;
        }

        return $this->save(null, $this->check(['key' => $candidate, 'application' => null, 'tokens' => (array) ($file['tokens'] ?? []),
            'custom_css' => $file['custom_css'] ?? null, 'i18n' => (array) ($file['i18n'] ?? []), 'acknowledge_warnings' => true], null), $actor);
    }

    public function storeAsset(Theme $t, string $kind, UploadedFile $upload, User $actor): void
    {
        abort_unless(in_array($kind, self::ASSETS, true), 404);
        $file = $this->files->storeImage($upload, 'theme');
        DB::transaction(function () use ($t, $kind, $file, $actor): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('theme_assets')->where('theme_id', $t->id)->where('kind', $kind)->delete();
            DB::table('theme_assets')->insert(['theme_id' => $t->id, 'kind' => $kind, 'locale' => null, 'file_id' => $file->id, 'created_at' => $now, 'updated_at' => $now]);
            $t->forceFill(['version' => $t->version + 1])->save();
            $this->audit->record('theme.asset_set', 'config', null, 'theme', $t->id, ['kind' => $kind], $actor->id);
        });
    }

    public function removeAsset(Theme $t, string $kind, User $actor): void
    {
        abort_unless(in_array($kind, self::ASSETS, true), 404);
        DB::table('theme_assets')->where('theme_id', $t->id)->where('kind', $kind)->delete();
        $t->forceFill(['version' => $t->version + 1])->save();
        $this->audit->record('theme.asset_removed', 'config', null, 'theme', $t->id, ['kind' => $kind], $actor->id);
    }

    public function assetFile(Theme $t, string $kind): ?StoredFile
    {
        $id = DB::table('theme_assets')->where('theme_id', $t->id)->where('kind', $kind)->value('file_id');

        return $id === null ? null : StoredFile::query()->where('id', $id)->where('owner_type', 'theme')->first();
    }

    /** @return array<string, string|null> kind => URL */
    public function assets(Theme $t): array
    {
        $have = DB::table('theme_assets')->where('theme_id', $t->id)->pluck('kind')->all();
        $out = [];
        foreach (self::ASSETS as $kind) {
            $out[$kind] = in_array($kind, $have, true) ? url('/api/v1/theme-assets/'.strtolower($t->uuid).'/'.$kind.'?v='.$t->version) : null;
        }

        return $out;
    }

    public function organizationDefault(): ?Theme
    {
        $id = DB::table('organizations')->where('id', app(TenantContext::class)->organizationId())->value('theme_id');

        return $id === null ? Theme::query()->where('is_default', true)->whereNull('application_id')->first() : Theme::query()->find($id);
    }

    public function forApplication(int $applicationId): ?Theme
    {
        $id = DB::table('applications')->where('id', $applicationId)->value('theme_id');

        return $id === null ? null : Theme::query()->find($id);
    }

    /**
     * What the browser needs to apply a theme: colours per mode, shape, the
     * stylesheet of its custom CSS, artwork and the sign-in texts in the
     * reader's language. Nothing here is sensitive.
     *
     * @return array<string, mixed>
     */
    public function client(Theme $t): array
    {
        $links = [];
        foreach ((array) ($t->tokens['legal_links'] ?? []) as $l) {
            $links[] = ['url' => $l['url'], 'label' => $this->translator->pick($l['label'] ?? [])];
        }

        return [
            'uuid' => strtolower($t->uuid), 'version' => $t->version,
            'colors' => (object) ($t->tokens['colors'] ?? []),
            'radius' => $t->tokens['radius'] ?? null, 'density' => $t->tokens['density'] ?? null, 'shadow' => $t->tokens['shadow'] ?? null,
            'font_latin' => $t->tokens['font_latin'] ?? null, 'font_arabic' => $t->tokens['font_arabic'] ?? null,
            'css' => $t->custom_css_compiled === null ? null : url('/api/v1/theme-css/'.strtolower($t->uuid).'.css?v='.$t->version),
            'assets' => $this->assets($t),
            'login' => [
                'welcome' => $this->translator->pick($t->translationsFor('login_welcome')),
                'support' => $this->translator->pick($t->translationsFor('support_contact')),
                'legal_links' => $links,
            ],
            'browser_title' => $this->translator->pick($t->translationsFor('browser_title')),
        ];
    }

    /** @return array<string, string> */
    private static function i18n(mixed $values, int $max): array
    {
        $out = [];
        foreach ((array) $values as $locale => $v) {
            if (is_string($locale) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $locale) === 1 && is_string($v) && trim($v) !== '') {
                $out[$locale] = mb_substr(trim($v), 0, $max);
            }
        }

        return $out;
    }
}
