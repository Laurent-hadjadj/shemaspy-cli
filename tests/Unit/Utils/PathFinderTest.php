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
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Tests\Support\FakeEnvironment;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\PathFinder;

/**
 * [Description PathFinderTest]
 * Tests unitaires pour PathFinder (sélection du JDK, détection/provisionnement de Graphviz)
 */
final class PathFinderTest extends TestCase
{
    private TempDir $tmp;
    private Config $config;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->config = new Config();
        $this->config->setBasePath($this->tmp->path);
        $this->config->set('paths.java_folder', 'tools/jdk17');
        $this->config->set('paths.graphviz_folder', 'tools/graphviz');
        $this->config->set('schemaspy.version', '7.0.3-lh.2');
        $this->config->set('schemaspy.compatibility', ['6' => '11', '7' => '17']);
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    private function fakeJdk(string $relative): string
    {
        $this->tmp->file($relative . '/bin/java.fake');
        return str_replace('/', DIRECTORY_SEPARATOR, $this->tmp->path . '/' . $relative);
    }

    private function finder(FakeEnvironment $env): PathFinder
    {
        $logger = new Logger(false);
        $logger->setQuiet(true);
        return new PathFinder($this->config, $logger, $env);
    }

    private function osKey(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'windows' : 'unix';
    }

    // --- JDK -----------------------------------------------------------

    public function testOldSystemJdkDoesNotHideEmbeddedJdk(): void
    {
        $system = $this->fakeJdk('sys/jdk8');
        $embedded = $this->fakeJdk('tools/jdk17');

        $java = $this->finder(new FakeEnvironment($system, [$system => '1.8.0', $embedded => '17.0.8']))->detectJava();

        $this->assertSame('embarqué', $java['source']);
        $this->assertSame('17.0.8', $java['version']);
        $this->assertSame($embedded, $java['home']);
    }

    public function testCompatibleSystemJdkIsPreferred(): void
    {
        $system = $this->fakeJdk('sys/jdk21');
        $embedded = $this->fakeJdk('tools/jdk17');

        $java = $this->finder(new FakeEnvironment($system, [$system => '21.0.1', $embedded => '17.0.8']))->detectJava();

        $this->assertSame('système', $java['source']);
        $this->assertSame('21.0.1', $java['version']);
    }

    public function testFirstJdkIsReturnedWhenNoneIsCompatible(): void
    {
        $system = $this->fakeJdk('sys/jdk8');
        $embedded = $this->fakeJdk('tools/jdk17');

        $java = $this->finder(new FakeEnvironment($system, [$system => '1.8.0', $embedded => '11.0.2']))->detectJava();

        // Aucun JDK 17+ : on remonte le premier trouvé, l'appelant signalera l'incompatibilité
        $this->assertSame('système', $java['source']);
        $this->assertSame('1.8.0', $java['version']);
    }

    public function testNullWhenNoJdkIsFound(): void
    {
        $this->assertNull($this->finder(new FakeEnvironment())->detectJava());
    }

    public function testExplicitConfigJdkIsUsedAsLastResort(): void
    {
        $explicit = $this->fakeJdk('custom/jdk');
        $this->config->set('paths.java_home.' . $this->osKey(), $explicit);

        $java = $this->finder(new FakeEnvironment(null, [$explicit => '17.0.1']))->detectJava();

        $this->assertSame('config', $java['source']);
        $this->assertSame($explicit, $java['home']);
    }

    public function testJdkWithUnknownVersionIsAccepted(): void
    {
        $system = $this->fakeJdk('sys/jdk');

        $java = $this->finder(new FakeEnvironment($system, []))->detectJava();

        $this->assertSame('système', $java['source']);
        $this->assertNull($java['version']);
    }

    public function testRequiredJavaVersionFollowsSchemaSpyMajor(): void
    {
        $this->assertSame('17', $this->finder(new FakeEnvironment())->requiredJavaVersion());

        $this->config->set('schemaspy.version', '6.2.4');
        $this->assertSame('11', $this->finder(new FakeEnvironment())->requiredJavaVersion());

        $this->config->set('schemaspy.version', '9.0.0');
        $this->assertNull($this->finder(new FakeEnvironment())->requiredJavaVersion());
    }

