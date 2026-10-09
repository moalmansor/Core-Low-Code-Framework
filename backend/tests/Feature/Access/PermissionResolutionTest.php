<?php

declare(strict_types=1);

use App\Modules\Access\AccessCache;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\Models\Role;
use App\Modules\Core\Settings\SettingsRegistry;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Organization\DepartmentTree;
use App\Modules\Organization\Models\Department;
use Illuminate\Support\Facades\Cache;

function grant(string $subjectType, int $subjectId, string $effect, string $permission = 'system.manage_reports', bool $descendants = false, $validUntil = null): void
{
    PermissionAssignment::query()->updateOrCreate(
        ['permission_id' => Permission::query()->where('key', $permission)->value('id'), 'subject_type' => $subjectType, 'subject_id' => $subjectId],
        ['effect' => $effect, 'include_descendants' => $descendants, 'valid_until' => $validUntil],
    );
    app(AccessCache::class)->bump();
    app(AccessResolver::class)->forget();
}

function department(?Department $parent = null): Department
{
    static $n = 0;
    $n++;
    $d = new Department(['code' => "D{$n}", 'parent_id' => $parent?->id, 'is_active' => true, 'sort_order' => 0]);
    app(DepartmentTree::class)->create($d);

    return $d;
}

/*
 * The tier walk of specification §4.11 over every combination of grants a
 * user can receive from the three tiers, each tier being: no grant, allow,
 * deny, or allow+deny (two sources in the same tier).
 */
dataset('tier combinations', function () {
    $states = ['none', 'allow', 'deny', 'both'];
    foreach ($states as $dept) {
        foreach ($states as $role) {
            foreach ($states as $user) {
                // Expected: the most specific tier with any grant decides; within a tier deny wins.
                $expected = false;
                foreach ([$dept, $role, $user] as $tier) {
                    if ($tier === 'allow') {
                        $expected = true;
                    } elseif ($tier === 'deny' || $tier === 'both') {
                        $expected = false;
                    }
                }
                yield "dept={$dept} role={$role} user={$user}" => [$dept, $role, $user, $expected];
            }
        }
    }
});

it('resolves the tier walk', function (string $dept, string $role, string $user, bool $expected) {
    $grants = [];
    $add = function (string $tier, string $state) use (&$grants): void {
        if ($state === 'allow' || $state === 'both') {
            $grants[] = ['tier' => $tier, 'effect' => 'allow'];
        }
        if ($state === 'deny' || $state === 'both') {
            $grants[] = ['tier' => $tier, 'effect' => 'deny'];
        }
    };
    $add('department', $dept);
    $add('role', $role);
    $add('user', $user);
    [$granted] = app(AccessResolver::class)->decide($grants);
    expect($granted)->toBe($expected);

    // A hard deny anywhere overrides the result.
    [$withHard] = app(AccessResolver::class)->decide([...$grants, ['tier' => 'department', 'effect' => 'hard_deny']]);
    expect($withHard)->toBeFalse();
})->with('tier combinations');

it('denies by default', function () {
    $user = $this->makeUser([]);
    expect(app(AccessResolver::class)->allows($user, 'system.manage_reports'))->toBeFalse();
});

it('applies grants from department, role and user tiers stored in the database', function () {
    $parent = department();
    $child = department($parent);
    $role = Role::query()->create(['key' => 'analyst', 'audience' => 'internal']);
    $user = $this->makeUser([], ['department_id' => $child->id]);
    $user->roles()->attach($role->id, ['created_at' => now()->format('Y-m-d H:i:s.u')]);
    $resolver = app(AccessResolver::class);

    grant('department', $parent->id, 'allow');
    expect($resolver->allows($user, 'system.manage_reports'))->toBeFalse('ancestor grant without descendants must not reach a sub-department');
    grant('department', $parent->id, 'allow', descendants: true);
    expect($resolver->allows($user, 'system.manage_reports'))->toBeTrue();

    grant('role', $role->id, 'deny');
    expect($resolver->allows($user, 'system.manage_reports'))->toBeFalse('role tier overrides department tier');

    grant('user', $user->id, 'allow');
    expect($resolver->allows($user, 'system.manage_reports'))->toBeTrue('user tier overrides role deny');

    grant('department', $child->id, 'hard_deny');
    expect($resolver->allows($user, 'system.manage_reports'))->toBeFalse('hard deny cannot be overridden');
    expect($resolver->explain($user, 'system.manage_reports')['decided_by'])->toBe('hard_deny');
});

it('ignores expired grants and roles outside their validity window', function () {
    $role = Role::query()->create(['key' => 'temp', 'audience' => 'internal']);
    $user = $this->makeUser([]);
    $user->roles()->attach($role->id, ['created_at' => now()->format('Y-m-d H:i:s.u'), 'valid_until' => now()->addHour()->format('Y-m-d H:i:s.u')]);
    grant('role', $role->id, 'allow');
    expect(app(AccessResolver::class)->allows($user, 'system.manage_reports'))->toBeTrue();
    $this->travel(2)->hours();
    app(AccessResolver::class)->forget();
    app(AccessCache::class)->bump();
    expect(app(AccessResolver::class)->allows($user, 'system.manage_reports'))->toBeFalse();

    grant('user', $user->id, 'allow', validUntil: now()->addMinutes(5));
    expect(app(AccessResolver::class)->allows($user, 'system.manage_reports'))->toBeTrue();
    $this->travel(10)->minutes();
    app(AccessResolver::class)->forget();
    app(AccessCache::class)->bump();
    expect(app(AccessResolver::class)->allows($user, 'system.manage_reports'))->toBeFalse();
});

it('grants nothing to inactive users, whatever their roles', function () {
    $admin = $this->superAdmin();
    $admin->forceFill(['status' => 'suspended'])->save();
    expect(app(AccessResolver::class)->effective($admin))->toBe([]);
});

it('gives the seeded roles their catalog grants through ordinary rows', function () {
    $super = $this->superAdmin();
    $admin = $this->makeUser(['admin']);
    $resolver = app(AccessResolver::class);
    expect($resolver->allows($super, 'system.manage_code'))->toBeTrue()
        ->and($resolver->allows($admin, 'system.manage_users'))->toBeTrue()
        ->and($resolver->allows($admin, 'system.manage_code'))->toBeFalse()
        ->and(PermissionAssignment::query()->where('subject_type', 'role')->count())->toBeGreaterThan(90);
});

it('keeps the access epoch moving forward when the cache loses it (mirrored in settings)', function () {
    $cache = app(AccessCache::class);
    $cache->bump();
    $cache->bump();
    $before = $cache->epoch();
    expect($before)->toBeGreaterThan(1);

    Cache::forget('acc:epoch:'.app(TenantContext::class)->organizationId());
    expect($cache->epoch())->toBe($before);
    $cache->bump();
    expect($cache->epoch())->toBe($before + 1);
    // The mirror is internal: it never appears among the editable settings groups.
    expect(app(SettingsRegistry::class)->groups())->not->toContain('access');
});
