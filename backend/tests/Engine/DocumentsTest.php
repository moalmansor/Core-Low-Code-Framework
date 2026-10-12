<?php

declare(strict_types=1);

use App\Modules\Documents\PdfConverter;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** A form with a text, a long text and a repeater of lines; returns its uuid. */
function ordersForm(TestCase $t): string
{
    [, $form] = createForm($t, 'orders');
    $lines = groupDoc('lines', 'repeater');
    saveDraft($t, $form, [$lines], [
        fieldDoc('customer', 'text'),
        fieldDoc('notes', 'textarea'),
        fieldDoc('item', 'text', $lines['uuid']),
        fieldDoc('qty', 'number', $lines['uuid'], ['storage' => ['precision' => 9, 'scale' => 0]]),
    ])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return $form;
}

/**
 * A Word file built by hand, the way Word stores text: in runs.
 *
 * @param  array<string, string>  $extra  more entries (name => contents)
 */
function wordFile(string $body, array $extra = [], string $rels = ''): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
    $zip->addFromString('word/document.xml', str_starts_with($body, '<?xml') || str_starts_with($body, '<!') ? $body
        : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>');
    foreach ($extra as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    return new UploadedFile($path, 'template.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

/** The text of a generated Word file's body. */
function wordText(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'out');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return $xml;
}

/** Downloads a generated file through its signed link. */
function fetchGenerated(TestCase $t, string $url): string
{
    $res = $t->get($url)->assertOk();

    return $res->streamedContent();
}

const ORDER_BODY = '<w:p><w:r><w:t>Order for {{ cus</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>tomer }}</w:t></w:r><w:r><w:t xml:space="preserve"> ({{ @form }})</w:t></w:r></w:p>'
    .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Item</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Qty</w:t></w:r></w:p></w:tc></w:tr>'
    .'<w:tr><w:tc><w:p><w:r><w:t>{{ lines.item }}</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{{ lines.</w:t></w:r><w:r><w:t>qty }}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
    .'<w:p><w:r><w:t>{{ notes }}</w:t></w:r></w:p>';

it('fills a Word template: placeholders split across runs, repeater rows, escaped values and line breaks', function () {
    $form = ordersForm($this);
    $up = $this->post('/api/v1/documents/uploads', ['file' => wordFile(ORDER_BODY), 'form' => $form])->assertCreated()->json('data');
    expect($up['placeholders'])->toEqualCanonicalizing(['customer', '@form', 'lines.item', 'lines.qty', 'notes'])->and($up['unknown'])->toBe([]);

    // A placeholder that names nothing in the form is refused when saving, naming it.
    $bad = $this->post('/api/v1/documents/uploads', ['file' => wordFile('<w:p><w:r><w:t>{{ nothing_here }}</w:t></w:r></w:p>'), 'form' => $form])->assertCreated()->json('data');
    expect($bad['unknown'])->toBe(['nothing_here']);
    $this->postJson('/api/v1/documents/templates', ['key' => 'bad', 'type' => 'docx', 'form' => $form, 'name' => ['en' => 'Bad'], 'files' => ['en' => $bad['uuid']], 'formats' => ['docx']])
        ->assertStatus(422)->assertJsonValidationErrors(['files.en']);

    $tpl = $this->postJson('/api/v1/documents/templates', [
        'key' => 'order_sheet', 'type' => 'docx', 'form' => $form, 'name' => ['en' => 'Order sheet', 'ar' => 'ورقة الطلب'],
        'files' => ['en' => $up['uuid']], 'formats' => ['docx'],
    ])->assertCreated()->json('data');
    expect($tpl['files']['en']['name'])->toBe('template.docx')->and($tpl['issues'])->toBe([]);
    expect(StoredFile::query()->where('uuid', $up['uuid'])->value('owner_type'))->toBe('document_template');

    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => [
        'customer' => 'Acme <Trading> & Co', 'notes' => "First line\nSecond line",
        'lines' => [['item' => 'Bolts', 'qty' => 3], ['item' => 'Nuts', 'qty' => 7]],
    ]])->assertCreated()->json('data');
    $out = $this->postJson("/api/v1/documents/templates/{$tpl['uuid']}/generate", ['record' => $rec['uuid'], 'format' => 'docx', 'locale' => 'en'])->assertCreated()->json('data');
    expect($out['name'])->toStartWith('Order sheet');
    $xml = wordText(fetchGenerated($this, $out['url']));
    expect($xml)->not->toContain('{{')
        ->and($xml)->toContain('Order for Acme &lt;Trading&gt; &amp; Co')
        ->and($xml)->toContain('(Orders)')
        ->and($xml)->toContain('First line</w:t><w:br/><w:t xml:space="preserve">Second line')
        ->and(substr_count($xml, '<w:tr>'))->toBe(3)
        ->and(strpos($xml, 'Bolts'))->toBeLessThan(strpos($xml, 'Nuts'))
        ->and($xml)->toContain('>7<');

    // Only the downloader may fetch the generated file; it is theirs alone.
    $other = $this->makeUser();
    $this->flushSession();
    $this->actingAs($other, 'web');
    $file = StoredFile::query()->where('owner_type', 'document')->latest('id')->value('uuid');
    $this->getJson("/api/v1/files/{$file}/url")->assertNotFound();
});

