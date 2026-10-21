<?php

namespace Byte5\Doormat\Tests;

use Illuminate\Support\Str;

/**
 * Notices::active($site): a published localization in that site, the publish date
 * passed, the take-down date unset or ahead - the pinned one first, then the newest.
 */
class NoticesTest extends TestCase
{
    public function test_a_published_notice_of_the_site_is_active(): void
    {
        $notice = $this->notice('de', [], now()->subHour());

        $this->assertSame([$notice->id()], $this->activeIds('de'));
        $this->assertSame([], $this->activeIds('en'));
    }

    public function test_a_draft_is_not(): void
    {
        $this->notice('de', [], now()->subHour(), published: false);

        $this->assertSame([], $this->activeIds('de'));
    }

    public function test_a_future_dated_notice_is_not(): void
    {
        $this->notice('de', [], now()->addMinute());

        $this->assertSame([], $this->activeIds('de'));
    }

    public function test_a_taken_down_notice_is_not(): void
    {
        $this->notice('de', ['expires_at' => '2026-10-21 11:59']);

        $this->assertSame([], $this->activeIds('de'));
    }

    public function test_the_take_down_time_is_read_in_the_applications_timezone(): void
    {
        $ahead = $this->notice('de', ['expires_at' => '2026-10-21 12:01']);
        $this->notice('de', ['expires_at' => '2026-10-21 11:59']);

        $this->assertSame([$ahead->id()], $this->activeIds('de'));
    }

    public function test_newest_first_with_the_pinned_one_ahead(): void
    {
        $older = $this->notice('de', ['pinned' => true], now()->subDays(2));
        $newer = $this->notice('de', [], now()->subDay());
        $oldest = $this->notice('de', [], now()->subDays(3));

        $this->assertSame([$older->id(), $newer->id(), $oldest->id()], $this->activeIds('de'));
    }

    public function test_an_unpublished_localization_is_not_active_although_its_origin_is(): void
    {
        $origin = $this->notice('en', [], now()->subHour());
        $origin->makeLocalization('de')->id((string) Str::uuid())->published(false)->saveQuietly();

        $this->assertSame([], $this->activeIds('de'));
        $this->assertSame([$origin->id()], $this->activeIds('en'));
    }

    public function test_a_localization_inherits_the_pin_from_its_origin(): void
    {
        $origin = $this->notice('en', ['pinned' => true], now()->subDays(2));
        $german = $origin->makeLocalization('de')->id((string) Str::uuid())->published(true);
        $german->saveQuietly();
        $newer = $this->notice('de', [], now()->subDay());

        $this->assertSame([$german->id(), $newer->id()], $this->activeIds('de'));
    }

    public function test_a_site_the_collection_is_not_on_gives_nothing(): void
    {
        $this->assertSame([], $this->activeIds('xx'));
    }
}
