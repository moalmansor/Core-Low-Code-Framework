<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Modules\Access\AccessCache;
use App\Modules\Access\AccessResolver;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RuleRuntime;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Throwable;

/**
 * The budgets of architecture §20.2 that are not one HTTP request, timed in
 * the application container: permission resolution (cached and cold), the
 * formula set of a 100-field form, a 100 000-row import, a three-level
 * 100 000-row xlsx download, and an additive publish on the 1 000 000-row
 * table. Imports, downloads and publishing are queued exactly as from the
 * screens and run by the stack's workers; each is timed from its request to
 * the moment its job reports it finished.
 */
final class Measurements
{
    private const SAMPLES = 300;

    /** @param  array<string, mixed>  $targets */
    public function __construct(private readonly Client $api, private readonly array $targets, private readonly Closure $log) {}

    /** @return array<string, array{value: float, unit: string, detail?: array<string, mixed>}> */
    public function run(): array
    {
        // Each budget on its own: one that fails is reported (no value, its error) and the others are still measured.
        $out = [];
        $steps = [
            'permission_cached' => fn () => $this->permissions(),
            'expression_server' => fn () => $this->formulas(),
            'import_100k' => fn () => ['import_100k' => $this->import()],
            'download_3_level_xlsx' => fn () => ['download_3_level_xlsx' => $this->download()],
            'publish_additive' => fn () => ['publish_additive' => $this->publish()],
        ];
        foreach ($steps as $key => $step) {
            try {
                $out += $step();
            } catch (Throwable $e) {
                ($this->log)("  {$key} failed: ".$e->getMessage());
                $out[$key] = ['value' => null, 'unit' => '', 'error' => mb_substr($e->getMessage(), 0, 1000)];
            }
        }

        return $out;
    }

    /** @return array<string, array{value: float, unit: string}> */
    private function permissions(): array
    {
        ($this->log)('Permission resolution');
        $user = User::query()->withoutGlobalScopes()->where('email', $this->targets['users']['pool'][0]['email'])->firstOrFail();
        $key = 'form.'.$this->targets['forms']['order'].'.view';
        $resolver = app(AccessResolver::class);
        $cache = app(AccessCache::class);
        $cold = [];
        $warm = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            // Cold: the access epoch moved (a permission changed), so nothing is cached for anyone.
            $cache->bump();
            $resolver->forget();
            $t = hrtime(true);
            $resolver->allows($user, $key) || throw new RuntimeException('The pool user cannot view the measured form.');
            $cold[] = (hrtime(true) - $t) / 1e6;
            // Cached: a new request (empty in-process memo) with the shared cache warm.
            $resolver->forget();
            $t = hrtime(true);
            $resolver->allows($user, $key);
            $warm[] = (hrtime(true) - $t) / 1e6;
        }

