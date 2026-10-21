<?php

namespace Byte5\Doormat;

use Byte5\Doormat\Listeners\FlushStaticCache;
use Illuminate\Console\Scheduling\Schedule;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Collection;
use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    // A notice's change flushes the static cache the notices render into.
    protected $listen = [
        EntrySaved::class => [FlushStaticCache::class],
        EntryDeleted::class => [FlushStaticCache::class],
    ];

    public function bootAddon(): void
    {
        // The two views, to restyle or rewrite: php artisan vendor:publish --tag=doormat-views
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/doormat')], 'doormat-views');

        // Tools > Doormat leads to the notices; Statamic's collection permissions decide who sees it.
        // While the collection is not installed (php please doormat:install) there is nothing to link.
        Nav::extend(function ($nav): void {
            $collection = Collection::findByHandle(Notices::collection());

            if ($collection === null) {
                return;
            }

            $nav->tools('Doormat')
                ->url($collection->showUrl())
                ->icon('megaphone')
                ->can('view', $collection);
        });
    }

    /** A publish or take-down time that passed flushes the static cache within the minute. */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('doormat:flush-due')->everyMinute();
    }
}
