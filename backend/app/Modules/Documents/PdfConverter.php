<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Modules\Core\Settings\SettingsService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a Word document into PDF with LibreOffice, run headless with a
 * throw-away profile per conversion (no shared state between documents, no
 * macros, no first-start dialogs) and a time limit from System Settings.
 * Documents reaching it never point outside themselves: uploaded templates
 * with external content are refused (DocxPackage) and generated ones are
 * written from plain text, so the conversion makes no network requests.
 */
final class PdfConverter
{
    private ?bool $available = null;

    public function __construct(private readonly SettingsService $settings) {}

    /** Whether this installation can convert (the program is installed). */
    public function available(): bool
    {
        if ($this->available === null) {
            $bin = (string) config('services.documents.converter');
            $this->available = $bin !== '' && (str_contains($bin, '/') ? is_executable($bin) : Process::run(['sh', '-c', 'command -v "$0"', $bin])->successful());
        }

        return $this->available;
    }

    /** @return string the PDF */
    public function convert(string $docx): string
    {
        if (! $this->available()) {
            throw new RuntimeException('The document converter is not installed.');
        }
        $dir = storage_path('app/convert/'.Str::uuid7());
        if (! mkdir($dir.'/profile', 0700, true)) {
            throw new RuntimeException('Could not prepare the conversion.');
        }
        try {
            file_put_contents($dir.'/document.docx', $docx);
            $result = Process::timeout((int) $this->settings->get('documents', 'convert_timeout_seconds'))
                ->env(['HOME' => $dir.'/profile'])
                ->run([(string) config('services.documents.converter'), '--headless', '--invisible', '--nodefault', '--nologo', '--norestore', '--nolockcheck',
                    '-env:UserInstallation=file://'.$dir.'/profile', '--convert-to', 'pdf', '--outdir', $dir, $dir.'/document.docx']);
            $pdf = $dir.'/document.pdf';
            if (! $result->successful() || ! is_file($pdf)) {
                throw new RuntimeException('The document could not be converted to PDF: '.Str::limit(trim($result->errorOutput().' '.$result->output()), 500));
            }

            return (string) file_get_contents($pdf);
        } finally {
            Process::run(['rm', '-rf', $dir]);
        }
    }
}
