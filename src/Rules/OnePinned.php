<?php

namespace Byte5\Doormat\Rules;

use Byte5\Doormat\Notices;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

/**
 * At most one notice is pinned. The toggle is one value for every site, so only
 * origins carry it; the entry being saved is told apart by its id, which the
 * entry editor fills into the blueprint's `{id}` placeholder (unquoted there, so a
 * new entry hands in a real null, not the word) and is then compared against every
 * origin. A draft keeps its pin.
 */
final readonly class OnePinned implements ValidationRule
{
    public function __construct(private ?string $id) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $own = $this->id === null ? null : Entry::find($this->id);
        $ownOrigin = $own === null ? null : ($own->hasOrigin() ? $own->root()->id() : $own->id());

        $other = Entry::query()
            ->where('collection', Notices::collection())
            ->get()
            ->first(fn (EntryContract $entry): bool => ! $entry->hasOrigin()
                && $entry->id() !== $ownOrigin
                && $entry->get('pinned') === true);

        if ($other !== null) {
            $fail(__('Another notice is already pinned: :title', ['title' => $other->get('title')]));
        }
    }
}
