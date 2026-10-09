<?php

use App\Infrastructure\Database\DatabaseServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Forms\FormsServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\ModulesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    DatabaseServiceProvider::class,
    AppServiceProvider::class,
    CoreServiceProvider::class,
    IdentityServiceProvider::class,
    FormsServiceProvider::class,
    ModulesServiceProvider::class,
];
