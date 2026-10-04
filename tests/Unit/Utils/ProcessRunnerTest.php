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

namespace SchemaSpyCli\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\ProcessRunner;

/**
 * [Description ProcessRunnerTest]
 * Tests unitaires pour ProcessRunner
 */
final class ProcessRunnerTest extends TestCase
{
    private TempDir $tmp;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    /** Exécute un script PHP et retourne [code, fragments reçus]. */
    private function runScript(string $code): array
    {
        $script = $this->tmp->file('script.php', "<?php\n" . $code);
        $chunks = [];

        $exit = (new ProcessRunner())->run(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script),
            function (string $text) use (&$chunks): void {
                $chunks[] = $text;
            }
        );

        return [$exit, $chunks];
    }

    public function testLinesAreForwardedWithTheirNewlineAndExitCodeIsReturned(): void
    {
        [$exit, $chunks] = $this->runScript('echo "premiere\ndeuxieme\n"; exit(3);');

        $this->assertSame(3, $exit);
        $this->assertSame(["premiere\n", "deuxieme\n"], $chunks);
    }

    public function testStderrIsMergedIntoOutput(): void
    {
        [$exit, $chunks] = $this->runScript('fwrite(STDERR, "erreur\n"); echo "ok\n";');

        $this->assertSame(0, $exit);
        $this->assertContains("erreur\n", $chunks);
        $this->assertContains("ok\n", $chunks);
    }

    public function testProgressDotsAreForwardedImmediately(): void
    {
        [, $chunks] = $this->runScript('echo ".."; fflush(STDOUT); usleep(400000); echo "suite\n";');

        $this->assertSame(['..', "suite\n"], $chunks);
    }

    public function testTrailingTextWithoutNewlineIsFlushedAtEnd(): void
    {
        [, $chunks] = $this->runScript('echo "fin sans retour";');

        $this->assertSame(['fin sans retour'], $chunks);
    }

    public function testSuccessfulProcessWithoutOutput(): void
    {
        [$exit, $chunks] = $this->runScript('');

        $this->assertSame(0, $exit);
        $this->assertSame([], $chunks);
    }
}
