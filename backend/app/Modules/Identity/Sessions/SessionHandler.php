<?php

declare(strict_types=1);

namespace App\Modules\Identity\Sessions;

use App\Modules\Core\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Session\DatabaseSessionHandler;
use Throwable;

/**
 * Database sessions with the extra columns of the `sessions` table
 * (architecture §10.4): guard, creation time, absolute expiry, and the
 * external user signed in to the portal (ADR-0058), so an administrator's
 * decision on an external account can end its sessions. Sessions are
 * listed and revoked through the API (specification §5).
 */
final class SessionHandler extends DatabaseSessionHandler
{
    /** @param array<string, mixed> $payload */
    protected function performInsert($sessionId, $payload)
    {
        $absolute = 720;
        try {
            $absolute = (int) app(SettingsService::class)->get('security', 'session_absolute_minutes');
        } catch (Throwable) {
            // before installation the default applies
        }
        $now = now();
        $payload['guard'] = 'web';
        $payload['created_at'] = $now->format('Y-m-d H:i:s.u');
        $payload['absolute_expires_at'] = $now->copy()->addMinutes($absolute)->format('Y-m-d H:i:s.u');

        return parent::performInsert($sessionId, $payload);
    }

    /** @return array<string, mixed> */
    protected function getDefaultPayload($data)
    {
        $payload = parent::getDefaultPayload($data);
        $request = $this->container?->bound('request') ? $this->container->make('request') : null;
        $id = $request instanceof Request && $request->hasSession() ? $request->session()->get('external_user_id') : null;
        $payload['external_user_id'] = is_int($id) ? $id : null;

        return $payload;
    }
}
