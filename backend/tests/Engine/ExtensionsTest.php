<?php

declare(strict_types=1);

use App\Modules\Extensions\CodeGuard;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    config(['services.extensions.path' => storage_path('framework/testing/extensions-'.getmypid())]);
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->developer = $this->makeUser(['developer']);
    $this->approver = $this->makeUser();
    grantPermission($this->approver->id, 'system.approve_code');
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

afterEach(function () {
    $dir = (string) config('services.extensions.path');
    if (is_dir($dir)) {
        exec('rm -rf '.escapeshellarg($dir));
    }
});

function actAs(TestCase $t, $user): void
{
    $t->flushSession();
    $t->actingAs($user, 'web');
}

/** Tickets: subject (text) and code (text); open → closed. */
function extensionForm(TestCase $t): array
{
    [, $form] = createForm($t, 'tickets');
    $subject = fieldDoc('subject', 'text', null, ['validation' => ['required' => true]]);
    $code = fieldDoc('code', 'text');
    saveDraft($t, $form, [], [$subject, $code])->assertOk();
    $open = statusDoc('open', true);
    $closed = statusDoc('closed', false, true);
    $close = transitionDoc('close', $open, $closed);
    saveWorkflow($t, $form, [$open, $closed], [$close])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return ['form' => $form, 'close' => $close];
}

