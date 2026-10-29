<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Integrations\ExternalSources;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Reads a stale-while-revalidate source again in the background (ADR-0057). */
final class RefreshExternalSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /** @param  array<string, mixed>  $filters */
    public function __construct(public readonly int $sourceId, public readonly string $cacheKey, public readonly array $filters) {}

    public function handle(ExternalSources $sources, TenantContext $tenant): void
    {
        $org = DB::table('external_data_sources')->where('id', $this->sourceId)->value('organization_id');
        if ($org === null) {
            return;
        }
        $tenant->set((int) $org);
        $sources->refresh($this->sourceId, $this->cacheKey, $this->filters);
    }
}
