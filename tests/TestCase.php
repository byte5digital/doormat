<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Install\Definitions;
use Byte5\Doormat\Notices;
use Byte5\Doormat\ServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

/**
 * A Statamic with two sites, German and English, and the notices collection with the
 * addon's own blueprint, written by Definitions into a temporary folder - the stub is
 * what every test runs against. Entries live in the Stache and are never written to disk.
 */
abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected string $definitions;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.system.multisite', true);
        $app['config']->set('app.timezone', 'Europe/Berlin');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'de' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/'],
            'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => '/en/'],
        ]);

        $this->definitions = sys_get_temp_dir().'/doormat-tests-'.Str::random(8);
        (new Definitions($this->definitions.'/collections', $this->definitions.'/blueprints/collections'))
            ->write(Notices::collection(), ['de', 'en'], false);
        Blueprint::setDirectory($this->definitions.'/blueprints');

        Collection::make(Notices::collection())
            ->title('Doormat notices')
            ->sites(['de', 'en'])
            ->dated(true)
            ->pastDateBehavior('public')
            ->futureDateBehavior('private')
            ->requiresSlugs(false)
            ->save();

        // Noon in the application's timezone: the dates an editor enters are read in it.
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->definitions);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $data */
    protected function notice(string $site, array $data = [], ?Carbon $date = null, bool $published = true): EntryContract
    {
        $entry = Entry::make()->id((string) Str::uuid())->collection(Notices::collection())->locale($site)
            ->date($date ?? Carbon::now()->subDay())->published($published)
            ->data($data + [
                'title' => 'Test notice', 'heading' => 'Heading', 'type' => 'info',
                'text' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Body']]]],
            ]);
        // As the control panel saves: the last change is recorded, which is the notice's version.
        $entry->updateLastModified()->saveQuietly();
        Blink::forget('doormat.active.'.$site);

        return $entry;
    }

    /** @return list<string> */
    protected function activeIds(string $site): array
    {
        Blink::forget('doormat.active.'.$site);

        return Notices::active($site)->map->id()->values()->all();
    }
}
