<?php

namespace Byte5\Doormat\Tests;

/**
 * The addon's script is plain JavaScript PHPUnit cannot run; what it pins are the
 * facts a later edit could break unnoticed: the dialog opens with show(), never in
 * the browser's top layer above a consent banner; dismissals are keyed by id and
 * version in local or session storage behind try/catch; Escape closes; nothing is
 * global.
 */
class ScriptTest extends TestCase
{
    public function test_the_script_opens_the_dialog_without_the_top_layer_and_guards_storage(): void
    {
        $script = file_get_contents(__DIR__.'/../resources/js/doormat.js');

        $this->assertStringContainsString('.show()', $script);
        $this->assertStringNotContainsString('showModal', $script);
        $this->assertStringContainsString("'doormat:' +", $script);
        $this->assertStringContainsString('sessionStorage', $script);
        $this->assertStringContainsString('localStorage', $script);
        $this->assertStringContainsString('try {', $script);
        $this->assertStringContainsString("'Escape'", $script);
        $this->assertMatchesRegularExpression("/^\\(function \\(\\) \\{\\s*\\n\\s*'use strict';/", $script);
    }
}