it('refuses Word files that carry macros, outside content, entity declarations or external fields', function () {
    $cases = [
        'macros' => wordFile('<w:p/>', ['word/vbaProject.bin' => 'x']),
        'external_content' => wordFile('<w:p/>', [], '<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="http://169.254.169.254/latest" TargetMode="External"/>'),
        'bad_xml' => wordFile('<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>&x;</w:t></w:r></w:p></w:body></w:document>'),
        'external_field' => wordFile('<w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText> INCLUDETEXT "c:\\\\secret.txt" </w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>'),
    ];
    foreach ($cases as $problem => $file) {
        $res = $this->post('/api/v1/documents/uploads', ['file' => $file], ['Accept' => 'application/json'])->assertStatus(422);
        expect($res->json('errors.file'))->toContain(__('documents.problem.'.$problem));
    }
    // A hyperlink to a web page is fine.
    $this->post('/api/v1/documents/uploads', ['file' => wordFile('<w:p><w:hyperlink r:id="rId3"><w:r><w:t>site</w:t></w:r></w:hyperlink></w:p>', [], '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com" TargetMode="External"/>')])
        ->assertCreated();
});

it('designs a template from blocks and generates Word and PDF in both directions, with a live preview', function () {
    $form = ordersForm($this);
    $design = ['style' => ['accent' => '#0f766e'], 'blocks' => [
        ['type' => 'heading', 'text' => ['en' => 'Order {{ customer }}', 'ar' => 'طلب {{ customer }}']],
        ['type' => 'text', 'text' => ['en' => "Dear **{{ customer }}**,\n\nThank you.", 'ar' => 'شكرًا لك يا **{{ customer }}**']],
        ['type' => 'fields', 'fields' => ['customer', 'notes', '@record_number']],
        ['type' => 'table', 'group' => 'lines', 'columns' => ['item', 'qty']],
        ['type' => 'page_break'],
        ['type' => 'text', 'text' => ['en' => 'Shown only for big orders'], 'condition' => ['k' => 'call', 'fn' => 'contains', 'args' => [['k' => 'ref', 'scope' => 'record', 'path' => ['customer']], ['k' => 'lit', 't' => 'text', 'v' => 'Big']]]],
    ]];
    $paper = ['size' => 'a5', 'orientation' => 'landscape', 'margins' => ['top' => 10, 'right' => 15, 'bottom' => 10, 'left' => 15]];
    // A table of a repeater the form does not have is refused.
    $this->postJson('/api/v1/documents/templates', ['key' => 'x', 'type' => 'html', 'form' => $form, 'name' => ['en' => 'X'], 'formats' => ['pdf'],
        'design' => ['blocks' => [['type' => 'table', 'group' => 'nope', 'columns' => []]]]])->assertStatus(422)->assertJsonValidationErrors(['design.blocks.0.group']);

    $tpl = $this->postJson('/api/v1/documents/templates', ['key' => 'confirmation', 'type' => 'html', 'form' => $form, 'name' => ['en' => 'Confirmation', 'ar' => 'تأكيد'],
        'design' => $design, 'paper' => $paper, 'formats' => ['docx', 'pdf']])->assertCreated()->json('data');
    expect($tpl['paper'])->toEqual($paper)->and($tpl['placeholders'])->toContain('customer', 'lines.qty');
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['customer' => 'Acme', 'lines' => [['item' => 'Bolts', 'qty' => 3]]]])->assertCreated()->json('data');

    $word = $this->postJson("/api/v1/documents/templates/{$tpl['uuid']}/generate", ['record' => $rec['uuid'], 'format' => 'docx', 'locale' => 'en'])->assertCreated()->json('data');
    $xml = wordText(fetchGenerated($this, $word['url']));
    expect($xml)->toContain('Order Acme')->and($xml)->toContain('<w:b/><w:bCs/></w:rPr><w:t xml:space="preserve">Acme')
        ->and($xml)->toContain('Bolts')->and($xml)->toContain('w:orient="landscape"')
        ->and($xml)->not->toContain('big orders')->and($xml)->not->toContain('<w:bidi/>')
        // The page break only led to a block hidden by its condition: no blank last page.
        ->and($xml)->not->toContain('w:type="page"');
    $arabic = $this->postJson("/api/v1/documents/templates/{$tpl['uuid']}/generate", ['record' => $rec['uuid'], 'format' => 'docx', 'locale' => 'ar'])->assertCreated()->json('data');
    $xml = wordText(fetchGenerated($this, $arabic['url']));
    expect($xml)->toContain('طلب Acme')->and($xml)->toContain('<w:bidi/>')->and($xml)->toContain('<w:rtl/>');

    $pdf = $this->postJson("/api/v1/documents/templates/{$tpl['uuid']}/generate", ['record' => $rec['uuid'], 'format' => 'pdf', 'locale' => 'ar'])->assertCreated()->json('data');
    expect(fetchGenerated($this, $pdf['url']))->toStartWith('%PDF');
    // A format the template does not offer is refused.
    $this->putJson("/api/v1/documents/templates/{$tpl['uuid']}", ['key' => 'confirmation', 'type' => 'html', 'form' => $form, 'name' => ['en' => 'Confirmation'], 'design' => $design, 'formats' => ['pdf'], 'base_hash' => $tpl['hash']])->assertOk();
    $this->postJson("/api/v1/documents/templates/{$tpl['uuid']}/generate", ['record' => $rec['uuid'], 'format' => 'docx'])->assertStatus(422);
    // Saving over someone else's change is a conflict, never an overwrite.
    $this->putJson("/api/v1/documents/templates/{$tpl['uuid']}", ['key' => 'confirmation', 'type' => 'html', 'form' => $form, 'name' => ['en' => 'Again'], 'design' => $design, 'formats' => ['pdf'], 'base_hash' => $tpl['hash']])
        ->assertStatus(409)->assertJsonPath('code', 'template_changed');

    // The designer's preview: the page, with values, through the sandboxed link.
    $url = $this->postJson('/api/v1/documents/templates/preview', ['form' => $form, 'record' => $rec['uuid'], 'design' => $design, 'paper' => $paper, 'locale' => 'en'])->assertOk()->json('data.url');
    $page = $this->get($url)->assertOk()->getContent();
    expect($page)->toContain('Order Acme')->and($page)->toContain('<b>Acme</b>')->and($page)->toContain('<td>Bolts</td>');
});

