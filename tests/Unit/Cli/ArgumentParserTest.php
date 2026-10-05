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
use SchemaSpyCli\Cli\ArgumentParser;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\ProcessRunner;

/**
 * [Description ArgumentParserTest]
 * Tests unitaires pour ArgumentParser : drapeaux, paramètres de connexion, avertissements,
 * valeurs par défaut et aide en ligne. (Les options de génération ont aussi leur propre
 * fichier : ArgumentParserOptionsTest.)
 *
 * Les avertissements passent par error_log() : on les redirige vers un fichier temporaire.
 */
final class ArgumentParserTest extends TestCase
{
    private const CONNECTION = ['--host=db.local', '--database=demo', '--schema=public', '--user=scott', '--password=tiger'];

    private TempDir $tmp;
    private array $savedArgv;
    private string|false $savedErrorLog;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->savedArgv = $GLOBALS['argv'] ?? [];
        $this->savedErrorLog = ini_get('error_log');
        ini_set('error_log', $this->tmp->path . '/warnings.log');
    }

    protected function tearDown(): void
    {
        $GLOBALS['argv'] = $this->savedArgv;
        ini_set('error_log', $this->savedErrorLog === false ? '' : $this->savedErrorLog);
        $this->tmp->remove();
    }

    private function parse(string ...$args): ArgumentParser
    {
        $GLOBALS['argv'] = ['schemaspy', ...$args];
        $parser = new ArgumentParser();
        $parser->parse();
        return $parser;
    }

    private function warnings(): string
    {
        $file = $this->tmp->path . '/warnings.log';
        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    // --- drapeaux -------------------------------------------------------

    public function testDefaultsWithoutArguments(): void
    {
        $parser = $this->parse();

        $this->assertFalse($parser->isQuiet());
        $this->assertFalse($parser->isVerbose());
        $this->assertFalse($parser->isHelp());
        $this->assertNull($parser->getConfigFile());
        $this->assertFalse($parser->hasParams());
        $this->assertSame([], $parser->getOptions());
        $this->assertSame('', $this->warnings());
    }

    /** @dataProvider quietFlags */
    public function testQuietFlags(string $flag): void
    {
        $this->assertTrue($this->parse($flag)->isQuiet());
    }

    public static function quietFlags(): array
    {
        return [['--quiet'], ['-q']];
    }

    /** @dataProvider verboseFlags */
    public function testVerboseFlags(string $flag): void
    {
        $parser = $this->parse($flag);

        $this->assertTrue($parser->isVerbose());
        $this->assertFalse($parser->isQuiet());
    }

    public static function verboseFlags(): array
    {
        return [['--verbose'], ['-v']];
    }

    public function testFlagsCanBeCombinedAndOrderDoesNotMatter(): void
    {
        $a = $this->parse('--quiet', '--verbose', ...self::CONNECTION);
        $b = $this->parse(...[...self::CONNECTION, '-v', '-q']);

        foreach ([$a, $b] as $parser) {
            $this->assertTrue($parser->isQuiet());
            $this->assertTrue($parser->isVerbose());
            $this->assertSame('db.local', $parser->getParam('host'));
        }
    }

    public function testClusteredShortFlagsAreNotSupported(): void
    {
        $parser = $this->parse('-qv');

        $this->assertFalse($parser->isQuiet());
        $this->assertStringContainsString('Option inconnue: -qv', $this->warnings());
    }

    // --- --config=------------------------------------------------------

    public function testConfigFileIsCaptured(): void
    {
        $this->assertSame('config/prod.json', $this->parse('--config=config/prod.json')->getConfigFile());
    }

    public function testConfigPathMayContainEqualsAndSpaces(): void
    {
        $this->assertSame('C:/dir a/x=y.json', $this->parse('--config=C:/dir a/x=y.json')->getConfigFile());
    }

    /** Régression : --config= vide donnait « Erreur de parsing du fichier: <dossier du projet> (fichier vide) ». */
    public function testEmptyConfigValueFallsBackToTheDefaultFile(): void
    {
        $this->assertNull($this->parse('--config=')->getConfigFile());
        $this->assertNull($this->parse('--config=   ')->getConfigFile());
    }

    public function testConfigValueIsTrimmed(): void
    {
        $this->assertSame('config/prod.json', $this->parse('--config= config/prod.json ')->getConfigFile());
    }
    public function testConfigIsNotTreatedAsAConnectionParameter(): void
    {
        $parser = $this->parse('--config=config/prod.json');

        $this->assertFalse($parser->hasParams(), 'seule une option de connexion bascule en mode non interactif');
        $this->assertSame('', $this->warnings());
    }

    // --- paramètres de connexion ---------------------------------------

    public function testConnectionParametersAreCollected(): void
    {
        $parser = $this->parse('--db=oracle', '--port=1521', '--output=run_1', ...self::CONNECTION);

        $this->assertTrue($parser->hasParams());
        $this->assertSame('oracle', $parser->getParam('db'));
        $this->assertSame('1521', $parser->getParam('port'));
        $this->assertSame('db.local', $parser->getParam('host'));
        $this->assertSame('demo', $parser->getParam('database'));
        $this->assertSame('public', $parser->getParam('schema'));
        $this->assertSame('scott', $parser->getParam('user'));
        $this->assertSame('tiger', $parser->getParam('password'));
        $this->assertSame('run_1', $parser->getParam('output'));
        $this->assertSame('', $this->warnings());
    }

    public function testValueMayContainEqualsSigns(): void
    {
        $parser = $this->parse(...[...self::CONNECTION, '--password=a=b==c']);

        $this->assertSame('a=b==c', $parser->getParam('password'), 'seul le premier = sépare la clé de la valeur');
    }

    public function testKeysAndValuesAreTrimmed(): void
    {
        $parser = $this->parse('--host= db.local ', ...array_slice(self::CONNECTION, 1));

        $this->assertSame('db.local', $parser->getParam('host'));
    }

    public function testPasswordKeepsSpecialCharacters(): void
    {
        $parser = $this->parse(...[...array_slice(self::CONNECTION, 0, 4), '--password=P@ss;w0rd!#$%&*()']);

        $this->assertSame('P@ss;w0rd!#$%&*()', $parser->getParam('password'));
    }

    public function testLastOccurrenceWins(): void
    {
        $this->assertSame('b', $this->parse('--host=a', '--host=b')->getParam('host'));
    }

    // --- avertissements -------------------------------------------------

    public function testMissingRequiredParametersAreReportedTogether(): void
    {
        $this->parse('--host=db.local', '--user=scott');

        $warnings = $this->warnings();
        $this->assertStringContainsString('Paramètres requis manquants: database, schema, password', $warnings);
        $this->assertStringContainsString('--help', $warnings);
    }

    public function testEmptyRequiredValueCountsAsMissing(): void
    {
        $this->parse('--host=', '--database=demo', '--schema=public', '--user=scott', '--password=tiger');

        $this->assertStringContainsString('Paramètres requis manquants: host', $this->warnings());
    }

    public function testNoMissingParametersWarningWithoutAnyConnectionParameter(): void
    {
        $this->parse('--quiet', '--markdown');

        $this->assertSame('', $this->warnings());
    }

    public function testUnknownParameterIsReportedAndIgnored(): void
    {
        $parser = $this->parse('--colour=blue', ...self::CONNECTION);

        $this->assertStringContainsString('Paramètre inconnu: colour', $this->warnings());
        $this->assertNull($parser->getParam('colour'));
    }

    public function testLongOptionWithoutValueIsReported(): void
    {
        $this->parse('--host');

        $this->assertStringContainsString('Format invalide: --host. Utilisez --key=value', $this->warnings());
    }

    /** @dataProvider unknownArguments */
    public function testUnknownPositionalOrShortArgumentsAreReported(string $arg): void
    {
        $this->parse($arg);

        $this->assertStringContainsString("Option inconnue: {$arg}", $this->warnings());
    }

    public static function unknownArguments(): array
    {
        return [['-x'], ['positional'], ['-']];
    }

    // --- getParams / getParam / hasParam -------------------------------

    public function testGetParamsMergesDefaultsWithProvidedValues(): void
    {
        $params = $this->parse('--port=5433', ...self::CONNECTION)->getParams();

        $this->assertSame('postgresql', $params['db'], 'type par défaut');
        $this->assertSame('false', $params['vizjs']);
        $this->assertNull($params['output']);
        $this->assertSame('5433', $params['port']);
        $this->assertSame('db.local', $params['host']);
        $this->assertSame(
            ['db', 'host', 'port', 'database', 'schema', 'user', 'password', 'vizjs', 'output'],
            array_keys($params)
        );
    }

    public function testProvidedValueOverridesDefault(): void
    {
        $this->assertSame('oracle', $this->parse('--db=oracle', ...self::CONNECTION)->getParams()['db']);
    }

    public function testGetParamFallbacks(): void
    {
        $parser = $this->parse();

        $this->assertSame('postgresql', $parser->getParam('db'), 'valeur par défaut déclarée');
        $this->assertNull($parser->getParam('host'));
        $this->assertSame('x', $parser->getParam('host', 'x'), 'défaut fourni par l\'appelant');
        $this->assertSame('y', $parser->getParam('inexistant', 'y'));
    }

    public function testHasParamOnlyReflectsExplicitlyProvidedValues(): void
    {
        $parser = $this->parse('--host=h');

        $this->assertTrue($parser->hasParam('host'));
        $this->assertFalse($parser->hasParam('db'), 'une valeur par défaut n\'est pas "présente"');
    }

    // --- résumé ---------------------------------------------------------

    public function testSummaryMasksThePassword(): void
    {
        $summary = $this->parse(...self::CONNECTION)->getParamsSummary();

        $this->assertStringContainsString('host=db.local', $summary);
        $this->assertStringContainsString('password=******', $summary);
        $this->assertStringNotContainsString('tiger', $summary);
    }

    public function testSummaryWithoutParametersMentionsInteractiveMode(): void
    {
        $this->assertStringContainsString('mode interactif', $this->parse()->getParamsSummary());
    }

    // --- validateConsistency -------------------------------------------

    public function testConsistencyAcceptsValidValues(): void
    {
        $parser = $this->parse('--port=5432', '--vizjs=true', ...self::CONNECTION);

        $this->assertSame([], $parser->validateConsistency());
    }

    /** @dataProvider invalidPorts */
    public function testConsistencyRejectsPortsOutsideRange(string $port): void
    {
        $errors = $this->parse("--port={$port}", ...self::CONNECTION)->validateConsistency();

        $this->assertSame(['Le port doit être compris entre 1 et 65535'], $errors);
    }

    public static function invalidPorts(): array
    {
        return [['0'], ['65536'], ['abc'], ['-5']];
    }

    public function testConsistencyRejectsInvalidVizjs(): void
    {
        $errors = $this->parse('--vizjs=peut-etre', ...self::CONNECTION)->validateConsistency();

        $this->assertSame(['vizjs doit être true ou false'], $errors);
    }

    public function testConsistencyReportsAllErrorsAtOnce(): void
    {
        $errors = $this->parse('--vizjs=non', '--port=0', ...self::CONNECTION)->validateConsistency();

        $this->assertCount(2, $errors);
    }

    // --- options de génération (compléments) ---------------------------

    public function testGenerationValuesAreTrimmedAndKeepEquals(): void
    {
        $parser = $this->parse('--include= ^T_ ', '--exclude=a=b');

        $this->assertSame('^T_', $parser->getOptions()['include']);
        $this->assertSame('a=b', $parser->getOptions()['exclude']);
    }

    public function testLegacyVizjsFalseDoesNotForceAnEngine(): void
    {
        $this->assertArrayNotHasKey('engine', $this->parse('--vizjs=false', ...self::CONNECTION)->getOptions());
    }

    public function testLegacyVizjsIsCaseInsensitive(): void
    {
        $this->assertSame('vizjs', $this->parse('--vizjs=TRUE', ...self::CONNECTION)->getOptions()['engine']);
    }

    public function testRepeatedFlagsAreHarmless(): void
    {
        $options = $this->parse('--markdown', '--markdown', '--no-html', '--no-html')->getOptions();

        $this->assertSame(['markdown' => true, 'html' => false], $options);
    }

    // --- aide -----------------------------------------------------------

    private function help(): string
    {
        ob_start();
        (new ArgumentParser())->showHelp();
        return (string) ob_get_clean();
    }

    public function testHelpDescribesUsageAndExamples(): void
    {
        $help = $this->help();

        $this->assertStringContainsString('Utilisation:', $help);
        $this->assertStringContainsString('--quiet', $help);
        $this->assertStringContainsString('--config=FILE', $help);
        $this->assertStringContainsString('Exemples:', $help);
    }

    /**
     * Garde-fou : toute option reconnue par le code doit apparaître dans l'aide
     * (une option ajoutée sans documentation fait échouer ce test).
     */
    public function testHelpDocumentsEveryRecognisedOption(): void
    {
        $help = $this->help();
        $reflection = new \ReflectionClass(ArgumentParser::class);

        foreach (array_keys($reflection->getConstant('GENERATION_FLAGS')) as $flag) {
            $this->assertStringContainsString($flag, $help, "le drapeau {$flag} n'est pas documenté");
        }
        foreach ($reflection->getConstant('GENERATION_VALUES') as $name) {
            $this->assertStringContainsString("--{$name}=", $help, "l'option --{$name}= n'est pas documentée");
        }
        foreach (array_keys($reflection->getConstant('EXPECTED_PARAMS')) as $param) {
            $this->assertStringContainsString("--{$param}=", $help, "le paramètre --{$param}= n'est pas documenté");
        }
        foreach (['--help', '-h', '--quiet', '-q', '--verbose', '-v', '--config='] as $option) {
            $this->assertStringContainsString($option, $help);
        }
    }

    // --- --help / -h : sortie du processus ------------------------------

    /** @dataProvider helpFlags */
    public function testHelpFlagPrintsHelpAndExitsWithZero(string $flag): void
    {
        $script = $this->tmp->file('help.php', sprintf(
            "<?php\nrequire %s;\n\$GLOBALS['argv'] = ['schemaspy', %s, '--host=ignore'];\n(new SchemaSpyCli\\Cli\\ArgumentParser())->parse();\necho \"JAMAIS ATTEINT\";\n",
            var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true),
            var_export($flag, true)
        ));

        $output = '';
        $exit = (new ProcessRunner())->run(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script),
            function (string $text) use (&$output): void {
                $output .= $text;
            }
        );

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Utilisation:', $output);
        $this->assertStringNotContainsString('JAMAIS ATTEINT', $output, 'l\'aide termine le programme');
    }

    public static function helpFlags(): array
    {
        return [['--help'], ['-h']];
    }
}
