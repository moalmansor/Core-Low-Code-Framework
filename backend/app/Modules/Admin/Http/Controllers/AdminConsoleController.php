<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Infrastructure\Storage\VirusScanner;
use App\Modules\Access\AccessResolver;
use App\Modules\Admin\ConsoleAreas;
use App\Modules\Core\Mail\MailConfigurator;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Throwable;

/** The Admin Console (specification §4.1) and its system-health card. */
final class AdminConsoleController extends Controller
{
    public function index(Request $request, AccessResolver $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $areas = [];
        foreach (ConsoleAreas::BUILT as $key => [$section, $permissions, $route]) {
            foreach ($permissions as $permission) {
                if ($access->allows($user, $permission)) {
                    $areas[] = ['key' => $key, 'section' => $section, 'route' => $route];
                    break;
                }
            }
        }

        return response()->json(['data' => ['areas' => $areas]]);
    }

    public function health(MailConfigurator $mail, VirusScanner $scanner): JsonResponse
    {
        if (! Gate::allows('system.view_errors') && ! Gate::allows('system.manage_operations')) {
            abort(403);
        }
        $checks = [];
        $checks['database'] = $this->probe(static fn (): bool => DB::select('select 1 as ok') !== []);
        $checks['cache'] = $this->probe(static function (): bool {
            Cache::put('health:probe', 1, 10);

            return Cache::get('health:probe') === 1;
        });
        $checks['queue'] = $this->probe(static fn (): bool => Queue::size() >= 0);
        $checks['mail'] = $mail->apply() ? 'ok' : 'not_configured';
        $checks['virus_scanner'] = ! $scanner->enabled() ? 'disabled' : $this->probe(static fn (): bool => $scanner->ping());
        $chains = DB::table('audit_chain_heads')->selectRaw('min(last_verified_at) as oldest')->first();

        return response()->json(['data' => [
            'checks' => $checks,
            'audit_chain_verified_at' => $chains?->oldest,
            'open_error_groups' => DB::table('error_groups')->whereIn('status', ['new', 'in_progress'])->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'pending_outbox_events' => DB::table('outbox_events')->whereNull('dispatched_at')->count(),
            'php_version' => PHP_VERSION,
            'database_driver' => DB::connection()->getDriverName(),
            'app_version' => (string) config('app.version'),
        ]]);
    }

    private function probe(callable $check): string
    {
        try {
            return $check() ? 'ok' : 'failing';
        } catch (Throwable) {
            return 'failing';
        }
    }
}
