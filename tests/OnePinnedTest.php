<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Rules\OnePinned;
use Illuminate\Support\Str;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Fields\Validator;

/**
 * At most one notice is pinned: the rule on the toggle refuses a second pin and
 * names the notice that holds it by its internal name. The toggle is one value
 * for every site, so only origins carry it; the entry being saved is told apart
 * by its id, which Statamic's entry editor fills in for the rule.
 */
class OnePinnedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The message is asserted in English; the application's default locale is German.
        app()->setLocale('en');
    }

    private function fails(OnePinned $rule, mixed $value): ?string
    {
        $failed = null;
        $rule->validate('pinned', $value, function (string $message) use (&$failed) {
            $failed = $message;
        });

        return $failed;
    }

    public function test_a_second_pinned_notice_is_refused_and_the_first_one_named(): void
    {
        $this->notice('de', data: ['title' => 'Holiday closure', 'pinned' => true]);
        $second = $this->notice('de');

        $this->assertSame('Another notice is already pinned: Holiday closure', $this->fails(new OnePinned($second->id()), true));
    }

    public function test_the_draft_that_holds_the_pin_still_counts(): void
    {
        $this->notice('de', published: false, data: ['title' => 'Draft closure', 'pinned' => true]);
        $second = $this->notice('de');

        $this->assertSame('Another notice is already pinned: Draft closure', $this->fails(new OnePinned($second->id()), true));
    }

    public function test_a_new_notice_is_refused_while_one_is_pinned(): void
    {
        $this->notice('de', data: ['title' => 'Holiday closure', 'pinned' => true]);

        $this->assertSame('Another notice is already pinned: Holiday closure', $this->fails(new OnePinned(null), true));
    }

    public function test_the_pinned_notice_itself_passes(): void
    {
        $first = $this->notice('de', data: ['pinned' => true]);

        $this->assertNull($this->fails(new OnePinned($first->id()), true));
    }

    public function test_a_localization_of_the_pinned_notice_passes(): void
    {
        $first = $this->notice('en', data: ['pinned' => true]);
        $german = $first->makeLocalization('de')->id((string) Str::uuid());
        $german->saveQuietly();

        $this->assertNull($this->fails(new OnePinned($german->id()), true));
    }

    /**
     * The blueprint's rule string goes through Statamic's validator, which fills {id}: a
     * saved entry's id, nothing for a new entry - the rule must receive a real null then,
     * not the word "null".
     */
    public function test_the_blueprints_rule_string_builds_the_rule_with_the_entrys_id_or_null(): void
    {
        $string = collect(Blueprint::find('collections.doormat_notices.notice')->field('pinned')->get('validate'))
            ->first(fn ($rule) => is_string($rule) && str_contains($rule, 'OnePinned'));
        $this->assertNotNull($string);

        $id = fn (OnePinned $rule) => (fn () => $this->id)->call($rule);

        $forNew = (new Validator)->withReplacements([])->parse($string);
        $this->assertInstanceOf(OnePinned::class, $forNew);
        $this->assertNull($id($forNew));

        $forSaved = (new Validator)->withReplacements(['id' => 'abc-123'])->parse($string);
        $this->assertSame('abc-123', $id($forSaved));
    }

    public function test_an_unpinned_save_passes(): void
    {
        $this->notice('de', data: ['pinned' => true]);
        $second = $this->notice('de');

        $this->assertNull($this->fails(new OnePinned($second->id()), false));
    }
}
