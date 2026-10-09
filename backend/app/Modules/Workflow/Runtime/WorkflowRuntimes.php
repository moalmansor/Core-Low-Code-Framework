<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Runtime;

/** Per-request (scoped) cache of workflow runtimes. */
final class WorkflowRuntimes
{
    /** @var array<string, WorkflowRuntime> */
    public array $memo = [];
}
