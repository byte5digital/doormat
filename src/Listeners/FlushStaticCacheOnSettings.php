<?php

declare(strict_types=1);

namespace Byte5\Doormat\Listeners;

use Statamic\Events\AddonSettingsSaved;
use Statamic\Facades\StaticCache;

/** The settings decide what the cached pages show: saving them flushes the static cache. */
final class FlushStaticCacheOnSettings
{
    public function handle(AddonSettingsSaved $event): void
    {
        if ($event->settings->addon()->id() === 'byte5/doormat') {
            StaticCache::flush();
        }
    }
}
