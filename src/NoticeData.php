<?php

namespace Byte5\Doormat;

use Statamic\Contracts\Entries\Entry;

/**
 * A notice as the tag's views, the tag pair and the JSON endpoint see it: one shape,
 * so a site's own script and the addon's script read the same fields.
 */
final class NoticeData
{
    /** @return array<string, mixed> */
    public static function of(Entry $entry): array
    {
        $url = (string) $entry->augmentedValue('button_link')->value();
        $label = (string) $entry->value('button_label');

        return [
            'id' => $entry->id(),
            'version' => self::version($entry),
            'heading' => (string) $entry->value('heading'),
            'type' => $entry->value('type') ?: 'info',
            'text' => (string) $entry->augmentedValue('text')->value(),
            'button_label' => $url !== '' && $label !== '' ? $label : null,
            'button_url' => $url !== '' && $label !== '' ? $url : null,
            'pinned' => (bool) $entry->value('pinned'),
            'dismissible' => (bool) ($entry->value('dismissible') ?? true),
            'frequency' => $entry->value('frequency') ?: 'once',
        ];
    }

    /**
     * The later of the entry's and its origin's last change: the type, the dates and often
     * the texts live on the origin, and saving it does not touch the localizations' rows.
     */
    private static function version(Entry $entry): int
    {
        $origin = $entry->hasOrigin() ? $entry->root() : $entry;

        return max($entry->lastModified()->getTimestamp(), $origin->lastModified()->getTimestamp());
    }
}
