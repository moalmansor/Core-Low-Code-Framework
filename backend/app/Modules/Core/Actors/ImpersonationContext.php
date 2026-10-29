<?php

declare(strict_types=1);

namespace App\Modules\Core\Actors;

/**
 * The impersonation a request runs under (specification §4.36, architecture
 * §7.7, ADR-0061): who is really acting and as whom. The audit trail
 * records the administrator as the actor and the impersonated user as the
 * subject. Scoped to one request.
 */
final class ImpersonationContext
{
    public ?int $sessionId = null;

    public ?int $impersonatorId = null;

    public ?int $subjectId = null;

    public function active(): bool
    {
        return $this->sessionId !== null;
    }
}
