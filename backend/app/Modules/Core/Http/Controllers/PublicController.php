<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data the SPA needs before anyone signs in: branding, enabled locales,
 * formats, setup state, and interface strings. Nothing here is sensitive.
 */
final class PublicController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function bootstrap(): JsonResponse
    {
        $platform = Organization::query()->find(app(TenantContext::class)->platformOrganizationId());
        $locales = Locale::query()->where('is_enabled', true)->with('fallback:id,code')->orderBy('sort_order')->get();
        $branding = $this->settings->publicGroup('branding');

        return response()->json(['data' => [
            'setup_completed' => $this->settings->get('setup', 'completed_at') !== null,
            'system_name' => $platform?->translationsFor('name') ?? [],
            'default_locale' => ($locales->firstWhere('is_default', true)->code ?? 'en'),
            'locales' => $locales->map(static fn (Locale $l): array => LocaleController::present($l))->values(),
            'formats' => $this->settings->publicGroup('formats'),
            'calendar' => $this->settings->get('calendar', 'system'),
            'branding' => [
                'logo' => $branding['logo_file'] ? url('/api/v1/branding/logo') : null,
                'favicon' => $branding['favicon_file'] ? url('/api/v1/branding/favicon') : null,
            ],
        ]]);
    }

    public function catalog(string $locale, Translator $translator): JsonResponse
    {
        abort_unless(Locale::query()->where('code', $locale)->where('is_enabled', true)->exists(), 404);

        return response()->json(['data' => $translator->uiCatalog($locale)])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function brandingAsset(string $kind): Response
    {
        abort_unless(in_array($kind, ['logo', 'favicon'], true), 404);
        $uuid = $this->settings->get('branding', $kind.'_file');
        $file = $uuid === null ? null : StoredFile::query()->where('uuid', $uuid)->where('owner_type', 'branding')->first();
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