/** Writes, tests, submits, approves (as someone else) and deploys an extension; returns its uuid. */
function shipExtension(TestCase $t, array $body, array $test = []): string
{
    actAs($t, $t->developer);
    $uuid = $t->postJson('/api/v1/extensions', $body)->assertCreated()->json('data.uuid');
    $t->postJson("/api/v1/extensions/{$uuid}/test", $test)->assertOk()->assertJsonPath('data.report.ok', true);
    $t->postJson("/api/v1/extensions/{$uuid}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');
    actAs($t, $t->approver);
    $t->postJson("/api/v1/extensions/{$uuid}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    actAs($t, $t->developer);
    $t->postJson("/api/v1/extensions/{$uuid}/deploy")->assertOk()->assertJsonPath('data.status', 'deployed');
    actAs($t, $t->admin);

    return $uuid;
}

it('refuses code that reaches outside the extension sandbox and accepts plain computation', function (string $code, ?string $problem) {
    $found = app(CodeGuard::class)->check("final class Hook implements ServerHook {\n public function handle(HookContext \$ctx): void {\n {$code}\n }\n}", 'Hook', 'ServerHook');
    if ($problem === null) {
        expect($found)->toBe([]);
    } else {
        expect(collect($found)->pluck('message')->implode(' | '))->toContain($problem);
    }
})->with([
    'string functions' => ['$ctx->set("code", strtoupper(trim((string) $ctx->get("subject"))));', null],
    'closures for callbacks' => ['$x = array_map(fn ($v) => $v * 2, [1, 2]); usort($x, static fn ($a, $b) => $a <=> $b);', null],
    'dates' => ['$d = new \DateTimeImmutable("now"); $ctx->log($d->format("Y"));', null],
    'exec' => ['exec("id");', 'exec'],
    'shell_exec via backticks' => ['$a = `id`;', 'backticks'],
    'eval' => ['eval("1;");', 'eval'],
    'include' => ['include "/etc/passwd";', 'include'],
    'calling a string' => ['("sys"."tem")("id");', 'Calling a variable'],
    'calling a variable' => ['$f = "system"; $f("id");', 'Calling a variable'],
    'callback by name' => ['array_map("system", ["id"]);', 'inline function'],
    'framework classes' => ['\Illuminate\Support\Facades\DB::table("users")->delete();', 'is not allowed'],
    'imports' => ['$x = new \Illuminate\Support\Str(); ', 'is not allowed'],
    'relative names' => ['$x = Foo\Bar::baz();', 'namespaced'],
    'files' => ['file_put_contents("/tmp/x", "y");', 'file_put_contents'],
    'superglobals' => ['$a = $_SERVER["PATH"];', '$_SERVER'],
    'new from a variable' => ['$c = "PDO"; $x = new $c("x");', 'Creating an object'],
    'reflection' => ['$r = new ReflectionClass("x");', 'ReflectionClass'],
    'closure escape' => ['$f = fn () => 1; $g = $f->fromCallable("system");', 'fromCallable'],
    'output' => ['echo "x";', 'echo'],
]);

it('takes an extension from draft to deployed only through a passed test and someone else\'s approval, and runs it inside the save', function () {
    $w = extensionForm($this);
    $source = "final class Hook implements ServerHook\n{\n    public function handle(HookContext \$ctx): void\n    {\n        \$ctx->set('code', 'T-'.strtoupper((string) \$ctx->get('subject')));\n        if (\$ctx->get('subject') === 'forbidden') {\n            \$ctx->error('subject', 'Not this subject.');\n        }\n    }\n}\n";
    // Only Manage code writes; Approve code alone cannot.
    actAs($this, $this->approver);
    $this->postJson('/api/v1/extensions', ['key' => 'x', 'type' => 'server_hook', 'source' => $source])->assertForbidden();
    actAs($this, $this->developer);
    $this->postJson('/api/v1/extensions', ['key' => 'bad', 'type' => 'server_hook', 'hook_point' => 'beforeSave', 'form' => $w['form'], 'source' => 'final class Hook implements ServerHook { public function handle(HookContext $c): void { system("id"); } }'])
        ->assertStatus(422)->assertJsonPath('errors.source.0', 'Line 1: The function system is not allowed in an extension.');
    $made = $this->postJson('/api/v1/extensions', ['key' => 'ticket_code', 'type' => 'server_hook', 'hook_point' => 'beforeSave', 'form' => $w['form'], 'timeout_ms' => 1000, 'source' => $source])
        ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.version', 1);
    $uuid = $made->json('data.uuid');
    $dir = (string) config('services.extensions.path');
    expect(file_get_contents($dir.'/'.$this->developer->organization_id.'/ticket_code/v1/source.php'))->toBe(trim($source))
        ->and(json_decode((string) file_get_contents($dir.'/'.$this->developer->organization_id.'/ticket_code/v1/manifest.json'), true))->toMatchArray(['key' => 'ticket_code', 'version' => 1]);

    // Not before a passed test.
    $this->postJson("/api/v1/extensions/{$uuid}/submit")->assertStatus(422);
    $report = $this->postJson("/api/v1/extensions/{$uuid}/test", ['values' => ['subject' => 'printer']])->assertOk()->json('data.report');
    expect($report['ok'])->toBeTrue()->and($report['values']['code'])->toBe('T-PRINTER')->and($report['changed'])->toBe(['code']);
    $this->postJson("/api/v1/extensions/{$uuid}/submit")->assertOk();
    // The author cannot approve, nor can a developer without Approve code.
    $this->postJson("/api/v1/extensions/{$uuid}/approve")->assertForbidden();
    grantPermission($this->developer->id, 'system.approve_code');
    $this->postJson("/api/v1/extensions/{$uuid}/approve")->assertStatus(422)->assertJsonPath('errors.extension.0', __('extensions.own_code'));
    // Nothing runs before deployment.
    actAs($this, $this->admin);
    expect($this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'before']])->assertCreated()->json('data.values.code'))->toBeNull();
    actAs($this, $this->approver);
    $this->postJson("/api/v1/extensions/{$uuid}/approve")->assertOk();
    actAs($this, $this->developer);
    $this->postJson("/api/v1/extensions/{$uuid}/deploy")->assertOk()->assertJsonPath('data.deployed_version', 1);

    actAs($this, $this->admin);
    $rec = $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'router']])->assertCreated()->json('data');
    expect($rec['values']['code'])->toBe('T-ROUTER');
    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'forbidden']])->assertStatus(422)->assertJsonPath('errors.subject.0', 'Not this subject.');

    // A change after approval starts version 2; version 1 keeps running until 2 is deployed, and 1 can be deployed again.
    actAs($this, $this->developer);
    $this->putJson("/api/v1/extensions/{$uuid}", ['hook_point' => 'beforeSave', 'form' => $w['form'], 'timeout_ms' => 1000, 'source' => str_replace("'T-'", "'V2-'", $source)])
        ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.deployed_version', 1)->assertJsonPath('data.status', 'draft');
    actAs($this, $this->admin);
    expect($this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'still']])->json('data.values.code'))->toBe('T-STILL');
    actAs($this, $this->developer);
    $this->postJson("/api/v1/extensions/{$uuid}/test", ['values' => ['subject' => 'a']])->assertOk();
    $this->postJson("/api/v1/extensions/{$uuid}/submit")->assertOk();
    actAs($this, $this->approver);
    $this->postJson("/api/v1/extensions/{$uuid}/approve")->assertOk();
    actAs($this, $this->developer);
    $versions = $this->postJson("/api/v1/extensions/{$uuid}/deploy")->assertOk()->json('data.versions');
    actAs($this, $this->admin);
    expect($this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'new']])->json('data.values.code'))->toBe('V2-NEW');
    actAs($this, $this->developer);
    $this->postJson("/api/v1/extensions/{$uuid}/deploy", ['version' => collect($versions)->firstWhere('version', 1)['uuid']])->assertOk()->assertJsonPath('data.deployed_version', 1);
    actAs($this, $this->admin);
    expect($this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'back']])->json('data.values.code'))->toBe('T-BACK')
        ->and(DB::table('audit_logs')->whereIn('event', ['extension.created', 'extension.tested', 'extension.submitted', 'extension.approved', 'extension.deployed', 'extension.rolled_back'])->pluck('event')->unique()->count())->toBe(6);

    // Turned off, it stops running.
    actAs($this, $this->developer);
    $this->postJson("/api/v1/extensions/{$uuid}/disable", ['off' => true])->assertOk()->assertJsonPath('data.status', 'disabled');
    actAs($this, $this->admin);
    expect($this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'off']])->json('data.values.code'))->toBeNull();
});

