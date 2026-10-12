<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Documents\Models\DocumentTemplate;
use Illuminate\Support\ServiceProvider;

/** Registers document templates' translatable names with the Translations screen. */
final class DocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PdfConverter::class);
    }

    public function boot(): void
    {
        $this->app->make(TranslatableRegistry::class)->register('document_template', DocumentTemplate::class, ['name'], 'translations.type.document_template');
    }
}
