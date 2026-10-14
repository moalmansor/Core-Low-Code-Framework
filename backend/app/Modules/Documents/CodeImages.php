<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use Mpdf\Barcode;
use Mpdf\QrCode\Output\Png;
use Mpdf\QrCode\QrCode;
use RuntimeException;

/**
 * The QR code or barcode a document carries for its record (specification
 * §4.17), drawn as a PNG so the PDF, the Word file and the designer's preview
 * show the same image. A QR code holds the record's link, a barcode (Code 128)
 * its number; both are drawn here, nothing is fetched.
 */
final class CodeImages
{
    /** Width in pixels per size; a barcode is drawn wider than it is tall. */
    public const SIZES = ['small' => 96, 'medium' => 144, 'large' => 208];

    /** @return array{png: string, width: int, height: int} */
    public function draw(string $kind, string $value, string $size): array
    {
        $px = self::SIZES[$size] ?? self::SIZES['medium'];

        return $kind === 'barcode' ? $this->barcode($value, $px * 2, (int) round($px * 0.45)) : $this->qr($value, $px);
    }

    /** @return array{png: string, width: int, height: int} */
    private function qr(string $value, int $px): array
    {
        $code = new QrCode($value, 'M');

        return ['png' => (new Png)->output($code, $px * 2), 'width' => $px, 'height' => $px];
    }

    /** @return array{png: string, width: int, height: int} */
    private function barcode(string $value, int $width, int $height): array
    {
        $data = (new Barcode)->getBarcodeArray($value, 'C128B', '', 10, 10);
        if (! is_array($data) || ($data['maxw'] ?? 0) <= 0) {
            throw new RuntimeException('This value cannot be shown as a barcode.');
        }
        // Drawn at twice the size shown, so printing stays sharp.
        $scale = max(1, (int) floor(($width * 2) / $data['maxw']));
        $w = $data['maxw'] * $scale;
        $h = $height * 2;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            throw new RuntimeException('Could not draw the barcode.');
        }
        $white = (int) imagecolorallocate($im, 255, 255, 255);
        $black = (int) imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);
        $x = 0;
        foreach ($data['bcode'] as $bar) {
            $bw = (int) $bar['w'] * $scale;
            if ($bar['t']) {
                imagefilledrectangle($im, $x, 0, $x + $bw - 1, $h - 1, $black);
            }
            $x += $bw;
        }
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        return ['png' => $png, 'width' => (int) round($w / 2), 'height' => $height];
    }
}