it('rolls the whole save back when a hook fails, times out or refuses, and reports the failure', function () {
    $w = extensionForm($this);
    $hook = static fn (string $body) => "final class Hook implements ServerHook { public function handle(HookContext \$ctx): void { {$body} } }";
    $uuid = shipExtension($this, ['key' => 'guard_close', 'type' => 'server_hook', 'hook_point' => 'afterSave', 'form' => $w['form'], 'timeout_ms' => 300,
        'source' => $hook('if ($ctx->get("subject") === "boom") { throw new RuntimeException("database is on fire"); } if ($ctx->get("subject") === "loop") { while (true) { $x = 1; } } if ($ctx->get("subject") === "no") { $ctx->fail("Tickets named no are refused."); }')]);
    $table = (string) DB::table('forms')->where('uuid', $w['form'])->value('table_name');

    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'no']])->assertStatus(422)->assertJsonPath('message', 'Tickets named no are refused.');
    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'boom']])->assertStatus(422)->assertJsonPath('message', __('extensions.failed'));
    $started = microtime(true);
    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'loop']])->assertStatus(422)->assertJsonPath('code', 'extension_failed');
    expect(microtime(true) - $started)->toBeLessThan(5)
        ->and(DB::table($table)->count())->toBe(0);
    $failures = DB::table('audit_logs')->where('event', 'extension.failed')->orderBy('id')->get()->map(static fn ($r) => json_decode((string) $r->meta, true));
    expect($failures)->toHaveCount(2)->and($failures[0]['error'])->toBe('database is on fire')->and($failures[1]['error'])->toContain('300 ms')
        ->and(DB::table('error_groups')->count())->toBeGreaterThan(0);
    $jobs = $this->getJson('/api/v1/ops/jobs?kind=extension')->assertOk()->json('data');
    expect($jobs)->toHaveCount(2);
    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'fine']])->assertCreated();
});

it('never loads a build whose files changed after approval', function () {
    $w = extensionForm($this);
    $uuid = shipExtension($this, ['key' => 'tamper', 'type' => 'server_hook', 'hook_point' => 'beforeSave', 'form' => $w['form'],
        'source' => 'final class Hook implements ServerHook { public function handle(HookContext $ctx): void { $ctx->set("code", "ok"); } }']);
    $file = config('services.extensions.path').'/'.$this->admin->organization_id.'/tamper/v1/build.php';
    file_put_contents($file, str_replace('"ok"', '"pwned"', (string) file_get_contents($file)));
    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'x']])->assertStatus(422)->assertJsonPath('code', 'extension_failed');
    actAs($this, $this->developer);
    $this->postJson('/api/v1/extensions/'.$uuid.'/deploy')->assertStatus(500);
});

it('runs onStatusChange hooks inside the transition', function () {
    $w = extensionForm($this);
    shipExtension($this, ['key' => 'no_close', 'type' => 'server_hook', 'hook_point' => 'onStatusChange', 'form' => $w['form'],
        'source' => 'final class Hook implements ServerHook { public function handle(HookContext $ctx): void { if ($ctx->extra()["to"] === "closed" && $ctx->get("code") === null) { $ctx->fail("Give it a code before closing."); } } }'],
        ['values' => ['subject' => 'a', 'code' => 'C1'], 'extra' => ['transition' => 'close', 'from' => 'open', 'to' => 'closed']]);
    $rec = $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'x']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$w['form']}/{$rec['uuid']}/transitions/{$w['close']['uuid']}", ['row_version' => 1])->assertStatus(422)->assertJsonPath('message', 'Give it a code before closing.');
    expect(DB::table('status_history')->count())->toBe(1);
});

