<?php

namespace Byte5\Doormat\Listeners;

use Byte5\Doormat\Notices;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\StaticCache;

/**
 * The notices render into the statically cached pages: a notice's save, publish,
 * unpublish or delete flushes the static cache, so the change shows at once. Any
 * other collection's entries are none of its business.
 */
class FlushStaticCache
{
    public function handle(EntrySaved|EntryDeleted $event): void
    {
        if ($event->entry->collectionHandle() === Notices::collection()) {
            StaticCache::flush();
        }
    }
}
