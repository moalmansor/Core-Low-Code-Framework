<?php

use App\Infrastructure\Database\DatabaseServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Documents\DocumentsServiceProvider;
use App\Modules\Downloads\DownloadsServiceProvider;
use App\Modules\Forms\FormsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\ModulesServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
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
    NotificationsServiceProvider::class,
    ModulesServiceProvider::class,
];
