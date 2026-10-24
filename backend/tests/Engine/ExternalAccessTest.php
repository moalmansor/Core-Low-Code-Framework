<?php

declare(strict_types=1);

use App\Modules\External\ProofOfWork;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** A published "applications" form: name (required), email, notes, a file and an internal score; received is the status on submission. */
function applicationsForm($t): array
{
    [, $form] = createForm($t, 'applications');
    $f = [
        'name' => fieldDoc('name', 'text', null, ['validation' => ['required' => true]]),
        'email' => fieldDoc('email', 'email'),
        'notes' => fieldDoc('notes', 'textarea'),
        'cv' => fieldDoc('cv', 'file'),
        'score' => fieldDoc('score', 'number'),
    ];
    saveDraft($t, $form, [], array_values($f))->assertOk();
    $new = statusDoc('new', true);
    $received = statusDoc('received');
    saveWorkflow($t, $form, [$new, $received], [transitionDoc('receive', $new, $received)])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return ['form' => $form, 'fields' => $f, 'received' => $received];
}

function externalDoc(array $app, string $runAs, array $extra = []): array
{
    return array_replace([
        'form' => $app['form'], 'slug' => 'apply', 'title' => ['en' => 'Apply', 'ar' => 'قدّم'], 'intro' => ['en' => 'Tell us about you.'], 'success_message' => ['en' => 'Thank you.'],
        'theme' => null, 'initial_status' => $app['received']['uuid'], 'fields' => [$app['fields']['name']['uuid'], $app['fields']['email']['uuid'], $app['fields']['notes']['uuid']],
        'captcha' => false, 'rate_limit_per_ip' => 100, 'opens_at' => null, 'closes_at' => null, 'daily_window' => null, 'submission_cap' => null,
        'verification' => 'none', 'run_as' => $runAs, 'active' => true, 'audience' => 'public', 'roles' => [],
    ], $extra);
}

function asVisitor($t): void
{
    app('auth')->forgetGuards();
    $t->flushSession();
}

/** Keeps the session cookie of a response for the next requests, as a browser does. */
function keepSession($t, TestResponse $res): void
{
    $cookie = $res->getCookie((string) config('session.cookie'));
    if ($cookie !== null) {
        $t->withCookie((string) config('session.cookie'), (string) $cookie->getValue());
    }
}

function solve(array $c): array
{
    for ($n = 0; $n <= $c['maxnumber']; $n++) {
        if (hash('sha256', $c['salt'].$n) === $c['challenge']) {
            return ['salt' => $c['salt'], 'number' => $n, 'challenge' => $c['challenge'], 'signature' => $c['signature']];
        }
    }
    throw new RuntimeException('unsolved');
}

/** The body of the last email queued for an address. */
function mailTo(string $to): string
{
    return (string) DB::table('email_queue')->orderByDesc('id')->get(['to', 'body_html'])->first(fn ($m) => json_decode((string) $m->to, true) === [$to])?->body_html;
}

function lastCode(string $to): string
{
    $html = mailTo($to);
    preg_match('/\b(\d{6})\b/', $html, $m);

    return $m[1];
}

