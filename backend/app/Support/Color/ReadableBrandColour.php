<?php

declare(strict_types=1);

namespace App\Support\Color;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A brand colour must read as text on the theme's surfaces (WCAG AA, 4.5:1) in
 * the mode it is used in; the message names the nearest passing shade.
 */
final readonly class ReadableBrandColour implements ValidationRule
{
    public function __construct(private string $mode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! Contrast::isHex($value)) {
            $fail(__('ui.settings.colour_format'));

            return;
        }
        $ratio = Contrast::onSurfaces($value, $this->mode);
        if ($ratio < Contrast::AA_TEXT) {
            $fail(__('ui.settings.colour_contrast', [
                'ratio' => number_format($ratio, 2),
                'suggestion' => Contrast::nearestPassing($value, $this->mode) ?? '—',
            ]));
        }
    }
}
