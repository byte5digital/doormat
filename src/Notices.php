<?php

namespace Byte5\Doormat;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;

/**
 * The notices: entries of the configured collection.
 */
final class Notices
{
    /** The collection's handle, from the addon's config. */
    public static function collection(): string
    {
        return config('doormat.collection', 'doormat_notices');
    }

    /**
     * The notices live on a site right now: a published localization in that site
     * (the dated collection keeps a future-dated one unpublished until its time),
     * whose take-down date is unset or still ahead - the pinned one first, then the
     * newest first. Once per request and site.
     *
     * @return Collection<int, EntryContract>
     */
    public static function active(string $site): Collection
    {
        return Blink::once("doormat.active.{$site}", fn () => Entry::query()
            ->where('collection', self::collection())
            ->where('site', $site)
            ->whereStatus('published')
            ->get()
            ->reject(fn (EntryContract $entry): bool => self::takenDown($entry))
            ->sortBy([
                fn (EntryContract $a, EntryContract $b): int => (int) (bool) $b->value('pinned') <=> (int) (bool) $a->value('pinned'),
                fn (EntryContract $a, EntryContract $b): int => $b->date() <=> $a->date(),
            ])
            ->values());
    }

    /** The one-value-for-every-site fields sit on the origin: value() follows it, get() would not. */
    private static function takenDown(EntryContract $entry): bool
    {
        $expiresAt = $entry->augmentedValue('expires_at')->value();

        return $expiresAt instanceof Carbon && ! $expiresAt->isFuture();
    }
}
