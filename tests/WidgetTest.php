<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Widgets\LiveNotices;
use Statamic\Facades\Addon;
use Statamic\Facades\User;

/**
 * The dashboard's Doormat card: per language the notices live there now, "No notices active" for a
 * language without, "No Doormat notices active" when none is anywhere, a note while the master
 * switch is off, nothing for a user who may not view the notices.
 */
class WidgetTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(resource_path('addons/doormat.yaml'));

        parent::tearDown();
    }

    private function render(bool $super = true): string
    {
        $user = User::make()->email('widget-'.uniqid().'@example.com')->makeSuper();
        if (! $super) {
            $user = User::make()->email('widget-'.uniqid().'@example.com');
        }
        $user->save();
        $this->actingAs($user);

        $widget = new LiveNotices;
        $widget->setConfig([]);

        return (string) $widget->html();
    }

    public function test_it_lists_the_live_notices_per_language(): void
    {
        $this->notice('de', ['title' => 'Betriebsferien', 'type' => 'warning', 'pinned' => true]);
        $this->notice('de', ['title' => 'Entwurf'], null, false);
        $this->notice('de', ['title' => 'Geplant'], now()->addDay());

        $html = $this->render();

        $this->assertStringContainsString('Deutsch', $html);
        $this->assertStringContainsString('Betriebsferien', $html);
        $this->assertStringContainsString('Warning', $html);
        $this->assertStringContainsString('pinned', $html);
        $this->assertStringNotContainsString('Entwurf', $html);
        $this->assertStringNotContainsString('Geplant', $html);
        $this->assertStringContainsString('English', $html);
        $this->assertStringContainsString('No notices active', $html);
    }

    public function test_it_says_so_when_no_notice_is_live_anywhere(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-doormat-widget-empty', $html);
        $this->assertStringContainsString('No Doormat notices active', $html);
    }

    public function test_it_notes_the_master_switch(): void
    {
        $this->notice('de');
        Addon::get('byte5/doormat')->settings()->set(['enabled' => false])->save();

        $this->assertStringContainsString('data-doormat-widget-off', $this->render());
    }

    public function test_a_user_who_may_not_view_the_notices_gets_nothing(): void
    {
        $this->notice('de');

        $this->assertSame('', $this->render(super: false));
    }
}
