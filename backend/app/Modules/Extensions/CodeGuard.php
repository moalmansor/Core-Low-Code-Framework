<?php

declare(strict_types=1);

namespace App\Modules\Extensions;

use PhpToken;

/**
 * Checks the source of a server extension before it is stored (ADR-0051).
 *
 * The code is plain PHP: one class (`Hook` or `Endpoint`) plus any helper
 * classes, written without a namespace or imports; the framework adds both
 * when it builds the file. This check is a guard rail on top of the real
 * safeguards (the Manage code permission, a second person's approval, the
 * transaction and the time limit): it keeps extensions to computation over
 * the values and services the SDK hands them.
 *
 * - Functions: only those on the allow-list (strings, arrays, maths, dates,
 *   JSON, regular expressions); a function that takes a callback must be
 *   given an inline closure, never a name.
 * - Classes: only the SDK's, a few value classes of PHP, and the
 *   extension's own; no reflection, files, processes, network or database.
 * - Never: eval, include/require, exit, goto, declare, namespace, global,
 *   echo or print,
 *   backticks, superglobals, variable variables, calling a variable, a
 *   string or an expression, `new` on a variable, inline HTML or `?>`.
 */
final class CodeGuard
{
    public const SDK = ['ServerHook', 'ApiEndpoint', 'HookContext', 'ApiRequest', 'HookFailed', 'Records'];

    public const CLASSES = [
        'DateTimeImmutable', 'DateTimeInterface', 'DateInterval', 'DateTimeZone', 'DatePeriod',
        'InvalidArgumentException', 'RuntimeException', 'LogicException', 'DomainException', 'Throwable', 'Exception',
        'ArrayIterator', 'JsonException', 'Stringable', 'stdClass',
    ];

    private const FUNCTIONS = [
        // Strings.
        'strlen', 'mb_strlen', 'substr', 'mb_substr', 'strpos', 'mb_strpos', 'stripos', 'mb_stripos', 'strrpos', 'str_contains', 'str_starts_with', 'str_ends_with',
        'str_replace', 'str_ireplace', 'str_pad', 'mb_str_pad', 'str_repeat', 'str_split', 'mb_str_split', 'str_word_count', 'strrev', 'strtolower', 'strtoupper',
        'mb_strtolower', 'mb_strtoupper', 'ucfirst', 'lcfirst', 'ucwords', 'trim', 'ltrim', 'rtrim', 'mb_trim', 'sprintf', 'vsprintf', 'number_format', 'implode', 'explode',
        'join', 'nl2br', 'htmlspecialchars', 'strip_tags', 'wordwrap', 'similar_text', 'levenshtein', 'soundex', 'metaphone', 'strcmp', 'strcasecmp', 'strncmp',
        'strnatcmp', 'strnatcasecmp', 'substr_count', 'mb_substr_count', 'ctype_digit', 'ctype_alpha', 'ctype_alnum', 'ctype_upper', 'ctype_lower', 'ctype_space',
        'chr', 'ord', 'mb_chr', 'mb_ord', 'bin2hex', 'hex2bin', 'base64_encode', 'base64_decode', 'urlencode', 'rawurlencode', 'urldecode', 'http_build_query',
        'md5', 'sha1', 'hash', 'hash_hmac', 'crc32', 'uniqid', 'iconv', 'mb_convert_case', 'normalizer_normalize', 'transliterator_transliterate',
        'preg_match', 'preg_match_all', 'preg_replace', 'preg_split', 'preg_quote', 'preg_grep', 'preg_replace_callback',
        // Arrays.
        'count', 'sizeof', 'in_array', 'array_keys', 'array_values', 'array_merge', 'array_merge_recursive', 'array_replace', 'array_combine', 'array_flip',
        'array_slice', 'array_splice', 'array_search', 'array_key_exists', 'array_key_first', 'array_key_last', 'array_unique', 'array_reverse', 'array_sum',
        'array_product', 'array_fill', 'array_fill_keys', 'array_pad', 'array_chunk', 'array_column', 'array_diff', 'array_diff_key', 'array_intersect',
        'array_intersect_key', 'array_is_list', 'array_count_values', 'array_push', 'array_pop', 'array_shift', 'array_unshift', 'range', 'compact',
        'sort', 'rsort', 'asort', 'arsort', 'ksort', 'krsort', 'natsort', 'natcasesort', 'shuffle', 'array_rand', 'min', 'max', 'reset', 'end', 'key', 'current',
        'array_map', 'array_filter', 'array_reduce', 'array_walk', 'usort', 'uasort', 'uksort', 'array_find', 'array_any', 'array_all',
        // Numbers.
        'abs', 'ceil', 'floor', 'round', 'intdiv', 'fmod', 'pow', 'sqrt', 'exp', 'log', 'log10', 'pi', 'is_nan', 'is_finite', 'random_int',
        'bcadd', 'bcsub', 'bcmul', 'bcdiv', 'bcmod', 'bccomp', 'bcpow', 'bcsqrt', 'bcscale',
        // Types.
        'is_null', 'is_int', 'is_float', 'is_string', 'is_bool', 'is_array', 'is_numeric', 'is_scalar', 'is_iterable', 'is_countable', 'is_object',
        'intval', 'floatval', 'boolval', 'strval', 'settype', 'gettype', 'get_debug_type',
        // Dates and JSON.
        'date', 'gmdate', 'mktime', 'gmmktime', 'checkdate', 'strtotime', 'time', 'microtime', 'date_create_immutable', 'json_encode', 'json_decode',
    ];

