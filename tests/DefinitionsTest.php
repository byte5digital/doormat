<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Entries\Notice;
use Byte5\Doormat\Install\Definitions;
use Illuminate\Filesystem\Filesystem;
use Statamic\Facades\YAML;

/**
 * The addon writes its collection and blueprint into an application from its
 * stubs: named after the handle, the installation's sites listed, an existing
 * file left alone unless forced.
 */
class DefinitionsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/doormat-definitions-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function definitions(): Definitions
    {
        return new Definitions($this->dir.'/collections', $this->dir.'/blueprints');
    }

    public function test_it_writes_the_collection_and_the_blueprint_for_the_given_sites(): void
    {
        $result = $this->definitions()->write('doormat_notices', ['de', 'en'], false);

        $this->assertSame([$this->dir.'/collections/doormat_notices.yaml', $this->dir.'/blueprints/doormat_notices/notice.yaml'], $result['written']);
        $this->assertSame([], $result['skipped']);

        $collection = YAML::file($this->dir.'/collections/doormat_notices.yaml')->parse();
        $this->assertSame('Doormat notices', $collection['title']);
        $this->assertSame(['de', 'en'], $collection['sites']);
        $this->assertFalse($collection['propagate']);
        $this->assertTrue($collection['date']);
        $this->assertSame(['past' => 'public', 'future' => 'private'], $collection['date_behavior']);
        $this->assertSame('desc', $collection['sort_dir']);
        $this->assertSame(['seo' => false], $collection['inject']);
        $this->assertArrayNotHasKey('route', $collection);
        // Live preview without a route: the entry class offers it, the target opens the preview page.
        $this->assertSame(Notice::class, $collection['entry_class']);
        $this->assertSame([['label' => 'Page with the notice', 'url' => '{doormat_preview_url}', 'refresh' => true]], $collection['preview_targets']);

        $blueprint = YAML::file($this->dir.'/blueprints/doormat_notices/notice.yaml')->parse();
        $this->assertSame('Notice', $blueprint['title']);
    }

    public function test_it_names_the_files_after_the_handle(): void
    {
        $result = $this->definitions()->write('site_notices', ['de'], false);

        $this->assertSame([$this->dir.'/collections/site_notices.yaml', $this->dir.'/blueprints/site_notices/notice.yaml'], $result['written']);
    }

    public function test_it_skips_existing_files_unless_forced(): void
    {
        $this->definitions()->write('doormat_notices', ['de'], false);
        file_put_contents($this->dir.'/collections/doormat_notices.yaml', 'edited');

        $second = $this->definitions()->write('doormat_notices', ['de'], false);
        $this->assertSame([], $second['written']);
        $this->assertCount(2, $second['skipped']);
        $this->assertSame('edited', file_get_contents($this->dir.'/collections/doormat_notices.yaml'));

        $forced = $this->definitions()->write('doormat_notices', ['de'], true);
        $this->assertCount(2, $forced['written']);
        $this->assertStringContainsString('Doormat notices', file_get_contents($this->dir.'/collections/doormat_notices.yaml'));
    }
}
