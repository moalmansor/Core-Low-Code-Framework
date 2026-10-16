<?php

declare(strict_types=1);

namespace App\Modules\Actions;

/**
 * Who runs a step: an action or an automation (its uuid and label), which
 * the saves, messages and webhook log carry as their source. An automation's
 * test run simulates: data steps run inside a transaction that is rolled
 * back, and the steps that reach outside the database are only described.
 */
final readonly class StepOrigin
{
    /** Steps with effects a rollback cannot undo. */
    public const OUTSIDE = ['send_email', 'send_notification', 'call_webhook', 'generate_document', 'run_download'];

    /** @param  'action'|'automation'  $source */
    public function __construct(
        public string $source,
        public string $uuid,
        public string $label,
        public bool $simulate = false,
    ) {}
}
