<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\FrameworkTables;
use App\Infrastructure\Database\Naming;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use Illuminate\Support\Facades\DB;

/**
 * Physical table names of forms (architecture §11.1, ADR-0028): `f_{key}` for
 * forms and `c_{key}` for collections in the platform organization; other
 * organizations add their id (`f{org}_{key}`) because every organization's
 * tables share one database. Names are unique across the installation and
 * never collide with framework tables or tables of other forms.
 */
final class FormTables
{
    public function __construct(private readonly TenantContext $tenant, private readonly DatabaseDriver $driver) {}

    public function tableFor(Form $form, string $key): string
    {
        $prefix = $form->kind === 'collection' ? 'c' : 'f';
        $org = (int) $form->organization_id;
        $base = $org === $this->tenant->platformOrganizationId() ? "{$prefix}_{$key}" : "{$prefix}{$org}_{$key}";

        return Naming::fit($base);
    }

    /** Whether a managed table name is free: no other form claims it and no table of that name exists. */
    public function isAvailable(string $table, ?int $exceptFormId = null): bool
    {
        $claimed = DB::table('forms')->where('table_name', $table)->when($exceptFormId !== null, fn ($q) => $q->where('id', '!=', $exceptFormId))->exists();

        return ! $claimed && ! in_array(strtolower($table), array_map('strtolower', $this->driver->tables()), true);
    }

    /**
     * Tables that may be bound by a `bound` form: anything that is not a
     * framework table, not a managed record table, not an archive, and not
     * already bound by another form.
     *
     * @return list<string>
     */
    public function bindableTables(): array
    {
        $claimed = array_map('strtolower', DB::table('forms')->pluck('table_name')->all());

        return array_values(array_filter($this->driver->tables(), static function (string $t) use ($claimed): bool {
            $l = strtolower($t);

            return ! FrameworkTables::contains($l)
                && ! in_array($l, $claimed, true)
                && preg_match('/^(f\d*_|c\d*_|p_|zz_)/', $l) !== 1;
        }));
    }

    public function isFrameworkTable(string $table): bool
    {
        return FrameworkTables::contains($table);
    }
}
