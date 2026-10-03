export type FieldType = 'text' | 'number' | 'bool' | 'select' | 'secret' | 'list' | 'multiselect' | 'map' | 'timezone'

export interface FieldDef {
  key: string
  type: FieldType
  options?: (string | number)[]
  min?: number
  max?: number
  ltr?: boolean
}

const n = (key: string, min: number, max: number): FieldDef => ({ key, type: 'number', min, max })
const b = (key: string): FieldDef => ({ key, type: 'bool' })
const s = (key: string, ltr = false): FieldDef => ({ key, type: 'text', ltr })

/** The editable groups of System Settings and how each value is edited. */
export const settingsSchema: Record<string, FieldDef[]> = {
  formats: [
    { key: 'timezone', type: 'timezone' },
    s('date_format', true),
    { key: 'time_format', type: 'select', options: ['24h', '12h'] },
    { key: 'first_day_of_week', type: 'select', options: [0, 1, 2, 3, 4, 5, 6] },
    { key: 'digits', type: 'select', options: ['locale', 'western', 'arabic_indic'] },
    { key: 'decimal_separator', type: 'select', options: ['.', ','] },
    { key: 'thousands_separator', type: 'select', options: [',', '.', ' ', "'", ''] },
  ],
  calendar: [{ key: 'system', type: 'select', options: ['gregorian', 'hijri', 'both'] }],
  mail: [
    s('host', true),
    n('port', 1, 65535),
    { key: 'encryption', type: 'select', options: ['tls', 'ssl', 'none'] },
    s('username', true),
    { key: 'password', type: 'secret' },
    s('from_address', true),
    s('from_name'),
  ],
  files: [
    n('max_upload_mb', 1, 512),
    { key: 'allowed_image_types', type: 'multiselect', options: ['png', 'jpg', 'jpeg', 'webp', 'ico'] },
    { key: 'allowed_file_types', type: 'multiselect', options: ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'odt', 'ods'] },
  ],
  schema: [n('snapshot_retention_days', 1, 3650), n('blocking_confirmation_rows', 0, 1000000000), b('reconcile_daily')],
  records: [n('export_max_rows', 100, 1000000), n('import_max_rows', 10, 50000)],
  clamav: [b('enabled'), s('host', true), n('port', 1, 65535), n('timeout_seconds', 1, 300)],
  security: [
    n('password_min_length', 8, 128),
    b('password_require_uppercase'),
    b('password_require_lowercase'),
    b('password_require_digit'),
    b('password_require_symbol'),
    n('password_history', 0, 24),
    n('password_expiry_days', 0, 730),
    n('lockout_max_attempts', 3, 20),
    n('lockout_minutes', 1, 1440),
    n('session_idle_minutes', 5, 1440),
    n('session_absolute_minutes', 15, 10080),
    n('api_rate_limit_per_minute', 30, 10000),
  ],
  ldap: [
    b('enabled'),
    { key: 'hosts', type: 'list', ltr: true },
    n('port', 1, 65535),
    s('base_dn', true),
    s('bind_username', true),
    { key: 'bind_password', type: 'secret' },
    b('use_ssl'),
    b('use_tls'),
    n('timeout_seconds', 1, 60),
    s('login_attribute', true),
    s('email_attribute', true),
    s('name_attribute', true),
    { key: 'group_role_map', type: 'map', ltr: true },
    b('jit_provisioning'),
  ],
  monitoring: [
    { key: 'alert_role_keys', type: 'list', ltr: true },
    n('alert_cooldown_minutes', 1, 10080),
    { key: 'alert_min_severity', type: 'select', options: ['warning', 'error', 'critical', 'alert', 'emergency'] },
  ],
}
