<?php

declare(strict_types=1);

namespace App\Modules\Forms\FieldTypes;

use InvalidArgumentException;

/**
 * Every input, display element and group type of specification §4.4, keyed as
 * in architecture §14.4. The registry is the single source for the builder
 * palette, the definition compiler's storage mapping, and the renderer.
 */
final class FieldTypeRegistry
{
    public const GROUP_TYPES = ['section', 'fieldset', 'card', 'tabs', 'tab', 'wizard', 'step', 'row', 'column', 'panel', 'accordion', 'repeater', 'subform'];

    /** Group types that hold data rows of their own (child tables). */
    public const DATA_GROUPS = ['repeater', 'subform'];

    /**
     * Allowed parent group types for group types with structural constraints;
     * unlisted types may sit anywhere (or at the root).
     *
     * @var array<string, list<string|null>>
     */
    public const GROUP_PARENTS = [
        'tab' => ['tabs'],
        'step' => ['wizard'],
        'column' => ['row'],
    ];

    /** Children that structural containers accept exclusively. */
    public const EXCLUSIVE_CHILDREN = [
        'tabs' => 'tab',
        'wizard' => 'step',
        'row' => 'column',
    ];

    private const TEXT_RULES = ['required', 'length', 'pattern', 'format', 'unique', 'compare', 'async', 'custom'];

    private const NUMBER_RULES = ['required', 'number', 'unique', 'compare', 'custom'];

    private const DATE_RULES = ['required', 'date', 'unique', 'compare', 'custom'];

    private const CHOICE_RULES = ['required', 'unique', 'custom'];

    private const FILE_RULES = ['required', 'file', 'custom'];

    /** @var array<string, FieldType>|null */
    private static ?array $types = null;

    /** @return array<string, FieldType> */
    public static function all(): array
    {
        return self::$types ??= self::build();
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function get(string $key): FieldType
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown field type [{$key}].");
    }

    public static function isGroupType(string $key): bool
    {
        return in_array($key, self::GROUP_TYPES, true);
    }

