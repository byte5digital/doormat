<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Notices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

/**
 * {{ doormat_notices }} renders the current site's active notices in the configured
 * view - the modal (a dialog for the dismissible notices, bars for the rest) or the
 * notice view (bars) - with the data attributes the script reads; as a pair it hands
 * the notices to the template, and {{ doormat_notices:script }} prints the script.
 */
class DoormatNoticesTagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Site::setCurrent('de');
    }

    /** Parsed as a trusted template: tags do not run in Antlers' user-data mode, the facade's default. */
    private function render(string $template): string
    {
        return (string) Antlers::parse($template, [], true);
    }

    public function test_nothing_active_renders_nothing(): void
    {
        $this->assertSame('', $this->render('{{ doormat_notices }}'));
    }

    public function test_the_modal_view_is_the_default_and_carries_the_data_attributes(): void
    {
        $notice = $this->notice('de', [
            'heading' => 'Closed on Friday', 'notice_type' => 'warning',
            'text' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'We are closed.']]]],
        ]);

        $html = $this->render('{{ doormat_notices }}');

        foreach ([
            '<dialog class="doormat-dialog" data-doormat-dialog', 'data-doormat-backdrop', 'doormat-notice--warning',
            'data-doormat-id="'.$notice->id().'"', 'data-doormat-version="'.$notice->lastModified()->getTimestamp().'"',
            'data-doormat-frequency="once"', 'data-doormat-dismissible="true"', '<p>We are closed.</p>', 'Closed on Friday',
            'data-doormat-prev', 'data-doormat-next', 'data-doormat-counter', 'data-doormat-close',
            '<script data-cookieconsent="ignore">',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    public function test_the_notice_view_renders_bars_by_config_or_parameter(): void
    {
        $this->notice('de', ['notice_type' => 'info']);

        config(['doormat.view' => 'notice']);
        $html = $this->render('{{ doormat_notices }}');
        $this->assertStringContainsString('doormat-bar doormat-bar--info', $html);
        $this->assertStringNotContainsString('<dialog', $html);

        config(['doormat.view' => 'modal']);
        $html = $this->render('{{ doormat_notices view="notice" }}');
        $this->assertStringContainsString('doormat-bar', $html);
        $this->assertStringNotContainsString('<dialog', $html);
    }

    public function test_a_non_dismissible_notice_is_a_bar_in_the_modal_view_and_a_dismissible_bar_has_a_close(): void
    {
        $this->notice('de', ['heading' => 'Stay', 'dismissible' => false]);
        $this->notice('de', ['heading' => 'Go']);

        $html = $this->render('{{ doormat_notices }}');
        $bar = Str::before(Str::after($html, '<div class="doormat-bar'), '</div>');  // the opening tag, the heading and the text
        $this->assertStringContainsString('Stay', $bar);
        $this->assertStringNotContainsString('data-doormat-close', $bar);
        $this->assertStringContainsString('data-doormat-dismissible="false"', $bar);
        $this->assertStringContainsString('Go', Str::after($html, '<dialog'));
        $this->assertStringNotContainsString('Stay', Str::after($html, '<dialog'));
        $this->assertLessThan(strpos($html, '<dialog'), strpos($html, 'doormat-bar'));

        config(['doormat.view' => 'notice']);
        $html = $this->render('{{ doormat_notices }}');
        $markup = Str::before($html, '<script');
        $this->assertSame(2, substr_count($markup, 'class="doormat-bar '));
        $this->assertSame(1, substr_count($markup, 'data-doormat-close'));
    }

    public function test_the_script_is_printed_once_whatever_the_notices_are(): void
    {
        $script = '<script data-cookieconsent="ignore">';

        $this->notice('de', ['heading' => 'Stay', 'dismissible' => false]);
        $this->assertSame(1, substr_count($this->render('{{ doormat_notices }}'), $script), 'bars alone in the modal view');

        $this->notice('de', ['heading' => 'Go']);
        Blink::forget('doormat.active.de');
        $this->assertSame(1, substr_count($this->render('{{ doormat_notices }}'), $script), 'bars and a dialog');
        $this->assertSame(1, substr_count($this->render('{{ doormat_notices view="notice" }}'), $script), 'the notice view');
    }

    public function test_an_unknown_view_falls_back_to_the_modal(): void
    {
        $this->notice('de');

        $this->assertStringContainsString('<dialog', $this->render('{{ doormat_notices view="bogus" }}'));
    }

    public function test_the_button_renders_only_with_a_resolvable_link(): void
    {
        $this->notice('de', ['button_label' => 'Read more', 'button_link' => 'https://example.com/x']);
        $this->notice('de', ['button_label' => 'Gone', 'button_link' => 'entry::00000000-0000-0000-0000-000000000000']);

        $html = $this->render('{{ doormat_notices }}');

        $this->assertStringContainsString('<a class="doormat-notice__button" href="https://example.com/x">Read more</a>', $html);
        $this->assertStringNotContainsString('Gone', $html);
        $this->assertSame(1, substr_count($html, 'class="doormat-notice__button"'));
    }

    public function test_heading_and_label_are_escaped(): void
    {
        $this->notice('de', ['heading' => 'A <b>bold</b> "move"']);

        $html = $this->render('{{ doormat_notices }}');

        $this->assertStringContainsString('A &lt;b&gt;bold&lt;/b&gt; &quot;move&quot;', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
    }

    public function test_the_version_follows_the_origin_when_it_is_newer(): void
    {
        $origin = $this->notice('en');
        $german = $origin->makeLocalization('de')->id((string) Str::uuid())->published(true);
        $german->updateLastModified()->saveQuietly();

        Carbon::setTestNow(Carbon::parse('2026-10-21 13:00:00', 'Europe/Berlin'));
        $origin->set('heading', 'Edited')->updateLastModified()->saveQuietly();
        Blink::forget('doormat.active.de');

        $this->assertStringContainsString(
            'data-doormat-version="'.Entry::find($origin->id())->lastModified()->getTimestamp().'"',
            $this->render('{{ doormat_notices }}')
        );
        $this->assertGreaterThan($german->lastModified()->getTimestamp(), Entry::find($origin->id())->lastModified()->getTimestamp());
    }

    public function test_another_sites_notice_is_not_rendered(): void
    {
        $this->notice('en');

        $this->assertSame('', $this->render('{{ doormat_notices }}'));
        $this->assertStringContainsString('<dialog', $this->render('{{ doormat_notices site="en" }}'));
    }

    public function test_the_pair_loops_the_notices_and_the_script_tag_prints_the_script(): void
    {
        $this->assertSame('', $this->render('{{ doormat_notices }}[{{ heading }}]{{ /doormat_notices }}'));

        $this->notice('de', ['heading' => 'One'], now()->subDays(2));
        $this->notice('de', ['heading' => 'Two'], now()->subDay());

        $this->assertSame('[Two|info|once][One|info|once]', $this->render('{{ doormat_notices }}[{{ heading }}|{{ notice_type }}|{{ frequency }}]{{ /doormat_notices }}'));

        $script = $this->render('{{ doormat_notices:script }}');
        $this->assertStringStartsWith('<script data-cookieconsent="ignore">', $script);
        $this->assertStringEndsWith('</script>', $script);
    }
}
