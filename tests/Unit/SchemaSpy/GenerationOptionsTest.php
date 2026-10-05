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

namespace SchemaSpyCli\Tests\Unit\SchemaSpy;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Exceptions\ValidationException;
use SchemaSpyCli\SchemaSpy\GenerationOptions;

/**
 * [Description GenerationOptionsTest]
 * Tests unitaires pour GenerationOptions
 */
final class GenerationOptionsTest extends TestCase
{
    public function testDefaultsProduceNoProperties(): void
    {
        $this->assertSame([], GenerationOptions::fromArrays([])->toProperties());
    }

    public function testCliOverridesConfig(): void
    {
        $options = GenerationOptions::fromArrays(
            ['markdown' => false, 'orphans' => true],
            ['markdown' => true, 'orphans' => false]
        );

        $this->assertTrue($options->markdown);
        $this->assertFalse($options->orphans);
    }

    public function testPropertiesMapping(): void
    {
        $options = GenerationOptions::fromArrays([
            'markdown' => true, 'html' => false, 'orphans' => false, 'views' => false,
            'rows' => false, 'implied' => false, 'degree' => '1', 'include' => 'T_.*', 'exclude' => 'TMP_.*',
        ]);

        $this->assertSame([
            'schemaspy.nohtml' => 'true',
            'schemaspy.markdown' => 'true',
            'schemaspy.no-orphans' => 'true',
            'schemaspy.noviews' => 'true',
            'schemaspy.norows' => 'true',
            'schemaspy.noimplied' => 'true',
            'schemaspy.degree' => '1',
            'schemaspy.i' => 'T_.*',
            'schemaspy.I' => 'TMP_.*',
        ], $options->toProperties());
    }

    public function testNoHtmlWithoutMarkdownIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        GenerationOptions::fromArrays(['html' => false]);
    }

    public function testInvalidEngineIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        GenerationOptions::fromArrays(['engine' => 'cairo']);
    }

    public function testInvalidDegreeIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        GenerationOptions::fromArrays(['degree' => 3]);
    }

    public function testInvalidRegexIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        GenerationOptions::fromArrays(['include' => '(unclosed']);
    }

    /** @dataProvider booleanStrings */
    public function testBooleanLikeStringsAreInterpreted(string $value, bool $expected): void
    {
        $options = GenerationOptions::fromArrays(['markdown' => $value, 'html' => 'true']);

        $this->assertSame($expected, $options->markdown);
    }

    public static function booleanStrings(): array
    {
        return [
            'true' => ['true', true], 'TRUE' => ['TRUE', true], '1' => ['1', true], 'yes' => ['yes', true],
            'oui'  => ['oui', true],  'Oui'  => ['Oui', true],  'on' => ['on', true], 'ON' => ['ON', true],
            'false' => ['false', false], '0' => ['0', false], 'no' => ['no', false], 'non' => ['non', false],
            'off'  => ['off', false], 'inconnu' => ['peut-etre', false],
        ];
    }

    public function testEveryFilterAcceptsBooleanStrings(): void
    {
        $options = GenerationOptions::fromArrays([
            'orphans' => 'non', 'views' => 'no', 'rows' => '0', 'implied' => 'off',
        ]);

        $this->assertFalse($options->orphans);
        $this->assertFalse($options->views);
        $this->assertFalse($options->rows);
        $this->assertFalse($options->implied);
    }
}
