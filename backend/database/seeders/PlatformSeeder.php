<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/** The platform organization and the two default locales (ADR-0007). */
final class PlatformSeeder extends Seeder
{
    public function run(TenantContext $tenant, Translator $translator): void
    {
        $en = Locale::query()->firstOrCreate(['code' => 'en'], [
            'native_name' => 'English', 'direction' => 'ltr', 'calendar' => 'gregorian', 'digits' => 'western',
            'date_format' => 'yyyy-MM-dd', 'time_format' => '24h', 'number_format' => ['decimal' => '.', 'group' => ',', 'grouping' => 3],
            'first_day_of_week' => 0, 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Locale::query()->firstOrCreate(['code' => 'ar'], [
            'native_name' => 'العربية', 'direction' => 'rtl', 'calendar' => 'gregorian', 'digits' => 'western',
            'date_format' => 'dd/MM/yyyy', 'time_format' => '12h', 'number_format' => ['decimal' => '.', 'group' => ',', 'grouping' => 3],
            'first_day_of_week' => 0, 'fallback_locale_id' => $en->id, 'is_enabled' => true, 'is_default' => false, 'sort_order' => 2,
        ]);
        $translator->flush();

        $org = Organization::query()->withoutGlobalScopes()->where('is_platform', true)->first();
        if ($org === null) {
            $org = new Organization;
            $org->forceFill(['key' => 'platform', 'is_platform' => true, 'status' => 'active', 'default_locale' => 'en', 'timezone' => 'UTC'])->save();
            $tenant->reset();
            $org->setTranslations('name', ['en' => 'Low-Code Framework', 'ar' => 'إطار العمل منخفض الشيفرة']);
        }
        $tenant->reset();
    }
}