    /** Functions that take a callback, and the position of that argument. */
    private const CALLBACK_ARG = [
        'array_map' => 0, 'array_filter' => 1, 'array_reduce' => 1, 'array_walk' => 1, 'usort' => 1, 'uasort' => 1, 'uksort' => 1,
        'preg_replace_callback' => 1, 'array_find' => 1, 'array_any' => 1, 'array_all' => 1,
    ];

    private const SUPERGLOBALS = ['$GLOBALS', '$_SERVER', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_REQUEST', '$_ENV'];

    private const FORBIDDEN = [
        T_EVAL => 'eval', T_INCLUDE => 'include', T_INCLUDE_ONCE => 'include_once', T_REQUIRE => 'require', T_REQUIRE_ONCE => 'require_once',
        T_EXIT => 'exit', T_GOTO => 'goto', T_HALT_COMPILER => '__halt_compiler', T_DECLARE => 'declare', T_NAMESPACE => 'namespace',
        T_GLOBAL => 'global', T_INLINE_HTML => 'HTML outside PHP', T_CLOSE_TAG => '?>', T_OPEN_TAG_WITH_ECHO => '<?=',
        T_NAME_QUALIFIED => 'a namespaced name', T_NAME_RELATIVE => 'a namespaced name', T_ECHO => 'echo', T_PRINT => 'print',
    ];

    /** Methods that turn a name into something callable. */
    private const FORBIDDEN_METHODS = ['fromcallable', '__invoke', 'call', 'bind', 'bindto'];

    /**
     * Problems with the source, each with its line; empty when it may be stored.
     *
     * @return list<array{line: int, message: string}>
     */
    public function check(string $source, string $requiredClass, string $contract): array
    {
        $problems = [];
        $code = ltrim($source);
        if (str_starts_with($code, '<?php')) {
            $code = substr($code, 5);
        }
        try {
            $tokens = PhpToken::tokenize('<?php '.$code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return [['line' => max(1, $e->getLine()), 'message' => __('extensions.guard.syntax', ['error' => $e->getMessage()])]];
        }
        $sig = array_values(array_filter($tokens, static fn (PhpToken $t) => ! $t->isIgnorable()));
        $own = [];
        foreach ($sig as $i => $t) {
            if ($t->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && ($sig[$i + 1] ?? null)?->is(T_STRING) && ! ($sig[$i - 1] ?? null)?->is([T_DOUBLE_COLON, T_NEW])) {
                $own[] = strtolower($sig[$i + 1]->text);
            }
        }
        if (! in_array(strtolower($requiredClass), $own, true)) {
            $problems[] = ['line' => 1, 'message' => __('extensions.guard.class_missing', ['class' => $requiredClass, 'contract' => $contract])];
        }
        $allowedClasses = array_map('strtolower', [...self::SDK, ...self::CLASSES, ...['self', 'static', 'parent']]);
        $allowedFunctions = array_flip(self::FUNCTIONS);
        $add = static function (PhpToken $t, string $message) use (&$problems): void {
            $problems[] = ['line' => $t->line, 'message' => $message];
        };
        $count = count($sig);
        for ($i = 1; $i < $count; $i++) {
            $t = $sig[$i];
            $prev = $sig[$i - 1];
            $next = $sig[$i + 1] ?? null;
            if (isset(self::FORBIDDEN[$t->id])) {
                $add($t, __('extensions.guard.forbidden', ['what' => self::FORBIDDEN[$t->id]]));

                continue;
            }
            if ($t->text === '`') {
                $add($t, __('extensions.guard.forbidden', ['what' => 'backticks']));
            } elseif ($t->is(T_USE) && ! $prev->is(')')) {
                // "use" is only for closures ("function () use ($x)"); imports are added by the framework.
                $add($t, __('extensions.guard.forbidden', ['what' => 'use']));
            } elseif ($t->is(T_VARIABLE) && in_array($t->text, self::SUPERGLOBALS, true)) {
                $add($t, __('extensions.guard.forbidden', ['what' => $t->text]));
            } elseif ($t->text === '$' || $t->is(T_DOLLAR_OPEN_CURLY_BRACES)) {
                $add($t, __('extensions.guard.forbidden', ['what' => 'variable variables']));
            } elseif ($next !== null && $next->text === '(' && ($t->is([T_VARIABLE, T_CONSTANT_ENCAPSED_STRING]) || in_array($t->text, [')', ']', '}'], true))) {
                $add($t, __('extensions.guard.dynamic_call'));
            } elseif ($t->is(T_VARIABLE) && $next?->is(T_DOUBLE_COLON)) {
                $add($t, __('extensions.guard.dynamic_call'));
            } elseif ($t->is(T_NEW) && $next !== null && ($next->is(T_VARIABLE) || $next->text === '(')) {
                $add($t, __('extensions.guard.dynamic_new'));
            } elseif ($t->is(T_NAME_FULLY_QUALIFIED)) {
                $name = strtolower(ltrim($t->text, '\\'));
                $isCall = $next?->text === '(' && ! $prev->is(T_NEW);
                if ($isCall ? ! isset($allowedFunctions[$name]) : ! in_array($name, $allowedClasses, true)) {
                    $add($t, __($isCall ? 'extensions.guard.function' : 'extensions.guard.class', ['name' => ltrim($t->text, '\\')]));
                }
            } elseif ($t->is(T_STRING)) {
                $this->checkName($sig, $i, $allowedClasses, $own, $allowedFunctions, $add);
            }
        }

        return $problems;
    }

    /**
     * A bare name: a function call, a class use, or something harmless
     * (a method or property name, a constant, a declaration).
     *
     * @param  list<PhpToken>  $sig
     * @param  list<string>  $allowedClasses
     * @param  list<string>  $own
     * @param  array<string, int>  $allowedFunctions
     */
    private function checkName(array $sig, int $i, array $allowedClasses, array $own, array $allowedFunctions, callable $add): void
    {
        $t = $sig[$i];
        $prev = $sig[$i - 1];
        $next = $sig[$i + 1] ?? null;
        $name = strtolower($t->text);
        if ($prev->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]) && in_array($name, self::FORBIDDEN_METHODS, true)) {
            $add($t, __('extensions.guard.forbidden', ['what' => $t->text]));

            return;
        }
        if ($prev->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION, T_CONST, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_CASE, T_GOTO])
            || ($prev->is(T_DOUBLE_COLON)) || $next?->is(T_DOUBLE_ARROW) || ($next?->text === ':' && $prev->text !== '?' && ! $prev->is([T_DOUBLE_COLON]))) {
            return;
        }
        $classContext = $prev->is([T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS]) || $next?->is([T_DOUBLE_COLON, T_VARIABLE, T_ELLIPSIS])
            || ($prev->text === ',' && $this->inImplementsList($sig, $i)) || $prev->text === '|' || $prev->text === '?' && $next?->is(T_VARIABLE);
        if ($classContext) {
            if (! in_array($name, $allowedClasses, true) && ! in_array($name, $own, true) && ! in_array($name, ['array', 'string', 'int', 'float', 'bool', 'mixed', 'null', 'callable', 'iterable', 'object', 'void', 'never', 'false', 'true'], true)) {
                $add($t, __('extensions.guard.class', ['name' => $t->text]));
            }

            return;
        }
        if ($next?->text === '(') {
            if (! isset($allowedFunctions[$name])) {
                $add($t, __('extensions.guard.function', ['name' => $t->text]));
            } elseif (isset(self::CALLBACK_ARG[$name]) && ! $this->closureAt($sig, $i + 1, self::CALLBACK_ARG[$name])) {
                $add($t, __('extensions.guard.callback', ['name' => $t->text]));
            }
        }
    }

    /** @param  list<PhpToken>  $sig */
    private function inImplementsList(array $sig, int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($sig[$j]->is(T_IMPLEMENTS)) {
                return true;
            }
            if (! ($sig[$j]->is(T_STRING) || $sig[$j]->text === ',')) {
                return false;
            }
        }

        return false;
    }

    /**
     * Whether argument $position of the call whose `(` is at $open starts
     * with an inline closure (`fn`, `function` or `static`).
     *
     * @param  list<PhpToken>  $sig
     */
    private function closureAt(array $sig, int $open, int $position): bool
    {
        $depth = 0;
        $arg = 0;
        $start = true;
        for ($j = $open + 1, $n = count($sig); $j < $n; $j++) {
            $t = $sig[$j];
            if ($start && $arg === $position) {
                return $t->is([T_FN, T_FUNCTION, T_STATIC]);
            }
            $start = false;
            if (in_array($t->text, ['(', '[', '{'], true) || $t->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif (in_array($t->text, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    return false;
                }
                $depth--;
            } elseif ($t->text === ',' && $depth === 0) {
                $arg++;
                $start = true;
            }
        }

        return false;
    }
}
