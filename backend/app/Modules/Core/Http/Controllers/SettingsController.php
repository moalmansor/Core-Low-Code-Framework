<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Mail\MailConfigurator;
use App\Modules\Core\Mail\TestMail;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Settings\SettingsRegistry;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Records\FileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/** System settings (specification §4.22, Phase 1 scope). */
final class SettingsController extends Controller
{
    /** Groups an administrator edits from the System Settings area. */
    public const EDITABLE = ['branding', 'formats', 'calendar', 'mail', 'files', 'clamav', 'security', 'sso', 'ldap', 'monitoring'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly SettingsRegistry $registry,
    ) {}

    public function show(string $group): JsonResponse
    {
        $this->authorizeGroup($group);
        $data = $this->settings->publicGroup($group);
        if ($group === 'branding') {
            $data['system_name'] = $this->platform()->translationsFor('name');
        }

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $this->authorizeGroup($group);
        $input = $request->except(['system_name']);
        if ($group === 'branding' && $request->has('system_name')) {
            $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
            $names = $request->validate([
                'system_name' => ['array'],
                'system_name.'.$default => ['required', 'string', 'max:120'],
                'system_name.*' => ['nullable', 'string', 'max:120'],
            ])['system_name'];
            $org = $this->platform();
            $old = $org->translationsFor('name');
            $org->setTranslations('name', $names);
            app(AuditWriter::class)->record('config.changed', 'config', [['field_key' => 'branding.system_name', 'old' => $old, 'new' => $names]], 'settings', meta: ['group' => 'branding']);
        }
        if ($input !== []) {
            $this->settings->update($group, $input);
        }

        return $this->show($group);
    }

    public function uploadBrandingAsset(Request $request, string $kind, FileStore $files): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        abort_unless(in_array($kind, ['logo', 'favicon'], true), 404);
        $request->validate(['file' => ['required', 'file']]);
        $file = $files->storeImage($request->file('file'), 'branding');
        $this->settings->update('branding', [$kind.'_file' => $file->uuid]);

        return response()->json(['data' => ['uuid' => $file->uuid]]);
    }

    public function testMail(Request $request, MailConfigurator $configurator): JsonResponse
    {
        Gate::authorize('system.manage_settings');
        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:255']]);
        abort_unless($configurator->apply(), 422, __('ui.settings.mail_not_configured'));
        try {
            Mail::to($data['to'])->send(new TestMail);
        } catch (Throwable $e) {
            app(AuditWriter::class)->record('mail.test_failed', 'operations', meta: ['to' => $data['to']]);

            return response()->json(['message' => __('ui.settings.mail_test_failed'), 'detail' => mb_substr($e->getMessage(), 0, 500)], 422);
        }
        app(AuditWriter::class)->record('mail.test_sent', 'operations', meta: ['to' => $data['to']]);

        return response()->json(['data' => ['sent' => true]]);
    }

    private function authorizeGroup(string $group): void
    {
        abort_unless(in_array($group, self::EDITABLE, true) && $this->registry->group($group) !== [], 404);
        Gate::authorize($group === 'branding' ? 'system.manage_branding' : 'system.manage_settings');
    }

    private function platform(): Organization
    {
        return Organization::query()->findOrFail(app(TenantContext::class)->organizationId());
    }
}
