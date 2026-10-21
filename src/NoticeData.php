<?php

namespace Byte5\Doormat;

use Statamic\Contracts\Entries\Entry;

/**
 * A notice as the tag's views, the tag pair and the JSON endpoint see it: one shape,
 * so a site's own script and the addon's script read the same fields. A notice in live
 * preview carries the editor's unsaved values as supplements, which win over the saved
 * ones (augmentation reads them first by itself).
 */
final class NoticeData
{
    /** @return array<string, mixed> */
    public static function of(Entry $entry): array
    {
        $url = (string) $entry->augmentedValue('button_link')->value();
        $label = (string) self::value($entry, 'button_label');

        return [
            'id' => $entry->id(),
            'version' => self::version($entry),
            'heading' => (string) self::value($entry, 'heading'),
            'notice_type' => self::value($entry, 'notice_type') ?: 'info',
            'text' => (string) $entry->augmentedValue('text')->value(),
            'button_label' => $url !== '' && $label !== '' ? $label : null,
            'button_url' => $url !== '' && $label !== '' ? $url : null,
            'pinned' => (bool) self::value($entry, 'pinned'),
            'dismissible' => (bool) (self::value($entry, 'dismissible') ?? true),
            'frequency' => self::value($entry, 'frequency') ?: 'once',
        ];
    }

    /** The unsaved value of a notice in live preview, else the saved one (the origin's where the entry has none). */
    private static function value(Entry $entry, string $key): mixed
    {
        return $entry->hasSupplement($key) ? $entry->getSupplement($key) : $entry->value($key);
    }

    /**
     * The later of the entry's and its origin's last change: the notice type, the dates and often
     * the texts live on the origin, and saving it does not touch the localizations' rows.
     */
    private static function version(Entry $entry): int
    {
        $origin = $entry->hasOrigin() ? $entry->root() : $entry;

        return max($entry->lastModified()->getTimestamp(), $origin->lastModified()->getTimestamp());
    }
}
