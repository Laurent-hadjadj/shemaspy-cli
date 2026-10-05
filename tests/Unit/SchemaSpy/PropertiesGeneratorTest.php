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
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Database\DriverManager;
use SchemaSpyCli\SchemaSpy\GenerationOptions;
use SchemaSpyCli\SchemaSpy\PropertiesGenerator;
use SchemaSpyCli\Tests\Support\FakeEnvironment;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\PathFinder;

/**
 * [Description PropertiesGeneratorTest]
 * Tests unitaires pour PropertiesGenerator
 *
 */
final class PropertiesGeneratorTest extends TestCase
{
    private TempDir $tmp;
    private Config $config;
    private string $logFile;
    private array $generated = [];

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->logFile = $this->tmp->path . '/test.log';

        $this->tmp->file('jdbc/postgresql-42.7.13.jar');
        $this->config = new Config();
        $this->config->setBasePath($this->tmp->path);
        $this->config->set('paths.jdbc_folder', 'jdbc');
        $this->config->set('paths.graphviz_folder', 'tools/graphviz');
        $this->config->set('application', ['name' => 'App', 'company' => 'ACME', 'copyright' => '(c) ACME']);
        $this->config->set('jdbc.postgresql', [
            'type' => 'pgsql11', 'port' => 5432, 'driver' => 'postgresql-42.7.13.jar', 'version' => '42.7.13',
        ]);
        $this->config->set('connprops', ['useSSL' => 'false']);
        $this->config->set('defaults.image_format', 'svg');
    }

    protected function tearDown(): void
    {
        foreach ($this->generated as $file) {
            @unlink($file);
        }
        $this->tmp->remove();
    }

    private function withGraphviz(): void
    {
        $dot = 'tools/graphviz/bin/' . (PHP_OS_FAMILY === 'Windows' ? 'dot.exe' : 'dot');
        $this->tmp->file($dot);
    }

    /** Génère le fichier et retourne [propriétés clé=>valeur, contenu brut]. */
    private function generate(array $overrides = []): array
    {
        $logger = new Logger(false, $this->logFile);
        $logger->setQuiet(true);
        $driverManager = new DriverManager($this->config, $logger);
        $generator = new PropertiesGenerator(
            $this->config,
            new Environment(),
            $logger,
            $driverManager,
            new PathFinder($this->config, null, new FakeEnvironment())
        );

        $params = array_merge([
            'dbType' => 'postgresql',
            'dbConfig' => $this->config->getDatabase('postgresql'),
            'host' => 'db.local', 'port' => 5432, 'database' => 'demo', 'schema' => 'public',
            'user' => 'scott', 'password' => 'S3cret!',
            'output' => 'ut_' . uniqid(), 'useVizJs' => false,
            'options' => new GenerationOptions(),
        ], $overrides);

        $file = $generator->generate($params, $this->tmp->path . '/report/out');
        $this->generated[] = $file;
        $content = file_get_contents($file);

        $props = [];
        foreach (explode("\n", $content) as $line) {
            if ($line !== '' && $line[0] !== '#' && str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $props[$k] = $v;
            }
        }
        return [$props, $content];
    }

    public function testConnectionProperties(): void
    {
        [$props] = $this->generate();

        $this->assertSame('pgsql11', $props['schemaspy.t']);
        $this->assertSame('db.local', $props['schemaspy.host']);
        $this->assertSame('5432', $props['schemaspy.port']);
        $this->assertSame('demo', $props['schemaspy.db']);
        $this->assertSame('public', $props['schemaspy.s']);
        $this->assertSame('scott', $props['schemaspy.u']);
        $this->assertSame('S3cret!', $props['schemaspy.p']);
        $this->assertSame('false', $props['schemaspy.connprops.useSSL']);
    }

    public function testPasswordIsMaskedInHeaderComment(): void
    {
        [, $content] = $this->generate();

        $header = substr($content, 0, strpos($content, 'schemaspy.t='));
        $this->assertStringNotContainsString('S3cret!', $header);
        $this->assertStringContainsString('***', $header);
    }

    public function testGraphvizIsUsedWhenDetected(): void
    {
        $this->withGraphviz();

        [$props] = $this->generate();

        $this->assertArrayHasKey('schemaspy.gv', $props);
        $this->assertSame('svg', $props['schemaspy.imageformat']);
        $this->assertArrayNotHasKey('schemaspy.vizjs', $props);
        $this->assertArrayNotHasKey('schemaspy.renderer', $props, 'renderer vide par défaut (pas de cairo imposé)');
        $this->assertArrayNotHasKey('schemaspy.render', $props, 'ancienne clé inexistante côté SchemaSpy');
    }

    public function testConfiguredRendererIsPrefixedWithColon(): void
    {
        $this->withGraphviz();
        $this->config->set('defaults.renderer', 'gd');

        [$props] = $this->generate();

        $this->assertSame(':gd', $props['schemaspy.renderer']);
    }

    public function testVizJsWhenRequested(): void
    {
        $this->withGraphviz();

        [$props] = $this->generate(['useVizJs' => true]);

        $this->assertSame('true', $props['schemaspy.vizjs']);
        $this->assertArrayNotHasKey('schemaspy.gv', $props);
    }

    public function testVizJsFallbackWhenGraphvizMissing(): void
    {
        [$props] = $this->generate();

        $this->assertSame('true', $props['schemaspy.vizjs']);
        $this->assertSame('svg', $props['schemaspy.imageformat']);
    }

    public function testExplicitGraphvizEngineWarnsOnFallback(): void
    {
        [$props] = $this->generate(['options' => new GenerationOptions(engine: GenerationOptions::ENGINE_GRAPHVIZ)]);

        $this->assertSame('true', $props['schemaspy.vizjs']);
        $this->assertStringContainsString('--engine=graphviz', file_get_contents($this->logFile));
    }

    public function testGenerationOptionsAreWrittenAsProperties(): void
    {
        [$props] = $this->generate(['options' => new GenerationOptions(
            markdown: true, html: false, orphans: false, degree: 1, exclude: 'TMP_.*'
        )]);

        $this->assertSame('true', $props['schemaspy.markdown']);
        $this->assertSame('true', $props['schemaspy.nohtml']);
        $this->assertSame('true', $props['schemaspy.no-orphans']);
        $this->assertSame('1', $props['schemaspy.degree']);
        $this->assertSame('TMP_.*', $props['schemaspy.I']);
    }

    public function testNoOptionsMeansNoOptionProperties(): void
    {
        [$props] = $this->generate(['options' => null]);

        foreach (['schemaspy.markdown', 'schemaspy.nohtml', 'schemaspy.no-orphans', 'schemaspy.degree'] as $key) {
            $this->assertArrayNotHasKey($key, $props);
        }
    }

    public function testMissingLogoDisablesLogo(): void
    {
        [$props] = $this->generate();

        $this->assertSame('true', $props['schemaspy.nologo']);
        $this->assertArrayNotHasKey('schemaspy.logo', $props);
    }

    public function testLogoIsUsedWhenPresent(): void
    {
        $this->tmp->file('ressources/logo.png');

        [$props] = $this->generate();

        $this->assertSame('false', $props['schemaspy.nologo']);
        $this->assertStringEndsWith('ressources/logo.png', $props['schemaspy.logo']);
    }

    public function testFaviconIsUsedWhenPresent(): void
    {
        $this->tmp->file('favicon.ico');

        [$props] = $this->generate();

        $this->assertStringEndsWith('favicon.ico', $props['schemaspy.favicon']);
    }

    public function testDriverInfoIsWrittenInHeader(): void
    {
        [, $content] = $this->generate();

        $this->assertStringContainsString('# Driver JDBC: postgresql-42.7.13.jar', $content);
    }

    /** @dataProvider driverSizes */
    public function testDriverSizeIsHumanReadableInTheHeader(int $bytes, string $expected): void
    {
        $this->tmp->file('jdbc/postgresql-42.7.13.jar', str_repeat('x', $bytes));

        [, $content] = $this->generate();

        $this->assertStringContainsString("# Taille: {$expected}", $content);
    }

    public static function driverSizes(): array
    {
        return [
            'quelques octets' => [500, '500 B'],
            'un kilo-octet'   => [1024, '1 KB'],
            'quelques Ko'     => [3000, '2.93 KB'],
            'un mega-octet'   => [1024 * 1024, '1 MB'],
        ];
    }
}
