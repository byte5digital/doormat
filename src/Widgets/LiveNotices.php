<?php

declare(strict_types=1);

namespace Byte5\Doormat\Widgets;

use Byte5\Doormat\Notices;
use Byte5\Doormat\Settings;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\View\View;
use Statamic\Widgets\Widget;

/**
 * The dashboard's "Doormat" card: per language the notices live there right now, or that none is.
 * Add it in config/statamic/cp.php: 'widgets' => [['type' => 'doormat', 'width' => 50]]. Shown only to
 * a user who may view the notices.
 */
class LiveNotices extends Widget
{
    /** @var string */
    protected static $handle = 'doormat';

    public function html(): string
    {
        $collection = Collection::findByHandle(Notices::collection());
        $user = User::current();

        if ($collection === null || $user === null || ! $user->can('view', $collection)) {
            return '';
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
            $sites[] = ['name' => $site->name(), 'notices' => $notices];
        }

        return View::make('doormat::widgets.live-notices', [
            'title' => $this->config('title', 'Doormat'),
            'sites' => $sites,
            'any' => $any,
            'enabled' => Settings::enabled(),
            'listUrl' => $collection->showUrl(),
        ])->render();
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
