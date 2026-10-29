<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages\Jobs;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Packages\PackageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Applies an imported configuration package on the schema queue (it publishes forms). */
final class ApplyPackage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $packageId, public readonly int $userId)
    {
        $this->onQueue('schema');
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_schema');
        }
    }

    public function handle(PackageService $packages, TenantContext $tenant, AuditWriter $audit): void
    {
        $row = DB::table('config_packages')->where('id', $this->packageId)->first();
        $user = User::query()->withoutGlobalScopes()->find($this->userId);
        if ($row === null || $user === null || $row->status !== 'applying') {
            return;
        }
        $tenant->set((int) $row->organization_id);
        $resolution = json_decode((string) $row->resolution, true) ?: [];
        try {
            $result = $packages->apply(json_decode((string) $row->manifest, true), (array) ($resolution['choices'] ?? []), (array) ($resolution['options'] ?? []), $user);
        } catch (Throwable $e) {
            report($e);
            $result = ['ok' => false, 'results' => [['outcome' => 'failed', 'notes' => [mb_substr($e->getMessage(), 0, 1000)]]], 'applied' => [], 'keys' => []];
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('config_packages')->where('id', $row->id)->update([
            'status' => $result['ok'] ? 'applied' : 'failed', 'updated_at' => $now, 'applied_at' => $result['ok'] ? $now : null, 'applied_by' => $result['ok'] ? $user->id : null,
            'resolution' => json_encode($resolution + ['results' => $result['results'], 'applied' => (object) $result['applied'], 'keys' => (object) $result['keys']], JSON_UNESCAPED_UNICODE),
        ]);
        $audit->record($result['ok'] ? 'package.applied' : 'package.failed', 'config', null, 'config_package', (int) $row->id,
            ['name' => $row->name, 'objects' => count($result['applied'])], $user->id);
    }
}
