<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Experience\Models\Announcement;
use App\Modules\Experience\Models\HomeScreen;
use App\Modules\Experience\Models\Page;
use Illuminate\Support\ServiceProvider;

/** Pages, home screens, announcements and themes: their texts are translatable (ADR-0054). */
final class ExperienceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(TranslatableRegistry::class);
        $registry->register('page', Page::class, ['name', 'content'], 'translations.type.page');
        $registry->register('announcement', Announcement::class, ['title', 'body'], 'translations.type.announcement');
        $registry->register('home_screen', HomeScreen::class, ['name'], 'translations.type.home_screen');
    }
}
