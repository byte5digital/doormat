<?php

namespace Byte5\Doormat\Tags;

use Byte5\Doormat\NoticeData;
use Byte5\Doormat\Notices;
use Byte5\Doormat\Preview;
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
 *
 * In a notice's live preview (the page loaded with its token) it renders that notice
 * alone, with the editor's unsaved changes, whatever its dates, its state or the master
 * switch, marked `data-doormat-preview` so the script opens it at once and records no
 * dismissal; {{ doormat_notices:preview }} tells a pair's own markup.
 */
class DoormatNotices extends Tags
{
    /** @return string|list<array<string, mixed>>|null */
    public function index(): string|array|null
    {
        $preview = Preview::notice();

        $notices = match (true) {
            $preview instanceof Entry => [NoticeData::of($preview)],
            Settings::enabled() => $this->items((string) ($this->params->get('site') ?? Site::current()->handle())),
            default => [],
        };

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
            $bars = $fixed === [] ? '' : $this->view('notice', $fixed, '', script: false);
        }

        return $this->view($view, $notices, $bars);
    }

    /** Whether this page is a notice's live preview: for a pair's own markup and script. */
    public function preview(): bool
    {
        return Preview::notice() instanceof Entry;
    }

    /** The script alone, for a site that writes the markup itself. */
    public function script(): string
    {
        return '<script data-cookieconsent="ignore">'.file_get_contents(__DIR__.'/../../resources/dist/doormat.js').'</script>';
    }

    /**
     * The view, with the script unless `$script` is false: the modal's bars are rendered without
     * it, since the modal prints it - or, with no dialog to show, the bars get it here.
     *
     * @param  list<array<string, mixed>>  $notices
     */
    private function view(string $view, array $notices, string $bars, bool $script = true): string
    {
        if ($notices === []) {
            return $bars === '' ? '' : $bars.$this->script();
        }

        $preview = $this->preview();
        $data = ['notices' => $notices, 'bars' => $bars, 'script' => $script ? $this->script() : '', 'delay' => $preview ? 0 : Settings::delay(), 'preview' => $preview];

        return View::make('doormat::'.$view, $data)->render();
    }

    /** @return list<array<string, mixed>> */
    private function items(string $site): array
    {
        return array_values(Notices::active($site)->map(fn (Entry $entry): array => NoticeData::of($entry))->all());
    }
}
