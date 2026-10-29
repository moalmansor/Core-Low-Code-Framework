<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Support\Files\ZipGuard;
use App\Support\Pdf\DataOnlyContentLoader;
use App\Support\Pdf\GatedHttpClient;
use App\Support\Pdf\PdfFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Untrusted input (specification §5): PDFs reach images only through the
 * egress gateway, zip containers that would unpack far beyond their size are
 * refused before parsing, and no module calls out or builds a PDF on its own.
 */
final class PinnedResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        return ['img.example.com' => ['93.184.216.34'], 'internal.example.com' => ['10.0.0.5']][$host] ?? [];
    }
}

function gatedClient(array $responses): GatedHttpClient
{
    return new GatedHttpClient(new EgressGateway(new AddressPolicy, new PinnedResolver, app(CorrelationId::class), HandlerStack::create(new MockHandler($responses))));
}

it('lets PDFs embed data images and allowlisted https images, and nothing local, internal or plain http', function () {
    EgressAllowlistEntry::query()->create(['host_pattern' => 'img.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    EgressAllowlistEntry::query()->create(['host_pattern' => 'internal.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    $client = gatedClient([new Response(200, [], 'PNGBYTES')]);
    $get = static fn (string $url) => $client->sendRequest(new Request('GET', $url));
    expect((string) $get('https://img.example.com/logo.png')->getBody())->toBe('PNGBYTES')
        ->and($get('https://internal.example.com/x.png')->getStatusCode())->toBe(403)
        ->and($get('https://not-allowed.test/x.png')->getStatusCode())->toBe(403)
        ->and($get('http://img.example.com/x.png')->getStatusCode())->toBe(403);
    $local = new DataOnlyContentLoader;
    expect($local->load('data:image/png;base64,'.base64_encode('abc')))->toBe('abc')
        ->and($local->load('/etc/passwd'))->toBeNull()
        ->and($local->load('file:///etc/passwd'))->toBeNull()
        ->and($local->load('phar:///tmp/x.phar/a.png'))->toBeNull();
});

it('renders a PDF whose HTML names local and internal images without reading them', function () {
    $pdf = app(PdfFactory::class)->make(['mode' => 'utf-8', 'tempDir' => sys_get_temp_dir()]);
    $pdf->showImageErrors = false;
    $pdf->WriteHTML('<p>Hello</p><img src="/etc/passwd"><img src="file:///etc/hostname"><img src="http://169.254.169.254/latest/meta-data">');
    expect($pdf->Output('', 'S'))->toStartWith('%PDF');
});

it('refuses a zip that would unpack far beyond its size', function () {
    $path = tempnam(sys_get_temp_dir(), 'zipbomb');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat('0', 20 * 1024 * 1024));
    $zip->close();
    $ok = tempnam(sys_get_temp_dir(), 'zipok');
    $zip->open($ok, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<w:document/>');
    $zip->close();
    expect(ZipGuard::safe($path))->toBeFalse()->and(ZipGuard::safe($ok))->toBeTrue()->and(ZipGuard::safe(__FILE__))->toBeFalse();
    @unlink($path);
    @unlink($ok);
});

it('keeps outbound HTTP in the egress gateway and PDF construction in the factory', function () {
    $offenders = [];
    foreach (File::allFiles(app_path()) as $file) {
        $rel = str_replace(app_path().'/', '', $file->getPathname());
        $src = $file->getContents();
        if (! str_starts_with($rel, 'Infrastructure/Egress/') && preg_match('/\bnew\s+(\\\\?GuzzleHttp\\\\)?Client\s*\(|Http::(get|post|put|patch|delete|send|withHeaders|acceptJson)\b|curl_init\s*\(|file_get_contents\s*\(\s*[\'"]https?:/', $src) === 1) {
            $offenders[] = $rel.' calls out directly';
        }
        if ($rel !== 'Support/Pdf/PdfFactory.php' && preg_match('/\bnew\s+(\\\\?Mpdf\\\\)?Mpdf\s*\(/', $src) === 1) {
            $offenders[] = $rel.' builds an mPDF instance outside PdfFactory';
        }
    }
    expect($offenders)->toBe([]);
});

it('checks image dimensions from the header before decoding an upload', function () {
    $png = imagecreatetruecolor(5000, 10);
    ob_start();
    imagepng($png);
    $bytes = (string) ob_get_clean();
    $path = tempnam(sys_get_temp_dir(), 'big').'.png';
    file_put_contents($path, $bytes);
    $this->completeSetup();
    $this->actingAs($this->superAdmin(), 'web');
    $this->post('/api/v1/files', ['file' => new UploadedFile($path, 'wide.png', 'image/png', null, true)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    @unlink($path);
});
