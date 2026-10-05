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
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Tests\Support\FakeEnvironment;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\VersionChecker;

/**
 * [Description VersionCheckerTest]
 * Tests unitaires pour VersionChecker (PHP, Java, extensions, version du JAR SchemaSpy).
 * L'environnement est simulé : aucun `java` n'est lancé et le résultat ne dépend pas des
 * extensions installées sur la machine.
 */
final class VersionCheckerTest extends TestCase
{
    private const ALL_EXTENSIONS = ['pdo', 'json', 'pdo_pgsql', 'pdo_mysql', 'pdo_oci'];

    private TempDir $tmp;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    /**
     * @param string|null $java version renvoyée par `java -version` (null = java absent)
     * @param list<string> $loaded extensions simulées comme chargées
     */
    private function checker(?string $java = '17.0.8', array $loaded = self::ALL_EXTENSIONS, ?string $php = '8.3.0'): VersionChecker
    {
        $logger = new Logger(false);
        $logger->setQuiet(true);

        return new VersionChecker(
            $logger,
            new FakeEnvironment(javaVersions: $java === null ? [] : ['PATH' => $java]),
            fn(string $ext): bool => in_array($ext, $loaded, true),
            $php
        );
    }

    // --- PHP ------------------------------------------------------------

    public function testPhpVersionDefaultsToTheRunningInterpreter(): void
    {
        $logger = new Logger(false);
        $logger->setQuiet(true);
        $checker = new VersionChecker($logger);

        $this->assertSame(PHP_VERSION, $checker->checkPhpVersion());
        $this->assertTrue($checker->checkRequiredPhpVersion(), 'le projet exige PHP 8.1+, les tests tournent donc dessus');
    }

    public function testPhpVersionCanBeSimulated(): void
    {
        $this->assertSame('8.3.0', $this->checker()->checkPhpVersion());
    }

    /** @dataProvider phpVersions */
    public function testRequiredPhpVersion(string $version, bool $expected): void
    {
        $this->assertSame($expected, $this->checker(php: $version)->checkRequiredPhpVersion());
    }

    public static function phpVersions(): array
    {
        return [
            '7.4'                 => ['7.4.33', false],
            '8.0 (dernier)'       => ['8.0.30', false],
            'juste en dessous'    => ['8.0.99', false],
            '8.1.0 exact'         => ['8.1.0', true],
            '8.1.0 en dev'        => ['8.1.0-dev', false],
            '8.2'                 => ['8.2.12', true],
            '8.5'                 => ['8.5.5', true],
            '9.0'                 => ['9.0.0', true],
        ];
    }

    // --- extensions -----------------------------------------------------

    public function testNoMissingExtensionWhenAllAreLoaded(): void
    {
        $this->assertSame([], $this->checker()->checkExtensions());
    }

    public function testMissingExtensionsAreListedInDeclaredOrder(): void
    {
        $missing = $this->checker(loaded: ['pdo', 'json', 'pdo_pgsql'])->checkExtensions();

        $this->assertSame(['pdo_mysql', 'pdo_oci'], $missing);
    }

    public function testEveryExtensionIsReportedWhenNoneIsLoaded(): void
    {
        $this->assertSame(self::ALL_EXTENSIONS, $this->checker(loaded: [])->checkExtensions());
    }

    public function testExtensionsAreCheckedIndependently(): void
    {
        $this->assertSame(['pdo'], $this->checker(loaded: ['json', 'pdo_pgsql', 'pdo_mysql', 'pdo_oci'])->checkExtensions());
    }

    public function testRealExtensionsAreUsedByDefault(): void
    {
        $logger = new Logger(false);
        $logger->setQuiet(true);
        $missing = (new VersionChecker($logger))->checkExtensions();

        // pdo et json sont indispensables au projet : s'ils manquaient, rien d'autre ne fonctionnerait
        $this->assertNotContains('pdo', $missing);
        $this->assertNotContains('json', $missing);
        foreach ($missing as $ext) {
            $this->assertFalse(extension_loaded($ext), "{$ext} signalée manquante alors qu'elle est chargée");
        }
    }

    // --- Java -----------------------------------------------------------

    /** @dataProvider javaVersions */
    public function testJavaVersionIsReadFromTheEnvironment(string $version): void
    {
        $this->assertSame($version, $this->checker($version)->checkJavaVersion());
    }

    public static function javaVersions(): array
    {
        return [
            'JDK 8 historique'  => ['1.8.0'],
            'JDK 11'            => ['11.0.22'],
            'JDK 17'            => ['17.0.8'],
            'JDK 21'            => ['21.0.2'],
            'version majeure'   => ['17'],
        ];
    }

    public function testJavaVersionIsNullWhenJavaIsMissing(): void
    {
        $this->assertNull($this->checker(java: null)->checkJavaVersion());
    }

    // --- version du JAR SchemaSpy --------------------------------------