it('serves custom API endpoints only to holders of their permission, and reads records as the caller', function () {
    $w = extensionForm($this);
    $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'visible']])->assertCreated();
    shipExtension($this, ['key' => 'ticket_count', 'type' => 'api_endpoint', 'permission' => "form.{$w['form']}.view",
        'source' => 'final class Endpoint implements ApiEndpoint { public function handle(ApiRequest $r): array { return ["count" => count($r->records()->list("tickets")), "hello" => $r->input("name")]; } }'],
        ['body' => ['name' => 'test']]);
    $this->postJson('/api/v1/ext-api/ticket_count', ['name' => 'Sara'])->assertOk()->assertJsonPath('data', ['count' => 1, 'hello' => 'Sara']);
    $nobody = $this->makeUser();
    actAs($this, $nobody);
    $this->postJson('/api/v1/ext-api/ticket_count')->assertForbidden();
    grantPermission($nobody->id, "form.{$w['form']}.view");
    // Record scope and permissions are the caller's: this one sees the form but owns no records.
    $this->postJson('/api/v1/ext-api/ticket_count')->assertOk();
});

it('compiles client extensions from TypeScript and serves them only to the sandboxed frame with their integrity hash', function () {
    $w = extensionForm($this);
    actAs($this, $this->developer);
    $this->postJson('/api/v1/extensions', ['key' => 'bad_ts', 'type' => 'client_validator', 'form' => $w['form'], 'source' => 'import x from "fs"; lcf.validator(() => ({ a: String(x) }))'])
        ->assertStatus(422)->assertJsonValidationErrors('source');
    actAs($this, $this->admin);
    shipExtension($this, ['key' => 'subject_check', 'type' => 'client_validator', 'form' => $w['form'],
        'source' => "lcf.validator((values: Record<string, unknown>) => (String(values.subject ?? '').length < 3 ? { subject: 'Too short' } : {}))"]);
    $list = $this->getJson("/api/v1/forms/{$w['form']}/client-extensions")->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0])->toMatchArray(['key' => 'subject_check', 'type' => 'client_validator']);
    $this->flushSession();
    $frame = $this->get('/ext/frame?v='.$list[0]['version'])->assertOk();
    $csp = (string) $frame->headers->get('Content-Security-Policy');
    expect($csp)->toContain("connect-src 'none'")->toContain("frame-ancestors 'self'")
        ->and($frame->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    $js = $this->get('/ext/b/'.$list[0]['version'].'.js')->assertOk();
    expect($js->getContent())->toContain('Too short')->not->toContain('Record<string')
        ->and($frame->getContent())->toContain('integrity="sha256-'.base64_encode(hash('sha256', (string) $js->getContent(), true)).'"');
    $this->get('/ext/runtime.js')->assertOk()->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
});

it('leaves an existing record exactly as it was when a hook throws during an update, and shows the failure in Error Monitoring', function () {
    $w = extensionForm($this);
    shipExtension($this, ['key' => 'guard_update', 'type' => 'server_hook', 'hook_point' => 'afterSave', 'form' => $w['form'], 'timeout_ms' => 500,
        'source' => 'final class Hook implements ServerHook { public function handle(HookContext $ctx): void { if ($ctx->get("subject") === "boom") { throw new RuntimeException("printer table melted"); } } }']);
    $record = $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'Printer', 'code' => 'P-1']])->assertCreated()->json('data');
    $table = (string) DB::table('forms')->where('uuid', $w['form'])->value('table_name');
    $before = (array) DB::table($table)->where('uuid', $record['uuid'])->first();
    $audits = DB::table('audit_logs')->where('event', 'like', 'record.%')->count();

    // The hook runs after the row was written; its failure rolls the whole update back.
    $this->patchJson("/api/v1/r/{$w['form']}/{$record['uuid']}", ['values' => ['subject' => 'boom', 'code' => 'P-2'], 'row_version' => $record['row_version']])
        ->assertStatus(422)->assertJsonPath('code', 'extension_failed');
    expect((array) DB::table($table)->where('uuid', $record['uuid'])->first())->toBe($before)
        ->and(DB::table('audit_logs')->where('event', 'like', 'record.%')->count())->toBe($audits);
    $shown = $this->getJson("/api/v1/r/{$w['form']}/{$record['uuid']}")->assertOk()->json('data');
    expect($shown['values']['subject'])->toBe('Printer')->and($shown['values']['code'])->toBe('P-1')->and($shown['row_version'])->toBe($record['row_version']);

    // Error Monitoring lists it, and the record can still be saved with the version it had.
    $groups = $this->getJson('/api/v1/errors/groups?search='.urlencode('printer table melted'))->assertOk()->json('data');
    expect($groups)->not->toBeEmpty();
    $this->patchJson("/api/v1/r/{$w['form']}/{$record['uuid']}", ['values' => ['subject' => 'Printer 2'], 'row_version' => $record['row_version']])->assertOk();
});
