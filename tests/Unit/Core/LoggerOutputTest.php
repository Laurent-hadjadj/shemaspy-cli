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

namespace SchemaSpyCli\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Tests\Support\TempDir;

/**
 * [Description LoggerOutputTest]
 * Sorties du Logger : mode silencieux, titres, tableaux, barre de progression, couleurs
 * (détection par système), saisies, journal fichier. Complète LoggerTest.
 */
final class LoggerOutputTest extends TestCase
{
    private const ENV_VARS = ['FORCE_COLOR', 'PSModulePath', 'WT_SESSION', 'TERM'];

    /** @var array<string, string|false> */
    private array $savedEnv = [];
    private TempDir $tmp;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        foreach (self::ENV_VARS as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name); // supprimée : chaque test positionne ce dont il a besoin
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
        $this->tmp->remove();
    }

    private function capture(callable $action): string
    {
        ob_start();
        try {
            $action();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    private function plain(string $text): string
    {
        return preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $text);
    }

    private function logger(bool $quiet = false, bool $verbose = false, ?string $file = null): Logger
    {
        $logger = new Logger(false, $file);
        $logger->setQuiet($quiet);
        $logger->setVerbose($verbose);

        return $logger;
    }

    private function logContent(string $file): string
    {
        return (string) file_get_contents($file);
    }

    // --- mode silencieux ---------------------------------------------------

    public function testQuietModeSuppressesAllDecorations(): void
    {
        $logger = $this->logger(quiet: true);

        $output = $this->capture(function () use ($logger): void {
            $logger->title('Titre');
            $logger->separator();
            $logger->table(['A'], [['1']]);
            $logger->clearLine();
            $logger->progressBar(1, 2, 'Étape');
            $logger->blankLine();
        });

        $this->assertSame('', $output);
    }

    public function testQuietModeAnswersPromptsWithDefaultsWithoutReading(): void
    {
        $logger = $this->logger(quiet: true);

        $this->assertSame('par défaut', $logger->prompt('Question', 'par défaut'));
        $this->assertSame('', $logger->promptPassword('Mot de passe'));
        $this->assertTrue($logger->promptConfirmation('Sûr ?', true));
        $this->assertFalse($logger->promptConfirmation('Sûr ?', false));
    }

    public function testCriticalGoesToTheFileAlwaysAndToTheConsoleOnlyWhenVerbose(): void
    {
        $file = $this->tmp->path . '/c.log';

        $silent = $this->capture(fn() => $this->logger(file: $file)->critical('panne'));
        $this->assertSame('', $silent, 'console muette hors --verbose');
        $this->assertStringContainsString('[CRITICAL]', $this->logContent($file));
        $this->assertStringContainsString('panne', $this->logContent($file));

        $loud = $this->capture(fn() => $this->logger(verbose: true)->critical('panne'));
        $this->assertStringContainsString('panne', $this->plain($loud));
    }

    // --- titres, séparateurs ----------------------------------------------

    public function testTitleIsFramedOnConsoleAndLoggedInFile(): void
    {
        $file = $this->tmp->path . '/t.log';
        $logger = $this->logger(file: $file);

        $output = $this->plain($this->capture(fn() => $logger->title('Connexion')));

        $this->assertStringContainsString(str_repeat('═', 50), $output);
        $this->assertStringContainsString('   Connexion', $output);
        $this->assertSame(2, substr_count($output, str_repeat('═', 50)), 'cadre haut et bas');
        $log = $this->logContent($file);
        $this->assertSame(2, substr_count($log, str_repeat('=', 50)));
        $this->assertStringContainsString('Connexion', $log);
    }

    public function testSeparatorDefaultsAndCustomisation(): void
    {
        $logger = $this->logger();

        $this->assertSame(str_repeat('═', 50) . "\n", $this->plain($this->capture(fn() => $logger->separator())));
        $this->assertSame(str_repeat('-', 10) . "\n", $this->plain($this->capture(fn() => $logger->separator('-', 10))));
    }

    // --- tableaux -----------------------------------------------------------

    public function testTableIsAlignedOnConsoleAndLoggedPlainInFile(): void
    {
        $file = $this->tmp->path . '/tab.log';
        $logger = $this->logger(file: $file);

        $output = $this->plain($this->capture(fn() => $logger->table(['Nom', 'Port'], [['a', 1], ['bb', null]])));

        $lines = explode("\n", rtrim($output, "\n"));
        $this->assertSame('| Nom | Port | ', $lines[0]);
        $this->assertSame('+' . str_repeat('-', 14) . '+', $lines[1]);
        $this->assertSame('| a   | 1    | ', $lines[2]);
        $this->assertSame('| bb  |      | ', $lines[3], 'une cellule nulle devient vide');

        $log = $this->logContent($file);
        $this->assertStringContainsString('Nom | Port', $log);
        $this->assertStringContainsString('a | 1', $log);
        $this->assertStringContainsString('bb | ', $log);
    }

    public function testTableColumnsGrowWithTheirContent(): void
    {
        $output = $this->plain($this->capture(fn() => $this->logger()->table(['X'], [['très long contenu']])));

        $lines = explode("\n", rtrim($output, "\n"));
        $this->assertSame('| X                 | ', $lines[0]);
        $this->assertSame('| très long contenu | ', $lines[2]);
    }

    public function testTableWithoutRowsStillShowsItsHeader(): void
    {
        $output = $this->plain($this->capture(fn() => $this->logger()->table(['Nom', 'Port'], [])));

        $this->assertStringContainsString('| Nom | Port | ', $output);
    }

    public function testTableInQuietModeIsOnlyWrittenToTheFile(): void
    {
        $file = $this->tmp->path . '/q.log';
        $logger = $this->logger(quiet: true, file: $file);

        $this->assertSame('', $this->capture(fn() => $logger->table(['A', 'B'], [['1', '2']])));
        $this->assertStringContainsString('A | B', $this->logContent($file));
        $this->assertStringContainsString('1 | 2', $this->logContent($file));
    }

    // --- barre de progression ---------------------------------------------

    /** @dataProvider progressCases */
    public function testProgressBarRendering(int $current, int $total, string $message, int $filled, string $percent): void
    {
        $logger = $this->logger();

        $output = $this->plain($this->capture(fn() => $logger->progressBar($current, $total, $message)));

        $this->assertStringContainsString('[' . str_repeat('█', $filled) . str_repeat('░', 40 - $filled) . '] ' . $percent, $output);
        if ($message !== '') {
            $this->assertStringContainsString($message, $output);
        }
        $this->assertSame($current >= $total, str_ends_with($output, "\n"), 'saut de ligne uniquement à 100 %');
    }

    public static function progressCases(): array
    {
        return [
            'début'              => [0, 4, 'Étape', 0, '0%'],
            'moitié'             => [2, 4, 'Étape', 20, '50%'],
            'un quart'           => [1, 4, '', 10, '25%'],
            'fin'                => [4, 4, 'Étape', 40, '100%'],
            'trois étapes sur 4' => [3, 4, '', 30, '75%'],
        ];
    }

    /** Régression : division par zéro (total nul) et ValueError de str_repeat() (avancement hors bornes). */
    public function testProgressBarToleratesOutOfRangeValues(): void
    {
        $logger = $this->logger();

        $this->assertSame('', $this->capture(fn() => $logger->progressBar(1, 0)), 'total nul : rien à afficher');
        $this->assertSame('', $this->capture(fn() => $logger->progressBar(1, -3)));

        $over = $this->plain($this->capture(fn() => $logger->progressBar(9, 3)));
        $this->assertStringContainsString('100%', $over, 'avancement borné au total');

        $under = $this->plain($this->capture(fn() => $logger->progressBar(-5, 3)));
        $this->assertStringContainsString('0%', $under, 'avancement borné à zéro');
    }

    public function testClearLineReturnsToLineStart(): void
    {
        $this->assertSame("\r\033[K", $this->capture(fn() => $this->logger()->clearLine()));
    }

    // --- couleurs --------------------------------------------------------------

    public function testForcedColorProducesAnsiSequences(): void
    {
        $logger = new Logger(true);

        $this->assertTrue($logger->hasColorSupport());
        $output = $this->capture(fn() => $logger->error('rouge'));
        $this->assertStringContainsString("\033[", $output);
        $this->assertStringEndsWith("\033[0m\n", $output);
    }

    public function testSetForceColorUpdatesTheSupportFlag(): void
    {
        $logger = $this->logger();

        $logger->setForceColor(true);
        $this->assertTrue($logger->hasColorSupport());

        $logger->setQuiet(true);
        $logger->setForceColor(false);
        $this->assertFalse($logger->hasColorSupport(), 'en mode silencieux la détection répond non');
    }

    public function testNoColorSequenceWhenColorIsNotSupported(): void
    {
        $logger = new Logger(false, null, 'Linux'); // TERM supprimé, sortie PHPUnit non terminal

        if ($logger->hasColorSupport()) {
            $this->markTestSkipped('Le terminal de test affiche les couleurs.');
        }
        $this->assertStringNotContainsString("\033[", $this->capture(fn() => $logger->error('texte')));
    }

    /** @dataProvider osFamilies */
    public function testForceColorEnvironmentVariableEnablesColorEverywhere(string $os): void
    {
        putenv('FORCE_COLOR=1');

        $this->assertTrue((new Logger(false, null, $os))->hasColorSupport());
    }

    public static function osFamilies(): array
    {
        return [['Windows'], ['Linux'], ['Darwin']];
    }

    public function testWindowsPowerShellSupportsColor(): void
    {
        putenv('PSModulePath=C:\\Windows\\system32\\WindowsPowerShell');

        $this->assertTrue((new Logger(false, null, 'Windows'))->hasColorSupport());
    }

    public function testWindowsWithoutPowerShellFallsBackOnTerminalCapabilities(): void
    {
        $logger = new Logger(false, null, 'Windows');

        if (function_exists('sapi_windows_vt100_support')) {
            // Vrai Windows : la réponse dépend du terminal qui exécute les tests, mais reste un booléen
            $this->assertIsBool($logger->hasColorSupport());
            return;
        }

        $this->assertFalse($logger->hasColorSupport(), 'ni PowerShell ni Windows Terminal');
        putenv('WT_SESSION=abc');
        $this->assertTrue((new Logger(false, null, 'Windows'))->hasColorSupport(), 'Windows Terminal');
    }

    public function testDumbUnixTerminalHasNoColor(): void
    {
        putenv('TERM=dumb');

        $this->assertFalse((new Logger(false, null, 'Linux'))->hasColorSupport());
    }

    public function testUnixColorDependsOnAnInteractiveTerminal(): void
    {
        putenv('TERM=xterm-256color');
        $logger = new Logger(false, null, 'Linux');

        $expected = function_exists('posix_isatty') && posix_isatty(STDOUT);
        $this->assertSame($expected, $logger->hasColorSupport());
    }

    // --- émoji et UTF-8 invalide -----------------------------------------------

    public function testInvalidUtf8IsPassedThroughUntouched(): void
    {
        $bad = "valeur \xff\xfe invalide";

        $output = $this->capture(fn() => $this->logger()->success($bad));

        $this->assertStringContainsString($bad, $output, 'le texte original est conservé tel quel');
        $this->assertStringContainsString('✅', $output, 'sans nettoyage possible, l\'émoji reste');
    }

    // --- journal fichier -----------------------------------------------------

    public function testUnwritableLogLocationIsSilentlyDisabled(): void
    {
        $blocker = $this->tmp->file('fichier.txt', 'je bloque');

        $logger = new Logger(false, $blocker . '/sous-dossier/trace.log');

        $this->assertNull($logger->getLogFile());
        $output = $this->capture(fn() => $logger->info('toujours affiché'));
        $this->assertStringContainsString('toujours affiché', $output);
    }

    public function testLogFileHeaderAndEntriesAreTimestamped(): void
    {
        $file = $this->tmp->path . '/sub/dir/app.log';
        $logger = new Logger(false, $file);
        $logger->setQuiet(true);

        $logger->warning('attention');

        $log = $this->logContent($file);
        $this->assertStringStartsWith('=== SchemaSpy CLI - ', $log);
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[WARN\]\s+.*attention$/m', $log);
    }

    // --- saisies -----------------------------------------------------------------

    private function stream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    public function testPromptReadsTrimmedLineFromAnExplicitStream(): void
    {
        $answer = '';
        $this->capture(function () use (&$answer): void {
            $answer = $this->logger()->prompt('Hôte', 'localhost', $this->stream("  db.local  \n"));
        });

        $this->assertSame('db.local', $answer);
    }

    public function testPromptFallsBackToTheDefaultOnEmptyInputOrEndOfStream(): void
    {
        $answers = [];
        $this->capture(function () use (&$answers): void {
            $logger = $this->logger();
            $answers[] = $logger->prompt('Hôte', 'localhost', $this->stream("\n"));
            $answers[] = $logger->prompt('Hôte', 'localhost', $this->stream(''));
            $answers[] = $logger->prompt('Sans défaut', '', $this->stream(''));
        });

        $this->assertSame(['localhost', 'localhost', ''], $answers);
    }

    public function testPromptDisplaysTheDefaultInBrackets(): void
    {
        $output = $this->capture(fn() => $this->logger()->prompt('Port', '5432', $this->stream("\n")));

        $this->assertStringContainsString('Port [5432]: ', $output);
        $this->assertStringContainsString('Libre: ', $this->capture(fn() => $this->logger()->prompt('Libre', '', $this->stream("\n"))));
    }

    public function testPasswordFallsBackOnPlainPromptWithInjectedInput(): void
    {
        $answer = '';
        $this->capture(function () use (&$answer): void {
            $answer = $this->logger()->promptPassword('Mot de passe', $this->stream("s3cr3t!\n"));
        });

        $this->assertSame('s3cr3t!', $answer);
    }

    /** @dataProvider confirmations */
    public function testConfirmationAnswers(string $typed, bool $default, bool $expected): void
    {
        $logger = $this->logger();
        $logger->setInputStream($this->stream($typed . "\n"));

        $answer = null;
        $this->capture(function () use ($logger, $default, &$answer): void {
            $answer = $logger->promptConfirmation('Continuer ?', $default);
        });

        $this->assertSame($expected, $answer);
    }

    public static function confirmations(): array
    {
        return [
            'Entrée => défaut oui' => ['', true, true],
            'Entrée => défaut non' => ['', false, false],
            'o'                    => ['o', false, true],
            'oui'                  => ['oui', false, true],
            'OUI (casse)'          => ['OUI', false, true],
            'yes'                  => ['yes', false, true],
            'n'                    => ['n', true, false],
            'non'                  => ['non', true, false],
            'autre réponse'        => ['peut-être', true, false],
        ];
    }

    public function testConfirmationShowsTheDefaultChoice(): void
    {
        $logger = $this->logger();
        $logger->setInputStream($this->stream("\n"));
        $withYes = $this->capture(fn() => $logger->promptConfirmation('Continuer ?', true));

        $logger->setInputStream($this->stream("\n"));
        $withNo = $this->capture(fn() => $logger->promptConfirmation('Continuer ?', false));

        $this->assertStringContainsString('(O/n)', $withYes);
        $this->assertStringContainsString('(o/N)', $withNo);
    }

    // --- accesseurs ---------------------------------------------------------------

    public function testStateAccessors(): void
    {
        $logger = new Logger();

        $this->assertFalse($logger->isQuiet());
        $this->assertFalse($logger->isVerbose());

        $logger->setQuiet(true);
        $logger->setVerbose(true);

        $this->assertTrue($logger->isQuiet());
        $this->assertTrue($logger->isVerbose());
    }
}
