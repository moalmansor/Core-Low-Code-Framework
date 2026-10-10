<?php

declare(strict_types=1);

namespace App\Modules\Records\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File uploads for file fields, images, camera captures, signatures and
 * attachments (architecture §21.2 "Files"): uploads are temporary until a
 * record references them; downloads go through a short-lived signed URL
 * issued only to someone who may view the owning record.
 */
final class FileController extends Controller
{
    /** Files a job produced for one user (imports, their error reports, exports, downloads): only that user reads them. */
    public const PERSONAL = ['import_job', 'import_report', 'export_job', 'download_job'];

    public function upload(Request $request, FileStore $files): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'form' => ['sometimes', 'nullable', 'uuid'],
            'field' => ['sometimes', 'nullable', 'string', 'max:48'],
        ]);
        $types = null;
        $maxKb = null;
        if (isset($data['form'], $data['field'])) {
            $form = Form::query()->where('uuid', $data['form'])->firstOrFail();
            abort_unless(app(AccessResolver::class)->allows($this->user(), "form.{$form->uuid}.view"), 403);
            $def = $form->current_version_id === null ? null : app(PublishedDefinitions::class)->version($form->id, (int) $form->current_version_id);
            foreach ($def['fields'] ?? [] as $f) {
                if ($f['key'] === $data['field']) {
                    $types = $f['validation']['file']['types'] ?? null ?: null;
                    $maxKb = $f['validation']['file']['maxSizeKb'] ?? null;
                }
            }
        }
        $file = $files->storeUpload($request->file('file'), $types, $maxKb);

        return response()->json(['data' => ['uuid' => $file->uuid, 'name' => $file->original_name, 'size' => $file->size_bytes, 'mime' => $file->mime_type, 'width' => $file->width, 'height' => $file->height]], 201);
    }

    /** A signed download URL valid for five minutes. */
    public function url(string $file): JsonResponse
    {
        $stored = StoredFile::query()->where('uuid', $file)->whereNull('deleted_at')->firstOrFail();
        abort_unless($this->mayRead($stored), 404);

        return response()->json(['data' => ['url' => URL::temporarySignedRoute('files.download', now()->addMinutes(5), ['file' => $stored->uuid])]]);
    }

    public function download(Request $request, string $file): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $stored = StoredFile::query()->withoutGlobalScopes()->where('uuid', $file)->whereNull('deleted_at')->firstOrFail();
        abort_if($stored->scan_status === 'infected', 404);
        $inline = in_array($stored->mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'application/pdf'], true) && $request->boolean('inline');

        return Storage::disk($stored->disk)->download($stored->path, $stored->original_name, [
            'Content-Type' => $stored->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($stored->original_name).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function mayRead(StoredFile $file): bool
    {
        $user = $this->user();
        if ($file->is_temporary || in_array($file->owner_type, self::PERSONAL, true)) {
            return $file->uploaded_by === $user->id;
        }
        if ($file->form_id !== null) {
            $uuid = DB::table('forms')->where('id', $file->form_id)->value('uuid');

            return $uuid !== null && app(AccessResolver::class)->allows($user, "form.{$uuid}.view");
        }

        return in_array($file->owner_type, ['branding'], true);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
