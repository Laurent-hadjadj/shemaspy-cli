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
use SchemaSpyCli\Cli\InteractiveMode;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Utils\Validator;

/**
 * [Description InteractiveModeTest]
  * Tests unitaires pour InteractiveMode (workflow en 4 étapes et options de génération)
 *
 * Les saisies sont injectées via Logger::setInputStream() : aucune interaction réelle.
 * saveLastParams() n'est volontairement pas appelée (elle écrit schemaspy.last.json).
 */
final class InteractiveModeTest extends TestCase
{
    /** Réponses aux étapes 1 et 2 : type n°1 (postgresql) puis connexion. */
    private const CONNECTION = ['1', 'localhost', '5432', 'demo', 'public', 'scott', 'tiger'];

    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->config->set('jdbc', [
            'postgresql' => ['type' => 'pgsql11', 'port' => 5432, 'driver' => 'postgresql-42.7.13.jar', 'version' => '42.7.13', 'db_version' => '11-18'],
            'oracle'     => ['type' => 'orathin', 'port' => 1521, 'driver' => 'ojdbc11.jar', 'version' => '23', 'db_version' => '19c-23c'],
        ]);
        $this->config->set('schemaspy.markdown_supported', true);
    }

    /**
     * Joue une session : retourne [params|null, sortie console sans ANSI].
     *
     * @param list<string> $answers
     */
    private function session(array $answers, array $optionDefaults = []): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, implode("\n", $answers) . "\n");
        rewind($stream);

        $logger = new Logger(false);
        $logger->setInputStream($stream);
        $mode = new InteractiveMode($this->config, $logger, new Validator());

        ob_start();
        try {
            $params = $mode->collect($optionDefaults);
        } finally {
            $output = (string) ob_get_clean();
            fclose($stream);
        }

        return [$params, preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $output)];
    }

    public function testDefaultPathKeepsCurrentOptionsAndShowsFourSteps(): void
    {
        [$params, $output] = $this->session([...self::CONNECTION, '', 'o']);

        $this->assertNotNull($params);
        $this->assertSame('postgresql', $params['dbType']);
        $this->assertSame('localhost', $params['host']);
        $this->assertSame(5432, $params['port']);
        $this->assertSame('demo', $params['database']);
        $this->assertSame('public', $params['schema']);
        $this->assertSame('scott', $params['user']);
        $this->assertSame('tiger', $params['password']);

        $options = $params['options'];
        $this->assertTrue($options->html);
        $this->assertFalse($options->markdown);
        $this->assertTrue($options->orphans);

        foreach (['Étape 1/4', 'Étape 2/4', 'Étape 3/4', 'Étape 4/4'] as $step) {
            $this->assertStringContainsString($step, $output);
        }
        $this->assertStringContainsString('Options actuelles : HTML / par défaut', $output);
        $this->assertStringContainsString('Personnaliser les options de génération ?', $output);
        $this->assertStringContainsString('Récapitulatif', $output);
    }

    public function testStepOrderIsConnectionThenOptionsThenSummary(): void
    {
        [, $output] = $this->session([...self::CONNECTION, '', 'o']);

        $positions = array_map(fn(string $s) => strpos($output, $s), [
            'Étape 1/4', 'Étape 2/4', 'Étape 3/4', 'Étape 4/4',
        ]);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertNotContains(false, $positions);
    }

    public function testCliOptionsPrefillTheStepAndTheSummary(): void
    {
        [$params, $output] = $this->session(
            [...self::CONNECTION, '', 'o'],
            ['markdown' => true, 'orphans' => false, 'rows' => false, 'engine' => 'vizjs']
        );

        $options = $params['options'];
        $this->assertTrue($options->markdown);
        $this->assertFalse($options->orphans);
        $this->assertFalse($options->rows);
        $this->assertStringContainsString('Options actuelles : HTML + Markdown / sans orphelines, sans comptage', $output);
        $this->assertStringContainsString('Sorties       : HTML + Markdown', $output);
        $this->assertStringContainsString('Options       : sans orphelines, sans comptage', $output);
        $this->assertStringContainsString('Moteur        : vizjs', $output);
    }

    public function testFullCustomization(): void
    {
        $answers = [
            ...self::CONNECTION,
            'o',      // personnaliser
            '3',      // moteur : vizjs
            'o',      // HTML
            'o',      // Markdown
            'o',      // exclure les orphelines
            'o',      // exclure les vues
            'n',      // compter les lignes
            'n',      // relations implicites
            '1',      // degré
            '^T_',    // include
            'TMP_',   // exclude
            'o',      // confirmation
        ];

        [$params, $output] = $this->session($answers);

        $options = $params['options'];
        $this->assertSame('vizjs', $options->engine);
        $this->assertTrue($options->html);
        $this->assertTrue($options->markdown);
        $this->assertFalse($options->orphans);
        $this->assertFalse($options->views);
        $this->assertFalse($options->rows);
        $this->assertFalse($options->implied);
        $this->assertSame(1, $options->degree);
        $this->assertSame('^T_', $options->include);
        $this->assertSame('TMP_', $options->exclude);

        $this->assertStringContainsString('Moteur        : vizjs', $output);
        $this->assertStringContainsString('Sorties       : HTML + Markdown', $output);
        $this->assertStringContainsString(
            'sans orphelines, sans vues, sans comptage, sans relations implicites, degré 1, inclure ^T_, exclure TMP_',
            $output
        );
    }

    public function testEntersKeepTheProposedValuesWhenCustomizing(): void
    {
        // Personnaliser, puis Entrée à chaque question : on retrouve les valeurs actuelles
        $answers = [...self::CONNECTION, 'o', '', '', '', '', '', '', '', '', '', '', 'o'];

        [$params] = $this->session($answers, ['orphans' => false]);

        $options = $params['options'];
        $this->assertSame('auto', $options->engine);
        $this->assertTrue($options->html);
        $this->assertFalse($options->markdown);
        $this->assertFalse($options->orphans, 'la valeur préremplie (CLI) est conservée');
        $this->assertTrue($options->views);
        $this->assertTrue($options->rows);
        $this->assertTrue($options->implied);
        $this->assertSame(2, $options->degree);
        $this->assertNull($options->include);
        $this->assertNull($options->exclude);
    }

    public function testEngineChoicesAreMapped(): void
    {
        foreach (['1' => 'auto', '2' => 'graphviz', '3' => 'vizjs'] as $choice => $expected) {
            [$params] = $this->session([...self::CONNECTION, 'o', $choice, '', '', '', '', '', '', '', '', '', 'o']);
            $this->assertSame($expected, $params['options']->engine);
        }
    }

    public function testInvalidAnswersAreAskedAgain(): void
    {
        $answers = [
            ...self::CONNECTION,
            'o',
            '9', '2',          // moteur : choix invalide puis graphviz
            '', '', '', '', '', '',
            '5', '2',          // degré invalide puis valide
            '(unclosed', '^OK_', // regex invalide puis valide
            '',
            'o',
        ];

        [$params, $output] = $this->session($answers);

        $options = $params['options'];
        $this->assertSame('graphviz', $options->engine);
        $this->assertSame(2, $options->degree);
        $this->assertSame('^OK_', $options->include);
        $this->assertStringContainsString("n'est pas une expression régulière valide", $output);
    }

    public function testDeselectingAllOutputsKeepsHtml(): void
    {
        $answers = [...self::CONNECTION, 'o', '1', 'n', 'n', '', '', '', '', '', '', '', 'o'];

        [$params, $output] = $this->session($answers);

        $this->assertTrue($params['options']->html);
        $this->assertFalse($params['options']->markdown);
        $this->assertStringContainsString('Aucune sortie sélectionnée', $output);
    }

    public function testMarkdownIsNotOfferedWhenJarDoesNotSupportIt(): void
    {
        $this->config->set('schemaspy.markdown_supported', false);
        // Pas de question Markdown : 1 de moins dans la liste de réponses
        $answers = [...self::CONNECTION, 'o', '1', '', '', '', '', '', '', '', 'o'];

        [$params, $output] = $this->session($answers, ['markdown' => true]);

        $this->assertFalse($params['options']->markdown);
        $this->assertStringContainsString('Markdown indisponible', $output);
        $this->assertStringNotContainsString('Générer la documentation Markdown', $output);
    }

    public function testDecliningConfirmationCancels(): void
    {
        [$params, $output] = $this->session([...self::CONNECTION, '', 'n']);

        $this->assertNull($params);
        $this->assertStringContainsString('Opération annulée', $output);
    }

    public function testSelectedDatabaseTypeIsHighlightedAsDefault(): void
    {
        [, $output] = $this->session([...self::CONNECTION, '', 'o']);

        $this->assertMatchesRegularExpression('/\[\d\] \w+ \(défaut\)/', $output);
        $this->assertStringNotContainsString('[]', $output, 'le marqueur par défaut ne doit pas être un crochet vide');
    }

    public function testInvalidOptionDefaultsAreRejectedEarly(): void
    {
        $this->expectException(\SchemaSpyCli\Exceptions\ValidationException::class);
        $this->session([...self::CONNECTION, '', 'o'], ['html' => false]);
    }

    public function testSummaryWarnsWhenVizJsIsSelected(): void
    {
        [, $output] = $this->session([...self::CONNECTION, 'o', '3', '', '', '', '', '', '', '', '', '', 'o']);

        $this->assertStringContainsString('Moteur        : vizjs (lent sur les grands schémas)', $output);
        $this->assertStringContainsString('très lent au-delà de ~50 tables', $output, 'avertissement dans le menu du moteur');
    }
}
