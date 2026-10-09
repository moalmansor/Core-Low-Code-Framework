<?php

declare(strict_types=1);

namespace App\Modules\Records;

use App\Infrastructure\Storage\VirusScanner;
use App\Modules\Audit\AuditWriter;
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

    /** Extension => accepted sniffed MIME types for record attachments and file fields. */
    public const MIMES = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'txt' => ['text/plain'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
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
                app(AuditWriter::class)->record('file.rejected_infected', 'security', meta: ['name' => $upload->getClientOriginalName()]);
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

    /**
     * A temporary upload for a file field, signature or attachment. It becomes
     * permanent when a record that references it is saved (`attach()`); stale
     * temporary files are purged by the scheduler.
     *
     * @param  list<string>|null  $types  extensions the field allows (null = installation default)
     */
    public function storeUpload(UploadedFile $upload, ?array $types = null, ?int $maxKb = null): StoredFile
    {
        $allowed = array_map('strtolower', (array) $this->settings->get('files', 'allowed_file_types'));
        if ($types !== null) {
            $allowed = array_values(array_intersect($allowed, array_map('strtolower', $types)));
        }
        $extension = strtolower($upload->getClientOriginalExtension());
        $mime = (string) $upload->getMimeType();
        if (! in_array($extension, $allowed, true) || ! in_array($mime, self::MIMES[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['file' => __('ui.files.type_not_allowed')]);
        }
        $maxBytes = min(1024 * 1024 * (int) $this->settings->get('files', 'max_upload_mb'), $maxKb === null ? PHP_INT_MAX : $maxKb * 1024);
        if ($upload->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['file' => __('ui.files.too_large', ['mb' => round($maxBytes / 1048576, 1)])]);
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
                app(AuditWriter::class)->record('file.rejected_infected', 'security', meta: ['name' => $upload->getClientOriginalName()]);
                throw ValidationException::withMessages(['file' => __('ui.files.infected')]);
            }
        }
        $width = $height = null;
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            [$contents, $width, $height] = $this->reencode($tmp, $extension === 'gif' ? 'png' : $extension);
        } else {
            $contents = (string) file_get_contents($tmp);
        }
        $path = 'records/'.now()->format('Y/m').'/'.Str::uuid7().'.'.$extension;
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
            'is_temporary' => true,
            'owner_type' => 'upload',
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

    /** Deletes temporary uploads no record adopted within the given hours. */
    public function purgeTemporary(int $hours): int
    {
        $count = 0;
        StoredFile::query()->withoutGlobalScopes()->where('is_temporary', true)->where('created_at', '<', now()->subHours($hours))
            ->orderBy('id')->limit(1000)->get()->each(function (StoredFile $file) use (&$count): void {
                Storage::disk($file->disk)->delete($file->path);
                $file->delete();
                $count++;
            });

        return $count;
    }
}
