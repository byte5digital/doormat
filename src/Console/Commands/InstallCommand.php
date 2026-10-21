<?php

namespace Byte5\Doormat\Console\Commands;

use Byte5\Doormat\Install\Definitions;
use Byte5\Doormat\Notices;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;

class InstallCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'doormat:install {--force : Overwrite the collection and blueprint files}';

    protected $description = 'Writes the notices collection and its blueprint into the application';

    public function handle(): int
    {
        // Statamic's own folders, as the site configured them: the collections' store and the blueprints path.
        $definitions = new Definitions(
            config('statamic.stache.stores.collections.directory', base_path('content/collections')),
            Blueprint::directory().'/collections',
        );

        $result = $definitions->write(
            Notices::collection(),
            Site::all()->map->handle()->values()->all(),
            (bool) $this->option('force'),
        );

        foreach ($result['written'] as $path) {
            $this->info('Written: '.$this->relative($path));
        }

        foreach ($result['skipped'] as $path) {
            $this->warn($this->relative($path).' exists, left as it is (--force overwrites)');
        }

        $this->call('statamic:stache:clear');
        $this->call('statamic:stache:warm');

        return self::SUCCESS;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
