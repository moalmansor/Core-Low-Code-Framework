<?php

use App\Infrastructure\Database\DatabaseServiceProvider;
use App\Modules\Automation\AutomationServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\DataQuality\DataQualityServiceProvider;
use App\Modules\Documents\DocumentsServiceProvider;
use App\Modules\Downloads\DownloadsServiceProvider;
use App\Modules\Experience\ExperienceServiceProvider;
use App\Modules\Extensions\ExtensionsServiceProvider;
use App\Modules\Forms\FormsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\ModulesServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Reports\ReportsServiceProvider;
use App\Modules\Scheduling\SchedulingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    DatabaseServiceProvider::class,
    AppServiceProvider::class,
    CoreServiceProvider::class,
    IdentityServiceProvider::class,
    FormsServiceProvider::class,
    SchedulingServiceProvider::class,
    DocumentsServiceProvider::class,
    DownloadsServiceProvider::class,
    AutomationServiceProvider::class,
    DataQualityServiceProvider::class,
    NotificationsServiceProvider::class,
    PlatformServiceProvider::class,
    ExtensionsServiceProvider::class,
    ReportsServiceProvider::class,
    ExperienceServiceProvider::class,
    ModulesServiceProvider::class,
];
