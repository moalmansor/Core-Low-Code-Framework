<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessResolver;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;

/**
 * Maintenance mode (specification §4.29): an application taken offline with
 * a message. People who may switch maintenance mode keep their access.
 */
final class Maintenance
{
    public function __construct(private readonly AccessResolver $access) {}

    public function applicationOffline(Application $app): bool
    {
        return $app->maintenance_mode && ($app->maintenance_until === null || Carbon::parse($app->maintenance_until, 'UTC')->isFuture());
    }

    /** Whether the application is offline for this person. */
    public function blocks(Application $app, User $user): bool
    {
        return $this->applicationOffline($app) && ! $this->access->allows($user, 'system.enable_maintenance_mode');
    }
}