it('makes PDF from a Word template with the converter, and refuses PDF when no converter is installed', function () {
    $form = ordersForm($this);
    $up = $this->post('/api/v1/documents/uploads', ['file' => wordFile(ORDER_BODY), 'form' => $form])->assertCreated()->json('data');
    $tpl = $this->postJson('/api/v1/documents/templates', ['key' => 'sheet', 'type' => 'docx', 'form' => $form, 'name' => ['en' => 'Sheet'], 'files' => ['en' => $up['uuid']], 'formats' => ['docx', 'pdf']])
        ->assertCreated()->json('data');
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['customer' => 'Acme', 'lines' => [['item' => 'Bolts', 'qty' => 3]]]])->assertCreated()->json('data');
    $pdf = $this->postJson("/api/v1/documents/templates/{$tpl['uuid']}/generate", ['record' => $rec['uuid'], 'format' => 'pdf'])->assertCreated()->json('data');
    expect(fetchGenerated($this, $pdf['url']))->toStartWith('%PDF');

    config(['services.documents.converter' => '/nonexistent/soffice']);
    app()->forgetInstance(PdfConverter::class);
    $this->getJson('/api/v1/documents/templates')->assertOk()->assertJsonPath('meta.pdf_from_word', false);
    $this->putJson("/api/v1/documents/templates/{$tpl['uuid']}", ['key' => 'sheet', 'type' => 'docx', 'form' => $form, 'name' => ['en' => 'Sheet'], 'files' => ['en' => $up['uuid']], 'formats' => ['docx', 'pdf'], 'base_hash' => $tpl['hash']])
        ->assertStatus(422)->assertJsonValidationErrors(['formats']);
});

