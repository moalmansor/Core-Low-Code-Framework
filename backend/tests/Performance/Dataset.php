<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The reference dataset of architecture §20.1, at a chosen scale (1.0 is the
 * full size): 300 departments six levels deep, 50 roles, 5 000 users, 200
 * forms, 1 000 000 records in the measured form (40 stored fields, two
 * repeaters averaging five rows, three lookups) and 20 000 000 audit rows.
 *
 * Every form, relation, permission, record rule, panel, search setting and
 * download profile is created through the API exactly as an administrator
 * would create it; only the bulk rows are generated inside the database.
 * The generated history rows sit on chains 1000–1015, outside the sixteen
 * hash chains the audit writer keeps, so they add volume without claiming to
 * be verifiable history.
 *
 * This is test tooling for an empty, throwaway database. It refuses to run
 * against a database that already holds forms.
 */
final class Dataset
{
    private const ROOTS = 5;

    private BulkRows $bulk;

    private string $reference;

    private int $org;

    /** @var array<string, array{uuid: string, table: string, base: int, count: int}> */
    private array $forms = [];

    public function __construct(private readonly Client $api, private readonly User $admin, private readonly float $scale, private readonly Closure $log)
    {
        $this->bulk = new BulkRows($log);
        $this->reference = Carbon::now('UTC')->startOfMinute()->format('Y-m-d H:i:s');
        $this->org = (int) $admin->organization_id;
    }

