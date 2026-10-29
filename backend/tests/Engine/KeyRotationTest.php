<?php

declare(strict_types=1);

use App\Infrastructure\Crypto\DataKeys;
use App\Modules\Core\Settings\SettingsService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** A form with a searchable encrypted national id. @return array{form: string, table: string} */
function vaultForm(TestCase $t): array
{
    [, $form] = createForm($t, 'vault');
    saveDraft($t, $form, [], [
        fieldDoc('name', 'text'),
        fieldDoc('national_id', 'text', null, ['flags' => ['encrypted' => true, 'blindIndex' => true]]),
    ])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return ['form' => $form, 'table' => (string) DB::table('forms')->where('uuid', $form)->value('table_name')];
}

function useAppKey(string $key, array $previous = []): void
{
    config(['app.key' => $key, 'app.previous_keys' => $previous]);
    app()->forgetInstance('encrypter');
    Facade::clearResolvedInstance('encrypter');
    app()->forgetScopedInstances();
}

it('encrypts field values with the organization data key, moves them to a new key on rotation, and keeps them readable', function () {
    $v = vaultForm($this);
    $a = $this->postJson("/api/v1/r/{$v['form']}", ['values' => ['name' => 'A', 'national_id' => '1010101010']])->assertCreated()->json('data');
    $raw = (string) DB::table($v['table'])->where('uuid', $a['uuid'])->value('national_id');
    expect($raw)->toStartWith(DataKeys::PREFIX);
    $first = DataKeys::keyIdOf($raw);

    // A value written before data keys existed still opens, and is moved by the rotation.
    $b = $this->postJson("/api/v1/r/{$v['form']}", ['values' => ['name' => 'B', 'national_id' => '2020202020']])->assertCreated()->json('data');
    DB::table($v['table'])->where('uuid', $b['uuid'])->update(['national_id' => Crypt::encryptString(json_encode('2020202020'))]);
    expect($this->getJson("/api/v1/r/{$v['form']}/{$b['uuid']}")->json('data.values.national_id'))->toBe('2020202020');

    $this->artisan('keys:rotate')->assertSuccessful();
    app()->forgetScopedInstances();
    $keys = DB::table('encryption_keys')->where('purpose', 'fields')->orderBy('id')->get(['key_id', 'status']);
    expect($keys->pluck('status')->all())->toBe(['retired', 'active']);
    foreach ([$a, $b] as $r) {
        $cipher = (string) DB::table($v['table'])->where('uuid', $r['uuid'])->value('national_id');
        expect(DataKeys::keyIdOf($cipher))->toBe($keys->last()->key_id)->not->toBe($first);
    }
    expect($this->getJson("/api/v1/r/{$v['form']}/{$a['uuid']}")->json('data.values.national_id'))->toBe('1010101010')
        ->and($this->getJson("/api/v1/r/{$v['form']}/{$b['uuid']}")->json('data.values.national_id'))->toBe('2020202020');
    expect(DB::table('audit_logs')->where('event', 'security.key_rotated')->exists())->toBeTrue();
});

it('recomputes blind indexes under a new key, so exact search on an encrypted field keeps working', function () {
    $v = vaultForm($this);
    $a = $this->postJson("/api/v1/r/{$v['form']}", ['values' => ['name' => 'A', 'national_id' => '3030303030']])->assertCreated()->json('data');
    $before = (string) DB::table($v['table'])->where('uuid', $a['uuid'])->value('national_id__bidx');
    $this->artisan('keys:rotate', ['--purpose' => 'blind_index'])->assertSuccessful();
    app()->forgetScopedInstances();
    $after = (string) DB::table($v['table'])->where('uuid', $a['uuid'])->value('national_id__bidx');
    expect($after)->not->toBe($before)
        ->and(app(DataKeys::class)->blindIndex((int) $this->admin->organization_id, '3030303030'))->toBe($after);
});

it('re-encrypts everything sealed with the old master key after APP_KEY changes', function () {
    $v = vaultForm($this);
    $a = $this->postJson("/api/v1/r/{$v['form']}", ['values' => ['name' => 'A', 'national_id' => '4040404040']])->assertCreated()->json('data');
    app(SettingsService::class)->write('mail', 'password', 'smtp-secret');
    $old = (string) config('app.key');
    $new = 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));

    // The new key in place, the old one only as a previous key: everything still opens, then is re-sealed.
    useAppKey($new, [$old]);
    $this->artisan('keys:rewrap')->assertSuccessful();

    // Without the old key at all, everything still opens.
    useAppKey($new);
    app(SettingsService::class)->forgetGroup('mail');
    expect(app(SettingsService::class)->get('mail', 'password'))->toBe('smtp-secret')
        ->and($this->getJson("/api/v1/r/{$v['form']}/{$a['uuid']}")->json('data.values.national_id'))->toBe('4040404040');
    expect(DB::table('audit_logs')->where('event', 'security.master_key_rewrapped')->exists())->toBeTrue();
    // Back to the suite's key the same way: everything sealed with the test key is re-sealed with it.
    useAppKey($old, [$new]);
    $this->artisan('keys:rewrap')->assertSuccessful();
    useAppKey($old);
});
