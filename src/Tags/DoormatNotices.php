<?php

namespace Byte5\Doormat\Tags;

use Byte5\Doormat\NoticeData;
use Byte5\Doormat\Notices;
use Byte5\Doormat\Settings;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;
use Statamic\View\View;

/**
 * {{ doormat_notices }} renders the current site's active notices in the configured
 * view: `modal` - a dialog for the notices a visitor may close, bars for the rest -
 * or `notice` - bars only. Parameters: view="modal|notice", site="<handle>". As a pair
 * it loops the notices instead; {{ doormat_notices:script }} prints the script alone.
 */
class DoormatNotices extends Tags
{
    /** @return string|list<array<string, mixed>>|null */
    public function index(): string|array|null
    {
        $notices = Settings::enabled() ? $this->items((string) ($this->params->get('site') ?? Site::current()->handle())) : [];

        if ($this->isPair) {
            return $notices ?: null;
        }

        if ($notices === []) {
            return '';
        }

        $view = $this->params->get('view') ?? Settings::view();
        $view = in_array($view, Settings::VIEWS, true) ? $view : 'modal';

        $bars = '';
        if ($view === 'modal') {
            $fixed = array_values(array_filter($notices, fn (array $notice): bool => ! $notice['dismissible']));
            $notices = array_values(array_filter($notices, fn (array $notice): bool => (bool) $notice['dismissible']));
            $bars = $fixed === [] ? '' : $this->view('notice', $fixed, '');
        }

        return $this->view($view, $notices, $bars);
    }

    /** The script alone, for a site that writes the markup itself. */
    public function script(): string
    {
        return '<script data-cookieconsent="ignore">'.file_get_contents(__DIR__.'/../../resources/js/doormat.js').'</script>';
    }

    /** @param  list<array<string, mixed>>  $notices */
    private function view(string $view, array $notices, string $bars): string
    {
        if ($notices === []) {
            return $bars;
        }

        $data = ['notices' => $notices, 'bars' => $bars, 'script' => $this->script(), 'delay' => Settings::delay()];

        return View::make('doormat::'.$view, $data)->render();
    }

    /** @return list<array<string, mixed>> */
    private function items(string $site): array
    {
        return array_values(Notices::active($site)->map(fn (Entry $entry): array => NoticeData::of($entry))->all());
    }
}
