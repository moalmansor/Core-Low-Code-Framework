<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings;

use App\Modules\Records\FileStore;
use App\Support\Color\ReadableBrandColour;
use Illuminate\Validation\Rule;

/**
 * Every setting an administrator can change from the UI (specification §4.22,
 * §5). Defaults are the secure baseline seeded on install (§2). Modules add
 * their own groups in later phases through `define()`.
 */
final class SettingsRegistry
{
    /** @var array<string, array<string, SettingDefinition>> */
    private array $definitions = [];

    public function __construct()
    {
        $this->defineDefaults();
    }

    public function define(SettingDefinition $definition): void
    {
        $this->definitions[$definition->group][$definition->key] = $definition;
    }

    /** @return array<string, SettingDefinition> */
    public function group(string $group): array
    {
        return $this->definitions[$group] ?? [];
    }

    /** @return list<string> */
    public function groups(): array
    {
        return array_keys($this->definitions);
    }

    public function get(string $group, string $key): ?SettingDefinition
    {
        return $this->definitions[$group][$key] ?? null;
    }

    private function defineDefaults(): void
    {
        $d = fn (string $group, string $key, mixed $default, array|string $rules, bool $secret = false, bool $immutable = false) => $this->define(new SettingDefinition($group, $key, $default, $rules, $secret, $immutable));

        // Setup and tenancy (§2, §4.27)
        $d('setup', 'completed_at', null, ['nullable', 'date']);
        $d('setup', 'token_hash', null, ['nullable', 'string', 'size:64']);
        $d('tenancy', 'mode', 'single', ['required', Rule::in(['single', 'multi'])], immutable: true);

        // Branding (§2, §4.28 basics). The system name is the platform
        // organization's translatable name.
        $d('branding', 'logo_file', null, ['nullable', 'uuid']);
        $d('branding', 'favicon_file', null, ['nullable', 'uuid']);
        // The brand colour (docs/design-system.md): unset means the theme's default.
        // The dark-mode shade is optional; without it one is derived.
        $d('branding', 'primary_color', null, ['nullable', 'string', new ReadableBrandColour('light')]);
        $d('branding', 'primary_color_dark', null, ['nullable', 'string', new ReadableBrandColour('dark')]);

        // System-wide maintenance mode (§4.29, ADR-0054): its message is the platform organization's
        // translatable `maintenance_message`; people who may switch it keep working.
        $d('maintenance', 'enabled', false, ['required', 'boolean']);
        $d('maintenance', 'until', null, ['nullable', 'date']);

        // Schema changes (§4.9, architecture §12.3, §12.5, §12.6)
        $d('schema', 'snapshot_retention_days', 30, ['required', 'integer', 'between:1,3650']);
        $d('schema', 'blocking_confirmation_rows', 100000, ['required', 'integer', 'between:0,1000000000']);
        $d('schema', 'reconcile_daily', true, ['required', 'boolean']);

        // Records import/export (§4.8)
        $d('records', 'export_max_rows', 50000, ['required', 'integer', 'between:100,1000000']);
        // Imports run as background jobs (§4.15): these are the job's own guards.
        $d('records', 'import_max_rows', 100000, ['required', 'integer', 'between:10,1000000']);
        $d('records', 'import_time_limit_minutes', 60, ['required', 'integer', 'between:1,1440']);
        // Exports of more rows than this run as background jobs; smaller ones download at once.
        $d('records', 'export_sync_rows', 5000, ['required', 'integer', 'between:0,100000']);
        // Exported and generated files (exports, error reports, downloads) expire after this many hours.
        $d('records', 'job_file_hours', 72, ['required', 'integer', 'between:1,2160']);

        // Data quality (§4.35, ADR-0044)
        // Deleted records can be restored for this many days; then the daily purge removes them.
        $d('data_quality', 'recycle_bin_days', 90, ['required', 'integer', 'between:1,3650']);
        // Duplicate checks while saving compare against at most this many records (the sweep covers all).
        $d('data_quality', 'duplicate_scan_limit', 20000, ['required', 'integer', 'between:100,1000000']);

        // Notifications and the Operations Center (§4.2, §4.16)
        $d('notifications', 'email_max_attempts', 5, ['required', 'integer', 'between:1,20']);
        $d('operations', 'stuck_email_minutes', 15, ['required', 'integer', 'between:1,1440']);

        // Actions (§4.15): the most records one run may touch, and above how many it runs in the background (each action may set its own).
        // REST API and webhooks (§4.22, ADR-0050)
        $d('api', 'enabled', true, ['required', 'boolean']);
        $d('api', 'token_max_days', 365, ['required', 'integer', 'between:1,3650']);
        $d('api', 'token_rate_limit_per_minute', 120, ['required', 'integer', 'between:10,10000']);
        $d('api', 'webhook_delivery_days', 30, ['required', 'integer', 'between:1,365']);
        $d('actions', 'max_records', 500, ['required', 'integer', 'between:1,100000']);
        $d('actions', 'queue_threshold', 50, ['required', 'integer', 'between:1,10000']);

        // Custom downloads (§4.23): how deep a profile's relation tree reaches, up to how many rows a download
        // is produced at once (larger ones run in the background), the most rows a profile may produce unless it
        // sets its own lower limit, and the largest file a scheduled download attaches to its email (larger ones
        // are announced with a link that needs sign-in).
        $d('downloads', 'max_depth', 5, ['required', 'integer', 'between:1,10']);
        $d('downloads', 'sync_rows', 5000, ['required', 'integer', 'between:1,100000']);
        $d('downloads', 'max_rows', 100000, ['required', 'integer', 'between:100,1000000']);
        $d('downloads', 'email_attachment_mb', 10, ['required', 'integer', 'between:1,25']);

        // External portal (§4.32, ADR-0058): whether people may register an external account, the external
        // role a new account gets, and whether an administrator approves each account before it can sign in.
        $d('external', 'registration_open', false, ['required', 'boolean']);
        $d('external', 'registration_role', null, ['nullable', 'uuid']);
        $d('external', 'registration_requires_approval', true, ['required', 'boolean']);

        // Reports (§4.18, ADR-0053): the most records one report reads; a report that stops there says so.
        $d('reports', 'max_records', 100000, ['required', 'integer', 'between:100,1000000']);

        // Document templates (§4.17): the size of an uploaded Word template, and how long one conversion to PDF may take.
        $d('documents', 'max_template_mb', 10, ['required', 'integer', 'between:1,50']);
        $d('documents', 'convert_timeout_seconds', 120, ['required', 'integer', 'between:10,600']);

        // Formats and calendar (§2)
        $d('formats', 'timezone', 'UTC', ['required', 'timezone:all']);
        $d('formats', 'date_format', 'yyyy-MM-dd', ['required', 'string', 'max:32', 'regex:/^[yMdHhmsaEG\/\-\.\s,]+$/']);
        $d('formats', 'time_format', '24h', ['required', Rule::in(['12h', '24h'])]);
        $d('formats', 'first_day_of_week', 0, ['required', 'integer', 'between:0,6']);
        $d('formats', 'digits', 'locale', ['required', Rule::in(['locale', 'western', 'arabic_indic'])]);
        $d('formats', 'decimal_separator', '.', ['required', Rule::in(['.', ','])]);
        $d('formats', 'thousands_separator', ',', ['present', 'nullable', Rule::in([',', '.', ' ', "'", ''])]);
        $d('calendar', 'system', 'gregorian', ['required', Rule::in(['gregorian', 'hijri', 'both'])]);

        // Outgoing mail (§2 SMTP)
        $d('mail', 'host', null, ['nullable', 'string', 'max:255']);
        $d('mail', 'port', 587, ['required', 'integer', 'between:1,65535']);
        $d('mail', 'encryption', 'tls', ['required', Rule::in(['tls', 'ssl', 'none'])]);
        $d('mail', 'username', null, ['nullable', 'string', 'max:255']);
        $d('mail', 'password', null, ['nullable', 'string', 'max:1024'], secret: true);
        $d('mail', 'from_address', null, ['nullable', 'email:rfc', 'max:255']);
        $d('mail', 'from_name', null, ['nullable', 'string', 'max:255']);

        // Files and virus scanning (§3, §5)
        $d('files', 'max_upload_mb', 10, ['required', 'integer', 'between:1,512']);
        $d('files', 'allowed_file_types', ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp'], ['required', 'array', 'min:1', '*' => ['distinct', Rule::in(array_keys(FileStore::MIMES))]]);
        $d('files', 'allowed_image_types', ['png', 'jpg', 'jpeg', 'webp', 'ico'], ['required', 'array', 'min:1', '*' => ['distinct', Rule::in(['png', 'jpg', 'jpeg', 'webp', 'ico'])]]);
        $d('clamav', 'enabled', false, ['required', 'boolean']);
        $d('clamav', 'host', 'clamav', ['required', 'string', 'max:255']);
        $d('clamav', 'port', 3310, ['required', 'integer', 'between:1,65535']);
        $d('clamav', 'timeout_seconds', 30, ['required', 'integer', 'between:1,300']);

        // Security policies (§5)
        $d('security', 'password_min_length', 12, ['required', 'integer', 'between:8,128']);
        $d('security', 'password_require_uppercase', true, ['required', 'boolean']);
        $d('security', 'password_require_lowercase', true, ['required', 'boolean']);
        $d('security', 'password_require_digit', true, ['required', 'boolean']);
        $d('security', 'password_require_symbol', true, ['required', 'boolean']);
        $d('security', 'password_history', 5, ['required', 'integer', 'between:0,24']);
        $d('security', 'password_expiry_days', 0, ['required', 'integer', 'between:0,730']);
        $d('security', 'lockout_max_attempts', 5, ['required', 'integer', 'between:3,20']);
        $d('security', 'lockout_minutes', 15, ['required', 'integer', 'between:1,1440']);
        $d('security', 'session_idle_minutes', 30, ['required', 'integer', 'between:5,1440']);
        $d('security', 'session_absolute_minutes', 720, ['required', 'integer', 'between:15,10080']);
        $d('security', 'api_rate_limit_per_minute', 240, ['required', 'integer', 'between:30,10000']);

        // Single sign-on (§5): OIDC providers; secrets are stored per provider key.
        $d('sso', 'providers', [], ['present', 'array', 'max:10',
            '*.key' => ['required', 'string', 'distinct', 'regex:/^[a-z0-9_-]{1,64}$/'],
            '*.name' => ['required', 'array'],
            '*.name.*' => ['nullable', 'string', 'max:120'],
            '*.issuer' => ['required', 'url:https', 'max:255'],
            '*.client_id' => ['required', 'string', 'max:255'],
            '*.scopes' => ['sometimes', 'array', 'max:20'],
            '*.scopes.*' => ['string', 'regex:/^[A-Za-z0-9_.:-]{1,64}$/'],
            '*.role_claim' => ['sometimes', 'nullable', 'string', 'max:64'],
            '*.role_map' => ['sometimes', 'array', 'max:100'],
            '*.role_map.*' => ['string', 'regex:/^[a-z][a-z0-9_]{1,63}$/'],
            '*.jit_provisioning' => ['sometimes', 'boolean'],
            '*.link_by_email' => ['sometimes', 'boolean'],
            '*.enabled' => ['sometimes', 'boolean'],
        ]);
        $d('sso', 'client_secrets', [], ['present', 'array', 'max:10', '*' => ['nullable', 'string', 'max:1024']], secret: true);

        // LDAP (§5)
        $d('ldap', 'enabled', false, ['required', 'boolean']);
        $d('ldap', 'hosts', [], ['present', 'array', 'max:5', '*' => ['string', 'max:253', 'regex:/^[A-Za-z0-9.-]+$/']]);
        $d('ldap', 'port', 389, ['required', 'integer', 'between:1,65535']);
        $d('ldap', 'base_dn', null, ['nullable', 'string', 'max:512']);
        $d('ldap', 'bind_username', null, ['nullable', 'string', 'max:512']);
        $d('ldap', 'bind_password', null, ['nullable', 'string', 'max:1024'], secret: true);
        $d('ldap', 'use_ssl', false, ['required', 'boolean']);
        $d('ldap', 'use_tls', true, ['required', 'boolean']);
        $d('ldap', 'timeout_seconds', 5, ['required', 'integer', 'between:1,60']);
        $d('ldap', 'login_attribute', 'mail', ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9-]{0,63}$/']);
        $d('ldap', 'email_attribute', 'mail', ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9-]{0,63}$/']);
        $d('ldap', 'name_attribute', 'cn', ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9-]{0,63}$/']);
        $d('ldap', 'group_role_map', [], ['present', 'array', 'max:200', '*' => ['string', 'regex:/^[a-z][a-z0-9_]{1,63}$/']]);
        $d('ldap', 'jit_provisioning', false, ['required', 'boolean']);

        // Error monitoring alerts (§4.21)
        $d('monitoring', 'alert_role_keys', ['super_admin'], ['present', 'array', 'max:20', '*' => ['string', 'regex:/^[a-z][a-z0-9_]{1,63}$/']]);
        $d('monitoring', 'alert_cooldown_minutes', 60, ['required', 'integer', 'between:1,10080']);
        $d('monitoring', 'alert_min_severity', 'error', ['required', Rule::in(['warning', 'error', 'critical', 'alert', 'emergency'])]);
    }
}