    private function jar(string $manifest): string
    {
        $path = $this->tmp->path . '/test.jar';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('META-INF/MANIFEST.MF', $manifest);
        $zip->addFromString('org/schemaspy/Main.class', 'x');
        $zip->close();
        return $path;
    }

    private function requireZip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('Extension zip non disponible.');
        }
    }

    public function testJarVersionIsReadFromTheManifest(): void
    {
        $this->requireZip();
        $jar = $this->jar("Manifest-Version: 1.0\nImplementation-Title: SchemaSpy\nImplementation-Version: 7.0.3-lh.3\n");

        $this->assertSame('7.0.3-lh.3', $this->checker()->getSchemaSpyVersion($jar));
    }

    public function testJarVersionIsTrimmedAndAcceptsWindowsLineEndings(): void
    {
        $this->requireZip();
        $jar = $this->jar("Manifest-Version: 1.0\r\nImplementation-Version:  7.0.2  \r\nMain-Class: x\r\n");

        $this->assertSame('7.0.2', $this->checker()->getSchemaSpyVersion($jar));
    }

    public function testJarWithoutImplementationVersionHasNoVersion(): void
    {
        $this->requireZip();
        $jar = $this->jar("Manifest-Version: 1.0\nMain-Class: org.schemaspy.Main\n");

        $this->assertNull($this->checker()->getSchemaSpyVersion($jar));
    }

    public function testJarWithoutManifestHasNoVersion(): void
    {
        $this->requireZip();
        $path = $this->tmp->path . '/nomanifest.jar';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'x');
        $zip->close();

        $this->assertNull($this->checker()->getSchemaSpyVersion($path));
    }

    public function testMissingJarHasNoVersion(): void
    {
        $this->assertNull($this->checker()->getSchemaSpyVersion($this->tmp->path . '/absent.jar'));
    }

    public function testFolderIsNotAJar(): void
    {
        $this->assertNull($this->checker()->getSchemaSpyVersion($this->tmp->dir('dossier.jar')));
    }

    public function testNonZipFileHasNoVersion(): void
    {
        $this->requireZip();
        $file = $this->tmp->file('faux.jar', 'ceci n\'est pas une archive zip');

        $this->assertNull($this->checker()->getSchemaSpyVersion($file));
    }

    /** Le JAR configuré doit annoncer la version déclarée dans config.json (ignoré si le JAR est absent). */
    public function testConfiguredJarMatchesTheDeclaredVersion(): void
    {
        $this->requireZip();
        $root = dirname(__DIR__, 3);
        $config = json_decode((string) file_get_contents($root . '/config/config.json'), true, 512, JSON_THROW_ON_ERROR);
        $jar = $root . '/' . $config['schemaspy']['jar'];
        if (!is_file($jar)) {
            $this->markTestSkipped("JAR {$config['schemaspy']['jar']} absent (téléchargé hors dépôt).");
        }

        $this->assertSame($config['schemaspy']['version'], $this->checker()->getSchemaSpyVersion($jar));
    }

    // --- compatibilité globale -----------------------------------------

    public function testNoIssueWhenEverythingIsFine(): void
    {
        $this->assertSame([], $this->checker('17.0.8')->checkCompatibility([]));
    }

    public function testOldPhpIsReportedWithItsVersion(): void
    {
        $issues = $this->checker(php: '8.0.30')->checkCompatibility([]);

        $this->assertSame(['PHP 8.1 ou supérieur requis (actuel: 8.0.30)'], $issues);
    }

    public function testMissingExtensionsAreReported(): void
    {
        $issues = $this->checker(loaded: ['pdo', 'json'])->checkCompatibility([]);

        $this->assertSame(['Extensions PHP manquantes: pdo_pgsql, pdo_mysql, pdo_oci'], $issues);
    }

    public function testMissingJavaIsReported(): void
    {
        $this->assertSame(['Java non trouvé dans le PATH'], $this->checker(java: null)->checkCompatibility([]));
    }

    /** @dataProvider tooOldJava */
    public function testOldJavaIsReported(string $version): void
    {
        $issues = $this->checker($version)->checkCompatibility([]);

        $this->assertSame(["Java 11 ou supérieur requis (actuel: {$version})"], $issues);
    }

    public static function tooOldJava(): array
    {
        return [['1.8.0'], ['9.0.4'], ['10.0.2']];
    }

    /** @dataProvider acceptableJava */
    public function testJavaFromElevenIsAccepted(string $version): void
    {
        $this->assertSame([], $this->checker($version)->checkCompatibility([]));
    }

    public static function acceptableJava(): array
    {
        return [['11'], ['11.0.0'], ['17.0.8'], ['21.0.2']];
    }

    public function testAllIssuesAreReportedTogether(): void
    {
        $issues = $this->checker(java: null, loaded: [], php: '7.4.33')->checkCompatibility([]);

        $this->assertCount(3, $issues);
        $this->assertStringContainsString('PHP 8.1', $issues[0]);
        $this->assertStringContainsString('Extensions PHP manquantes', $issues[1]);
        $this->assertStringContainsString('Java non trouvé', $issues[2]);
    }
}
