<?php

namespace Byte5\Doormat\Tests;

use Byte5\Doormat\Notices;
use Byte5\Doormat\UpdateScripts\RenameTypeToNoticeType;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

/** An installation from before 0.4: the blueprint's `type` field and the notices' stored `type` values move to `notice_type`. */
class RenameTypeUpdateScriptTest extends TestCase
{
    public function test_the_field_and_the_values_move_to_notice_type(): void
    {
        // The blueprint as doormat:install wrote it before 0.4.
        $path = Collection::findByHandle(Notices::collection())->entryBlueprint()->path();
        $before = str_replace('handle: notice_type', 'handle: type', (string) file_get_contents($path));
        file_put_contents($path, $before);
        $this->forgetBlueprints();

        $origin = $this->notice('de', ['notice_type' => null, 'type' => 'warning']);
        $localization = $this->notice('en', ['notice_type' => null]);
        $localization->data(collect($localization->data())->except('notice_type')->all())->origin($origin)->saveQuietly();

        (new RenameTypeToNoticeType('byte5/doormat'))->update();
        $this->forgetBlueprints();

        $blueprint = Collection::findByHandle(Notices::collection())->entryBlueprint();
        $this->assertTrue($blueprint->hasField('notice_type'));
        $this->assertFalse($blueprint->hasField('type'));
        // The file keeps every other line as it was: only the handle changes.
        $this->assertSame(str_replace('handle: type'."\n", 'handle: notice_type'."\n", $before), file_get_contents($blueprint->path()));

        $origin = Entry::find($origin->id());
        $this->assertSame('warning', $origin->get('notice_type'));
        $this->assertFalse($origin->has('type'));
        $this->assertSame('warning', Entry::find($localization->id())->value('notice_type'));
    }

    /** Statamic keeps read blueprints in Blink stores of their own, which Blink::flush() leaves alone. */
    private function forgetBlueprints(): void
    {
        Blink::flush();
        Blink::store('blueprints.found')->flush();
        Blink::store('blueprints.from-file')->flush();
    }
}
