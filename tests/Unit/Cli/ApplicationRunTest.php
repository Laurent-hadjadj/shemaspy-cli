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
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Tests\Support\FakeEnvironment;
use SchemaSpyCli\Tests\Support\FakeProcessRunner;
use SchemaSpyCli\Tests\Support\TempDir;

/**
 * [Description ApplicationRunTest]
 * Tests de bout en bout de Application::run() : vrai enchaînement (arguments, config, services,
 * bannière, vérifications, collecte, exécution) avec un faux processus Java et un serveur TCP local
 * à la place de la base. L'application est ancrée dans un dossier temporaire : ni logs/, ni
 * schemaspy.last.json, ni jdbc/ du dépôt ne sont touchés.
 */
final class ApplicationRunTest extends TestCase
{
    private const NOISE = [
        "WARN  - C:\\gv\\bin\\dot -Tsvg A.dot -oA.svg: Warning: cell size too small for content\n",
        "WARN  - C:\\gv\\bin\\dot -Tsvg A.dot -oA.svg: in label of node A\n",
    ];

    private TempDir $base;
    private TempDir $elsewhere;
    private string $savedCwd;
    private array $savedArgv;
    /** @var resource|null */
    private $server = null;

    protected function setUp(): void
    {
        if (extension_loaded('pdo_oci')) {
            $this->markTestSkipped("pdo_oci installée : le test de connexion Oracle n'est plus un simple test TCP.");
        }

        $this->savedCwd = getcwd();
        $this->savedArgv = $GLOBALS['argv'] ?? [];
        $this->base = new TempDir();
        $this->elsewhere = new TempDir();

        $this->base->file('jar/fake.jar');
        $this->base->file('jdbc/ojdbc.jar');
        // Faux JDK : simple fichier jamais exécuté (FakeEnvironment simule la version). Ne JAMAIS
        // créer un faux .exe vide : l'exécuter ouvre une popup Windows bloquante.
        $this->base->file('tools/jdk17/bin/java.fake');
        $this->writeConfig();

        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    }

    protected function tearDown(): void
    {
        chdir($this->savedCwd);
        $GLOBALS['argv'] = $this->savedArgv;
        if (is_resource($this->server)) {
            fclose($this->server);
        }
        $this->base->remove();
        $this->elsewhere->remove();
    }

    private function writeConfig(bool $markdownSupported = true): void
    {
        $this->base->file('config/config.json', json_encode([
            'application' => ['name' => 'Test App', 'version' => '9.9.9', 'company' => 'ACME'],
            'schemaspy' => [
                'version' => '7.0.3-lh.2', 'jar' => 'jar/fake.jar',
                'markdown_supported' => $markdownSupported, 'compatibility' => ['7' => '17'],
            ],
            'paths' => [
                'jdbc_folder' => 'jdbc', 'output_folder' => 'report',
                'java_folder' => 'tools/jdk17', 'graphviz_folder' => 'tools/gv',
            ],
            'jdbc' => [
                'oracle' => ['type' => 'orathin', 'port' => 1521, 'driver' => 'ojdbc.jar', 'version' => '1', 'db_version' => '19c'],
            ],
            'jdbc_validation' => ['enabled' => false],
        ], JSON_PRETTY_PRINT));
    }

    private function serverPort(): int
    {
        return (int) substr(strrchr((string) stream_socket_get_name($this->server, false), ':'), 1);
    }

    /** Arguments de connexion valides vers le serveur TCP local. */
    private function connection(?int $port = null): array
    {
        return [
            '--db=oracle', '--host=127.0.0.1', '--port=' . ($port ?? $this->serverPort()),
            '--database=XE', '--schema=APP', '--user=scott', '--password=tiger', '--output=out1',
        ];
    }

