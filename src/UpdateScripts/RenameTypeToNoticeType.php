<?php

namespace Byte5\Doormat\UpdateScripts;

use Byte5\Doormat\Notices;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as Entries;
use Statamic\Fields\Blueprint;
use Statamic\UpdateScripts\UpdateScript;

/**
 * 0.4 renames the notice's `type` field to `notice_type`: Statamic's entry list answers a column
 * named `type` with the collection's title, so the Type column stayed empty. Renames the field in
 * the installed blueprint and moves every notice's stored value; a second run finds nothing to do.
 */
class RenameTypeToNoticeType extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion): bool
    {
        return $this->isUpdatingTo('0.4.0');
    }

    public function update(): void
    {
        $collection = Collection::findByHandle(Notices::collection());

        if (! $collection) {
            return;
        }

        $collection->entryBlueprints()->each(fn (Blueprint $blueprint) => $this->renameField($blueprint));

        Entries::query()->where('collection', $collection->handle())->get()
            ->each(fn (Entry $entry) => $this->moveValue($entry));
    }

    private function renameField(Blueprint $blueprint): void
    {
        $contents = $blueprint->contents();
        $renamed = false;

        foreach ($contents['tabs'] ?? [] as $tab => $sections) {
            foreach ($sections['sections'] ?? [] as $section => $fields) {
                foreach ($fields['fields'] ?? [] as $index => $field) {
                    if (($field['handle'] ?? null) === 'type' && is_array($field['field'] ?? null)) {
                        $contents['tabs'][$tab]['sections'][$section]['fields'][$index]['handle'] = 'notice_type';
                        $renamed = true;
                    }
                }
            }
        }

        if ($renamed) {
            $blueprint->setContents($contents)->save();
        }
    }

    private function moveValue(Entry $entry): void
    {
        if ($entry->has('type')) {
            $entry->set('notice_type', $entry->get('type'))->remove('type')->saveQuietly();
        }
    }
}
