<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Read access to published definitions (architecture §3.1). Versions are
 * immutable, so `def:{org}:{form}:{version}` never goes stale; only the
 * current-version pointer changes on publish/rollback.
 */
final class PublishedDefinitions
{
    /** @var array<string, array<string, mixed>|null> per-request memo by form uuid */
    private array $memo = [];

    public function __construct(private readonly TenantContext $tenant) {}

    /** @return array<string, mixed>|null the current published definition of a form, by uuid */
    public function current(string $formUuid): ?array
    {
        if (array_key_exists($formUuid, $this->memo)) {
            return $this->memo[$formUuid];
        }
        $form = Form::query()->where('uuid', $formUuid)->first(['id', 'current_version_id']);
        if ($form === null || $form->current_version_id === null) {
            return $this->memo[$formUuid] = null;
        }

        return $this->memo[$formUuid] = $this->version((int) $form->id, (int) $form->current_version_id);
    }

    /** @return array<string, mixed>|null */
    public function version(int $formId, int $versionId): ?array
    {
        return Cache::remember(
            sprintf('def:%d:%d:%d', $this->tenant->organizationId(), $formId, $versionId),
            86400,
            static fn (): ?array => FormVersion::query()->whereKey($versionId)->where('form_id', $formId)->first()?->definition,
        );
    }

    public function forget(string $formUuid): void
    {
        unset($this->memo[$formUuid]);
    }
}
