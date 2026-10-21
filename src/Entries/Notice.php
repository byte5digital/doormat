<?php

namespace Byte5\Doormat\Entries;

use Byte5\Doormat\PreviewPage;
use Statamic\Contracts\Entries\Entry as EntryContract;

/*
 * The parent is whatever entry class the site binds - the Stache's, the Eloquent driver's or a site's
 * own - so a notice is saved and read by the same driver as every other entry. The file is loaded
 * when the first notice is made, after every provider has registered its binding.
 */
if (! class_exists(NoticeBase::class, false)) {
    class_alias(resolve(EntryContract::class)::class, NoticeBase::class);
}

/**
 * The notices' entry class (`entry_class` in the collection's file). A notice has no route, and Statamic
 * offers live preview only to entries with one: this class offers it anyway, and hands the preview
 * target the page the notice is previewed on as `{doormat_preview_url}`.
 */
class Notice extends NoticeBase
{
    /** The control panel's preview endpoint, which a routed entry gets from Statamic itself. */
    public function livePreviewUrl(): ?string
    {
        return $this->cpUrl('collections.entries.preview.edit');
    }

    /**
     * The values a preview target's URL is built from: the entry's, plus the page of the notice's site
     * the preview opens on.
     *
     * @return array<string, mixed>
     */
    public function routeData()
    {
        return array_merge(parent::routeData(), ['doormat_preview_url' => PreviewPage::url($this->site())]);
    }
}
