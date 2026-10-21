<?php

namespace Byte5\Doormat\Tests;

use Statamic\Facades\Antlers;

/**
 * The script is TypeScript (resources/ts, tested with Vitest) compiled to resources/dist/doormat.js,
 * which the views inline: what PHP checks is that the compiled file is there, self-contained, and
 * what the tag prints.
 */
class ScriptTest extends TestCase
{
    public function test_the_compiled_script_is_self_contained_and_never_uses_the_top_layer(): void
    {
        $script = file_get_contents(__DIR__.'/../resources/dist/doormat.js');

        $this->assertStringStartsWith('"use strict";(()=>{', $script);
        $this->assertStringNotContainsString('showModal', $script);
        $this->assertStringNotContainsString('export ', $script);
        $this->assertStringContainsString('doormat:', $script);
    }

    public function test_the_tag_prints_the_compiled_script(): void
    {
        $compiled = trim(file_get_contents(__DIR__.'/../resources/dist/doormat.js'));

        $this->assertStringContainsString($compiled, (string) Antlers::parse('{{ doormat_notices:script }}', [], true));
    }
}