        return [
            'permission_cached' => ['value' => self::p95($warm), 'unit' => 'ms'],
            'permission_cold' => ['value' => self::p95($cold), 'unit' => 'ms'],
        ];
    }

    /** @return array<string, array{value: float, unit: string}> */
    private function formulas(): array
    {
        ($this->log)('Formula set of the 100-field form');
        $form = Form::query()->withoutGlobalScopes()->where('uuid', $this->targets['forms']['wide'])->firstOrFail();
        $rt = app(FormRuntimes::class)->forForm($form) ?? throw new RuntimeException('The wide form is not published.');
        $user = User::query()->withoutGlobalScopes()->where('email', $this->targets['admin'])->firstOrFail();
        $rules = app(RuleRuntime::class);
        $times = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $values = [];
            for ($n = 1; $n <= 60; $n++) {
                $values[sprintf('n%02d', $n)] = (string) (($i * 7 + $n * 13) % 1000 / 10);
            }
            $t = hrtime(true);
            $result = $rules->run($rt, $values, null, 'create', $user, true);
            $times[] = (hrtime(true) - $t) / 1e6;
        }
        $expected = round(((float) $values['n01']) * ((float) $values['n02']) + (float) $values['n21'], 2);
        if (abs((float) ($result['values']['f01'] ?? $result[0]['f01'] ?? NAN) - $expected) > 0.001) {
            throw new RuntimeException('The formula set computed a wrong value.');
        }

        return ['expression_server' => ['value' => self::p95($times), 'unit' => 'ms']];
    }

    /** @return array{value: float, unit: string, detail: array<string, mixed>} */
    private function import(): array
    {
        $rows = (int) min(100_000, max(1000, round(100_000 * (float) $this->targets['scale'] * 10)));
        ($this->log)("Import of {$rows} rows");
        $path = tempnam(sys_get_temp_dir(), 'lcf-perf-').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Subject', 'Reference', 'City', 'Quantity', 'Amount', 'Order date', 'Channel', 'Urgent']));
        $channels = ['Web', 'Phone', 'Store', 'Partner'];
        for ($i = 1; $i <= $rows; $i++) {
            $writer->addRow(Row::fromValues(["Imported {$i}", "IMP-{$i}", 'City '.($i % 50), $i % 20 + 1, ($i % 9000) + 0.5, sprintf('2026-%02d-%02d', $i % 12 + 1, $i % 28 + 1), $channels[$i % 4], $i % 2 === 0 ? 'Yes' : 'No']));
        }
        $writer->close();
        $form = $this->targets['forms']['order'];
        $file = new UploadedFile($path, 'orders.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $t = microtime(true);
        $inspect = $this->api->upload("/r/{$form}/imports/inspect", ['file' => $file])['data'];
        $mapping = array_map(static fn ($c) => ['column' => $c['column'], 'field' => $c['field'], 'system' => $c['system']], $inspect['columns']);
        if (count(array_filter($mapping, static fn ($m) => $m['field'] !== null)) < 8) {
            throw new RuntimeException('The import columns were not all matched: '.json_encode($mapping));
        }
        $job = $this->api->send('POST', "/r/{$form}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert'])['data'];
        $status = $this->await(fn () => $this->api->send('GET', "/imports/{$job['uuid']}")['data'], ['completed', 'failed', 'cancelled'], 1800);
        $seconds = microtime(true) - $t;
        if ($status['status'] !== 'completed' || (int) $status['created'] !== $rows) {
            throw new RuntimeException('The import did not complete: '.json_encode($status));
        }

        return ['value' => round($seconds, 1), 'unit' => 's', 'detail' => ['rows' => $rows, 'rows_per_second' => round($rows / $seconds)]];
    }

    /** @return array{value: float, unit: string, detail: array<string, mixed>} */
    private function download(): array
    {
        ($this->log)('Three-level xlsx download');
        $form = $this->targets['forms']['order'];
        // Amounts are spread evenly over 0 … 9 999.99: this range selects a tenth of the orders.
        $filters = ['filter' => ['amount' => ['from' => 0, 'to' => 999.99]]];
        $t = microtime(true);
        $job = $this->api->send('POST', "/r/{$form}/downloads/{$this->targets['profile']}", ['format' => 'xlsx', 'filters' => $filters])['data'];
        $job = $this->await(fn () => $this->api->send('GET', "/download-jobs/{$job['uuid']}")['data'], ['completed', 'failed', 'cancelled'], 1800);
        $seconds = microtime(true) - $t;
        if ($job['status'] !== 'completed') {
            throw new RuntimeException('The download did not complete: '.json_encode($job));
        }

        return ['value' => round($seconds, 1), 'unit' => 's', 'detail' => ['rows' => $job['rows']]];
    }

    /** @return array{value: float, unit: string, detail: array<string, mixed>} */
    private function publish(): array
    {
        ($this->log)('Additive publish on the measured form');
        $form = $this->targets['forms']['order'];
        $current = $this->api->send('GET', "/forms/{$form}/draft")['data'];
        $doc = $current['document'];
        $doc['fields'][] = [
            'uuid' => (string) Str::uuid7(), 'key' => 'extra_'.date('His'), 'type' => 'text', 'group' => null, 'order' => count($doc['fields']),
            'storage' => ['nullable' => true, 'index' => 'none'], 'options' => null, 'validation' => [], 'behavior' => [], 'ui' => [], 'table' => [], 'export' => [], 'events' => [],
            'hook' => null, 'flags' => ['trackChanges' => true], 'justification' => 'inherit', 'relation' => null, 'template' => null,
            'i18n' => ['label' => ['en' => 'Extra', 'ar' => 'إضافي']],
        ];
        $this->api->send('PUT', "/forms/{$form}/draft", ['document' => $doc, 'draft_updated_at' => $current['draft_updated_at']]);
        $table = (string) Form::query()->withoutGlobalScopes()->where('uuid', $form)->value('table_name');
        $rows = DB::table($table)->count();
        $t = microtime(true);
        $impact = $this->api->send('POST', "/forms/{$form}/impact")['data'];
        $plan = $this->api->send('POST', "/forms/{$form}/publish", ['impact_hash' => $impact['impact_hash']])['data']['plan'];
        $status = $this->await(fn () => $this->api->send('GET', "/migration-plans/{$plan}")['data'], ['applied', 'failed', 'reversed', 'reverse_failed', 'error'], 600)['status'];
        $seconds = microtime(true) - $t;
        if ($status !== 'applied') {
            throw new RuntimeException("The additive publish ended as {$status}.");
        }

        return ['value' => round($seconds, 1), 'unit' => 's', 'detail' => ['rows' => $rows]];
    }

    /**
     * Polls until the job reaches one of the final states.
     *
     * @param  callable(): array<string, mixed>  $poll
     * @param  list<string>  $final
     * @return array<string, mixed>
     */
    private function await(callable $poll, array $final, int $timeoutSeconds): array
    {
        $until = microtime(true) + $timeoutSeconds;
        while (true) {
            $state = $poll();
            if (in_array($state['status'] ?? null, $final, true)) {
                return $state;
            }
            if (microtime(true) > $until) {
                throw new RuntimeException('Timed out waiting for a job: '.json_encode($state));
            }
            usleep(250_000);
        }
    }

    /** @param  list<float>  $values */
    public static function p95(array $values): float
    {
        sort($values);

        return round($values[(int) max(0, ceil(count($values) * 0.95) - 1)], 2);
    }
}
