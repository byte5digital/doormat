<?php

declare(strict_types=1);

namespace Byte5\Doormat\Actions;

use Byte5\Doormat\Notices;
use Illuminate\Support\Collection;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\User;

/**
 * Brings notices back for the visitors who closed them, without editing their text: the save
 * records a new last change, which is the notice's version in the dismissal key. Saved on the
 * origin, every language returns; on a localization, that language.
 */
class ShowAgain extends Action
{
    /** @var string */
    protected $icon = 'history';

    public static function title(): string
    {
        return __('Show again to everyone');
    }

    public function visibleTo(mixed $item): bool
    {
        return $item instanceof Entry && $item->collectionHandle() === Notices::collection();
    }

    /** @param  Collection<int, mixed>  $items */
    public function visibleToBulk($items): bool
    {
        return $items->every(fn (mixed $item): bool => $this->visibleTo($item));
    }

    public function authorize(mixed $user, mixed $item): bool
    {
        return $user->can('edit', $item);
    }

    public function confirmationText(): string
    {
        return 'Visitors who closed this notice will see it again.|Visitors who closed these :count notices will see them again.';
    }

    public function buttonText(): string
    {
        return 'Show again|Show :count again';
    }

    /**
     * @param  Collection<int, Entry>  $items
     * @param  array<string, mixed>  $values
     */
    public function run($items, $values): string
    {
        $items->each(fn (Entry $entry) => $entry->updateLastModified(User::current())->save());

        return trans_choice('Shown again to everyone.|:count notices shown again to everyone.', $items->count());
    }
}
