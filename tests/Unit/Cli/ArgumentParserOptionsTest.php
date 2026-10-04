<?php

/**
 *  Ma-Moulinette - ShemaSpy-Cli
 *  --------------
 *  Copyright (c) 2015-2026.
 *  Laurent HADJADJ <laurent_h@me.com>.
 *  Licensed Creative Common  CC-BY-NC-SA 4.0.
 *  ---
 *  Vous pouvez obtenir une copie de la licence à l'adresse suivante :
 *  http://creativecommons.org/licenses/by-nc-sa/4.0/
 */

namespace SchemaSpyCli\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Cli\ArgumentParser;

/**
 * [Description ArgumentParserOptionsTest]
 * Tests des options de génération de ArgumentParser
 */
final class ArgumentParserOptionsTest extends TestCase
{
    private array $savedArgv;

    protected function setUp(): void
    {
        $this->savedArgv = $GLOBALS['argv'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['argv'] = $this->savedArgv;
    }

    private function parse(string ...$args): ArgumentParser
    {
        $GLOBALS['argv'] = ['schemaspy', ...$args];
        $parser = new ArgumentParser();
        $parser->parse();
        return $parser;
    }

    public function testFlagsAndValuesAreCollected(): void
    {
        $parser = $this->parse('--markdown', '--no-html', '--no-orphans', '--engine=vizjs', '--degree=1', '--exclude=TMP_.*');

        $this->assertSame([
            'markdown' => true, 'html' => false, 'orphans' => false,
            'engine' => 'vizjs', 'degree' => '1', 'exclude' => 'TMP_.*',
        ], $parser->getOptions());
    }

    public function testOptionsDoNotSwitchToNonInteractiveMode(): void
    {
        $this->assertFalse($this->parse('--markdown', '--no-orphans')->hasParams());
    }

    public function testLegacyVizjsParamMapsToEngine(): void
    {
        $parser = $this->parse('--vizjs=true');
        $this->assertSame('vizjs', $parser->getOptions()['engine']);
    }

    public function testExplicitEngineWinsOverLegacyVizjs(): void
    {
        $parser = $this->parse('--vizjs=true', '--engine=graphviz');
        $this->assertSame('graphviz', $parser->getOptions()['engine']);
    }
}