it('reports placeholders the form no longer has, and replaces the Word file of a language', function () {
    $form = ordersForm($this);
    $up = $this->post('/api/v1/documents/uploads', ['file' => wordFile(ORDER_BODY), 'form' => $form])->assertCreated()->json('data');
    $tpl = $this->postJson('/api/v1/documents/templates', ['key' => 'sheet', 'type' => 'docx', 'form' => $form, 'name' => ['en' => 'Sheet'], 'files' => ['en' => $up['uuid']], 'formats' => ['docx']])
        ->assertCreated()->json('data');

    // Notes is removed from the form and published.
    $doc = $this->getJson("/api/v1/forms/{$form}/draft")->json('data.document');
    $doc['fields'] = array_values(array_filter($doc['fields'], static fn ($f) => $f['key'] !== 'notes'));
    $this->putJson("/api/v1/forms/{$form}/draft", ['document' => $doc, 'draft_updated_at' => $this->getJson("/api/v1/forms/{$form}/draft")->json('data.draft_updated_at')])->assertOk();
    publish($this, $form, ['confirm_destructive' => true, 'typed_confirmation' => 'orders']);
    // Each request starts with fresh per-request caches; the test client reuses one application.
    app()->forgetScopedInstances();
    expect($this->getJson("/api/v1/documents/templates/{$tpl['uuid']}")->json('data.issues'))->toBe([__('documents.placeholder_gone', ['placeholder' => 'notes'])]);

    // A new file without it replaces the old one, which is removed.
    $next = $this->post('/api/v1/documents/uploads', ['file' => wordFile('<w:p><w:r><w:t>{{ customer }}</w:t></w:r></w:p>'), 'form' => $form])->assertCreated()->json('data');
    $saved = $this->putJson("/api/v1/documents/templates/{$tpl['uuid']}", ['key' => 'sheet', 'type' => 'docx', 'form' => $form, 'name' => ['en' => 'Sheet'], 'files' => ['en' => $next['uuid']], 'formats' => ['docx'], 'base_hash' => $this->getJson("/api/v1/documents/templates/{$tpl['uuid']}")->json('data.hash')])
        ->assertOk()->json('data');
    expect($saved['issues'])->toBe([])->and(StoredFile::query()->where('uuid', $up['uuid'])->value('deleted_at'))->not->toBeNull();
    Storage::disk('private')->assertExists(StoredFile::query()->where('uuid', $next['uuid'])->value('path'));
});

it('attaches documents generated for the recipients to notification emails, and keeps a template in use', function () {
    $form = ordersForm($this);
    $tpl = $this->postJson('/api/v1/documents/templates', ['key' => 'receipt', 'type' => 'html', 'form' => $form, 'name' => ['en' => 'Receipt', 'ar' => 'إيصال'], 'formats' => ['pdf'],
        'design' => ['blocks' => [['type' => 'heading', 'text' => ['en' => 'Receipt for {{ customer }}']]]]])->assertCreated()->json('data');
    $email = $this->postJson('/api/v1/notifications/templates', ['key' => 'new_order', 'form' => $form, 'subject' => ['en' => 'New order {{ customer }}'],
        'design' => ['blocks' => [['type' => 'text', 'text' => ['en' => 'See the receipt.']]]]])->assertCreated()->json('data');
    $rule = ['uuid' => uid(), 'key' => 'on_create', 'name' => ['en' => 'On create'], 'trigger' => 'record_created', 'fromStatus' => null, 'toStatus' => null, 'field' => null,
        'condition' => null, 'schedule' => null, 'template' => $email['uuid'], 'channels' => ['email'],
        'recipients' => ['to' => [['type' => 'creator', 'ref' => null]], 'cc' => [], 'bcc' => []],
        'attachments' => ['fields' => [], 'documents' => [['template' => $tpl['uuid'], 'format' => 'docx']]], 'delayMinutes' => null, 'respectPreferences' => false, 'active' => true];
    $hash = fn () => $this->getJson("/api/v1/forms/{$form}/notification-rules")->json('data.hash');
    // A format the template does not produce is refused.
    $this->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => [$rule], 'base_hash' => $hash()])->assertStatus(422)->assertJsonValidationErrors(['rules.0.attachments.documents.0']);
    $rule['attachments']['documents'][0]['format'] = 'pdf';
    $this->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => [$rule], 'base_hash' => $hash()])->assertOk();

    $this->postJson("/api/v1/r/{$form}", ['values' => ['customer' => 'Acme']])->assertCreated();
    $row = DB::table('email_queue')->where('source', 'rule')->first();
    expect($row->status)->toBe('sent');
    $file = StoredFile::query()->find(json_decode((string) $row->attachment_file_ids, true)[0]);
    expect($file->owner_type)->toBe('email_attachment')->and($file->original_name)->toBe('Receipt.pdf')
        ->and(Storage::disk($file->disk)->get($file->path))->toStartWith('%PDF');
    $sent = iterator_to_array(Mail::mailer()->getSymfonyTransport()->messages())[0]->getOriginalMessage();
    expect($sent->getAttachments()[0]->getFilename())->toBe('Receipt.pdf');

    $this->deleteJson("/api/v1/documents/templates/{$tpl['uuid']}")->assertStatus(422)->assertJsonValidationErrors(['template']);
});
