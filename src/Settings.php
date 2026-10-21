<?php

declare(strict_types=1);

namespace Byte5\Doormat;

use Statamic\Facades\Addon;

/**
 * The addon's settings page in the control panel (resources/blueprints/settings.yaml): a master
 * switch, how the notices are shown and how long the dialog waits. An unset presentation falls
 * back to the config file's.
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

    /** Seconds the dialog waits after the page has loaded. */
    public static function delay(): int
    {
        return max(0, (int) self::get('delay', 0));
    }

    private static function get(string $key, mixed $default = null): mixed
    {
        return Addon::get('byte5/doormat')->setting($key) ?? $default;
    }
}
