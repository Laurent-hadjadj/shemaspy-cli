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
use SchemaSpyCli\SchemaSpy\OutputFilter;

/**
 * [Description OutputFilterTest]
 * Tests unitaires pour OutputFilter
*/
final class OutputFilterTest extends TestCase
{
    private const CELL_SIZE =
        "WARN  - C:\\gv\\bin\\dot -Tsvg USERS.1degree.dot -oUSERS.1degree.svg: Warning: cell size too small for content\n";
    private const IN_LABEL =
        "WARN  - C:\\gv\\bin\\dot -Tsvg USERS.1degree.dot -oUSERS.1degree.svg: in label of node USERS\n";

    public function testCellSizeWarningIsSuppressedAndCounted(): void
    {
        $filter = new OutputFilter();

        $this->assertSame('', $filter->filter(self::CELL_SIZE));
        $this->assertSame(1, $filter->getSuppressedCount());
    }

    public function testInLabelWarningIsSuppressed(): void
    {
        $filter = new OutputFilter();

        $this->assertSame('', $filter->filter(self::IN_LABEL));
        $this->assertSame(1, $filter->getSuppressedCount());
    }

    public function testProgressDotsPrefixAreKept(): void
    {
        $filter = new OutputFilter();

        $this->assertSame('..', $filter->filter('..' . self::CELL_SIZE));
        $this->assertSame(1, $filter->getSuppressedCount());
    }

    public function testWindowsLineEndingsAreHandled(): void
    {
        $filter = new OutputFilter();

        $this->assertSame('', $filter->filter(rtrim(self::CELL_SIZE) . "\r\n"));
    }

    /** @dataProvider keptLines */
    public function testOtherLinesAreKeptUntouched(string $line): void
    {
        $filter = new OutputFilter();

        $this->assertSame($line, $filter->filter($line));
        $this->assertSame(0, $filter->getSuppressedCount());
    }

    public static function keptLines(): array
    {
        return [
            'info'                      => ["INFO  - Wrote 3 routines pages in 0 seconds\n"],
            'autre warning dot'         => ["WARN  - C:\\gv\\bin\\dot -Tsvg a.dot -oa.svg: Error: dot: can't open a.dot\n"],
            'warning sans rapport'      => ["WARN  - Unable to determine foreign keys\n"],
            'erreur schemaspy'          => ["ERROR - RelationShipDiagramError\n"],
            'points de progression'     => ['..'],
            'ligne vide'                => ["\n"],
        ];
    }

    public function testSuppressedLinesAreForwardedToCallback(): void
    {
        $seen = [];
        $filter = new OutputFilter(function (string $line) use (&$seen): void {
            $seen[] = $line;
        });

        $filter->filter(self::CELL_SIZE);
        $filter->filter("INFO  - ok\n");
        $filter->filter(self::IN_LABEL);

        $this->assertSame([rtrim(self::CELL_SIZE), rtrim(self::IN_LABEL)], $seen);
        $this->assertSame(2, $filter->getSuppressedCount());
    }
}
