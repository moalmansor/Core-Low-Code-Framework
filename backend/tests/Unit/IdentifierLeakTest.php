<?php

declare(strict_types=1);

/*
 * Internal identifiers never reach the interface (design system §5.6), and
 * that includes text the server writes: workflow warnings once named statuses
 * by key although the browser-side guard (frontend/src/i18n/identifiers.spec.ts)
 * was in place, because that guard only reads the browser code. This one reads
 * the server: messages and the names, labels and titles it sends for display.
 */

/** @return list<string> */
function identifierLeakSources(): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            $out[] = $file->getPathname();
        }
    }
    sort($out);

    return $out;
}

// An identifier read from a row or an array: $x->key, $x['uuid'], (string) $r->uuid, $id.
const IDENTIFIER = '(\(string\)\s*)?\$[\w>\[\]\'-]*?(->|\[\')(key|uuid|code)\b';

/** Messages that are about the identifier itself, with their reason. */
const ALLOWED_MESSAGES = [
    'workflow.duplicate_key' => 'tells the administrator which key, as typed in the Key input, is taken',
    'workflow.key_of_removed_status' => 'the same, for a key that belonged to a removed status',
    'workflow.key_of_removed_transition' => 'the same, for a key that belonged to a removed transition',
    'records.import.record_not_found' => 'quotes back the ID the user put in the import file',
];

/** Files that show keys on purpose, with their reason. */
const ALLOWED_FILES = [
    'BlueprintService.php' => 'the propagation preview lists each element by its key as a code (shown as an LTR value), like the version diff',
];

it('never puts an identifier in a server message', function () {
    $found = [];
    foreach (glob(dirname(__DIR__, 2).'/lang/en/*.php') as $file) {
        $group = basename($file, '.php');
        $messages = require $file;
        $flat = static function (array $a, string $prefix) use (&$flat, &$found): void {
            foreach ($a as $k => $v) {
                $key = "{$prefix}.{$k}";
                if (is_array($v)) {
                    $flat($v, $key);
                } elseif (preg_match('/:(key|uuid|id)\b/', (string) $v) === 1 && ! isset(ALLOWED_MESSAGES[$key])) {
                    $found[] = "{$key}: {$v}";
                }
            }
        };
        $flat($messages, $group);
    }
    expect($found)->toBe([]);
});

it('never hands an identifier to a message as its name', function () {
    $found = [];
    foreach (identifierLeakSources() as $file) {
        foreach (file($file) as $i => $line) {
            // __('…', ['name' => $s['key']]): any parameter filled from an identifier, unless the message is about it.
            if (preg_match("/__\\(\\s*'([\\w.]+)'\\s*,\\s*\\[[^\\]]*=>\\s*".IDENTIFIER.'/', $line, $m) === 1 && ! isset(ALLOWED_MESSAGES[$m[1]])) {
                $found[] = basename($file).':'.($i + 1).' '.trim($line);
            }
        }
    }
    expect($found)->toBe([]);
});

it('never shows an identifier where a name belongs', function () {
    $found = [];
    foreach (identifierLeakSources() as $file) {
        foreach (file($file) as $i => $raw) {
            // What humanize() is given is turned into words, so it does not count.
            $line = (string) preg_replace('/humanize\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', 'humanize()', $raw);
            // A name, label or title that falls back to a key or uuid instead of humanize() or an "untitled" text
            // (up to the next array entry, so a code shown on purpose beside the name is not counted).
            if (! isset(ALLOWED_FILES[basename($file)]) && preg_match("/('(name|label|title|form_name)'\\s*=>|\\\$(name|label|title)\\s*=)((?!'\\w+'\\s*=>).)*\\?\\?\\s*\\(?".IDENTIFIER.'/', $line) === 1) {
                $found[] = basename($file).':'.($i + 1).' '.trim($line);
            }
        }
    }
    expect($found)->toBe([]);
});
