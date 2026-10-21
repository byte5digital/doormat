<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Notices;
use Statamic\Facades\Site;

/**
 * GET /!/doormat/notices answers the active notices of the asked site (the current
 * one without the parameter) as JSON in the shape the tag renders, fresh on every
 * call, for a site whose own JavaScript shows them.
 */
class NoticesEndpointTest extends TestCase
{
    private const FIELDS = ['id', 'version', 'heading', 'notice_type', 'text', 'button_label', 'button_url', 'pinned', 'dismissible', 'frequency'];

    public function test_the_endpoint_answers_the_active_notices_of_the_asked_site(): void
    {
        $this->notice('de', ['heading' => 'Older'], now()->subDays(2));
        $newer = $this->notice('de', ['heading' => 'Newer', 'notice_type' => 'warning', 'button_label' => 'More', 'button_link' => 'https://example.com'], now()->subDay());
        $this->notice('en');

        $this->getJson('/!/doormat/notices?site=de')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.heading', 'Newer')
            ->assertJsonPath('data.0.notice_type', 'warning')
            ->assertJsonPath('data.0.button_url', 'https://example.com')
            ->assertJsonPath('data.0.button_label', 'More')
            ->assertJsonPath('data.0.id', $newer->id())
            ->assertJsonPath('data.0.version', $newer->lastModified()->getTimestamp())
            ->assertJsonPath('data.0.dismissible', true)
            ->assertJsonPath('data.0.frequency', 'once')
            ->assertJsonPath('data.0.pinned', false)
            ->assertJsonPath('data.1.heading', 'Older')
            ->assertJsonPath('data.1.text', '<p>Body</p>')
            ->assertJsonPath('data.1.button_url', null);
    }

    public function test_without_the_parameter_the_current_site_answers(): void
    {
        $this->notice('de');
        Site::setCurrent('de');

        $this->getJson('/!/doormat/notices')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/!/doormat/notices?site=en')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_unknown_site_is_not_found(): void
    {
        $this->getJson('/!/doormat/notices?site=nope')->assertNotFound();
    }

    public function test_the_tag_and_the_endpoint_share_the_shape(): void
    {
        $this->notice('de');

        $this->assertSame(self::FIELDS, array_keys($this->getJson('/!/doormat/notices?site=de')->json('data.0')));
    }
}
