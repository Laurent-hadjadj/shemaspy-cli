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
 * Tests unitaires pour ProcessRunner : mêmes scénarios avec proc_open (nominal) et
 * avec popen (repli quand proc_open est refusé, ex: terminal intégré de VS Code).
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

    /** @return array<string, array{0: ProcessRunner}> */
    public static function runners(): array
    {
        return [
            'proc_open' => [new ProcessRunner()],
            // proc_open "refusé" : force le repli sur popen
            'popen (repli)' => [new class extends ProcessRunner {
                protected function startWithProcOpen(string $command): ?array
                {
                    return null;
                }
            }],
        ];
    }

    /** Exécute un script PHP et retourne [code, fragments reçus]. */
    private function runScript(ProcessRunner $runner, string $code): array
    {
        $script = $this->tmp->file('script.php', "<?php\n" . $code);
        $chunks = [];

        $exit = $runner->run(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script),
            function (string $text) use (&$chunks): void {
                $chunks[] = $text;
            }
        );

        return [$exit, $chunks];
    }

    /** @dataProvider runners */
    public function testLinesAreForwardedWithTheirNewlineAndExitCodeIsReturned(ProcessRunner $runner): void
    {
        [$exit, $chunks] = $this->runScript($runner, 'echo "premiere\ndeuxieme\n"; exit(3);');

        $this->assertSame(3, $exit);
        $this->assertSame(["premiere\n", "deuxieme\n"], $chunks);
    }

    /** @dataProvider runners */
    public function testStderrIsMergedIntoOutput(ProcessRunner $runner): void
    {
        [$exit, $chunks] = $this->runScript($runner, 'fwrite(STDERR, "erreur\n"); echo "ok\n";');

        $this->assertSame(0, $exit);
        $this->assertContains("erreur\n", $chunks);
        $this->assertContains("ok\n", $chunks);
    }

    /** @dataProvider runners */
    public function testProgressDotsAreForwardedImmediately(ProcessRunner $runner): void
    {
        [, $chunks] = $this->runScript($runner, 'echo ".."; fflush(STDOUT); usleep(400000); echo "suite\n";');

        $this->assertSame(['..', "suite\n"], $chunks);
    }

    /** @dataProvider runners */
    public function testTrailingTextWithoutNewlineIsFlushedAtEnd(ProcessRunner $runner): void
    {
        [, $chunks] = $this->runScript($runner, 'echo "fin sans retour";');

        $this->assertSame(['fin sans retour'], $chunks);
    }

    /** @dataProvider runners */
    public function testSuccessfulProcessWithoutOutput(ProcessRunner $runner): void
    {
        [$exit, $chunks] = $this->runScript($runner, '');

        $this->assertSame(0, $exit);
        $this->assertSame([], $chunks);
    }

    public function testExplicitErrorWhenNoStrategyCanStartTheProcess(): void
    {
        $runner = new class extends ProcessRunner {
            protected function startWithProcOpen(string $command): ?array
            {
                return null;
            }

            protected function startWithPopen(string $command): ?array
            {
                return null;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Impossible de lancer le processus');
        $runner->run('commande-quelconque', static function (): void {
        });
    }
}
