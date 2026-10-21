<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Notices;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\StaticCache;

/**
 * The notices render into the statically cached page, so the addon keeps the cache
 * current: a notice's save or delete flushes it, and doormat:flush-due, scheduled
 * every minute, flushes when a publish or take-down time passed within the last
 * minute - a half-open window (now - 60 s, now], so a boundary counts once.
 */
class StaticCacheFlushTest extends TestCase
{
    /** @param  array<string, mixed>  $data */
    private function make(string $collection, Carbon $date, array $data = []): EntryContract
    {
        return Entry::make()->id((string) Str::uuid())->collection($collection)->locale('de')
            ->slug('notice-'.Str::lower(Str::random(6)))->date($date)->published(true)
            ->data($data + ['title' => 'Flush probe', 'heading' => 'Heading', 'notice_type' => 'info']);
    }

    public function test_saving_a_notice_flushes_the_static_cache(): void
    {
        StaticCache::shouldReceive('flush')->once();

        $this->make(Notices::collection(), now()->subDay())->save();
    }

    public function test_deleting_a_notice_flushes_the_static_cache(): void
    {
        $notice = $this->make(Notices::collection(), now()->subDay());
        $notice->saveQuietly();

        StaticCache::shouldReceive('flush')->once();

        $notice->delete();
    }

    public function test_another_collections_save_does_not_flush(): void
    {
        StaticCache::shouldReceive('flush')->never();

        Collection::make('pages')->sites(['de', 'en'])->save();
        Entry::make()->id((string) Str::uuid())->collection('pages')->locale('en')
            ->slug('flush-probe-'.Str::lower(Str::random(6)))->published(false)->data(['title' => 'Flush probe'])->save();
    }

    public function test_the_command_flushes_when_a_publish_or_take_down_time_passed_within_the_last_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:30', 'Europe/Berlin'));

        $this->make(Notices::collection(), Carbon::parse('2026-10-21 12:00:00', 'Europe/Berlin'))->saveQuietly();
        StaticCache::shouldReceive('flush')->once();
        $this->artisan('doormat:flush-due')->assertSuccessful();
    }

    public function test_a_take_down_time_counts_like_a_publish_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:30', 'Europe/Berlin'));

        $this->make(Notices::collection(), Carbon::parse('2026-10-21 11:58:00'), ['expires_at' => '2026-10-21 12:00'])->saveQuietly();
        StaticCache::shouldReceive('flush')->once();
        $this->artisan('doormat:flush-due')->assertSuccessful();
    }

    public function test_nothing_due_means_no_flush(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:30', 'Europe/Berlin'));

        $this->make(Notices::collection(), Carbon::parse('2026-10-21 11:58:00', 'Europe/Berlin'))->saveQuietly();
        $this->make(Notices::collection(), Carbon::parse('2026-10-21 12:00:31', 'Europe/Berlin'))->saveQuietly();
        StaticCache::shouldReceive('flush')->never();
        $this->artisan('doormat:flush-due')->expectsOutputToContain('Nothing due')->assertSuccessful();
    }

    public function test_the_window_is_half_open_so_a_boundary_counts_once(): void
    {
        $this->make(Notices::collection(), Carbon::parse('2026-10-21 12:00:00', 'Europe/Berlin'))->saveQuietly();

        Carbon::setTestNow(Carbon::parse('2026-10-21 12:01:00', 'Europe/Berlin'));
        StaticCache::shouldReceive('flush')->never();
        $this->artisan('doormat:flush-due')->assertSuccessful();
    }

    public function test_the_command_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'doormat:flush-due'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
