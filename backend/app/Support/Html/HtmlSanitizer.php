<?php

declare(strict_types=1);

namespace App\Support\Html;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Allow-list HTML sanitizer for admin-authored rich content (static HTML
 * blocks, rich-text values, comments): no scripts, event handlers, styles
 * that load resources, iframes or forms. Links get rel="noopener noreferrer".
 */
final class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(string $html): string
    {
        return $this->purifier()->purify($html);
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier !== null) {
            return $this->purifier;
        }
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,sub,sup,span[dir],div[dir],h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,code,pre,hr,a[href|title|target],img[src|alt|width|height],table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]');
        $config->set('URI.AllowedSchemes', ['http' => false, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return $this->purifier = new HTMLPurifier($config);
    }
}
