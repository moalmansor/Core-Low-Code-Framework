<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps generated documents as files: one for a person to download (owner
 * `document`, theirs alone, removed after `records.job_file_hours`), or one
 * attached to an email (owner `email_attachment`, kept with the email so it
 * can be resent).
 */
final class GeneratedDocuments
{
    public const OWNER_DOWNLOAD = 'document';

    public const OWNER_EMAIL = 'email_attachment';

    public function __construct(
        private readonly FileStore $files,
        private readonly SettingsService $settings,
    ) {}

    /** @param  array{bytes: string, name: string, extension: string, mime: string}  $out */
    public function store(array $out, string $ownerType, ?int $formId, ?int $recordId, ?int $userId): StoredFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lcf-doc-');
        if ($tmp === false) {
            throw new \RuntimeException('Could not store the document.');
        }
        try {
            file_put_contents($tmp, $out['bytes']);

            return $this->files->storeGenerated($tmp, $out['name'], $out['extension'], $out['mime'], $ownerType, ['form_id' => $formId, 'record_id' => $recordId, 'user_id' => $userId]);
        } finally {
            @unlink($tmp);
        }
    }

    /** Removes downloaded documents past their time. */
    public function expire(): int
    {
        $count = 0;
        StoredFile::query()->withoutGlobalScopes()->where('owner_type', self::OWNER_DOWNLOAD)->whereNull('deleted_at')
            ->where('created_at', '<', now()->subHours((int) $this->settings->get('records', 'job_file_hours')))
            ->orderBy('id')->limit(500)->get()->each(function (StoredFile $f) use (&$count): void {
                Storage::disk($f->disk)->delete($f->path);
                $f->forceFill(['deleted_at' => now()])->save();
                $count++;
            });

        return $count;
    }
}
