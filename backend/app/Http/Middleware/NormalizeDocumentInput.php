<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input normalisation for requests that carry metadata documents and
 * expressions (ADR-0029). Laravel's global TrimStrings and
 * ConvertEmptyStringsToNull are skipped for them, because inside an expression
 * an empty or space-padded text literal is a value (`status = ""`), not a
 * missing one. Everywhere else in the payload the usual rules apply: strings
 * are trimmed and empty strings become null. Expression nodes are recognised by
 * their `k` member, which only AST nodes carry.
 */
final class NormalizeDocumentInput
{
    public static function applies(Request $request): bool
    {
        return $request->is('api/v1/forms/*/draft', 'api/v1/forms/*/draft/validate', 'api/v1/expressions/*', 'api/v1/field-templates', 'api/v1/field-templates/*');
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::applies($request) && $request->isJson()) {
            $request->json()->replace(self::clean($request->json()->all()));
        }

        return $next($request);
    }

    /** @param  array<mixed>  $value  */
    public static function clean(array $value): array
    {
        if (isset($value['k']) && is_string($value['k'])) {
            return $value; // an expression node: kept verbatim
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::clean($item);
            } elseif (is_string($item)) {
                $trimmed = Str::trim($item);
                $value[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        return $value;
    }
}
