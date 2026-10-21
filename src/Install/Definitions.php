<?php

namespace Byte5\Doormat\Install;

use RuntimeException;

/**
 * Writes the notices collection and its blueprint into an application from the
 * addon's stubs: the collection named after the handle with the installation's
 * sites listed, the blueprint as shipped. A file that exists is left alone
 * unless forced.
 */
final readonly class Definitions
{
    // Untyped on purpose: a typed class constant needs PHP 8.3, the addon runs on 8.2.
    private const STUBS = __DIR__.'/../../resources/stubs';

    public function __construct(
        private string $collectionsPath,
        private string $blueprintsPath,
    ) {}

    /**
     * @param  list<string>  $sites
     * @return array{written: list<string>, skipped: list<string>}
     */
    public function write(string $handle, array $sites, bool $force): array
    {
        $files = [
            "{$this->collectionsPath}/{$handle}.yaml" => $this->collection($sites),
            "{$this->blueprintsPath}/{$handle}/notice.yaml" => $this->stub('blueprint.yaml.stub'),
        ];

        $result = ['written' => [], 'skipped' => []];

        foreach ($files as $path => $contents) {
            if (file_exists($path) && ! $force) {
                $result['skipped'][] = $path;

                continue;
            }

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $contents);
            $result['written'][] = $path;
        }

        return $result;
    }

    private function stub(string $name): string
    {
        $contents = file_get_contents(self::STUBS.'/'.$name);

        throw_if($contents === false, RuntimeException::class, "The stub {$name} cannot be read.");

        return $contents;
    }

    /** @param  list<string>  $sites */
    private function collection(array $sites): string
    {
        $lines = implode("\n", array_map(fn (string $site): string => "  - {$site}", $sites));

        return str_replace('{{ sites }}', $lines, $this->stub('collection.yaml.stub'));
    }
}