    public function testAnyJdkAcceptedWhenNoCompatibilityRule(): void
    {
        $this->config->set('schemaspy.version', '9.0.0');
        $system = $this->fakeJdk('sys/jdk8');

        $java = $this->finder(new FakeEnvironment($system, [$system => '1.8.0']))->detectJava();

        $this->assertSame('système', $java['source']);
    }

    // --- Graphviz ------------------------------------------------------

    private function dotName(string $base): string
    {
        return $base . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }

    public function testGraphvizFoundInPath(): void
    {
        $dot = $this->tmp->file('usr/graphviz/bin/' . $this->dotName('dot'));

        $gv = $this->finder(new FakeEnvironment(null, [], $dot))->detectGraphviz();

        $this->assertSame('système', $gv['source']);
        $this->assertSame('16.1.0', $gv['version']);
        $this->assertSame(dirname($dot, 2), str_replace('\\', '/', $gv['path']));
    }

    public function testEmbeddedGraphvizIsDetected(): void
    {
        $dot = $this->tmp->file('tools/graphviz/bin/' . $this->dotName('dot'), 'binaire');

        $gv = $this->finder(new FakeEnvironment())->detectGraphviz();

        $this->assertSame('embarqué', $gv['source']);
        $this->assertSame('binaire', file_get_contents($dot), 'dot existant : ne doit pas être remplacé');
    }

    public function testDotIsProvisionedFromDotBuiltins(): void
    {
        $builtins = $this->tmp->file('tools/graphviz/bin/' . $this->dotName('dot_builtins'), 'builtins');
        $expectedDot = dirname($builtins) . '/' . $this->dotName('dot');
        $this->assertFileDoesNotExist($expectedDot);

        $gv = $this->finder(new FakeEnvironment())->detectGraphviz();

        $this->assertNotNull($gv);
        $this->assertSame('embarqué', $gv['source']);
        $this->assertFileExists($expectedDot);
        $this->assertSame('builtins', file_get_contents($expectedDot));
    }

    public function testExplicitConfigGraphviz(): void
    {
        $dot = $this->tmp->file('custom/gv/bin/' . $this->dotName('dot'));
        $this->config->set('paths.graphviz_home.' . $this->osKey(), dirname($dot, 2));

        $gv = $this->finder(new FakeEnvironment())->detectGraphviz();

        $this->assertSame('config', $gv['source']);
    }

    public function testNullWhenGraphvizIsNotFound(): void
    {
        $this->assertNull($this->finder(new FakeEnvironment())->detectGraphviz());
    }

    // --- mémorisation --------------------------------------------------

    public function testJdkDetectionRunsOnlyOnce(): void
    {
        $system = $this->fakeJdk('sys/jdk21');
        $env = new FakeEnvironment($system, [$system => '21.0.1']);
        $finder = $this->finder($env);

        $first = $finder->detectJava();
        $second = $finder->detectJava();

        $this->assertSame($first, $second);
        $this->assertSame(1, $env->javaVersionCalls, 'un seul `java -version`');
        $this->assertSame(1, $env->javaHomeCalls);
    }

    public function testMissingJdkIsAlsoMemoized(): void
    {
        $env = new FakeEnvironment();
        $finder = $this->finder($env);

        $this->assertNull($finder->detectJava());
        $this->assertNull($finder->detectJava());
        $this->assertSame(1, $env->javaHomeCalls, 'l\'absence de JDK n\'est pas recherchée à nouveau');
    }

    public function testGraphvizDetectionRunsOnlyOnce(): void
    {
        $env = new FakeEnvironment();
        $finder = $this->finder($env);

        $this->assertNull($finder->detectGraphviz());
        $this->assertNull($finder->detectGraphviz());
        $this->assertSame(1, $env->graphvizPathCalls);
    }

    public function testFailedDotProvisioningIsLoggedAndGraphvizIsNotDetected(): void
    {
        // « dot_builtins » existe mais n'est pas copiable (c'est un dossier) : copy() échoue
        $this->tmp->dir('tools/graphviz/bin/' . $this->dotName('dot_builtins'));
        $logFile = $this->tmp->path . '/pathfinder.log';
        $logger = new Logger(false, $logFile);
        $logger->setQuiet(true);

        $gv = (new PathFinder($this->config, $logger, new FakeEnvironment()))->detectGraphviz();

        $this->assertNull($gv);
        $this->assertStringContainsString('Impossible de créer', (string) file_get_contents($logFile));
    }
}
