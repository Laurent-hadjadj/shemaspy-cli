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
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Tests\Support\ScriptedEnvironment;

/**
 * [Description EnvironmentDiscoveryTest]
 * Tests d'Environment hors lecture des versions (voir EnvironmentTest) : chemins des exécutables,
 * découverte de Java et de Graphviz, compatibilités, chemins absolus, utilitaires du système.
 * Les commandes shell sont scriptées (ScriptedEnvironment) : aucun processus réel.
 */
final class EnvironmentDiscoveryTest extends TestCase
{
    private string|false $savedJavaHome;

    protected function setUp(): void
    {
        $this->savedJavaHome = getenv('JAVA_HOME');
        putenv('JAVA_HOME'); // supprimée : chaque test choisit explicitement
    }

    protected function tearDown(): void
    {
        putenv($this->savedJavaHome === false ? 'JAVA_HOME' : 'JAVA_HOME=' . $this->savedJavaHome);
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    private function sep(): string
    {
        return DIRECTORY_SEPARATOR;
    }

    private function javaExe(): string
    {
        return $this->isWindows() ? 'java.exe' : 'java';
    }

    /** Chemin d'un exécutable java tel que le renvoient where/which sur la plateforme courante. */
    private function javaOnPath(string $home = 'jdk17'): string
    {
        $sep = $this->sep();
        $root = $this->isWindows() ? 'C:' . $sep . 'Program Files' : $sep . 'opt';

        return $root . $sep . $home . $sep . 'bin' . $sep . $this->javaExe();
    }

    private function javaHomeOf(string $home = 'jdk17'): string
    {
        $sep = $this->sep();

        return ($this->isWindows() ? 'C:' . $sep . 'Program Files' : $sep . 'opt') . $sep . $home;
    }

    // --- chemins des exécutables ---------------------------------------

    /** @dataProvider homes */
    public function testJavaExecutablePath(string $home, string $normalizedHome): void
    {
        $expected = $normalizedHome . $this->sep() . 'bin' . $this->sep() . $this->javaExe();

        $this->assertSame($expected, (new Environment())->getJavaExecutable($home));
    }

    /** @dataProvider homes */
    public function testDotExecutablePath(string $home, string $normalizedHome): void
    {
        $expected = $normalizedHome . $this->sep() . 'bin' . $this->sep() . ($this->isWindows() ? 'dot.exe' : 'dot');

        $this->assertSame($expected, (new Environment())->getDotExecutable($home));
    }

    public static function homes(): array
    {
        $s = DIRECTORY_SEPARATOR;
        $abs = PHP_OS_FAMILY === 'Windows' ? 'C:' : '';

        return [
            'chemin simple'              => ["{$abs}/opt/jdk", "{$abs}{$s}opt{$s}jdk"],
            'antislashs'                 => ["{$abs}\\opt\\jdk", "{$abs}{$s}opt{$s}jdk"],
            'séparateurs mélangés'       => ["{$abs}\\opt/jdk\\17", "{$abs}{$s}opt{$s}jdk{$s}17"],
            'slash final (JAVA_HOME)'    => ["{$abs}/opt/jdk/", "{$abs}{$s}opt{$s}jdk"],
            'antislash final'            => ["{$abs}\\opt\\jdk\\", "{$abs}{$s}opt{$s}jdk"],
            'séparateurs finaux doublés' => ["{$abs}/opt/jdk//", "{$abs}{$s}opt{$s}jdk"],
            'relatif'                    => ['tools/jdk17', "tools{$s}jdk17"],
            'espaces dans le chemin'     => ["{$abs}/Program Files/Java/jdk", "{$abs}{$s}Program Files{$s}Java{$s}jdk"],
        ];
    }

    // --- JAVA_HOME / PATH ------------------------------------------------

    public function testJavaHomeComesFromTheEnvironmentVariable(): void
    {
        putenv('JAVA_HOME=' . $this->javaHomeOf());
        $env = new ScriptedEnvironment();

        $this->assertSame($this->javaHomeOf(), $env->getJavaHome());
        $this->assertSame([], $env->commands, 'JAVA_HOME suffit : aucune commande lancée');
    }

    public function testJavaHomeSeparatorsAreNormalized(): void
    {
        putenv('JAVA_HOME=' . str_replace($this->sep(), $this->isWindows() ? '/' : '\\', $this->javaHomeOf()));

        $this->assertSame($this->javaHomeOf(), (new ScriptedEnvironment())->getJavaHome());
    }

    /** @dataProvider noisyJavaHomes */
    public function testJavaHomeToleratesQuotesAndStrayWhitespace(string $value): void
    {
        putenv('JAVA_HOME=' . $value);

        $this->assertSame($this->javaHomeOf(), (new ScriptedEnvironment())->getJavaHome());
    }

    public static function noisyJavaHomes(): array
    {
        $home = PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\jdk17' : '/opt/jdk17';

        return [
            'guillemets doubles' => ["\"{$home}\""],
            'guillemets simples' => ["'{$home}'"],
            'espaces autour'     => ["  {$home}  "],
        ];
    }

    public function testJavaHomeIsCachedAfterTheFirstCall(): void
    {
        putenv('JAVA_HOME=' . $this->javaHomeOf('premier'));
        $env = new ScriptedEnvironment();

        $first = $env->getJavaHome();
        putenv('JAVA_HOME=' . $this->javaHomeOf('second'));

        $this->assertSame($first, $env->getJavaHome());
    }

    public function testJavaHomeFallsBackToThePathLookup(): void
    {
        $env = new ScriptedEnvironment([$this->javaOnPath() . "\r\n"]);

        $this->assertSame($this->javaHomeOf(), $env->getJavaHome());
        $this->assertSame([$this->isWindows() ? 'where java 2>nul' : 'which java 2>/dev/null'], $env->commands);
    }

    /** Régression : « where java » liste une installation par ligne, la sortie entière servait de chemin. */
    public function testOnlyTheFirstJavaFoundOnThePathIsUsed(): void
    {
        $env = new ScriptedEnvironment([
            $this->javaOnPath('premier') . "\r\n" . $this->javaOnPath('second') . "\r\n" . $this->javaOnPath('troisieme') . "\r\n",
        ]);

        $this->assertSame($this->javaHomeOf('premier'), $env->getJavaHome());
    }

    public function testPathLookupWithUnixLineEndings(): void
    {
        $env = new ScriptedEnvironment([$this->javaOnPath('a') . "\n" . $this->javaOnPath('b') . "\n"]);

        $this->assertSame($this->javaHomeOf('a'), $env->getJavaHome());
    }

    public function testPathLookupResultIsCached(): void
    {
        $env = new ScriptedEnvironment([$this->javaOnPath()]);

        $env->getJavaHome();
        $env->getJavaHome();

        $this->assertCount(1, $env->commands);
    }

    /** @dataProvider emptyOutputs */
    public function testJavaHomeIsNullWhenJavaIsNowhere(string $output): void
    {
        $this->assertNull((new ScriptedEnvironment([$output]))->getJavaHome());
    }

    public static function emptyOutputs(): array
    {
        return [['',], ["\n"], ["  \r\n  "]];
    }

    // --- Graphviz dans le PATH -------------------------------------------

    public function testGraphvizIsFoundOnThePath(): void
    {
        $dot = $this->isWindows() ? 'C:\\Graphviz\\bin\\dot.exe' : '/usr/bin/dot';
        $env = new ScriptedEnvironment([$dot . "\r\n"]);

        $this->assertSame($dot, $env->findGraphvizInPath());
        $this->assertSame([$this->isWindows() ? 'where dot 2>nul' : 'which dot 2>/dev/null'], $env->commands);
    }

    public function testOnlyTheFirstGraphvizOnThePathIsUsed(): void
    {
        $first = $this->isWindows() ? 'C:\\Graphviz\\bin\\dot.exe' : '/usr/bin/dot';
        $second = $this->isWindows() ? 'D:\\Autre\\bin\\dot.exe' : '/opt/gv/bin/dot';

        $this->assertSame($first, (new ScriptedEnvironment(["{$first}\r\n{$second}\r\n"]))->findGraphvizInPath());
    }

    public function testGraphvizPathSeparatorsAreNormalized(): void
    {
        $env = new ScriptedEnvironment(['C:/Graphviz/bin/dot.exe']);

        $this->assertSame('C:' . $this->sep() . 'Graphviz' . $this->sep() . 'bin' . $this->sep() . 'dot.exe', $env->findGraphvizInPath());
    }

    public function testGraphvizIsNullWhenAbsent(): void
    {
        $this->assertNull((new ScriptedEnvironment(['']))->findGraphvizInPath());
        $this->assertNull((new ScriptedEnvironment(["\r\n"]))->findGraphvizInPath());
    }

    // --- lecture des versions (analyse de sortie, processus scripté) ---

    /** @dataProvider javaOutputs */
    public function testJavaVersionParsing(string $output, ?string $expected): void
    {
        $this->assertSame($expected, (new ScriptedEnvironment([$output]))->getJavaVersion());
    }

    public static function javaOutputs(): array
    {
        return [
            'JDK 8'                    => ['java version "1.8.0_231"', '1.8.0'],
            'OpenJDK 17'               => ["openjdk version \"17.0.9\" 2023-10-17\nOpenJDK Runtime Environment", '17.0.9'],
            'JDK 11 sans mineur'       => ['openjdk version "11" 2018-09-25', '11'],
            'build early access'       => ['openjdk version "22-ea" 2024-03-19', '22'],
            'build avec numéro'        => ['openjdk version "21.0.1+12" 2023-10-17', '21.0.1'],
            'JAVA_TOOL_OPTIONS avant'  => ["Picked up JAVA_TOOL_OPTIONS: -Dfile.encoding=UTF8\nopenjdk version \"17.0.8\" 2023-07-18", '17.0.8'],
            'sortie inexploitable'     => ['Command not found', null],
            'sortie vide'              => ['', null],
        ];
    }

    public function testJavaVersionCommandUsesTheGivenExecutableOrThePathOne(): void
    {
        $env = new ScriptedEnvironment(['openjdk version "17.0.1"']);

        $env->getJavaVersion();
        $env->getJavaVersion('C:\\jdk 17\\bin\\java.exe');

        $this->assertSame(escapeshellarg('java') . ' -version 2>&1', $env->commands[0]);
        $this->assertSame(escapeshellarg('C:\\jdk 17\\bin\\java.exe') . ' -version 2>&1', $env->commands[1]);
    }

    /** @dataProvider graphvizOutputs */
    public function testGraphvizVersionParsing(string $output, ?string $expected): void
    {
        $this->assertSame($expected, (new ScriptedEnvironment([$output]))->getGraphvizVersion());
    }

    public static function graphvizOutputs(): array
    {
        return [
            'dot 16'            => ['dot - graphviz version 16.1.0 (20260904.0139)', '16.1.0'],
            'dot_builtins'      => ['dot_builtins - graphviz version 16.1.0 (20260904.0139)', '16.1.0'],
            'dot 2.38'          => ['dot - graphviz version 2.38.0 (20140413.2041)', '2.38.0'],
            'casse différente'  => ['DOT - Graphviz Version 12.2.1', '12.2.1'],
            'sortie inexploitable' => ['dot: command not found', null],
            'sortie vide'       => ['', null],
        ];
    }

    public function testGraphvizVersionCommandUsesTheGivenExecutableOrThePathOne(): void
    {
        $env = new ScriptedEnvironment(['dot - graphviz version 16.1.0']);

        $env->getGraphvizVersion();
        $env->getGraphvizVersion('tools/gv/bin/dot.exe');

        $this->assertSame(escapeshellarg('dot') . ' -V 2>&1', $env->commands[0]);
        $this->assertSame(escapeshellarg('tools/gv/bin/dot.exe') . ' -V 2>&1', $env->commands[1]);
    }

    // --- compatibilités --------------------------------------------------

    /** @dataProvider javaCompatibility */
    public function testJavaVersionCompatibility(string $output, string|null $min, bool $expected): void
    {
        $env = new ScriptedEnvironment([$output]);

        $this->assertSame($expected, $min === null ? $env->isJavaVersionCompatible() : $env->isJavaVersionCompatible($min));
    }

    public static function javaCompatibility(): array
    {
        return [
            /** Régression : version_compare('11', '11.0', '>=') est faux en PHP. */
            'JDK « 11 » accepté par défaut' => ['openjdk version "11"', null, true],
            'JDK 11.0.2'                    => ['openjdk version "11.0.2"', null, true],
            'JDK 17'                        => ['openjdk version "17.0.9"', null, true],
            'JDK 8 refusé'                  => ['java version "1.8.0_231"', null, false],
            'JDK 10 refusé'                 => ['openjdk version "10.0.2"', null, false],
            'java absent'                   => ['', null, false],
            'minimum 17 : 11 refusé'        => ['openjdk version "11.0.2"', '17', false],
            'minimum 17 : 17 accepté'       => ['openjdk version "17"', '17', true],
            'minimum 17 : 17.0.1 accepté'   => ['openjdk version "17.0.1"', '17', true],
            'minimum 17 : 21 accepté'       => ['openjdk version "21.0.2"', '17', true],
        ];
    }

    public function testPhpVersionAndCompatibility(): void
    {
        $env = new Environment();

        $this->assertSame(PHP_VERSION, $env->getPhpVersion());
        $this->assertTrue($env->isPhpVersionCompatible(), 'le projet exige PHP 8.1+');
        $this->assertTrue($env->isPhpVersionCompatible(PHP_VERSION));
        $this->assertFalse($env->isPhpVersionCompatible('99.0.0'));
        $this->assertTrue($env->isPhpVersionCompatible('5.6.0'));
    }

    // --- chemins absolus --------------------------------------------------

    /** @dataProvider absolutePaths */
    public function testAbsolutePathsAreRecognized(string $path): void
    {
        $this->assertTrue((new Environment())->isPathAbsolute($path), $path);
    }

    public static function absolutePaths(): array
    {
        return [
            'lecteur et antislash'          => ['C:\\environnement\\tools'],
            'lecteur et slash (PHP)'        => ['C:/environnement/tools'],
            'lecteur en minuscule, antislash' => ['c:\\environnement'],
            'lecteur en minuscule, slash'   => ['c:/environnement'],
            'autre lecteur'                 => ['D:/x'],
            'racine du lecteur'             => ['C:\\'],
            'unix'                          => ['/usr/bin/java'],
            'racine unix'                   => ['/'],
            'UNC antislash'                 => ['\\\\serveur\\partage\\dossier'],
            'UNC slash'                     => ['//serveur/partage'],
        ];
    }

    /** @dataProvider relativePaths */
    public function testRelativePathsAreNotAbsolute(string $path): void
    {
        $this->assertFalse((new Environment())->isPathAbsolute($path), $path);
    }

    public static function relativePaths(): array
    {
        return [
            'dossier'                      => ['tools/jdk17'],
            'point'                        => ['./tools'],
            'remontée'                     => ['../tools'],
            'antislash relatif'            => ['tools\\jdk17'],
            'remontée antislash'           => ['..\\tools'],
            'relatif au lecteur courant'   => ['C:tools'],
            'racine du lecteur courant'    => ['\\tools'],
            'vide'                         => [''],
            'nom seul'                     => ['config.json'],
        ];
    }

    // --- utilitaires système ---------------------------------------------

    public function testNormalizePathUsesThePlatformSeparator(): void
    {
        $sep = $this->sep();

        $this->assertSame("a{$sep}b{$sep}c", (new Environment())->normalizePath('a/b\\c'));
        $this->assertSame('', (new Environment())->normalizePath(''));
    }

    public function testOsInformation(): void
    {
        $env = new Environment();

        $this->assertSame(PHP_OS_FAMILY, $env->getOsFamily());
        $this->assertSame($this->isWindows(), $env->isWindows());
        $this->assertSame(PATH_SEPARATOR, $env->getClasspathSeparator(), 'séparateur de classpath = séparateur de PATH');
        $this->assertSame($this->isWindows() ? ';' : ':', $env->getClasspathSeparator());
    }

    public function testOsFullName(): void
    {
        $name = (new Environment())->getOsFullName();

        $this->assertStringStartsWith($this->isWindows() ? 'Windows ' : php_uname('s') . ' ', $name);
        $this->assertStringEndsWith(php_uname('r'), $name);
    }

    public function testTemporaryAndCurrentDirectoriesAreNormalized(): void
    {
        $env = new Environment();

        $this->assertSame(str_replace(['/', '\\'], $this->sep(), sys_get_temp_dir()), $env->getTempDirectory());
        $this->assertSame(str_replace(['/', '\\'], $this->sep(), (string) getcwd()), $env->getCurrentDirectory());
        $this->assertDirectoryExists($env->getTempDirectory());
    }

    public function testDateFormats(): void
    {
        $env = new Environment();

        $this->assertMatchesRegularExpression('/^\d{8}_\d{6}$/', $env->getTimestamp());
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $env->getDate());
        $this->assertStringStartsWith(date('Ymd'), $env->getTimestamp());
        $this->assertStringStartsWith(date('d/m/Y'), $env->getDate());
    }

    // --- isCommandAvailable ----------------------------------------------

    public function testCommandIsAvailableWhenTheLookupFindsSomething(): void
    {
        $env = new ScriptedEnvironment(['/usr/bin/git']);

        $this->assertTrue($env->isCommandAvailable('git'));
        $this->assertStringContainsString(escapeshellarg('git'), $env->commands[0]);
        $this->assertStringContainsString($this->isWindows() ? 'where ' : 'command -v ', $env->commands[0]);
    }

    /** @dataProvider emptyOutputs */
    public function testCommandIsUnavailableWhenTheLookupFindsNothing(string $output): void
    {
        $this->assertFalse((new ScriptedEnvironment([$output]))->isCommandAvailable('inconnu'));
    }

    /** Le nom de la commande est passé au shell : il doit être échappé. */
    public function testCommandNameIsEscapedBeforeReachingTheShell(): void
    {
        $env = new ScriptedEnvironment(['']);
        $hostile = 'java; echo PIRATE';

        $env->isCommandAvailable($hostile);

        $this->assertStringContainsString(escapeshellarg($hostile), $env->commands[0]);
        $this->assertSame(1, substr_count($env->commands[0], 'echo PIRATE'));
    }
}