it('publishes a form outside with only safe fields shown, and takes submissions through the pipeline as the run-as user', function () {
    $app = applicationsForm($this);
    $runAs = $this->admin->uuid;
    // A required field left out, a file field, or an unknown address are refused.
    $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $runAs, ['fields' => [$app['fields']['email']['uuid']]])])->assertUnprocessable()->assertJsonValidationErrors('fields');
    $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $runAs, ['fields' => [$app['fields']['name']['uuid'], $app['fields']['cv']['uuid']]])])->assertUnprocessable()->assertJsonValidationErrors('fields');
    $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $runAs, ['slug' => 'Bad Slug'])])->assertUnprocessable()->assertJsonValidationErrors('slug');
    $fields = $this->getJson('/api/v1/external-forms/fields?form='.$app['form'])->assertOk()->json('data.fields');
    expect(array_column($fields, 'key'))->toContain('name', 'email', 'notes', 'score')->not->toContain('cv');

    $x = $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $runAs, ['rate_limit_per_ip' => 3])])->assertCreated()->json('data');
    expect($x['url'])->toEndWith('/x/f/apply');
    $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $runAs)])->assertUnprocessable()->assertJsonValidationErrors('slug');

    asVisitor($this);
    $page = $this->getJson('/api/v1/x/forms/apply')->assertOk()->json('data');
    expect($page['title'])->toBe('Apply');
    $shown = array_column($page['definition']['fields'], 'key');
    sort($shown);
    expect($shown)->toBe(['email', 'name', 'notes']);
    expect($page['definition']['user']['id'])->toBeNull();
    expect(json_encode($page))->not->toContain($this->admin->email);

    // The visitor cannot set a field the form does not show.
    $done = $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => ['name' => 'Huda', 'email' => 'huda@example.com', 'score' => 99]])->assertCreated()->json('data');
    expect($done['message'])->toBe('Thank you.');
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => ['email' => 'nobody@example.com']])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => ['name' => 'Omar']])->assertCreated();
    // Three submissions from this address this hour, valid or not: the next waits.
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => ['name' => 'Sara']])->assertStatus(429);

    $this->actingAs($this->admin, 'web');
    $records = $this->getJson("/api/v1/r/{$app['form']}")->assertOk()->json('data');
    expect($records)->toHaveCount(2);
    $huda = collect($records)->firstWhere('values.name', 'Huda');
    expect($huda['values']['score'])->toBeNull();
    $table = DB::table('forms')->where('uuid', $app['form'])->value('id');
    $row = DB::table('submission_journal')->where('form_id', $table)->where('source', 'external')->where('status', 'processed')->count();
    expect($row)->toBe(2);
    expect($this->getJson("/api/v1/r/{$app['form']}/{$huda['uuid']}")->json('data.system.status.key'))->toBe('received');
    expect(DB::table('audit_logs')->where('event', 'external.form_submitted')->count())->toBe(2);
    expect(DB::table('external_forms')->where('slug', 'apply')->value('submissions_count'))->toBe(2);

    // Closed, capped or inactive forms say so.
    $x = $this->getJson("/api/v1/external-forms/{$x['uuid']}")->json('data');
    $x = $this->putJson("/api/v1/external-forms/{$x['uuid']}", ['base_hash' => $x['hash'], 'form' => externalDoc($app, $runAs, ['submission_cap' => 2, 'rate_limit_per_ip' => 100])])->assertOk()->json('data');
    asVisitor($this);
    $this->getJson('/api/v1/x/forms/apply')->assertStatus(409)->assertJsonPath('message', __('external.unavailable.full'));
    $this->actingAs($this->admin, 'web');
    $this->putJson("/api/v1/external-forms/{$x['uuid']}", ['base_hash' => $x['hash'], 'form' => externalDoc($app, $runAs, ['active' => false])])->assertOk();
    $this->putJson("/api/v1/external-forms/{$x['uuid']}", ['base_hash' => $x['hash'], 'form' => externalDoc($app, $runAs)])->assertStatus(409);
    asVisitor($this);
    $this->getJson('/api/v1/x/forms/apply')->assertNotFound();
});

it('protects a form with a password, a proof-of-work check and a one-time email code', function () {
    $app = applicationsForm($this);
    $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $this->admin->uuid, ['password' => 'open-sesame-1', 'captcha' => true, 'verification' => 'email'])])->assertCreated();
    asVisitor($this);

    $page = $this->getJson('/api/v1/x/forms/apply')->assertOk()->json('data');
    expect($page)->toMatchArray(['password' => true, 'captcha' => true, 'verification' => 'email'])->not->toHaveKey('definition');
    $this->postJson('/api/v1/x/forms/apply/unlock', ['password' => 'wrong'])->assertUnprocessable();
    expect($this->postJson('/api/v1/x/forms/apply/unlock', ['password' => 'open-sesame-1'])->assertOk()->json('data.definition.fields'))->not->toBeEmpty();

    $values = ['name' => 'Layla'];
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => $values, 'password' => 'wrong'])->assertForbidden();
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => $values, 'password' => 'open-sesame-1'])->assertUnprocessable()->assertJsonPath('code', 'captcha');
    // A forged answer fails; a solved one passes once.
    $challenge = $this->getJson('/api/v1/x/forms/apply/challenge')->assertOk()->json('data');
    expect(app(ProofOfWork::class)->verify(['number' => 1] + solve($challenge)))->toBeFalse();
    $answer = solve($challenge);

    $this->postJson('/api/v1/x/forms/apply/code', ['to' => 'not-an-email'])->assertUnprocessable();
    $this->postJson('/api/v1/x/forms/apply/code', ['to' => 'Layla@Example.com'])->assertStatus(202);
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => $values, 'password' => 'open-sesame-1', 'captcha' => $answer, 'verification' => ['to' => 'layla@example.com', 'code' => '000000']])
        ->assertUnprocessable()->assertJsonPath('code', 'verification');
    // The failed attempt used the answer up.
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => $values, 'password' => 'open-sesame-1', 'captcha' => $answer, 'verification' => ['to' => 'layla@example.com', 'code' => lastCode('layla@example.com')]])
        ->assertUnprocessable()->assertJsonPath('code', 'captcha');
    $answer = solve($this->getJson('/api/v1/x/forms/apply/challenge')->json('data'));
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => $values, 'password' => 'open-sesame-1', 'captcha' => $answer, 'verification' => ['to' => 'layla@example.com', 'code' => lastCode('layla@example.com')]])
        ->assertCreated();
    $meta = json_decode((string) DB::table('audit_logs')->where('event', 'external.form_submitted')->value('meta'), true);
    expect($meta['verified'])->toBe('layla@example.com');
});

