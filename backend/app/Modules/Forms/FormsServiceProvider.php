<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\Modules\Access\RecordScope;
use App\Modules\Assignment\DelegationResolver;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Field;
use App\Modules\Forms\Models\FieldGroup;
use App\Modules\Forms\Models\FieldOption;
use App\Modules\Forms\Models\FieldTemplate;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\MenuItem;
use App\Modules\Justification\JustificationGate;
use App\Modules\Records\Runtime\ExpressionContext;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\SubmissionJournal;
use App\Modules\Reference\Models\BusinessCalendar;
use App\Modules\Reference\Models\Currency;
use App\Modules\Reference\Models\Holiday;
use App\Modules\Reference\Models\UnitOfMeasure;
use App\Modules\Workflow\Runtime\WorkflowRuntimes;
use App\Support\Json\SchemaValidator;
use Illuminate\Support\ServiceProvider;

/** Registers the Phase 2 modules' translatable object types with the translation manager. */
final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Definition\PublishedDefinitions::class);
        $this->app->scoped(FormRuntimes::class);
        $this->app->scoped(ExpressionContext::class);
        // Phase 3 request memos: workflow indexes, record scopes, delegations, justification rules.
        $this->app->scoped(WorkflowRuntimes::class);
        $this->app->scoped(RecordScope::class);
        $this->app->scoped(DelegationResolver::class);
        $this->app->scoped(JustificationGate::class);
        $this->app->scoped(SubmissionJournal::class);
        $this->app->singleton(SchemaValidator::class);
    }

    public function boot(): void
    {
        $r = $this->app->make(TranslatableRegistry::class);
        $r->register('application', Application::class, ['name', 'description', 'maintenance_message'], 'translations.type.application');
        $r->register('menu_item', MenuItem::class, ['label'], 'translations.type.menu_item');
        $r->register('form', Form::class, ['name', 'description', 'submit_button_label'], 'translations.type.form');
        $r->register('field_group', FieldGroup::class, ['title', 'description'], 'translations.type.field_group');
        $r->register('field', Field::class, ['label', 'placeholder', 'help_text', 'tooltip', 'description', 'prefix', 'suffix', 'column_label'], 'translations.type.field');
        $r->register('field_option', FieldOption::class, ['label'], 'translations.type.field_option');
        $r->register('field_template', FieldTemplate::class, ['name', 'description'], 'translations.type.field_template');
        $r->register('business_calendar', BusinessCalendar::class, ['name'], 'translations.type.business_calendar');
        $r->register('holiday', Holiday::class, ['name'], 'translations.type.holiday');
        $r->register('currency', Currency::class, ['name'], 'translations.type.currency');
        $r->register('unit_of_measure', UnitOfMeasure::class, ['name'], 'translations.type.unit_of_measure');
        $r->register('blueprint', Blueprint::class, ['name', 'description'], 'translations.type.blueprint');
    }
}
