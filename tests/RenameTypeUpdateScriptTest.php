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
        $blueprint = Collection::findByHandle(Notices::collection())->entryBlueprint();
        $contents = $blueprint->contents();
        $contents['tabs']['appearance']['sections'][0]['fields'][0]['handle'] = 'type';
        $blueprint->setContents($contents)->save();

        $origin = $this->notice('de', ['notice_type' => null, 'type' => 'warning']);
        $localization = $this->notice('en', ['notice_type' => null]);
        $localization->data(collect($localization->data())->except('notice_type')->all())->origin($origin)->saveQuietly();

        (new RenameTypeToNoticeType('byte5/doormat'))->update();
        Blink::flush();

        $blueprint = Collection::findByHandle(Notices::collection())->entryBlueprint();
        $this->assertTrue($blueprint->hasField('notice_type'));
        $this->assertFalse($blueprint->hasField('type'));

        $origin = Entry::find($origin->id());
        $this->assertSame('warning', $origin->get('notice_type'));
        $this->assertFalse($origin->has('type'));
        $this->assertSame('warning', Entry::find($localization->id())->value('notice_type'));
    }
}
