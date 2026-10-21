<?php

namespace Byte5\Doormat;

use Byte5\Doormat\Entries\Notice;
use Byte5\Doormat\Listeners\FlushStaticCache;
use Byte5\Doormat\Listeners\FlushStaticCacheOnSettings;
use Illuminate\Console\Scheduling\Schedule;
use Statamic\Contracts\Entries\Entry;
use Statamic\Events\AddonSettingsSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Collection;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    // The control panel bundle: the dashboard widget on Statamic's UI kit (npm run build:cp).
    protected $vite = [
        'input' => ['resources/js/cp.js'],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    // A notice's change flushes the static cache the notices render into.
    protected $listen = [
        EntrySaved::class => [FlushStaticCache::class],
        EntryDeleted::class => [FlushStaticCache::class],
        AddonSettingsSaved::class => [FlushStaticCacheOnSettings::class],
    ];

    /**
     * Live preview keeps the previewed entry in the cache. Where the application limits the classes a
     * cached value may unserialize into, the notices' entry class must be on that list - and before the
     * cache store is built, which reads the list once: in register(), not in bootAddon().
     */
    public function register(): void
    {
        parent::register();

        $this->registerSerializableClasses([Notice::class]);
    }

    public function bootAddon(): void
    {
        // The two views, to restyle or rewrite: php artisan vendor:publish --tag=doormat-views
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/doormat')], 'doormat-views');

        // The list's State column: draft, scheduled, live or taken down, in the editor's language.
        Collection::computed(Notices::collection(), 'state', fn (Entry $entry): string => __('doormat::notices.states.'.Notices::state($entry)));
    }

    /** A publish or take-down time that passed flushes the static cache within the minute. */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('doormat:flush-due')->everyMinute();
    }
}
