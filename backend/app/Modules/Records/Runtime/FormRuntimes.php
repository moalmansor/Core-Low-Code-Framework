<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Models\Form;

/** Per-request cache of FormRuntime objects for the current published versions. */
final class FormRuntimes
{
    /** @var array<string, FormRuntime|null> */
    private array $byUuid = [];

    public function __construct(private readonly PublishedDefinitions $definitions) {}

    public function forForm(Form $form): ?FormRuntime
    {
        if (array_key_exists($form->uuid, $this->byUuid)) {
            return $this->byUuid[$form->uuid];
        }
        if ($form->current_version_id === null) {
            return $this->byUuid[$form->uuid] = null;
        }
        $def = $this->definitions->version($form->id, (int) $form->current_version_id);

        return $this->byUuid[$form->uuid] = $def === null ? null : new FormRuntime($form, $def, (int) $form->current_version_id);
    }

    public function forUuid(string $uuid): ?FormRuntime
    {
        if (array_key_exists($uuid, $this->byUuid)) {
            return $this->byUuid[$uuid];
        }
        $form = Form::query()->where('uuid', $uuid)->first();

        return $form === null ? ($this->byUuid[$uuid] = null) : $this->forForm($form);
    }
}
