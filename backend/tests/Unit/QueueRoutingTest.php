<?php

declare(strict_types=1);

/*
 * The heavy Horizon supervisor consumes the `heavy` queue on its own
 * connection, `redis_heavy` (config/horizon.php). A job that names the queue
 * but stays on the default connection is never run by any worker: the
 * retention policy run and the personal-data search sat queued that way on
 * the Docker stack. Every job on the heavy queue must also take the
 * connection.
 */
it('sends every job on the heavy queue to the heavy workers\' connection', function () {
    $missing = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (str_contains($code, "onQueue('heavy')") && ! str_contains($code, "onConnection('redis_heavy')")) {
            $missing[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());
        }
    }
    expect($missing)->toBe([]);
});
