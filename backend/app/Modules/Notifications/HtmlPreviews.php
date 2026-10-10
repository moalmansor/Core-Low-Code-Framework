<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Shows rendered email HTML to an administrator (the template preview, an
 * email in the Operations Center) without trusting it: the HTML is kept for a
 * few minutes behind a signed, unguessable link and served with its own
 * Content-Security-Policy that allows inline styles and nothing else — no
 * scripts, no frames, no forms, no outside requests — inside a sandboxed
 * frame.
 */
final class HtmlPreviews
{
    public const HEADER = 'X-LCF-Sandboxed-Html';

    public const POLICY = "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'";

    public function put(string $html): string
    {
        $id = (string) Str::uuid7();
        Cache::put("html-preview:{$id}", $html, now()->addMinutes(10));

        return URL::temporarySignedRoute('html-previews.show', now()->addMinutes(10), ['preview' => $id]);
    }

    public function get(string $id): ?string
    {
        $html = Cache::get("html-preview:{$id}");

        return is_string($html) ? $html : null;
    }
}
