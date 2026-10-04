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
use SchemaSpyCli\Cli\Application;
use SchemaSpyCli\Cli\ArgumentParser;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Exceptions\ConfigException;
use SchemaSpyCli\Exceptions\ValidationException;
use SchemaSpyCli\SchemaSpy\GenerationOptions;

/**
 * [Description ApplicationOptionsTest]
  * Tests de la fusion des options de génération dans Application
 * (config.json "generation" + ligne de commande + options saisies en interactif).
 *
 * Application est instanciée sans constructeur : celui-ci écrase logs/schemaspy-cli.log.
 */
final class ApplicationOptionsTest extends TestCase
{
    private array $savedArgv;
    private Config $config;

    protected function setUp(): void
    {
        $this->savedArgv = $GLOBALS['argv'] ?? [];
        $this->config = new Config();
        $this->config->set('schemaspy.markdown_supported', true);
    }

    protected function tearDown(): void
    {
        $GLOBALS['argv'] = $this->savedArgv;
    }

    private function app(string ...$cliArgs): Application
    {
        $GLOBALS['argv'] = ['schemaspy', ...$cliArgs];
        $parser = new ArgumentParser();
        $parser->parse();

        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $config = $this->config;
        (function () use ($config, $parser): void {
            $this->config = $config;
            $this->argumentParser = $parser;
        })->call($app);

        return $app;
    }

    private function call(Application $app, string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(Application::class, $method))->invoke($app, ...$args);
    }

    public function testDefaultsMergeConfigAndCommandLineWithCliWinning(): void
    {
        $this->config->set('generation', ['engine' => 'auto', 'markdown' => false, 'orphans' => true]);

        $defaults = $this->call($this->app('--markdown', '--no-orphans'), 'generationDefaults');

        $this->assertSame('auto', $defaults['engine']);
        $this->assertTrue($defaults['markdown']);
        $this->assertFalse($defaults['orphans']);
    }

    public function testDefaultsWithoutAnyConfiguration(): void
    {
        $this->assertSame([], $this->call($this->app(), 'generationDefaults'));
    }

    public function testOptionsAreBuiltFromDefaultsInNonInteractiveMode(): void
    {
        $this->config->set('generation', ['rows' => false]);

        $params = $this->call($this->app('--no-orphans'), 'applyGenerationOptions', ['useVizJs' => false]);

        $this->assertInstanceOf(GenerationOptions::class, $params['options']);
        $this->assertFalse($params['options']->rows);
        $this->assertFalse($params['options']->orphans);
        $this->assertFalse($params['useVizJs'], 'engine auto : la détection est conservée');
    }

    public function testOptionsChosenInteractivelyAreKept(): void
    {
        $chosen = new GenerationOptions(markdown: true, degree: 1);

        $params = $this->call($this->app('--no-orphans'), 'applyGenerationOptions', [
            'useVizJs' => false, 'options' => $chosen,
        ]);

        $this->assertSame($chosen, $params['options'], 'ni écrasées ni fusionnées une seconde fois');
    }

    /** @dataProvider engines */
    public function testExplicitEngineOverridesDetection(string $engine, bool $detectedVizJs, bool $expectedVizJs): void
    {
        $params = $this->call($this->app("--engine={$engine}"), 'applyGenerationOptions', ['useVizJs' => $detectedVizJs]);

        $this->assertSame($expectedVizJs, $params['useVizJs']);
    }

    public static function engines(): array
    {
        return [
            'vizjs impose viz.js'          => ['vizjs', false, true],
            'graphviz impose graphviz'     => ['graphviz', true, false],
            'auto garde la détection (1)'  => ['auto', true, true],
            'auto garde la détection (2)'  => ['auto', false, false],
        ];
    }

    public function testLegacyVizjsParameterStillWorks(): void
    {
        $params = $this->call($this->app('--vizjs=true', '--host=h', '--database=d', '--schema=s', '--user=u', '--password=p'), 'applyGenerationOptions', ['useVizJs' => false]);

        $this->assertSame('vizjs', $params['options']->engine);
        $this->assertTrue($params['useVizJs']);
    }

    public function testMarkdownIsRefusedWhenJarDoesNotSupportIt(): void
    {
        $this->config->set('schemaspy.markdown_supported', false);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('markdown_supported');
        $this->call($this->app('--markdown'), 'applyGenerationOptions', ['useVizJs' => false]);
    }

    public function testMarkdownIsRefusedWhenSupportFlagIsMissing(): void
    {
        $this->config->set('schemaspy', []);

        $this->expectException(ConfigException::class);
        $this->call($this->app('--markdown'), 'applyGenerationOptions', ['useVizJs' => false]);
    }

    public function testMarkdownChosenInteractivelyIsAlsoChecked(): void
    {
        $this->config->set('schemaspy.markdown_supported', false);

        $this->expectException(ConfigException::class);
        $this->call($this->app(), 'applyGenerationOptions', [
            'useVizJs' => false, 'options' => new GenerationOptions(markdown: true),
        ]);
    }

    public function testMarkdownIsAcceptedWhenSupported(): void
    {
        $params = $this->call($this->app('--markdown', '--no-html'), 'applyGenerationOptions', ['useVizJs' => false]);

        $this->assertTrue($params['options']->markdown);
        $this->assertFalse($params['options']->html);
    }

    public function testInvalidCombinationRaisesValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->call($this->app('--no-html'), 'applyGenerationOptions', ['useVizJs' => false]);
    }

    public function testInvalidEngineRaisesValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->call($this->app('--engine=cairo'), 'applyGenerationOptions', ['useVizJs' => false]);
    }
}
