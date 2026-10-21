<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Actions\ShowAgain;
use Byte5\Doormat\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Addon;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\StaticCache;

/**
 * What an editor works with in the control panel: the list's State column, the addon's
 * settings page (master switch, presentation, delay) and the "Show again to everyone" action.
 */
class ControlPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Site::setCurrent('de');
    }

    protected function tearDown(): void
    {
        @unlink(resource_path('addons/doormat.yaml'));

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $values */
    private function settings(array $values): void
    {
        Addon::get('byte5/doormat')->settings()->set($values)->save();
    }

    private function render(string $template): string
    {
        return (string) Antlers::parse($template, [], true);
    }

    public function test_the_state_column_names_where_a_notice_stands(): void
    {
        $state = fn ($entry) => (string) $entry->augmentedValue('state')->value();

        $this->assertSame('Live', $state($this->notice('de')));
        $this->assertSame('Draft', $state($this->notice('de', [], null, false)));
        $this->assertSame('Scheduled', $state($this->notice('de', [], now()->addDay())));
        $this->assertSame('Taken down', $state($this->notice('de', ['expires_at' => '2026-10-21 11:00'])));
    }

    public function test_the_blueprint_lists_the_columns_an_editor_scans(): void
    {
        $fields = Collection::findByHandle('doormat_notices')->entryBlueprint()->fields()->all();
        $listable = $fields->filter(fn ($field) => $field->isListable())->keys()->all();

        foreach (['title', 'state', 'type', 'date', 'expires_at', 'pinned'] as $handle) {
            $this->assertContains($handle, $listable, $handle);
        }
        $this->assertNotContains('text', $listable);
    }

    public function test_the_settings_default_to_on_the_config_and_no_delay(): void
    {
        $this->assertTrue(Settings::enabled());
        $this->assertSame('modal', Settings::view());
        $this->assertSame(0, Settings::delay());

        config(['doormat.view' => 'notice']);
        $this->assertSame('notice', Settings::view());
    }

    public function test_the_master_switch_silences_the_tag_and_the_endpoint(): void
    {
        $this->notice('de');
        $this->settings(['enabled' => false]);

        $this->assertSame('', $this->render('{{ doormat_notices }}'));
        $this->getJson('/!/doormat/notices?site=de')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_presentation_and_the_delay_reach_the_tag_and_the_endpoint(): void
    {
        $this->notice('de');

        $this->settings(['view' => 'modal', 'wait' => 'wait', 'delay' => 3]);
        $this->assertStringContainsString('data-doormat-delay="3"', $this->render('{{ doormat_notices }}'));
        $this->getJson('/!/doormat/notices?site=de')->assertJsonPath('meta.view', 'modal')->assertJsonPath('meta.delay', 3);

        $this->settings(['view' => 'notice']);
        $html = $this->render('{{ doormat_notices }}');
        $this->assertStringContainsString('doormat-bar', $html);
        $this->assertStringNotContainsString('<dialog', $html);
    }

    public function test_the_delay_is_a_choice_with_seconds(): void
    {
        $this->settings(['wait' => 'no_wait', 'delay' => 5]);
        $this->assertSame(0, Settings::delay());

        $this->settings(['wait' => 'wait', 'delay' => 5]);
        $this->assertSame(5, Settings::delay());
    }

    public function test_the_seconds_are_checked_only_while_wait_is_picked(): void
    {
        $fields = Addon::get('byte5/doormat')->settingsBlueprint()->fields();

        $fields->addValues(['wait' => 'no_wait', 'delay' => 0])->validator()->validate();

        $this->expectException(ValidationException::class);
        $fields->addValues(['wait' => 'wait', 'delay' => 0])->validator()->validate();
    }

    public function test_the_presentation_is_required_while_notices_are_shown(): void
    {
        $fields = Addon::get('byte5/doormat')->settingsBlueprint()->fields();

        $fields->addValues(['enabled' => false, 'view' => null, 'wait' => 'no_wait'])->validator()->validate();

        $this->expectException(ValidationException::class);
        $fields->addValues(['enabled' => true, 'view' => null, 'wait' => 'no_wait'])->validator()->validate();
    }

    public function test_saving_the_settings_flushes_the_static_cache(): void
    {
        StaticCache::shouldReceive('flush')->once();

        $this->settings(['delay' => 2]);
    }

    public function test_show_again_gives_the_notice_a_new_version(): void
    {
        $notice = $this->notice('de');
        $before = $notice->lastModified()->getTimestamp();

        Carbon::setTestNow(now()->addMinutes(5));
        (new ShowAgain)->run(collect([$notice]), []);

        $this->assertGreaterThan($before, Entry::find($notice->id())->lastModified()->getTimestamp());
    }

    public function test_show_again_is_offered_on_notices_only(): void
    {
        Collection::make('pages')->sites(['de'])->save();
        $page = Entry::make()->id((string) Str::uuid())->collection('pages')->locale('de')->slug('probe')->data(['title' => 'Probe']);

        $action = new ShowAgain;
        $this->assertTrue($action->visibleTo($this->notice('de')));
        $this->assertFalse($action->visibleTo($page));
    }
}