it('lets people register, verify and wait for approval, then use account-only forms and see only their own records', function () {
    $app = applicationsForm($this);
    // External roles never appear among internal roles.
    $role = $this->postJson('/api/v1/external-roles', ['key' => 'applicant', 'name' => ['en' => 'Applicant', 'ar' => 'متقدم']])->assertCreated()->json('data.uuid');
    expect(collect($this->getJson('/api/v1/roles/options')->json('data'))->pluck('uuid')->map(fn ($u) => strtolower($u)))->not->toContain($role);
    $this->putJson('/api/v1/external-settings', ['registration_open' => true, 'registration_role' => null, 'registration_requires_approval' => true])->assertUnprocessable();
    $this->putJson('/api/v1/external-settings', ['registration_open' => true, 'registration_role' => $role, 'registration_requires_approval' => true])->assertOk();
    $this->postJson('/api/v1/external-forms', ['form' => externalDoc($app, $this->admin->uuid, ['audience' => 'accounts', 'roles' => [$role]])])->assertCreated();

    asVisitor($this);
    expect($this->getJson('/api/v1/x/portal')->json('data.registration_open'))->toBeTrue();
    $this->getJson('/api/v1/x/forms/apply')->assertOk()->assertJsonPath('data.admitted', false)->assertJsonMissingPath('data.definition');
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => ['name' => 'Anon']])->assertForbidden();

    $password = 'Str0ng!Passw0rd#2026';
    $this->postJson('/api/v1/x/portal/register', ['name' => 'Nora Ali', 'email' => 'nora@example.com', 'password' => $password, 'password_confirmation' => $password])->assertStatus(202);
    // The same address again: the same answer, no second account.
    $this->postJson('/api/v1/x/portal/register', ['name' => 'Someone', 'email' => 'nora@example.com', 'password' => $password, 'password_confirmation' => $password])->assertStatus(202);
    expect(DB::table('external_users')->where('email', 'nora@example.com')->count())->toBe(1);
    $this->postJson('/api/v1/x/portal/login', ['email' => 'nora@example.com', 'password' => $password])->assertUnprocessable()->assertJsonPath('errors.email.0', __('external.status.pending_verification'));
    $this->postJson('/api/v1/x/portal/verify', ['email' => 'nora@example.com', 'code' => lastCode('nora@example.com')])->assertOk()->assertJsonPath('data.status', 'pending_approval');
    $this->postJson('/api/v1/x/portal/login', ['email' => 'nora@example.com', 'password' => $password])->assertUnprocessable()->assertJsonPath('errors.email.0', __('external.status.pending_approval'));

    $this->actingAs($this->admin, 'web');
    $account = $this->getJson('/api/v1/external-accounts?status=pending_approval')->assertOk()->json('data.0');
    expect($account['email'])->toBe('nora@example.com');
    $this->postJson("/api/v1/external-accounts/{$account['uuid']}/decision", ['decision' => 'approve'])->assertOk()->assertJsonPath('data.status', 'approved');

    asVisitor($this);
    $this->postJson('/api/v1/x/portal/login', ['email' => 'nora@example.com', 'password' => 'wrong-password'])->assertUnprocessable()->assertJsonPath('errors.email.0', __('external.login_failed'));
    $login = $this->postJson('/api/v1/x/portal/login', ['email' => 'nora@example.com', 'password' => $password])->assertOk();
    keepSession($this, $login);
    // An external user is never an internal user.
    $this->getJson('/api/v1/me')->assertUnauthorized();
    expect($this->getJson('/api/v1/x/portal/forms')->assertOk()->json('data.0.slug'))->toBe('apply');
    $this->getJson('/api/v1/x/forms/apply')->assertOk()->assertJsonPath('data.admitted', true);
    $this->postJson('/api/v1/x/forms/apply', ['submission' => uid(), 'values' => ['name' => 'Nora', 'notes' => 'Hello']])->assertCreated();

    $mine = $this->getJson('/api/v1/x/portal/records')->assertOk()->json('data');
    expect($mine)->toHaveCount(1);
    expect($mine[0]['status']['name'])->toBe('Received');
    $detail = $this->getJson("/api/v1/x/portal/records/apply/{$mine[0]['uuid']}")->assertOk()->json('data');
    expect($detail['values'])->toHaveKey('name', 'Nora')->not->toHaveKey('score');
    $xid = DB::table('external_users')->where('email', 'nora@example.com')->value('id');
    expect(DB::table('audit_logs')->where('event', 'external.form_submitted')->value('external_user_id'))->toBe($xid);
    expect(DB::table('status_history')->where('external_user_id', $xid)->count())->toBe(1);

    // Another person's record is not reachable; a suspended account is signed out.
    $this->actingAs($this->admin, 'web');
    $other = $this->postJson("/api/v1/r/{$app['form']}", ['values' => ['name' => 'Internal']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/external-accounts/{$account['uuid']}/decision", ['decision' => 'suspend'])->assertOk();
    app('auth')->forgetGuards();
    $this->getJson("/api/v1/x/portal/records/apply/{$other}")->assertUnauthorized();
    $this->getJson('/api/v1/x/portal/records')->assertUnauthorized();
});

