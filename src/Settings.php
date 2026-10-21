<?php

declare(strict_types=1);

namespace Byte5\Doormat;

use Statamic\Facades\Addon;

/**
 * The addon's settings page in the control panel (resources/blueprints/settings.yaml): a master
 * switch, how the notices are shown, how long the dialog waits and the page a notice's live preview
 * opens on. An unset presentation falls back to the config file's.
 */
final class Settings
{
    public const VIEWS = ['modal', 'notice'];

    /** Off, no notice reaches a visitor on any site. */
    public static function enabled(): bool
    {
        return (bool) self::get('enabled', true);
    }

    /** `modal` or `notice`: the settings page's choice, else config('doormat.view'). */
    public static function view(): string
    {
        $view = self::get('view') ?: config('doormat.view', 'modal');

        return in_array($view, self::VIEWS, true) ? $view : 'modal';
    }

    /** Seconds the dialog waits after the page has loaded: none while "No wait" is picked. */
    public static function delay(): int
    {
        return self::get('wait') === 'wait' ? max(1, (int) self::get('delay', 1)) : 0;
    }

    /**
     * The id of the page a notice's live preview opens on, or null for the homepage. The entries field
     * keeps one id; a list (a field once configured for more) counts its first.
     */
    public static function previewPage(): ?string
    {
        $page = self::get('preview_page');
        $page = is_array($page) ? reset($page) : $page;

        return is_string($page) && $page !== '' ? $page : null;
    }

    private static function get(string $key, mixed $default = null): mixed
    {
        return Addon::get('byte5/doormat')->setting($key) ?? $default;
    }
}
