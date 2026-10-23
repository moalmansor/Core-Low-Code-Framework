<?php

declare(strict_types=1);

namespace App\Modules\Core\Actors;

/**
 * The external user a request acts for (specification §4.32, ADR-0058).
 * External forms and the portal set it while a submission runs, so the
 * audit trail, the submission journal and the status history record the
 * external user next to the run-as user. Scoped to one request or job.
 */
final class ExternalActor
{
    public ?int $id = null;

    /**
     * Runs the callback acting for the external user, then restores the previous one.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function as(?int $externalUserId, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $externalUserId;
        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
