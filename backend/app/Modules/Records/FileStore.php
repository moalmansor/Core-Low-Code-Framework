<?php

declare(strict_types=1);

namespace App\Modules\Records;

use App\Infrastructure\Storage\VirusScanner;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores uploads outside the public root after type, size, and virus checks
 * (specification §5). Images are re-encoded to strip embedded payloads.
 */
final class FileStore
{
    public const DISK = 'private';

    /** Extension => allowed sniffed MIME types. */
    private const IMAGE_MIMES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly VirusScanner $scanner,
    ) {}

    public function storeImage(UploadedFile $upload, string $ownerType): StoredFile
    {
        $maxBytes = 1024 * 1024 * (int) $this->settings->get('files', 'max_upload_mb');
        $allowed = array_map('strtolower', (array) $this->settings->get('files', 'allowed_image_types'));
        $extension = strtolower($upload->getClientOriginalExtension());
        $mime = (string) $upload->getMimeType(); // sniffed from content, not the client header
        if (! in_array($extension, $allowed, true) || ! in_array($mime, self::IMAGE_MIMES[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['file' => __('ui.files.type_not_allowed')]);
        }
        if ($upload->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['file' => __('ui.files.too_large', ['mb' => (int) $this->settings->get('files', 'max_upload_mb')])]);
        }
        $tmp = (string) $upload->getRealPath();
        $scan = 'skipped';
        if ($this->scanner->enabled()) {
            try {
                $scan = $this->scanner->scan($tmp);
            } catch (Throwable $e) {
                report($e);
                throw ValidationException::withMessages(['file' => __('ui.files.scan_unavailable')]);
            }
            if ($scan === 'infected') {
                app(\App\Modules\Audit\AuditWriter::class)->record('file.rejected_infected', 'security', meta: ['name' => $upload->getClientOriginalName()]);
                throw ValidationException::withMessages(['file' => __('ui.files.infected')]);
            }
        }
        [$contents, $width, $height] = $extension === 'ico' ? [(string) file_get_contents($tmp), null, null] : $this->reencode($tmp, $extension);
        $path = 'branding/'.now()->format('Y/m').'/'.Str::uuid7().'.'.$extension;
        Storage::disk(self::DISK)->put($path, $contents);

        return StoredFile::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr(preg_replace('/[^\pL\pN._ -]/u', '_', $upload->getClientOriginalName()) ?? 'file', 0, 255),
            'mime_type' => $mime,
            'extension' => $extension,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'scan_status' => $scan,
            'scanned_at' => $scan === 'skipped' ? null : now(),
            'width' => $width,
            'height' => $height,
            'owner_type' => $ownerType,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function reencode(string $path, string $extension): array
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            throw ValidationException::withMessages(['file' => __('ui.files.invalid_image')]);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > 4096 || $height > 4096) {
            imagedestroy($image);
            throw ValidationException::withMessages(['file' => __('ui.files.image_too_big')]);
        }
        imagesavealpha($image, true);
        ob_start();
        match ($extension) {
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
            default => imagejpeg($image, null, 90),
        };
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return [$contents, $width, $height];
    }
}
