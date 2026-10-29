<?php

declare(strict_types=1);

/*
 * Performance harness (architecture §20, docs/performance.md).
 *
 *   php tests/Performance/bench.php dataset --admin=EMAIL [--scale=1] --out=FILE
 *   php tests/Performance/bench.php measure --targets=FILE --out=FILE
 *
 * "dataset" builds the reference dataset on an empty installation and writes
 * the targets the load test opens; "measure" times the budgets that are not
 * single HTTP requests (permission resolution, formulas, import, download,
 * additive publish) and writes their results. Imports, downloads and
 * publishing are queued as from the screens and run by the stack's workers;
 * each is timed from its request until the job reports it finished.
 *
 * It writes millions of rows, so it runs only when LCF_PERFORMANCE=1 is set
 * in the environment, never by accident against a real installation.
 */

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Tests\Performance\Client;
use Tests\Performance\Dataset;
use Tests\Performance\Measurements;

require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/Client.php';
require_once __DIR__.'/BulkRows.php';
require_once __DIR__.'/Dataset.php';
require_once __DIR__.'/Measurements.php';

if (getenv('LCF_PERFORMANCE') !== '1') {
    fwrite(STDERR, "Refusing to run: set LCF_PERFORMANCE=1 to build or measure the performance dataset.\n");
    exit(2);
}

$command = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    }
}

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
ini_set('memory_limit', '2G');

$log = static function (string $line): void {
    fwrite(STDERR, '['.date('H:i:s').'] '.$line.PHP_EOL);
};

switch ($command) {
    case 'dataset':
        // Building the dataset publishes forms in this process; the measurements use the real queue.
        config(['queue.default' => 'sync']);
        $admin = User::query()->withoutGlobalScopes()->where('email', $options['admin'] ?? '')->firstOrFail();
        $targets = (new Dataset(new Client($app, $admin), $admin, (float) ($options['scale'] ?? '1'), $log))->build();
        $targets['admin'] = $admin->email;
        file_put_contents($options['out'] ?? 'perf-targets.json', json_encode($targets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        break;
    case 'measure':
        $targets = json_decode((string) file_get_contents($options['targets'] ?? 'perf-targets.json'), true, flags: JSON_THROW_ON_ERROR);
        $admin = User::query()->withoutGlobalScopes()->where('email', $targets['admin'])->firstOrFail();
        $results = (new Measurements(new Client($app, $admin), $targets, $log))->run();
        file_put_contents($options['out'] ?? 'perf-measure.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        break;
    default:
        fwrite(STDERR, "Usage: bench.php dataset|measure [options]\n");
        exit(2);
}
