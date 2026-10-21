<?php

declare(strict_types=1);

namespace Byte5\Doormat\Widgets;

use Byte5\Doormat\Notices;
use Byte5\Doormat\Settings;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's "Doormat" card, a Vue component on Statamic's own widget (resources/js): per language the notices live there right now, or that none is.
 * Add it in config/statamic/cp.php: 'widgets' => [['type' => 'doormat', 'width' => 50]], with
 * 'languages' => 'active' to list only the languages that have a live notice (default 'all'). Shown only
 * to a user who may view the notices.
 */
class LiveNotices extends Widget
{
    /** @var string */
    protected static $handle = 'doormat';

    public function component(): ?VueComponent
    {
        $collection = Collection::findByHandle(Notices::collection());
        $user = User::current();

        if ($collection === null || $user === null || ! $user->can('view', $collection)) {
            return null;
        }

        $sites = [];
        $any = false;

        foreach ($collection->sites() as $handle) {
            $site = Site::get($handle);
            if ($site === null) {
                continue;
            }

            $notices = [];
            foreach (Notices::active($handle) as $entry) {
                $notices[] = $this->notice($entry);
            }

            $any = $any || $notices !== [];

            // `languages: active` lists only the languages with a live notice; `all` (the default) every one.
            if ($notices === [] && $this->config('languages', 'all') === 'active') {
                continue;
            }

            $sites[] = ['name' => $site->name(), 'notices' => $notices];
        }

        return VueComponent::render('doormat-live-notices-widget', [
            'title' => __((string) $this->config('title', 'Doormat')),
            'url' => $collection->showUrl(),
            'enabled' => Settings::enabled(),
            'sites' => $sites,
            'any' => $any,
            'texts' => [
                'all' => __('doormat::notices.widget.all'),
                'none' => __('doormat::notices.widget.none'),
                'noneHere' => __('doormat::notices.widget.none_here'),
                'pinned' => __('doormat::notices.widget.pinned'),
                'switchedOff' => __('doormat::notices.widget.switched_off'),
            ],
        ]);
    }

    /** @return array{title: string, type: string, pinned: bool, url: string} */
    private function notice(Entry $entry): array
    {
        return [
            'title' => (string) $entry->value('title'),
            'type' => (string) __('doormat::notices.types.'.($entry->value('type') ?: 'info')),
            'pinned' => (bool) $entry->value('pinned'),
            'url' => (string) $entry->editUrl(),
        ];
    }
}