it('sends a record link that approves through a chosen step as its sender, once', function () {
    $w = buildWorkflowForm($this);
    $rec = $this->postJson("/api/v1/r/{$w['form']}", ['values' => ['subject' => 'Leave', 'reason' => 'Family']])->assertCreated()->json('data');
    $choices = $this->getJson("/api/v1/r/{$w['form']}/{$rec['uuid']}/sharing")->assertOk()->json('data.choices');
    expect($choices['actions'])->toContain('view', 'approve');
    expect(array_column($choices['transitions'], 'uuid'))->toBe([$w['submit']['uuid']]);
    $this->postJson("/api/v1/r/{$w['form']}/{$rec['uuid']}/links", ['link' => ['action' => 'approve', 'transition' => $w['approve']['uuid']]])->assertUnprocessable()->assertJsonValidationErrors('transition');
    $link = $this->postJson("/api/v1/r/{$w['form']}/{$rec['uuid']}/links", ['link' => ['action' => 'approve', 'transition' => $w['submit']['uuid'], 'recipient' => 'boss@example.com', 'days' => 3, 'uses' => 1]])
        ->assertCreated()->json('data');
    $token = basename($link['url']);
    expect(DB::table('access_tokens')->where('token_hash', $token)->exists())->toBeFalse();
    expect(mailTo('boss@example.com'))->toContain($link['url']);

    asVisitor($this);
    $this->getJson('/api/v1/x/links/'.str_repeat('a', 43))->assertNotFound();
    $page = $this->getJson("/api/v1/x/links/{$token}")->assertOk()->json('data');
    expect($page)->toMatchArray(['action' => 'approve', 'uses_left' => 1]);
    expect($page['transition']['comment'])->toBe('mandatory');
    expect($page['values']['subject'])->toBe('Leave');
    // The step's own rules apply: no comment, no move, and the use is given back.
    $this->postJson("/api/v1/x/links/{$token}", ['row_version' => $page['row_version']])->assertUnprocessable()->assertJsonValidationErrors('comment');
    $this->postJson("/api/v1/x/links/{$token}", ['row_version' => $page['row_version'], 'comment' => 'Approved by email'])->assertOk();
    $this->getJson("/api/v1/x/links/{$token}")->assertStatus(410);

    $this->actingAs($this->admin, 'web');
    expect($this->getJson("/api/v1/r/{$w['form']}/{$rec['uuid']}")->json('data.system.status.key'))->toBe('submitted');
    expect(DB::table('audit_logs')->where('event', 'external.link_used')->count())->toBe(1);
    $sharing = $this->getJson("/api/v1/r/{$w['form']}/{$rec['uuid']}/sharing")->json('data.links');
    expect($sharing[0]['state'])->toBe('used');

    // A view link shows the record and can be revoked.
    $view = $this->postJson("/api/v1/r/{$w['form']}/{$rec['uuid']}/links", ['link' => ['action' => 'view', 'uses' => 5]])->assertCreated()->json('data');
    $this->deleteJson("/api/v1/record-links/{$view['uuid']}")->assertNoContent();
    asVisitor($this);
    $this->getJson('/api/v1/x/links/'.basename($view['url']))->assertStatus(410)->assertJsonPath('message', __('external.link_state.revoked'));
});

