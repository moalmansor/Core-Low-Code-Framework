<?php

return [
    App\Infrastructure\Database\DatabaseServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Modules\Core\CoreServiceProvider::class,
    App\Modules\Identity\IdentityServiceProvider::class,
    App\Modules\ModulesServiceProvider::class,
];
