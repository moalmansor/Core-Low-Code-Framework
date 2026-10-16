<?php

declare(strict_types=1);

namespace App\Modules\Extensions;

use App\Modules\Extensions\Models\Extension;
use App\Modules\Extensions\Models\ExtensionVersion;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Extension files on disk (architecture §19.13, ADR-0051):
 * `{path}/{organization}/{key}/v{n}/` holds the source as written
 * (`source.php` or `source.ts`), what runs (`build.php` or `build.js`) and
 * `manifest.json` — plain files a team can keep in Git. The checksum of the
 * build is kept with the version; a build whose file no longer matches is
 * never loaded.
 *
 * - Server code is checked by CodeGuard, then wrapped in a namespace of its
 *   own version (so versions never collide) with the SDK imported and
 *   `declare(ticks=1)` for the time limit.
 * - Client code is TypeScript compiled by esbuild into one script that may
 *   import nothing but "vue" (the frame provides Vue's runtime build); it
 *   runs only inside a sandboxed frame.
 */
final class ExtensionStore
{
    /** Lines the framework puts before server code; error lines are given in the author's numbering. */
    public const HEADER_LINES = 6;

    public function __construct(private readonly CodeGuard $guard) {}

    public static function root(): string
    {
        return rtrim((string) config('services.extensions.path'), '/');
    }

    /** The fully qualified class a server version defines. */
    public static function className(ExtensionVersion $v, string $type): string
    {
        return self::namespace($v).'\\'.($type === 'api_endpoint' ? 'Endpoint' : 'Hook');
    }

    /** Each version has a namespace of its own (by its uuid), so no two versions ever share a class. */
    public static function namespace(ExtensionVersion $v): string
    {
        return 'Extensions\\V'.str_replace('-', '', strtolower((string) $v->uuid));
    }

    /**
     * Writes a version's files and returns the checksum of its build.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function write(Extension $ext, ExtensionVersion $v, string $source, array $manifest): string
    {
        $dir = $this->dir($ext, $v->version);
        if (! is_dir($dir) && ! mkdir($dir, 0o750, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}");
        }
        if ($ext->isServer()) {
            $build = $this->buildPhp($ext, $v, $source);
            file_put_contents($dir.'/source.php', $source);
            file_put_contents($dir.'/build.php', $build);
        } else {
            $build = $this->buildTs($source);
            file_put_contents($dir.'/source.ts', $source);
            file_put_contents($dir.'/build.js', $build);
        }
        file_put_contents($dir.'/manifest.json', json_encode($manifest + ['key' => $ext->key, 'type' => $ext->type, 'version' => $v->version], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $v->source_path = $this->relative($dir);

        return hash('sha256', $build);
    }

    public function source(ExtensionVersion $v, Extension $ext): string
    {
        $file = self::root().'/'.$v->source_path.'/source.'.($ext->isServer() ? 'php' : 'ts');

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /** The build, after checking it is exactly what was approved. */
    public function build(ExtensionVersion $v, Extension $ext): string
    {
        $file = self::root().'/'.$v->source_path.'/build.'.($ext->isServer() ? 'php' : 'js');
        $content = is_file($file) ? (string) file_get_contents($file) : '';
        if ($content === '' || ! hash_equals($v->checksum, hash('sha256', $content))) {
            throw new RuntimeException(__('extensions.checksum_mismatch', ['key' => $ext->key, 'version' => $v->version]));
        }

        return $content;
    }

    /** Loads a server version's class once per process, after the checksum check. */
    public function load(ExtensionVersion $v, Extension $ext): string
    {
        $class = self::className($v, $ext->type);
        // Checked on every use: a file changed after approval stops the extension even in a long-running worker.
        $this->build($v, $ext);
        if (! class_exists($class, false)) {
            require_once self::root().'/'.$v->source_path.'/build.php';
        }

        return $class;
    }

    private function buildPhp(Extension $ext, ExtensionVersion $v, string $source): string
    {
        [$required, $contract] = $ext->type === 'api_endpoint' ? ['Endpoint', 'ApiEndpoint'] : ['Hook', 'ServerHook'];
        $problems = $this->guard->check($source, $required, $contract);
        if ($problems !== []) {
            throw ValidationException::withMessages(['source' => array_map(static fn ($p) => __('extensions.guard.line', ['line' => $p['line'], 'message' => $p['message']]), $problems)]);
        }
        $code = ltrim($source);
        if (str_starts_with($code, '<?php')) {
            $code = ltrim(substr($code, 5), "\r\n");
        }
        $build = "<?php declare(strict_types=1);\ndeclare(ticks=1);\nnamespace ".self::namespace($v).";\n"
            .'use App\\Modules\\Extensions\\Sdk\\{ServerHook, ApiEndpoint, HookContext, ApiRequest, HookFailed, Records}; use '.implode(', ', CodeGuard::CLASSES).";\n"
            ."// {$ext->key} v{$v->version}: generated from source.php — edit the source, not this file.\n\n".$code."\n";
        // The syntax check runs in a separate PHP process: nothing of the code is loaded here.
        $tmp = tempnam(sys_get_temp_dir(), 'lcfext');
        file_put_contents((string) $tmp, $build);
        try {
            $lint = Process::timeout(20)->run([PHP_BINARY, '-l', (string) $tmp]);
        } finally {
            @unlink((string) $tmp);
        }
        if (! $lint->successful()) {
            $out = trim($lint->output().' '.$lint->errorOutput());
            $line = preg_match('/on line (\d+)/', $out, $m) === 1 ? max(1, (int) $m[1] - self::HEADER_LINES) : 1;
            $message = trim((string) preg_replace(['/^PHP\s+/m', '/ in \S+ on line \d+/', '/Errors parsing.*$/s'], '', $out));
            throw ValidationException::withMessages(['source' => [__('extensions.guard.line', ['line' => $line, 'message' => $message])]]);
        }

        return $build;
    }

    private function buildTs(string $source): string
    {
        $bin = (string) config('services.extensions.esbuild');
        // A relative path is relative to the application, not to the empty folder esbuild runs in.
        if (str_contains($bin, '/') && ! str_starts_with($bin, '/')) {
            $bin = base_path($bin);
        }
        try {
            // Run from an empty folder so an import can resolve nothing; "vue" is the frame's own copy.
            $empty = sys_get_temp_dir().'/lcf-esbuild-empty';
            if (! is_dir($empty)) {
                @mkdir($empty, 0o700);
            }
            $result = Process::timeout(30)->path($empty)->input($source)
                ->run([$bin, '--bundle', '--loader=ts', '--format=iife', '--target=es2020', '--minify', '--log-level=error', '--charset=utf8', '--external:vue']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['source' => [__('extensions.no_compiler')]]);
        }
        if (! $result->successful()) {
            if (str_contains($result->errorOutput(), 'not found') && $result->output() === '') {
                throw ValidationException::withMessages(['source' => [__('extensions.no_compiler')]]);
            }
            $lines = array_values(array_filter(array_map('trim', explode("\n", $result->errorOutput())), static fn ($l) => str_contains($l, 'ERROR') || str_contains($l, '<stdin>:')));

            throw ValidationException::withMessages(['source' => array_slice($lines ?: [trim($result->errorOutput())], 0, 10)]);
        }

        return $result->output();
    }

    private function dir(Extension $ext, int $version): string
    {
        return self::root().'/'.$ext->organization_id.'/'.$ext->key.'/v'.$version;
    }

    private function relative(string $dir): string
    {
        return ltrim(substr($dir, strlen(self::root())), '/');
    }
}
