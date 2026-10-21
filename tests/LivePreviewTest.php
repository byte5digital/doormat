<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Entries\Notice;
use Byte5\Doormat\PreviewPage;
use Byte5\Doormat\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\CP\LivePreview;
use Statamic\Facades\Addon;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Token;
use Statamic\Facades\User;

/**
 * Live preview of a notice: the collection's entry class offers the control panel's button though
 * notices have no route, the preview target opens the picked page (else the homepage) of the
 * notice's site with Statamic's token, and with that token the endpoint and the tag show the
 * previewed notice alone, unsaved changes included, whatever its dates or state.
 */
class LivePreviewTest extends TestCase
{
    /**
     * An application that limits the classes a cached value may unserialize into (Laravel 13), set
     * where a real application has it: in its configuration, loaded before any provider registers.
     */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('cache.serializable_classes', []);

        // The list as the providers' boot sees it - when a cache store built during boot reads it once.
        $app->booting(function ($app): void {
            self::$classesAtBoot = $app['config']->get('cache.serializable_classes');
        });
    }

    /** @var array<int, string>|null */
    private static ?array $classesAtBoot = null;

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

    /**
     * What Statamic's PreviewController does with the edit form's values: supplements on the entry,
     * the entry cached under a live-preview token.
     *
     * @param  array<string, mixed>  $unsaved
     */
    private function previewToken(EntryContract $entry, array $unsaved = []): string
    {
        foreach ($unsaved as $key => $value) {
            $entry->setSupplement($key, $value);
        }
        $entry->setSupplement('live_preview', true);

        return app(LivePreview::class)->tokenize(null, $entry)->token();
    }

    private function render(string $template, string $query = ''): string
    {
        $this->app->instance('request', Request::create('/'.$query));

        return (string) Antlers::parse($template, [], true);
    }

    private function page(string $slug, bool $published = true): EntryContract
    {
        if (! Collection::findByHandle('pages')) {
            Collection::make('pages')->routes('{slug}')->sites(['de', 'en'])->save();
        }

        $page = Entry::make()->id((string) Str::uuid())->collection('pages')->locale('de')->slug($slug)->published($published)->data(['title' => $slug]);
        $page->saveQuietly();

        return $page;
    }

    public function test_a_notice_offers_live_preview_though_it_has_no_route(): void
    {
        $notice = $this->notice('en');

        $this->assertInstanceOf(Notice::class, $notice);
        $this->assertNull($notice->url());
        $this->assertSame(cp_route('collections.entries.preview.edit', ['doormat_notices', $notice->id()]), $notice->livePreviewUrl());
        $this->assertSame('http://localhost/en', $notice->previewTargets()->first()['url']);
        $this->assertSame('http://localhost', $this->notice('de')->previewTargets()->first()['url']);
    }

    public function test_the_preview_page_falls_back_to_the_homepage(): void
    {
        $this->assertNull(Settings::previewPage());
        $this->assertSame('http://localhost/en', PreviewPage::url(Site::get('en')));

        $contact = $this->page('kontakt');
        Addon::get('byte5/doormat')->settings()->set(['preview_page' => $contact->id()])->save();

        $this->assertSame($contact->id(), Settings::previewPage());
        $this->assertSame('http://localhost/kontakt', PreviewPage::url(Site::get('de')));
        $this->assertSame('http://localhost/kontakt', $this->notice('de')->previewTargets()->first()['url']);
        // No English version of the page: the English homepage.
        $this->assertSame('http://localhost/en', PreviewPage::url(Site::get('en')));

        Addon::get('byte5/doormat')->settings()->set(['preview_page' => [$this->page('entwurf', false)->id()]])->save();
        $this->assertSame('http://localhost', PreviewPage::url(Site::get('de')));

        Addon::get('byte5/doormat')->settings()->set(['preview_page' => 'gone'])->save();
        $this->assertSame('http://localhost', PreviewPage::url(Site::get('de')));
    }

    public function test_the_control_panel_opens_the_preview_page_with_a_token_the_endpoint_reads(): void
    {
        $notice = $this->notice('de', ['heading' => 'Saved']);
        $editor = tap(User::make()->email('editor@example.com')->makeSuper())->save();

        $response = $this->actingAs($editor)->postJson(cp_route('collections.entries.preview.edit', ['doormat_notices', $notice->id()]), [
            'preview' => ['title' => 'Test notice', 'heading' => 'Unsaved', 'type' => 'warning'],
        ])->assertOk();

        $this->assertStringStartsWith('http://localhost?live-preview=', $response->json('url'));
        $this->assertStringEndsWith('&token='.$response->json('token'), $response->json('url'));

        $this->getJson('/!/doormat/notices?site=de&token='.$response->json('token'))
            ->assertOk()
            ->assertJsonPath('meta.preview', true)
            ->assertJsonPath('data.0.heading', 'Unsaved')
            ->assertJsonPath('data.0.type', 'warning');
    }

    public function test_with_a_valid_token_the_endpoint_answers_the_previewed_notice_alone(): void
    {
        $this->notice('de', ['heading' => 'Live']);
        $draft = $this->notice('de', ['heading' => 'Draft'], now()->addWeek(), false);
        Addon::get('byte5/doormat')->settings()->set(['wait' => 'wait', 'delay' => 5])->save();

        $token = $this->previewToken($draft, ['heading' => 'Being edited', 'dismissible' => false]);

        $this->getJson('/!/doormat/notices?site=de&token='.$token)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id())
            ->assertJsonPath('data.0.heading', 'Being edited')
            ->assertJsonPath('data.0.dismissible', false)
            ->assertJsonPath('meta', ['view' => 'modal', 'delay' => 0, 'preview' => true]);

        // The header Statamic's own clients send works the same.
        $this->getJson('/!/doormat/notices?site=de', ['X-Statamic-Token' => $token])->assertJsonPath('data.0.heading', 'Being edited');

        // The master switch keeps visitors from the notices, not the editor from the preview.
        Addon::get('byte5/doormat')->settings()->set(['enabled' => false])->save();
        $this->getJson('/!/doormat/notices?site=de&token='.$token)->assertJsonPath('data.0.heading', 'Being edited');
    }

    public function test_an_invalid_or_unrelated_token_changes_nothing(): void
    {
        $this->notice('de', ['heading' => 'Live']);
        $page = $this->page('kontakt');
        $unrelated = $this->previewToken($page);
        $foreign = Token::make(null, 'App\Tokens\Other');
        $foreign->save();

        foreach (['', 'nope', 'not a token!', $unrelated, $foreign->token()] as $token) {
            $this->getJson('/!/doormat/notices?site=de&token='.urlencode($token))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.heading', 'Live')
                ->assertJsonPath('meta.preview', false);
        }
    }

    public function test_without_a_token_visitors_get_the_live_notices(): void
    {
        $this->notice('de', ['heading' => 'Live']);
        $this->notice('de', ['heading' => 'Draft'], null, false);

        $this->getJson('/!/doormat/notices?site=de')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.heading', 'Live')
            ->assertJsonPath('meta', ['view' => 'modal', 'delay' => 0, 'preview' => false]);
    }

    public function test_the_tag_renders_the_previewed_notice_marked_for_the_script(): void
    {
        $this->notice('de', ['heading' => 'Live']);
        $draft = $this->notice('de', ['heading' => 'Draft'], null, false);
        $fixed = $this->notice('de', ['heading' => 'Fixed', 'dismissible' => false], null, false);
        Addon::get('byte5/doormat')->settings()->set(['wait' => 'wait', 'delay' => 5])->save();

        $html = $this->render('{{ doormat_notices }}', '?token='.$this->previewToken($draft, ['heading' => 'Being edited']));

        $this->assertStringContainsString('Being edited', $html);
        $this->assertStringNotContainsString('Live', $html);
        $this->assertStringContainsString('data-doormat-delay="0" data-doormat-preview', $html);
        $this->assertStringContainsString('data-doormat-id="'.$draft->id().'" data-doormat-version', $html);
        $this->assertMatchesRegularExpression('/<section [^>]*data-doormat-preview hidden>/', $html);
        $this->assertSame('[Being edited]', $this->render('{{ doormat_notices }}[{{ heading }}]{{ /doormat_notices }}', '?token='.$this->previewToken($draft, ['heading' => 'Being edited'])));
        $this->assertSame('yes', $this->render('{{ if {doormat_notices:preview} }}yes{{ /if }}', '?token='.$this->previewToken($draft)));

        // A notice the visitor cannot close is previewed as the bar it is.
        $bar = $this->render('{{ doormat_notices }}', '?token='.$this->previewToken($fixed));
        $this->assertMatchesRegularExpression('/<div class="doormat-bar[^>]*data-doormat-preview>/', $bar);
        $this->assertStringNotContainsString('<dialog', $bar);
    }

    public function test_without_a_token_the_tag_marks_nothing(): void
    {
        $this->notice('de', ['heading' => 'Live']);

        $html = $this->render('{{ doormat_notices }}', '?token=nope');

        $this->assertStringContainsString('Live', $html);
        $this->assertStringNotContainsString('data-doormat-preview', Str::before($html, '<script'));
        $this->assertSame('', $this->render('{{ if {doormat_notices:preview} }}yes{{ /if }}'));
    }

    public function test_the_notices_class_may_come_back_from_a_cache_that_limits_its_classes(): void
    {
        $this->assertContains(Notice::class, config('cache.serializable_classes'));
    }

    /**
     * The class is on the list before any provider boots: a cache store resolved during boot reads the
     * list once, and a class added in bootAddon() came back as an incomplete object (found on a site
     * with the eloquent driver, whose boot resolves the cache).
     */
    public function test_the_notices_class_is_listed_before_the_providers_boot(): void
    {
        $this->assertContains(Notice::class, self::$classesAtBoot ?? []);
    }
}
