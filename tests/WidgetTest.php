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

    /** @return array<string, mixed>|null the props of the widget's Vue component, null when it shows nothing */
    private function props(bool $super = true): ?array
    {
        $user = User::make()->email('widget-'.uniqid().'@example.com');
        if ($super) {
            $user->makeSuper();
        }
        $user->save();
        $this->actingAs($user);

        $widget = new LiveNotices;
        $widget->setConfig([]);
        $component = $widget->component();

        if ($component === null) {
            return null;
        }

        $this->assertSame('doormat-live-notices-widget', $component->toArray()['name']);

        return $component->toArray()['props'];
    }

    public function test_it_lists_the_live_notices_per_language(): void
    {
        $this->notice('de', ['title' => 'Betriebsferien', 'type' => 'warning', 'pinned' => true]);
        $this->notice('de', ['title' => 'Entwurf'], null, false);
        $this->notice('de', ['title' => 'Geplant'], now()->addDay());

        $props = $this->props();

        $this->assertTrue($props['any']);
        $this->assertSame(['Deutsch', 'English'], array_column($props['sites'], 'name'));
        $this->assertSame([['title' => 'Betriebsferien', 'type' => 'Warning', 'pinned' => true]], array_map(
            fn (array $notice) => array_intersect_key($notice, array_flip(['title', 'type', 'pinned'])),
            $props['sites'][0]['notices']
        ));
        $this->assertSame([], $props['sites'][1]['notices']);
        $this->assertSame('No notices active', $props['texts']['noneHere']);
        $this->assertStringEndsWith('/collections/doormat_notices', $props['url']);
    }

    public function test_it_says_so_when_no_notice_is_live_anywhere(): void
    {
        $props = $this->props();

        $this->assertFalse($props['any']);
        $this->assertSame('No Doormat notices active', $props['texts']['none']);
    }

    public function test_it_notes_the_master_switch(): void
    {
        $this->notice('de');
        Addon::get('byte5/doormat')->settings()->set(['enabled' => false])->save();

        $this->assertFalse($this->props()['enabled']);
    }

    public function test_a_user_who_may_not_view_the_notices_gets_nothing(): void
    {
        $this->notice('de');

        $this->assertNull($this->props(super: false));
    }
}
