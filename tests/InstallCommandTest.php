<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Notices;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;

/**
 * `php please doormat:install` writes the collection and the blueprint into the folders the
 * site configured for Statamic - the collections' store and the blueprints path - named after
 * the configured handle, with every site listed; an existing file is left alone unless forced.
 */
class InstallCommandTest extends TestCase
{
    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->target = sys_get_temp_dir().'/doormat-install-'.Str::random(8);
        config(['statamic.stache.stores.collections.directory' => $this->target.'/content/collections']);
        Blueprint::setDirectory($this->target.'/blueprints');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->target);

        parent::tearDown();
    }

    public function test_the_handle_comes_from_the_config(): void
    {
        $this->assertSame('doormat_notices', Notices::collection());

        config(['doormat.collection' => 'site_notices']);

        $this->assertSame('site_notices', Notices::collection());
    }

    public function test_it_writes_both_files_into_statamics_folders_for_every_site(): void
    {
        $this->artisan('doormat:install')->expectsOutputToContain('Written:')->assertSuccessful();

        $collection = YAML::file($this->target.'/content/collections/doormat_notices.yaml')->parse();
        $this->assertSame(['de', 'en'], $collection['sites']);
        $this->assertFileExists($this->target.'/blueprints/collections/doormat_notices/notice.yaml');
    }

    public function test_a_second_run_leaves_the_files_alone_unless_forced(): void
    {
        $this->artisan('doormat:install')->assertSuccessful();
        file_put_contents($this->target.'/content/collections/doormat_notices.yaml', 'edited');

        $this->artisan('doormat:install')->expectsOutputToContain('exists')->assertSuccessful();
        $this->assertSame('edited', file_get_contents($this->target.'/content/collections/doormat_notices.yaml'));

        $this->artisan('doormat:install', ['--force' => true])->assertSuccessful();
        $this->assertStringContainsString('Doormat notices', file_get_contents($this->target.'/content/collections/doormat_notices.yaml'));
    }

    public function test_a_changed_handle_names_the_files(): void
    {
        config(['doormat.collection' => 'site_notices']);

        $this->artisan('doormat:install')->assertSuccessful();

        $this->assertFileExists($this->target.'/content/collections/site_notices.yaml');
        $this->assertFileExists($this->target.'/blueprints/collections/site_notices/notice.yaml');
    }
}