    /**
     * Lance Application::run() avec ces arguments.
     *
     * @param list<string> $args
     * @param list<string>|null $answers saisies interactives (null = pas de flux injecté)
     * @return array{0: int, 1: string} [code de sortie, sortie console sans ANSI]
     */
    private function runApp(array $args, ?FakeProcessRunner $process = null, ?array $answers = null): array
    {
        $GLOBALS['argv'] = ['schemaspy', '--config=' . $this->base->path . '/config/config.json', ...$args];

        $logger = new Logger(false);
        if ($answers !== null) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, implode("\n", $answers) . "\n");
            rewind($stream);
            $logger->setInputStream($stream);
        }

        $jdk = str_replace('/', DIRECTORY_SEPARATOR, $this->base->path . '/tools/jdk17');
        $environment = new FakeEnvironment(null, [$jdk => '17.0.8']);
        $app = new Application($logger, $this->base->path, $process ?? new FakeProcessRunner(), $environment);

        ob_start();
        try {
            $code = $app->run();
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$code, preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $output)];
    }

    // --- mode non interactif -------------------------------------------

    public function testSuccessfulRunFromAnyWorkingDirectory(): void
    {
        chdir($this->elsewhere->path); // ni config/, ni jdbc/, ni tools/ ici : tout vient du base path
        $process = new FakeProcessRunner(self::NOISE);

        [$code, $output] = $this->runApp($this->connection(), $process);

        $this->assertSame(0, $code, $output);
        // bannière, après chargement de la config
        $this->assertStringContainsString('Version: 9.9.9', $output);
        $this->assertStringContainsString('Application: Test App', $output);
        $this->assertStringContainsString('SchemaSpy: 7.0.3-lh.2', $output);
        $this->assertStringContainsString('Drivers JDBC: 1 trouvé', $output);
        // vérifications d'environnement et résultat
        $this->assertStringContainsString('JDK détecté', $output);
        $this->assertStringContainsString('Documentation générée avec succès', $output);
        $this->assertStringContainsString('2 avertissement(s) Graphviz sans incidence masqué(s)', $output);
        $this->assertStringContainsString("Merci d'avoir utilisé SchemaSpy-Cli", $output);
        $this->assertStringContainsString('ACME', $output);

        $this->assertNotNull($process->command);
        $this->assertStringContainsString('fake.jar', $process->command);
        $this->assertStringContainsString('ojdbc.jar', $process->command);
        $this->assertStringContainsString('schemaspy.u=scott', (string) $process->propertiesContent);
    }

    public function testQuietRunIsSilentAndSkipsBannerAndFooter(): void
    {
        $process = new FakeProcessRunner();

        [$code, $output] = $this->runApp([...$this->connection(), '--quiet'], $process);

        $this->assertSame(0, $code);
        $this->assertSame('', $output);
        $this->assertNotNull($process->command, 'SchemaSpy est bien lancé');
    }

    public function testGenerationOptionsReachSchemaSpy(): void
    {
        $process = new FakeProcessRunner();

        [$code, $output] = $this->runApp([...$this->connection(), '--markdown', '--no-html', '--no-orphans', '--degree=1'], $process);

        $this->assertSame(0, $code, $output);
        $props = (string) $process->propertiesContent;
        foreach (['schemaspy.markdown=true', 'schemaspy.nohtml=true', 'schemaspy.no-orphans=true', 'schemaspy.degree=1'] as $expected) {
            $this->assertStringContainsString($expected, $props);
        }
        $this->assertStringContainsString('markdown', $output);
        $this->assertStringNotContainsString('index.html', $output);
    }

    public function testSchemaSpyExitCodeIsReturned(): void
    {
        [$code, $output] = $this->runApp($this->connection(), new FakeProcessRunner([], 2));

        $this->assertSame(2, $code);
        $this->assertStringContainsString('SchemaSpy a retourné le code: 2', $output);
    }

    public function testUnreachableDatabaseReturns1WithoutLaunchingSchemaSpy(): void
    {
        $port = $this->serverPort();
        fclose($this->server);
        $this->server = null;
        $process = new FakeProcessRunner();

        [$code, $output] = $this->runApp($this->connection($port), $process);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Impossible de se connecter', $output);
        $this->assertNull($process->command);
        $this->assertStringContainsString("Merci d'avoir utilisé", $output, 'le pied de page est affiché même en erreur');
    }

    // --- erreurs de configuration et de paramètres ---------------------

    public function testMissingConfigFileIsReported(): void
    {
        chdir($this->elsewhere->path); // aucun config/config.json en repli

        [$code, $output] = $this->runApp(['--config=' . $this->elsewhere->path . '/absent.json']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Erreur de configuration', $output);
        $this->assertStringContainsString('Fichier de configuration introuvable', $output);
    }

    public function testMarkdownIsRefusedWhenJarDoesNotSupportIt(): void
    {
        $this->writeConfig(markdownSupported: false);
        $process = new FakeProcessRunner();

        [$code, $output] = $this->runApp([...$this->connection(), '--markdown'], $process);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Erreur de configuration', $output);
        $this->assertStringContainsString('markdown_supported', $output);
        $this->assertNull($process->command);
    }

    public function testInvalidOptionCombinationIsReportedAsInvalidParameter(): void
    {
        [$code, $output] = $this->runApp([...$this->connection(), '--no-html']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Paramètre invalide', $output);
        $this->assertStringContainsString('--no-html sans --markdown', $output);
    }

    public function testUnknownDatabaseTypeIsReportedAsInvalidParameter(): void
    {
        [$code, $output] = $this->runApp(['--db=sybase', '--host=h', '--database=d', '--schema=s', '--user=u', '--password=p']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Paramètre invalide', $output);
        $this->assertStringContainsString('Type de base de données', $output);
    }

    public function testUnexpectedErrorIsReportedAndVerboseAddsTrace(): void
    {
        unlink($this->base->path . '/jar/fake.jar'); // SchemaSpyException levée par Runner

        [$code, $output] = $this->runApp($this->connection());
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Erreur inattendue: Fichier introuvable', $output);
        $this->assertStringNotContainsString('#0 ', $output, 'pas de trace hors --verbose');

        [$code, $output] = $this->runApp([...$this->connection(), '--verbose']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('#0 ', $output, 'trace affichée avec --verbose');
        $this->assertStringContainsString('Configuration chargée depuis', $output);
    }

    // --- mode interactif ------------------------------------------------

    public function testInteractiveRunCollectsParametersRunsAndRemembersThem(): void
    {
        $process = new FakeProcessRunner();
        $answers = ['1', '127.0.0.1', (string) $this->serverPort(), 'XE', 'APP', 'scott', 'tiger', '', 'o'];

        [$code, $output] = $this->runApp([], $process, $answers);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('Étape 4/4', $output);
        $this->assertStringContainsString('Documentation générée avec succès', $output);
        $this->assertStringContainsString('schemaspy.u=scott', (string) $process->propertiesContent);

        $last = json_decode((string) file_get_contents($this->base->path . '/schemaspy.last.json'), true);
        $this->assertSame('oracle', $last['dbType']);
        $this->assertSame('127.0.0.1', $last['host']);
        $this->assertSame('APP', $last['schema']);
        $this->assertArrayNotHasKey('password', $last, 'le mot de passe n\'est jamais mémorisé');
    }

    public function testInteractiveCancellationExitsCleanlyWithoutRunning(): void
    {
        $process = new FakeProcessRunner();
        $answers = ['1', '127.0.0.1', (string) $this->serverPort(), 'XE', 'APP', 'scott', 'tiger', '', 'n'];

        [$code, $output] = $this->runApp([], $process, $answers);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Opération annulée', $output);
        $this->assertNull($process->command);
        $this->assertFileDoesNotExist($this->base->path . '/schemaspy.last.json');
        $this->assertStringContainsString("Merci d'avoir utilisé", $output);
    }

    public function testInteractiveModeIsPrefilledByCommandLineOptions(): void
    {
        $process = new FakeProcessRunner();
        $answers = ['1', '127.0.0.1', (string) $this->serverPort(), 'XE', 'APP', 'scott', 'tiger', '', 'o'];

        [$code, $output] = $this->runApp(['--no-orphans', '--markdown'], $process, $answers);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('Options actuelles : HTML + Markdown / sans orphelines', $output);
        $this->assertStringContainsString('schemaspy.no-orphans=true', (string) $process->propertiesContent);
        $this->assertStringContainsString('schemaspy.markdown=true', (string) $process->propertiesContent);
    }
}
