<?php

namespace Byte5\Doormat\Console\Commands;

use Byte5\Doormat\Notices;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\StaticCache;

/**
 * Run every minute by the scheduler: a notice whose publish date or take-down time
 * passed within the last minute - the half-open window (now - 60 s, now], so a
 * boundary counts once - flushes the static cache, and the cached pages follow the
 * date at most a minute late. Without a running scheduler a date shows at the
 * cache's next expiry.
 */
class FlushDueCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'doormat:flush-due';

    protected $description = "Flushes the static cache when a notice's publish or take-down time passed within the last minute (run every minute by the scheduler)";

    public function handle(): int
    {
        $now = Date::now();
        $since = $now->copy()->subSeconds(60);

        $due = Entries::query()
            ->where('collection', Notices::collection())
            ->get()
            ->flatMap(fn (Entry $entry): array => [$entry->date(), $entry->augmentedValue('expires_at')->value()])
            ->filter(fn ($moment): bool => $moment instanceof Carbon && $moment->gt($since) && $moment->lte($now))
            ->count();

        if ($due === 0) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        StaticCache::flush();
        $this->info("Flushed: {$due} moment(s) passed within the last minute.");

        return self::SUCCESS;
    }
}