    /** @return array<string, FieldType> */
    private static function build(): array
    {
        $t = static fn (string $key, string $category, string $storage, string $valueType, array $validation = [], array $extra = []): FieldType => new FieldType(
            key: $key,
            category: $category,
            storage: $storage,
            valueType: $valueType,
            validation: $validation,
            options: $extra['options'] ?? false,
            multiple: $extra['multiple'] ?? false,
            filter: $extra['filter'] ?? 'none',
            native: $extra['native'] ?? false,
            icon: $extra['icon'] ?? 'pi pi-pencil',
            defaultLength: $extra['length'] ?? 255,
            defaultPrecision: $extra['precision'] ?? null,
            defaultScale: $extra['scale'] ?? null,
            calculated: $extra['calculated'] ?? false,
        );
        $native = ['native' => true];
        $types = [
            // Native HTML input types (§4.4)
            $t('text', 'text', 'string', 'text', self::TEXT_RULES, $native + ['filter' => 'text', 'icon' => 'pi pi-pencil']),
            $t('password', 'text', 'string', 'text', ['required', 'length', 'pattern', 'custom'], $native + ['icon' => 'pi pi-lock']),
            $t('email', 'text', 'string', 'text', self::TEXT_RULES, $native + ['filter' => 'text', 'icon' => 'pi pi-envelope']),
            $t('tel', 'text', 'string', 'text', self::TEXT_RULES, $native + ['filter' => 'text', 'icon' => 'pi pi-phone', 'length' => 32]),
            $t('url', 'text', 'string', 'text', self::TEXT_RULES, $native + ['filter' => 'text', 'icon' => 'pi pi-link', 'length' => 2048]),
            $t('search', 'text', 'string', 'text', self::TEXT_RULES, $native + ['filter' => 'text', 'icon' => 'pi pi-search']),
            $t('number', 'number', 'decimal', 'number', self::NUMBER_RULES, $native + ['filter' => 'number', 'icon' => 'pi pi-hashtag', 'precision' => 19, 'scale' => 4]),
            $t('range', 'number', 'decimal', 'number', self::NUMBER_RULES, $native + ['filter' => 'number', 'icon' => 'pi pi-sliders-h', 'precision' => 19, 'scale' => 4]),
            $t('date', 'datetime', 'date', 'date', self::DATE_RULES, $native + ['filter' => 'date', 'icon' => 'pi pi-calendar']),
            $t('time', 'datetime', 'time', 'time', ['required', 'compare', 'custom'], $native + ['filter' => 'time', 'icon' => 'pi pi-clock']),
            $t('datetime_local', 'datetime', 'datetime', 'datetime', self::DATE_RULES, $native + ['filter' => 'datetime', 'icon' => 'pi pi-calendar-clock']),
            $t('month', 'datetime', 'date', 'date', self::DATE_RULES, $native + ['filter' => 'date', 'icon' => 'pi pi-calendar']),
            $t('week', 'datetime', 'date', 'date', self::DATE_RULES, $native + ['filter' => 'date', 'icon' => 'pi pi-calendar']),
            $t('checkbox', 'choice', 'bool', 'boolean', ['required', 'custom'], $native + ['filter' => 'boolean', 'icon' => 'pi pi-check-square']),
            $t('radio', 'choice', 'choice', 'text', self::CHOICE_RULES, $native + ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-circle-on']),
            $t('color', 'text', 'string', 'text', ['required', 'custom'], $native + ['filter' => 'text', 'icon' => 'pi pi-palette', 'length' => 16]),
            $t('file', 'file', 'file', 'list', self::FILE_RULES, $native + ['icon' => 'pi pi-paperclip']),
            $t('hidden', 'text', 'string', 'text', ['custom'], $native + ['filter' => 'text', 'icon' => 'pi pi-eye-slash']),
            $t('image_button', 'action', 'none', 'null', [], $native + ['icon' => 'pi pi-image']),
            $t('button', 'action', 'none', 'null', [], $native + ['icon' => 'pi pi-stop']),
            $t('submit', 'action', 'none', 'null', [], $native + ['icon' => 'pi pi-send']),
            $t('reset', 'action', 'none', 'null', [], $native + ['icon' => 'pi pi-refresh']),
            // Native form elements
            $t('textarea', 'text', 'text', 'text', ['required', 'length', 'pattern', 'format', 'custom'], $native + ['filter' => 'text', 'icon' => 'pi pi-align-left']),
            $t('select', 'choice', 'choice', 'text', self::CHOICE_RULES, $native + ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-chevron-down']),
            $t('select_multiple', 'choice', 'multi_choice', 'list', self::CHOICE_RULES, $native + ['options' => true, 'multiple' => true, 'filter' => 'choice', 'icon' => 'pi pi-list']),
            $t('select_grouped', 'choice', 'choice', 'text', self::CHOICE_RULES, $native + ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-sitemap']),
            $t('datalist', 'choice', 'string', 'text', self::TEXT_RULES, $native + ['options' => true, 'filter' => 'text', 'icon' => 'pi pi-bars']),
            $t('output', 'display', 'none', 'null', [], $native + ['icon' => 'pi pi-calculator', 'calculated' => true]),
            $t('progress', 'display', 'none', 'null', [], $native + ['icon' => 'pi pi-spinner', 'calculated' => true]),
            $t('meter', 'display', 'none', 'null', [], $native + ['icon' => 'pi pi-gauge', 'calculated' => true]),
            // Extended — text and rich content
            $t('rich_text', 'text', 'longtext', 'text', ['required', 'length', 'custom'], ['filter' => 'text', 'icon' => 'pi pi-file-edit']),
            $t('markdown', 'text', 'longtext', 'text', ['required', 'length', 'custom'], ['filter' => 'text', 'icon' => 'pi pi-hashtag']),
            $t('code', 'text', 'longtext', 'text', ['required', 'length', 'custom'], ['icon' => 'pi pi-code']),
            // Numbers and money
            $t('currency', 'number', 'currency', 'number', self::NUMBER_RULES, ['filter' => 'number', 'icon' => 'pi pi-money-bill', 'precision' => 19, 'scale' => 4]),
            $t('percentage', 'number', 'decimal', 'number', self::NUMBER_RULES, ['filter' => 'number', 'icon' => 'pi pi-percentage', 'precision' => 9, 'scale' => 4]),
            $t('decimal', 'number', 'decimal', 'number', self::NUMBER_RULES, ['filter' => 'number', 'icon' => 'pi pi-hashtag', 'precision' => 19, 'scale' => 4]),
            $t('auto_number', 'number', 'auto_number', 'text', [], ['filter' => 'text', 'icon' => 'pi pi-sort-numeric-up', 'length' => 64]),
            $t('formula', 'number', 'formula', 'any', [], ['filter' => 'number', 'icon' => 'pi pi-calculator', 'calculated' => true, 'precision' => 19, 'scale' => 4]),
            // Dates and calendars
            $t('calendar_date', 'datetime', 'date', 'date', self::DATE_RULES, ['filter' => 'date', 'icon' => 'pi pi-calendar-plus']),
            $t('date_range', 'datetime', 'range_date', 'date', ['required', 'date', 'custom'], ['filter' => 'date', 'icon' => 'pi pi-calendar']),
            $t('time_range', 'datetime', 'range_time', 'time', ['required', 'custom'], ['filter' => 'time', 'icon' => 'pi pi-clock']),
            $t('datetime_range', 'datetime', 'range_datetime', 'datetime', ['required', 'date', 'custom'], ['filter' => 'datetime', 'icon' => 'pi pi-calendar-clock']),
            $t('duration', 'datetime', 'duration', 'duration', ['required', 'number', 'custom'], ['filter' => 'number', 'icon' => 'pi pi-stopwatch']),
            // Choices and pickers
            $t('toggle', 'choice', 'bool', 'boolean', ['required', 'custom'], ['filter' => 'boolean', 'icon' => 'pi pi-power-off']),
            $t('checkbox_group', 'choice', 'multi_choice', 'list', self::CHOICE_RULES, ['options' => true, 'multiple' => true, 'filter' => 'choice', 'icon' => 'pi pi-check-square']),
            $t('radio_group', 'choice', 'choice', 'text', self::CHOICE_RULES, ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-circle-on']),
            $t('button_group', 'choice', 'choice', 'text', self::CHOICE_RULES, ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-th-large']),
            $t('dropdown_search', 'choice', 'choice', 'text', self::CHOICE_RULES, ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-search']),
            $t('multi_select_chips', 'choice', 'multi_choice', 'list', self::CHOICE_RULES, ['options' => true, 'multiple' => true, 'filter' => 'choice', 'icon' => 'pi pi-tags']),
            $t('tags', 'choice', 'multi_choice', 'list', self::CHOICE_RULES, ['options' => true, 'multiple' => true, 'filter' => 'choice', 'icon' => 'pi pi-tag']),
            $t('cascading_select', 'choice', 'choice', 'text', self::CHOICE_RULES, ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-sitemap']),
            $t('lookup', 'reference', 'lookup', 'record', ['required', 'unique', 'async', 'custom'], ['filter' => 'lookup', 'icon' => 'pi pi-link']),
            $t('user_picker', 'reference', 'user', 'record', ['required', 'unique', 'custom'], ['filter' => 'lookup', 'icon' => 'pi pi-user']),
            $t('role_picker', 'reference', 'role', 'record', ['required', 'custom'], ['filter' => 'lookup', 'icon' => 'pi pi-users']),
            $t('department_picker', 'reference', 'department', 'record', ['required', 'custom'], ['filter' => 'lookup', 'icon' => 'pi pi-building']),
            $t('country_picker', 'reference', 'lookup', 'record', ['required', 'custom'], ['filter' => 'lookup', 'icon' => 'pi pi-globe']),
            $t('city_picker', 'reference', 'lookup', 'record', ['required', 'custom'], ['filter' => 'lookup', 'icon' => 'pi pi-map-marker']),
            $t('rating', 'number', 'int', 'number', ['required', 'number', 'custom'], ['filter' => 'number', 'icon' => 'pi pi-star']),
            $t('slider', 'number', 'decimal', 'number', ['required', 'number', 'custom'], ['filter' => 'number', 'icon' => 'pi pi-sliders-h', 'precision' => 19, 'scale' => 4]),
            $t('color_palette', 'choice', 'string', 'text', ['required', 'custom'], ['options' => true, 'filter' => 'choice', 'icon' => 'pi pi-palette', 'length' => 16]),
            // Files and media
            $t('file_multi', 'file', 'files', 'list', self::FILE_RULES, ['multiple' => true, 'icon' => 'pi pi-upload']),
            $t('image_upload', 'file', 'file', 'list', self::FILE_RULES, ['icon' => 'pi pi-image']),
            $t('camera', 'file', 'file', 'list', self::FILE_RULES, ['icon' => 'pi pi-camera']),
            // Special inputs
            $t('signature', 'special', 'file', 'list', ['required', 'custom'], ['icon' => 'pi pi-pencil']),
            $t('map_location', 'special', 'map', 'text', ['required', 'custom'], ['icon' => 'pi pi-map']),
            $t('phone_intl', 'special', 'phone', 'text', ['required', 'unique', 'format', 'custom'], ['filter' => 'text', 'icon' => 'pi pi-phone', 'length' => 20]),
            $t('national_id', 'special', 'string', 'text', ['required', 'length', 'pattern', 'format', 'unique', 'async', 'custom'], ['filter' => 'text', 'icon' => 'pi pi-id-card', 'length' => 32]),
            $t('iban', 'special', 'string', 'text', ['required', 'format', 'unique', 'custom'], ['filter' => 'text', 'icon' => 'pi pi-wallet', 'length' => 34]),
            $t('barcode', 'special', 'string', 'text', ['required', 'length', 'pattern', 'unique', 'async', 'custom'], ['filter' => 'text', 'icon' => 'pi pi-qrcode']),
            $t('json', 'special', 'json', 'text', ['required', 'custom'], ['icon' => 'pi pi-code']),
            $t('key_value', 'special', 'json', 'text', ['required', 'custom'], ['icon' => 'pi pi-list']),
            $t('consent', 'special', 'consent', 'boolean', ['required', 'custom'], ['filter' => 'boolean', 'icon' => 'pi pi-verified']),
            // Display elements
            $t('static_html', 'display', 'none', 'null', [], ['icon' => 'pi pi-code']),
            $t('heading', 'display', 'none', 'null', [], ['icon' => 'pi pi-bars']),
            $t('divider', 'display', 'none', 'null', [], ['icon' => 'pi pi-minus']),
            $t('spacer', 'display', 'none', 'null', [], ['icon' => 'pi pi-arrows-v']),
            $t('display_image', 'display', 'none', 'null', [], ['icon' => 'pi pi-image']),
            $t('alert_box', 'display', 'none', 'null', [], ['icon' => 'pi pi-info-circle']),
            $t('link', 'display', 'none', 'null', [], ['icon' => 'pi pi-external-link']),
        ];
        $out = [];
        foreach ($types as $type) {
            $out[$type->key] = $type;
        }

        return $out;
    }
}