    /** @return array<string, mixed> the targets the load test and the measurements use */
    public function build(): array
    {
        if (Form::query()->withoutGlobalScopes()->exists()) {
            throw new RuntimeException('The database already holds forms; the performance dataset is built only on an empty installation.');
        }
        $started = microtime(true);
        $this->bulk->prepare();
        // Limits an administrator raises for bulk work (Settings → Records).
        app(SettingsService::class)->write('records', 'export_max_rows', 1_000_000);
        app(SettingsService::class)->write('records', 'import_max_rows', 100_000);

        $app = $this->api->send('POST', '/applications', ['key' => 'perf', 'name' => ['en' => 'Performance', 'ar' => 'الأداء']])['data']['uuid'];
        $departments = $this->departments();
        $roles = $this->roles();

        ($this->log)('Forms');
        $region = $this->publishForm($app, 'perf_region', [
            $this->field('name', 'text', ['table' => ['filterable' => true, 'sortable' => true, 'searchable' => true]]),
            $this->field('code', 'text'),
        ]);
        $customer = $this->publishForm($app, 'perf_customer', [
            $name = $this->field('name', 'text', ['table' => ['filterable' => true, 'sortable' => true, 'searchable' => true]]),
            $this->field('email', 'email'),
            $this->field('tier', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['gold', 'silver', 'bronze'])], 'table' => ['filterable' => true]]),
            $this->field('since', 'date'),
            $this->field('credit', 'decimal'),
        ], [['region', $region, 'name']]);
        $product = $this->publishForm($app, 'perf_product', [
            $this->field('name', 'text', ['table' => ['filterable' => true, 'searchable' => true]]),
            $this->field('sku', 'text', ['table' => ['filterable' => true]]),
            $this->field('price', 'decimal'),
            $this->field('category', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['a', 'b', 'c', 'd', 'e', 'f'])]]),
        ]);
        $order = $this->publishOrder($app, $customer, $product, $region);
        $wide = $this->publishWide($app);
        $searchable = [];
        for ($i = 1; $i <= 20; $i++) {
            $key = sprintf('perf_s%02d', $i);
            $searchable[$key] = $this->publishForm($app, $key, [
                $this->field('title', 'text', ['table' => ['searchable' => true, 'filterable' => true]]),
                $this->field('code', 'text', ['table' => ['filterable' => true]]),
                $this->field('notes', 'textarea'),
            ]);
        }
        $fillers = [];
        $fillerCount = max(5, (int) round(175 * min(1.0, $this->scale * 10)));
        for ($i = 1; $i <= $fillerCount; $i++) {
            $key = sprintf('perf_f%03d', $i);
            $fillers[$key] = $this->publishForm($app, $key, [
                $this->field('title', 'text'), $this->field('amount', 'number'), $this->field('day', 'date'), $this->field('done', 'checkbox'),
                $this->field('state', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['open', 'closed'])]]),
            ]);
        }
        ($this->log)(sprintf('  %d forms published', Form::query()->withoutGlobalScopes()->count()));

        ($this->log)('Access');
        $all = [$region, $customer, $product, $order, $wide, ...array_values($searchable), ...array_values($fillers)];
        $this->grant($roles, $all);
        // Record scope on the measured form: each role sees the records of its department tree.
        $this->recordScope($order, $roles);
        $users = $this->users($departments, $roles);

        ($this->log)('Configuration');
        $this->panels($customer, $order);
        $configs = array_column($this->api->send('GET', '/search-configs')['data'], null, 'form');
        foreach ($searchable as $form) {
            $byKey = array_column($configs[$form]['available'], 'uuid', 'key');
            $this->api->send('PUT', '/search-configs/'.$form, ['config' => ['enabled' => true, 'fields' => [['field' => $byKey['title'], 'weight' => 3], ['field' => $byKey['code'], 'weight' => 1]]]]);
        }
        $profile = $this->downloadProfile($order);
        $view = $this->listView($order);

        ($this->log)('Rows');
        $this->fill('perf_region', 300, $departments);
        $this->fill('perf_customer', $this->n(10_000), $departments);
        $this->fill('perf_product', $this->n(5_000), $departments);
        $this->fill('perf_order', $this->n(1_000_000), $departments);
        foreach (array_keys($searchable) as $key) {
            $this->fill($key, $this->n(5_000), $departments);
        }
        $this->fill('perf_wide', 100, $departments);
        foreach (array_keys($fillers) as $key) {
            $this->fill($key, 20, $departments);
        }
        $this->auditRows($this->n(20_000_000));
        ($this->log)(sprintf('Dataset ready in %.0f s', microtime(true) - $started));

        return [
            'scale' => $this->scale,
            'reference_time' => $this->reference,
            'forms' => ['region' => $region, 'customer' => $customer, 'product' => $product, 'order' => $order, 'wide' => $wide, 'searchable' => array_values($searchable)],
            'counts' => array_map(static fn (array $f) => $f['count'], $this->forms),
            'profile' => $profile,
            'view' => $view,
            'users' => $users,
            'samples' => $this->samples($users['roots']),
            'search_terms' => ['item 4242', 'item 17', 'code 99', 'title 12345'],
            'lookups' => [
                'customers' => DB::table($this->forms['perf_customer']['table'])->orderBy('id')->limit(50)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all(),
                'products' => DB::table($this->forms['perf_product']['table'])->orderBy('id')->limit(50)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all(),
                'regions' => DB::table($this->forms['perf_region']['table'])->orderBy('id')->limit(50)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all(),
            ],
        ];
    }

    private function n(int $full): int
    {
        return max(10, (int) round($full * $this->scale));
    }

    /** 300 departments, six levels deep: five roots, each node with two children. @return list<array{uuid: string, id: int, root: int}> */
    private function departments(): array
    {
        ($this->log)('Departments');
        $out = [];
        $queue = [];
        for ($r = 0; $r < self::ROOTS; $r++) {
            $uuid = $this->api->send('POST', '/departments', ['code' => sprintf('P%d', $r), 'name' => ['en' => "Division {$r}", 'ar' => "القطاع {$r}"]])['data']['uuid'];
            $out[] = ['uuid' => $uuid, 'root' => $r];
            $queue[] = [$uuid, $r, sprintf('P%d', $r)];
        }
        while (count($out) < 300 && $queue !== []) {
            [$parent, $root, $code] = array_shift($queue);
            for ($c = 0; $c < 2 && count($out) < 300; $c++) {
                $childCode = $code.'.'.$c;
                $uuid = $this->api->send('POST', '/departments', ['code' => $childCode, 'parent' => $parent, 'name' => ['en' => "Unit {$childCode}", 'ar' => "وحدة {$childCode}"]])['data']['uuid'];
                $out[] = ['uuid' => $uuid, 'root' => $root];
                $queue[] = [$uuid, $root, $childCode];
            }
        }
        $ids = DB::table('departments')->whereIn('uuid', array_column($out, 'uuid'))->pluck('id', 'uuid');
        foreach ($out as $i => $d) {
            $out[$i]['id'] = (int) $ids[$d['uuid']];
        }
        // The lookup table generated rows take their owning department from: k => department id.
        DB::getSchemaBuilder()->dropIfExists('perf_departments');
        DB::getSchemaBuilder()->create('perf_departments', static function ($t): void {
            $t->integer('k')->primary();
            $t->unsignedBigInteger('id');
        });
        DB::table('perf_departments')->insert(array_map(static fn (int $k, array $d) => ['k' => $k, 'id' => $d['id']], array_keys($out), $out));

        return $out;
    }

    /** @return list<array{uuid: string, id: int}> */
    private function roles(): array
    {
        ($this->log)('Roles');
        $out = [];
        for ($i = 1; $i <= 50; $i++) {
            $uuid = $this->api->send('POST', '/roles', ['key' => sprintf('perf_role_%02d', $i), 'name' => ['en' => "Team {$i}", 'ar' => "فريق {$i}"]])['data']['uuid'];
            $out[] = ['uuid' => $uuid, 'id' => (int) DB::table('roles')->where('uuid', $uuid)->value('id')];
        }

        return $out;
    }

    /**
     * 5 000 users in the departments, one team role each. The first 200 sit in
     * the five division roots and sign in during the load test.
     *
     * @param  list<array{uuid: string, id: int, root: int}>  $departments
     * @param  list<array{uuid: string, id: int}>  $roles
     * @return array{password: string, pool: list<array{email: string, root: int}>, roots: list<list<int>>}
     */
    private function users(array $departments, array $roles): array
    {
        ($this->log)('Users');
        $password = Str::password(20);
        $hash = Hash::make($password);
        $now = now()->format('Y-m-d H:i:s.u');
        $userRole = (int) DB::table('roles')->where('key', 'user')->value('id');
        $total = max(250, (int) round(5000 * min(1.0, $this->scale * 10)));
        $pool = [];
        for ($i = 1; $i <= $total; $i++) {
            $inPool = $i <= 200;
            $dept = $inPool ? $departments[($i - 1) % self::ROOTS] : $departments[$i % count($departments)];
            $user = new User;
            $user->forceFill([
                'name' => "Perf User {$i}", 'email' => "perf{$i}@perf.test", 'status' => 'active', 'auth_source' => 'local',
                'password' => $hash, 'password_changed_at' => now(), 'department_id' => $dept['id'], 'organization_id' => $this->org,
            ])->save();
            $role = $roles[$i % count($roles)];
            DB::table('user_roles')->insert([
                ['user_id' => $user->id, 'role_id' => $userRole, 'created_at' => $now],
                ['user_id' => $user->id, 'role_id' => $role['id'], 'created_at' => $now],
            ]);
            if ($inPool) {
                $pool[] = ['email' => $user->email, 'root' => $dept['root']];
            }
        }
        app(AccessCache::class)->bump();
        $roots = [];
        foreach ($departments as $d) {
            $roots[$d['root']][] = $d['id'];
        }

        return ['password' => $password, 'pool' => $pool, 'roots' => array_values($roots)];
    }

    /**
     * @param  list<array{uuid: string, id: int}>  $roles
     * @param  list<string>  $forms
     */
    private function grant(array $roles, array $forms): void
    {
        $permissions = DB::table('permissions')->whereIn('key', array_merge(...array_map(static fn (string $f) => ["form.{$f}.view", "form.{$f}.create", "form.{$f}.edit"], $forms)))->pluck('id', 'key');
        foreach ($roles as $role) {
            foreach ($permissions as $id) {
                PermissionAssignment::query()->create(['permission_id' => $id, 'subject_type' => 'role', 'subject_id' => $role['id'], 'effect' => 'allow', 'include_descendants' => false]);
            }
        }
        if ($permissions->count() !== count($forms) * 3) {
            throw new RuntimeException('Form permissions are missing after publishing.');
        }
        app(AccessCache::class)->bump();
    }

    /** @param  list<array{uuid: string, id: int}>  $roles */
    private function recordScope(string $form, array $roles): void
    {
        $hash = $this->api->send('GET', "/forms/{$form}/record-access-rules")['data']['hash'];
        $rules = array_map(static fn (array $r) => ['uuid' => (string) Str::uuid7(), 'subject' => ['type' => 'role', 'uuid' => $r['uuid']], 'operation' => 'all', 'scope' => 'department_tree', 'condition' => null, 'effect' => 'allow'], $roles);
        $this->api->send('PUT', "/forms/{$form}/record-access-rules", ['base_hash' => $hash, 'rules' => $rules]);
    }

    /** A customer's record page: the region (two levels), its orders, and their total. */
    private function panels(string $customer, string $order): void
    {
        $hash = $this->api->send('GET', "/forms/{$customer}/view-panels")['data']['hash'];
        $this->api->send('PUT', "/forms/{$customer}/view-panels", ['base_hash' => $hash, 'panels' => [
            ['uuid' => (string) Str::uuid7(), 'parent' => null, 'type' => 'derived_fields', 'i18n' => ['title' => ['en' => 'Region']], 'config' => ['paths' => [['region', 'name'], ['region', 'code']]], 'visibility' => null, 'order' => 0],
            ['uuid' => (string) Str::uuid7(), 'parent' => null, 'type' => 'related_table', 'i18n' => ['title' => ['en' => 'Orders']], 'config' => ['source' => $order, 'via' => 'customer', 'columns' => [['subject'], ['amount'], ['order_date']]], 'visibility' => null, 'order' => 1],
            ['uuid' => (string) Str::uuid7(), 'parent' => null, 'type' => 'summary_widget', 'i18n' => ['title' => ['en' => 'Order total']], 'config' => ['source' => $order, 'via' => 'customer', 'aggregate' => 'sum', 'field' => 'amount'], 'visibility' => null, 'order' => 2],
        ]]);
    }

    /** The measured list: ten columns, two of them through lookups (architecture §20.2). */
    private function listView(string $order): string
    {
        $hash = $this->api->send('GET', "/forms/{$order}/views")['data']['hash'];
        $paths = [['subject'], ['reference'], ['amount'], ['order_date'], ['channel'], ['urgent'], ['city'], ['quantity'], ['customer', 'name'], ['product', 'name']];
        $saved = $this->api->send('PUT', "/forms/{$order}/views", ['base_hash' => $hash, 'views' => [[
            'uuid' => (string) Str::uuid7(), 'key' => 'perf_list', 'i18n' => ['name' => ['en' => 'Orders', 'ar' => 'الطلبات']], 'default' => true, 'priority' => 0, 'pageSize' => 25,
            'defaultSort' => [], 'showTotals' => false, 'columnChooser' => true, 'globalSearch' => true, 'rowOptions' => ['view' => true, 'edit' => true, 'log' => true],
            'includeInQueues' => false,
            'columns' => array_map(static fn (array $p) => ['path' => $p, 'i18n' => ['label' => []], 'pinned' => 'none', 'visible' => true, 'sortable' => true, 'aggregate' => 'none'], $paths),
            'filters' => [],
        ]]])['data'];

        return (string) $saved['views'][0]['uuid'];
    }

    /** Orders with their customer, the customer's region (three levels), the product, and the lines on a sheet of their own. */
    private function downloadProfile(string $order): string
    {
        $col = static fn (array $path, array $extra = []) => array_replace(['kind' => 'field', 'path' => $path, 'i18n' => ['header' => []]], $extra);

        return $this->api->send('POST', "/forms/{$order}/download-profiles", ['profile' => [
            'key' => 'perf_orders', 'i18n' => ['name' => ['en' => 'Orders', 'ar' => 'الطلبات']], 'formats' => ['xlsx'], 'fileName' => '{form} {date:yyyyMMdd}',
            'xlsx' => ['styledHeader' => true, 'freezeHeader' => true, 'titleRow' => false], 'csv' => ['delimiter' => ',', 'bom' => true], 'pdf' => ['orientation' => 'landscape', 'pageNumbers' => true],
            'applyUserFilters' => true, 'allowSelected' => true, 'availableIn' => ['table_toolbar'], 'parameters' => [], 'maxRows' => 0, 'active' => true, 'filters' => [],
            'columns' => [
                $col(['subject']), $col(['reference']), $col(['amount']), $col(['order_date']),
                $col(['customer', 'name']), $col(['customer', 'tier']), $col(['customer', 'region', 'name']), $col(['product', 'name']),
                $col(['#lines', 'item'], ['toMany' => 'separate_sheet', 'sheet' => 'lines']), $col(['#lines', 'qty'], ['toMany' => 'separate_sheet', 'sheet' => 'lines']),
            ],
        ]])['data']['profile']['uuid'];
    }

    /** The measured form: 40 stored fields, two repeaters, three lookups. */
    private function publishOrder(string $app, string $customer, string $product, string $region): string
    {
        $lines = $this->group('lines');
        $payments = $this->group('payments');
        $fields = [
            $this->field('subject', 'text', ['validation' => ['required' => true], 'table' => ['searchable' => true, 'filterable' => true, 'sortable' => true]]),
            $this->field('reference', 'text', ['table' => ['searchable' => true, 'filterable' => true]]),
            $this->field('contact_name', 'text'), $this->field('contact_phone', 'tel'), $this->field('contact_email', 'email'),
            $this->field('city', 'text', ['table' => ['filterable' => true]]), $this->field('street', 'text'), $this->field('note', 'text'),
            $this->field('description', 'textarea'), $this->field('internal_notes', 'textarea'), $this->field('delivery_notes', 'textarea'), $this->field('terms', 'textarea'),
            $this->field('quantity', 'number'), $this->field('weight', 'number'), $this->field('volume', 'number'), $this->field('discount', 'percentage'),
            $this->field('score', 'number'), $this->field('items', 'number'),
            $this->field('amount', 'decimal', ['table' => ['filterable' => true, 'sortable' => true]]),
            $this->field('tax', 'decimal'), $this->field('shipping', 'decimal'), $this->field('cost', 'decimal'), $this->field('paid', 'decimal'), $this->field('balance', 'decimal'),
            $this->field('order_date', 'date', ['table' => ['filterable' => true, 'sortable' => true]]),
            $this->field('due_date', 'date'), $this->field('ship_date', 'date'), $this->field('invoice_date', 'date'), $this->field('close_date', 'date'),
            $this->field('received_at', 'datetime_local'), $this->field('confirmed_at', 'datetime_local'),
            $this->field('urgent', 'checkbox', ['table' => ['filterable' => true]]), $this->field('gift', 'checkbox'), $this->field('insured', 'checkbox'),
            $this->field('channel', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['web', 'phone', 'store', 'partner'])], 'table' => ['filterable' => true]]),
            $this->field('payment_method', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['card', 'cash', 'transfer'])]]),
            $this->field('zone', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['north', 'south', 'east', 'west', 'central'])]]),
            $this->field('item', 'text', [], $lines['uuid']), $this->field('qty', 'number', [], $lines['uuid']),
            $this->field('unit_price', 'decimal', [], $lines['uuid']), $this->field('line_date', 'date', [], $lines['uuid']),
            $this->field('method', 'select', ['options' => ['source' => 'static', 'static' => $this->options(['card', 'cash'])]], $payments['uuid']),
            $this->field('paid_amount', 'decimal', [], $payments['uuid']), $this->field('paid_on', 'date', [], $payments['uuid']), $this->field('receipt', 'text', [], $payments['uuid']),
        ];

        return $this->publishForm($app, 'perf_order', $fields, [['customer', $customer, 'name'], ['product', $product, 'name'], ['region', $region, 'name']], [$lines, $payments]);
    }

    /** A 100-field form: 60 inputs and 40 formulas over them. */
    private function publishWide(string $app): string
    {
        $fields = [];
        for ($i = 1; $i <= 60; $i++) {
            $fields[] = $this->field(sprintf('n%02d', $i), 'number');
        }
        $ref = static fn (string $key) => ['k' => 'ref', 'scope' => 'record', 'path' => [$key]];
        for ($i = 1; $i <= 40; $i++) {
            // f_i = n_i × n_(i+1) + n_(i+20), rounded to two places.
            $expr = ['k' => 'call', 'fn' => 'round', 'args' => [
                ['k' => 'bin', 'op' => '+', 'a' => ['k' => 'bin', 'op' => '*', 'a' => $ref(sprintf('n%02d', $i)), 'b' => $ref(sprintf('n%02d', $i + 1))], 'b' => $ref(sprintf('n%02d', $i + 20))],
                ['k' => 'lit', 't' => 'number', 'v' => '2'],
            ]];
            $fields[] = $this->field(sprintf('f%02d', $i), 'formula', ['behavior' => ['formula' => $expr]]);
        }

        return $this->publishForm($app, 'perf_wide', $fields);
    }

    /**
     * Creates, saves and publishes a form; $lookups are [key, target form, display field key].
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array{0: string, 1: string, 2: string}>  $lookups
     * @param  list<array<string, mixed>>  $groups
     */
    private function publishForm(string $app, string $key, array $fields, array $lookups = [], array $groups = []): string
    {
        $form = $this->api->send('POST', '/forms', ['kind' => 'form', 'key' => $key, 'application' => $app, 'name' => ['en' => Str::headline($key), 'ar' => $key]])['data']['uuid'];
        $relations = [];
        foreach ($lookups as [$lkey, $target, $display]) {
            $targetDoc = $this->api->send('GET', "/forms/{$target}/draft")['data']['document'];
            $displayUuid = collect($targetDoc['fields'])->firstWhere('key', $display)['uuid'];
            $relation = ['uuid' => (string) Str::uuid7(), 'key' => $lkey, 'type' => 'many_to_one', 'target' => $target, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $displayUuid, 'value' => null, 'inverse' => null];
            $relations[] = $relation;
            $fields[] = $this->field($lkey, 'lookup', ['relation' => $relation['uuid'], 'table' => ['filterable' => true]]);
        }
        $current = $this->api->send('GET', "/forms/{$form}/draft")['data'];
        $doc = $current['document'];
        $doc['groups'] = $groups;
        $doc['fields'] = array_values(array_map(static fn (array $f, int $i) => ['order' => $i] + $f, $fields, array_keys($fields)));
        $doc['relations'] = $relations;
        $doc['conditions'] = [];
        $this->api->send('PUT', "/forms/{$form}/draft", ['document' => $doc, 'draft_updated_at' => $current['draft_updated_at']]);
        $impact = $this->api->send('POST', "/forms/{$form}/impact")['data'];
        $plan = $this->api->send('POST', "/forms/{$form}/publish", ['impact_hash' => $impact['impact_hash']])['data']['plan'];
        $status = $this->api->send('GET', "/migration-plans/{$plan}")['data']['status'];
        if ($status !== 'applied') {
            throw new RuntimeException("Publishing {$key} ended as {$status}.");
        }
        $model = Form::query()->withoutGlobalScopes()->where('uuid', $form)->firstOrFail();
        $this->forms[$key] = ['uuid' => $form, 'table' => (string) $model->table_name, 'base' => 0, 'count' => 0];

        return $form;
    }

    /** @return array<string, mixed> */
    private function field(string $key, string $type, array $extra = [], ?string $group = null): array
    {
        return array_replace_recursive([
            'uuid' => (string) Str::uuid7(), 'key' => $key, 'type' => $type, 'group' => $group, 'order' => 0,
            'storage' => ['nullable' => true, 'index' => 'none'],
            'options' => null, 'validation' => [], 'behavior' => [], 'ui' => [], 'table' => [], 'export' => [], 'events' => [],
            'hook' => null, 'flags' => ['trackChanges' => true], 'justification' => 'inherit', 'relation' => null, 'template' => null,
            'i18n' => ['label' => ['en' => Str::headline($key), 'ar' => $key]],
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function group(string $key): array
    {
        return [
            'uuid' => (string) Str::uuid7(), 'key' => $key, 'type' => 'repeater', 'parent' => null, 'order' => 0, 'layout' => [],
            'collapsible' => false, 'defaultState' => 'open', 'validation' => null, 'repeater' => ['minRows' => 0, 'display' => 'table'],
            'wizard' => null, 'subform' => null, 'justification' => 'inherit', 'i18n' => ['title' => ['en' => Str::headline($key), 'ar' => $key]],
        ];
    }

    /** @param  list<string>  $values @return list<array<string, mixed>> */
    private function options(array $values): array
    {
        return array_map(static fn (string $v, int $i) => ['uuid' => (string) Str::uuid7(), 'value' => $v, 'order' => $i, 'i18n' => ['label' => ['en' => ucfirst($v), 'ar' => $v]]], $values, array_keys($values));
    }

    /**
     * Fills a published form's tables: the main table with $count rows, each
     * repeater with five rows per record on average.
     *
     * @param  list<array{uuid: string, id: int, root: int}>  $departments
     */
    private function fill(string $key, int $count, array $departments): void
    {
        $form = Form::query()->withoutGlobalScopes()->where('uuid', $this->forms[$key]['uuid'])->firstOrFail();
        $rt = app(FormRuntimes::class)->forForm($form) ?? throw new RuntimeException("{$key} is not published.");
        $main = null;
        $children = [];
        foreach ($rt->definition['schema']['tables'] as $t) {
            if ($t['role'] === 'main') {
                $main = $t;
            } elseif ($t['role'] === 'child') {
                $children[] = $t;
            }
        }
        $deptCount = count($departments);
        $b = $this->bulk;
        $columns = [];
        $search = [];
        foreach ($main['columns'] as $c) {
            $expr = match ($c['name']) {
                'uuid' => $b->uuid(),
                'organization_id' => (string) $this->org,
                'form_version_id' => (string) $rt->versionId,
                'row_version' => '1',
                'owner_user_id', 'created_by', 'updated_by' => (string) $this->admin->id,
                'owner_department_id' => "(select d.id from perf_departments d where d.k = s.n % {$deptCount})",
                'created_at', 'updated_at' => $b->ago('(s.n * 61) % 63072000', $this->reference),
                'legal_hold' => '0',
                default => ($c['system'] ?? false) ? null : $this->valueOf($rt, $main, $c),
            };
            if ($expr !== null) {
                $columns[$c['name']] = $expr;
            }
            $field = isset($c['field']) ? ($rt->fields[$c['field']] ?? null) : null;
            if ($field !== null && ($field['table']['searchable'] ?? false) && in_array($c['type'], ['string', 'text'], true)) {
                $search[] = $columns[$c['name']];
            }
        }
        if ($search !== []) {
            $columns['search_text'] = 'lower('.$b->concat(array_merge(...array_map(static fn (string $s) => [$s, "' '"], $search))).')';
        }
        $base = $b->insert($main['name'], $count, $columns);
        $this->forms[$key]['base'] = $base;
        $this->forms[$key]['count'] = $count;
        foreach ($children as $child) {
            $cols = [
                'uuid' => $b->uuid(), 'organization_id' => (string) $this->org,
                'parent_id' => $base.' + '.$b->idiv('s.n - 1', 5).' + 1', 'sort_order' => '(s.n - 1) % 5',
                'created_by' => (string) $this->admin->id, 'updated_by' => (string) $this->admin->id,
                'created_at' => $b->ago('(s.n * 7) % 63072000', $this->reference), 'updated_at' => $b->ago('(s.n * 7) % 63072000', $this->reference),
            ];
            foreach ($child['columns'] as $c) {
                if (! isset($cols[$c['name']]) && $c['name'] !== 'id' && ! ($c['system'] ?? false) && ($v = $this->valueOf($rt, $child, $c)) !== null) {
                    $cols[$c['name']] = $v;
                }
            }
            $b->insert($child['name'], $count * 5, $cols);
        }
    }

    /**
     * An SQL expression over s.n for a field column, by its logical type.
     *
     * @param  array<string, mixed>  $table
     * @param  array<string, mixed>  $c
     */
    private function valueOf(mixed $rt, array $table, array $c): ?string
    {
        $b = $this->bulk;
        foreach ($table['foreignKeys'] ?? [] as $fk) {
            if ($fk['column'] === $c['name']) {
                $target = collect($this->forms)->firstWhere('table', $fk['references']);
                if ($target === null || $target['count'] === 0) {
                    throw new RuntimeException("Fill {$fk['references']} before {$table['name']}.");
                }

                return "{$target['base']} + 1 + (s.n * 7919) % {$target['count']}";
            }
        }
        $field = isset($c['field']) ? ($rt->fields[$c['field']] ?? null) : null;
        if ($field !== null && is_array($field['options']['static'] ?? null)) {
            return $b->pick('s.n', array_column($field['options']['static'], 'value'));
        }
        $key = (string) ($field['key'] ?? $c['name']);

        return match ($c['type']) {
            'string' => $b->concat(match ($key) {
                'subject', 'title', 'name' => ["'{$key} '", 's.n', "' item '", 's.n % 10000'],
                'code', 'sku', 'reference' => ["'code '", 's.n'],
                'email', 'contact_email' => ["'p'", 's.n', "'@example.test'"],
                'contact_phone' => ["'+9665'", '10000000 + s.n % 89999999'],
                default => ["'{$key} '", 's.n % 997'],
            }),
            'text' => $b->concat(["'Notes for record '", 's.n', "'. Delivered on schedule; the customer asked for a call before arrival.'"]),
            'decimal' => 'cast((s.n * 37) % 1000000 as decimal(19,4)) / 100',
            'bigint', 'int' => '(s.n * 13) % 1000',
            'date' => $b->dateOf($b->ago('(s.n * 3607) % 63072000', $this->reference)),
            'datetime' => $b->ago('(s.n * 3607) % 63072000', $this->reference),
            'time' => $b->timeOf('(s.n * 61) % 86400'),
            'bool' => 's.n % 2',
            default => ($c['nullable'] ?? true) ? null : throw new RuntimeException("No generator for {$table['name']}.{$c['name']} ({$c['type']})."),
        };
    }

    /** Twenty million history rows over the last year, outside the verifiable chains. */
    private function auditRows(int $count): void
    {
        $b = $this->bulk;
        $order = $this->forms['perf_order'];
        $formId = (int) Form::query()->withoutGlobalScopes()->where('uuid', $order['uuid'])->value('id');
        $zero = "'".str_repeat('0', 64)."'";
        $b->insert('audit_logs', $count, [
            'occurred_at' => $b->ago('(s.n * 3) % 31536000', $this->reference),
            'organization_id' => (string) $this->org,
            'chain_id' => '1000 + s.n % 16',
            'chain_seq' => 's.n',
            'event' => $b->pick('s.n', ['record.updated', 'record.created', 'record.viewed', 'workflow.transitioned']),
            'category' => "'data'",
            'object_type' => "'record'",
            'object_id' => "{$order['base']} + 1 + s.n % {$order['count']}",
            'form_id' => (string) $formId,
            'record_id' => "{$order['base']} + 1 + s.n % {$order['count']}",
            'changes' => $b->concat(["'{\"amount\":{\"from\":\"'", 's.n % 1000', "'\",\"to\":\"'", '(s.n + 7) % 1000', "'\"}}'"]),
            'actor_user_id' => (string) $this->admin->id,
            'prev_hash' => $zero,
            'hash' => $zero,
        ]);
    }

    /**
     * Record uuids in each division's scope, for the load test to open.
     *
     * @param  list<list<int>>  $roots
     * @return array<string, mixed>
     */
    private function samples(array $roots): array
    {
        $out = ['order' => [], 'customer' => []];
        $order = $this->forms['perf_order'];
        foreach ($roots as $r => $ids) {
            $out['order'][$r] = DB::table($order['table'])->whereIn('owner_department_id', $ids)->whereNull('deleted_at')
                ->orderBy('id')->limit(200)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        }
        $out['customer'] = DB::table($this->forms['perf_customer']['table'])->orderBy('id')->limit(500)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();

        return $out;
    }
}