it('sends a document for signature, stores the signed PDF with its hash, and closes the request', function () {
    [, $form] = createForm($this, 'contracts');
    saveDraft($this, $form, [], [fieldDoc('party', 'text', null, ['validation' => ['required' => true]])])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['party' => 'Acme']])->assertCreated()->json('data');
    $tpl = $this->postJson('/api/v1/documents/templates', ['key' => 'contract', 'type' => 'html', 'form' => $form, 'name' => ['en' => 'Contract'], 'formats' => ['pdf'],
        'design' => ['blocks' => [['type' => 'heading', 'text' => ['en' => 'Contract with {{ party }}']]]]])->assertCreated()->json('data');
    expect($this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/sharing")->json('data.templates.0.uuid'))->toBe($tpl['uuid']);

    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/signature-requests", ['signature' => ['template' => $tpl['uuid'], 'signer_type' => 'email', 'signer' => 'nope']])->assertUnprocessable()->assertJsonValidationErrors('signer');
    $req = $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/signature-requests", ['signature' => ['template' => $tpl['uuid'], 'signer_type' => 'email', 'signer' => 'signer@example.com', 'days' => 5]])
        ->assertCreated()->json('data');
    preg_match('#/x/s/([A-Za-z0-9_-]{43})#', mailTo('signer@example.com'), $m);
    $token = $m[1];

    asVisitor($this);
    expect($this->getJson("/api/v1/x/signatures/{$token}")->assertOk()->json('data.state'))->toBeNull();
    $pdf = $this->get("/api/v1/x/signatures/{$token}/document")->assertOk()->streamedContent();
    expect($pdf)->toStartWith('%PDF');
    $this->postJson("/api/v1/x/signatures/{$token}/sign", ['signature' => 'data:image/png;base64,AAAA'])->assertUnprocessable();
    $img = imagecreatetruecolor(300, 100);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    imageline($img, 10, 80, 290, 20, imagecolorallocate($img, 0, 0, 0));
    ob_start();
    imagepng($img);
    $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    $this->postJson("/api/v1/x/signatures/{$token}/sign", ['signature' => $png])->assertOk();
    $this->postJson("/api/v1/x/signatures/{$token}/sign", ['signature' => $png])->assertStatus(410);

    $this->actingAs($this->admin, 'web');
    $now = collect($this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/sharing")->json('data.signatures'))->firstWhere('uuid', $req['uuid']);
    expect($now['status'])->toBe('signed');
    $signed = $this->get("/api/v1/signature-requests/{$req['uuid']}/signed")->assertOk()->streamedContent();
    expect($signed)->toStartWith('%PDF');
    expect(hash('sha256', $signed))->toBe($now['signed_sha256']);
    expect(substr_count($signed, '/Type /Page') - substr_count($signed, '/Type /Pages'))->toBeGreaterThan(substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages'));
    expect(DB::table('audit_logs')->where('event', 'external.signed')->count())->toBe(1);
    $this->postJson("/api/v1/signature-requests/{$req['uuid']}/cancel")->assertUnprocessable();
});

it('sends a link to complete one section: only its fields change, against the version read', function () {
    [, $form] = createForm($this, 'reviews');
    $main = groupDoc('main', 'section');
    $review = groupDoc('review', 'section');
    saveDraft($this, $form, [$main, $review], [fieldDoc('subject', 'text', $main['uuid']), fieldDoc('remarks', 'textarea', $review['uuid'])])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Budget']])->assertCreated()->json('data');
    $choices = $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/sharing")->json('data.choices');
    expect(array_column($choices['sections'], 'key'))->toBe(['main', 'review']);
    $link = $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/links", ['link' => ['action' => 'complete_section', 'section' => 'review', 'uses' => 2]])->assertCreated()->json('data');
    $token = basename($link['url']);

    asVisitor($this);
    $page = $this->getJson("/api/v1/x/links/{$token}")->assertOk()->json('data');
    expect(array_column($page['definition']['fields'], 'key'))->toBe(['remarks']);
    $this->postJson("/api/v1/x/links/{$token}", ['row_version' => $page['row_version'] + 5, 'values' => ['remarks' => 'Late']])->assertStatus(409);
    $this->postJson("/api/v1/x/links/{$token}", ['row_version' => $page['row_version'], 'values' => ['remarks' => 'Looks right', 'subject' => 'Changed']])->assertOk();

    $this->actingAs($this->admin, 'web');
    $values = $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}")->json('data.values');
    expect($values)->toMatchArray(['subject' => 'Budget', 'remarks' => 'Looks right']);
    // The failed attempt gave its use back.
    expect(DB::table('access_tokens')->where('uuid', $link['uuid'])->value('uses'))->toBe(1);
});
