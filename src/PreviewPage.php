<?php

declare(strict_types=1);

namespace Byte5\Doormat;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Sites\Site;

/**
 * The page a notice's live preview opens on: the page picked on the settings page, in the notice's
 * site, else that site's homepage. A notice has no page of its own; any page that renders the tag (or
 * a site's own script asking the endpoint) shows it.
 */
final class PreviewPage
{
    public static function url(Site $site): string
    {
        return self::picked($site)?->absoluteUrl() ?? $site->absoluteUrl();
    }

    /**
     * The picked page's version in the site - a page only where it has a published localization with
     * a URL there: a missing, unpublished or route-less one would open a 404 instead of the notice.
     */
    private static function picked(Site $site): ?EntryContract
    {
        $id = Settings::previewPage();
        $page = $id === null ? null : Entry::find($id)?->in($site->handle());

        return $page instanceof EntryContract && $page->published() && $page->url() !== null ? $page : null;
    }
}
