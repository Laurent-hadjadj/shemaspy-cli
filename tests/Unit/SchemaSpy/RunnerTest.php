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
use SchemaSpyCli\Exceptions\SchemaSpyException;
use SchemaSpyCli\SchemaSpy\GenerationOptions;
use SchemaSpyCli\SchemaSpy\Runner;
use SchemaSpyCli\Tests\Support\FakeEnvironment;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\PathFinder;
use SchemaSpyCli\Tests\Support\FakeProcessRunner;
use SchemaSpyCli\Tests\Support\StubPdo;

/**
 * [Description RunnerTest]
 * Tests unitaires pour Runner (processus SchemaSpy simulé, aucune base ni JDK réels)
 */
final class RunnerTest extends TestCase
{
    private const NOISE = [
        "WARN  - C:\\gv\\bin\\dot -Tsvg A.dot -oA.svg: Warning: cell size too small for content\n",
        "WARN  - C:\\gv\\bin\\dot -Tsvg A.dot -oA.svg: in label of node A\n",
    ];

    private TempDir $tmp;
    private Config $config;
    private Logger $logger;
    private string $logFile;
    private string|false $savedJavaHome;
    /** @var resource|null */
    private $server = null;

    protected function setUp(): void
    {
        if (extension_loaded('pdo_oci')) {
            $this->markTestSkipped('pdo_oci installée : le test de connexion Oracle n\'est plus un simple test TCP.');
        }

        $this->savedJavaHome = getenv('JAVA_HOME');
        $this->tmp = new TempDir();
        $this->logFile = $this->tmp->path . '/run.log';
        $this->logger = new Logger(false, $this->logFile);

        $this->tmp->file('jar/fake.jar');
        $this->tmp->file('jdbc/ojdbc.jar');
        $this->tmp->file('tools/jdk17/bin/java.fake');

        $this->config = new Config();
        $this->config->setBasePath($this->tmp->path);
        $this->config->set('paths', [
            'jdbc_folder' => 'jdbc', 'output_folder' => 'report',
            'java_folder' => 'tools/jdk17', 'graphviz_folder' => 'tools/gv',
        ]);
        $this->config->set('schemaspy', [
            'version' => '7.0.3-lh.2', 'jar' => 'jar/fake.jar', 'compatibility' => ['7' => '17'],
        ]);
        $this->config->set('jdbc', [
            'oracle' => ['type' => 'orathin', 'port' => 1521, 'driver' => 'ojdbc.jar', 'version' => '1'],
            'mysql'  => ['type' => 'mysql', 'port' => 3306, 'driver' => 'mysql-absent.jar', 'version' => '1'],
        ]);
        $this->config->set('jdbc_validation', ['enabled' => false]);
        $this->config->set('application', ['name' => 'Test']);

        // Serveur TCP local : fait office de base "joignable" (repli TCP sans pdo_oci)
        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            fclose($this->server);
        }
        $savedJavaHome = $this->savedJavaHome ?? false;
        putenv($savedJavaHome === false ? 'JAVA_HOME' : 'JAVA_HOME=' . $savedJavaHome);
        $this->tmp->remove();
    }

    private function serverPort(): int
    {
        return (int) substr(strrchr((string) stream_socket_get_name($this->server, false), ':'), 1);
    }

    private function runner(FakeProcessRunner $process, ?string $jdkVersion = '17.0.8', bool $withJdk = true, ?\Closure $pdoFactory = null): Runner
    {
        $jdk = str_replace('/', DIRECTORY_SEPARATOR, $this->tmp->path . '/tools/jdk17');
        if (!$withJdk) {
            @unlink($jdk . '/bin/java.fake');
        }
        $env = new FakeEnvironment(null, $jdkVersion !== null ? [$jdk => $jdkVersion] : []);

        $quiet = new Logger(false);
        $quiet->setQuiet(true);
        $pathFinder = new PathFinder($this->config, $quiet, $env);
        $driverManager = new DriverManager($this->config, $quiet);

        return new Runner($this->config, $this->logger, new Environment(), $pathFinder, $driverManager, $process, $pdoFactory);
    }

    private function params(array $overrides = []): array
    {
        return array_merge([
            'dbType' => 'oracle',
            'dbConfig' => $this->config->getDatabase('oracle'),
            'host' => '127.0.0.1', 'port' => $overrides['port'] ?? $this->serverPort(), 'database' => 'XE', 'schema' => 'S',
            'user' => 'u', 'password' => 'p', 'output' => 'run1', 'useVizJs' => true,
            'options' => new GenerationOptions(),
        ], $overrides);
    }

    /** Exécute et retourne [code de sortie, sortie console sans séquences ANSI]. */
    private function execute(Runner $runner, array $params): array
    {
        ob_start();
        try {
            $code = $runner->execute($params);
        } finally {
            $output = (string) ob_get_clean();
        }
        return [$code, preg_replace('/\e\[[0-9;]*m/', '', $output)];
    }

    public function testSuccessfulRunBuildsCommandAndSummarizesGraphvizNoise(): void
    {
        $process = new FakeProcessRunner(array_merge(["INFO  - Starting\n"], self::NOISE, [".", "INFO  - Done\n"]));

        [$code, $output] = $this->execute($this->runner($process), $this->params());

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Documentation générée avec succès', $output);
        $this->assertStringContainsString('index.html', $output);
        $this->assertStringNotContainsString('cell size too small', $output);
        $this->assertStringContainsString('INFO  - Starting', $output);
        $this->assertStringContainsString('2 avertissement(s) Graphviz sans incidence masqué(s)', $output);

        $this->assertStringContainsString('-jar', $process->command);
        $this->assertStringContainsString('fake.jar', $process->command);
        $this->assertStringContainsString('-dp', $process->command);
        $this->assertStringContainsString('ojdbc.jar', $process->command);
        $this->assertStringContainsString('-configFile', $process->command);
    }

    public function testSuppressedWarningsAreKeptInLogFile(): void
    {
        $process = new FakeProcessRunner(self::NOISE);

        $this->execute($this->runner($process), $this->params());

        $log = file_get_contents($this->logFile);
        $this->assertSame(2, substr_count($log, '[SCHEMASPY]'));
        $this->assertStringContainsString('in label of node A', $log);
    }

    public function testPropertiesFileExistsDuringRunAndIsRemovedAfterwards(): void
    {
        $process = new FakeProcessRunner();

        $this->execute($this->runner($process), $this->params());

        $this->assertTrue($process->propertiesFileExistedDuringRun);
        $this->assertNotNull($process->propertiesFile());
        $this->assertFileDoesNotExist($process->propertiesFile(), 'le fichier contenant le mot de passe doit être supprimé');
    }

    public function testPropertiesFileIsRemovedEvenWhenSchemaSpyFails(): void
    {
        $process = new FakeProcessRunner([], 1);

        $this->execute($this->runner($process), $this->params());

        $this->assertFileDoesNotExist($process->propertiesFile());
    }

    public function testJavaHomeIsExportedForTheProcess(): void
    {
        $process = new FakeProcessRunner();

        $this->execute($this->runner($process), $this->params());

        $this->assertStringEndsWith('jdk17', str_replace('\\', '/', (string) $process->javaHomeDuringRun));
    }

    public function testNonZeroExitCodeIsPropagated(): void
    {
        [$code, $output] = $this->execute($this->runner(new FakeProcessRunner([], 2)), $this->params());

        $this->assertSame(2, $code);
        $this->assertStringContainsString('SchemaSpy a retourné le code: 2', $output);
        $this->assertStringNotContainsString('Documentation générée', $output);
    }

    public function testVerboseModeShowsEverythingWithoutSummary(): void
    {
        $this->logger->setVerbose(true);

        [, $output] = $this->execute($this->runner(new FakeProcessRunner(self::NOISE)), $this->params());

        $this->assertStringContainsString('cell size too small', $output);
        $this->assertStringContainsString('in label of node A', $output);
        $this->assertStringNotContainsString('avertissement(s) Graphviz', $output);
    }

    public function testMarkdownOnlyRunAnnouncesMarkdownFolder(): void
    {
        $options = new GenerationOptions(markdown: true, html: false);

        [, $output] = $this->execute($this->runner(new FakeProcessRunner()), $this->params(['options' => $options]));

        $this->assertStringContainsString('markdown', $output);
        $this->assertStringNotContainsString('index.html', $output);
    }

    public function testRunWithoutOptionsAnnouncesHtmlOnly(): void
    {
        [, $output] = $this->execute($this->runner(new FakeProcessRunner()), $this->params(['options' => null]));

        $this->assertStringContainsString('index.html', $output);
        $this->assertStringNotContainsString('Markdown:', $output);
    }

    public function testMissingPdoExtensionOnlyWarns(): void
    {
        [$code, $output] = $this->execute($this->runner(new FakeProcessRunner()), $this->params());

        $this->assertSame(0, $code);
        $this->assertStringContainsString('pdo_oci absente', $output);
        $this->assertStringContainsString('identifiants non testés', $output);
    }

    public function testUnreachableServerStopsBeforeLaunchingSchemaSpy(): void
    {
        $port = $this->serverPort();
        fclose($this->server);
        $this->server = null;
        $process = new FakeProcessRunner();

        [$code, $output] = $this->execute($this->runner($process), $this->params(['port' => $port]));

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Impossible de se connecter', $output);
        $this->assertNull($process->command, 'SchemaSpy ne doit pas être lancé');
    }

    public function testMissingJdbcDriverStopsEarly(): void
    {
        $process = new FakeProcessRunner();
        $params = $this->params(['dbType' => 'mysql', 'dbConfig' => $this->config->getDatabase('mysql')]);

        [$code, $output] = $this->execute($this->runner($process), $params);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Driver JDBC non trouvé pour mysql', $output);
        $this->assertNull($process->command);
    }

    public function testMissingJarThrows(): void
    {
        unlink($this->tmp->path . '/jar/fake.jar');

        $this->expectException(SchemaSpyException::class);
        $this->expectExceptionMessage('Fichier introuvable');
        $this->execute($this->runner(new FakeProcessRunner()), $this->params());
    }

    public function testMissingJdkThrows(): void
    {
        $this->expectException(SchemaSpyException::class);
        $this->expectExceptionMessage('JDK introuvable');
        $this->execute($this->runner(new FakeProcessRunner(), '17.0.8', false), $this->params());
    }

    public function testTooOldJdkThrows(): void
    {
        $this->expectException(SchemaSpyException::class);
        $this->expectExceptionMessage('requiert un JDK 17+');
        $this->execute($this->runner(new FakeProcessRunner(), '1.8.0'), $this->params());
    }

    public function testUnknownJdkVersionDoesNotBlock(): void
    {
        [$code] = $this->execute($this->runner(new FakeProcessRunner(), null), $this->params());

        $this->assertSame(0, $code);
    }

    public function testNoCompatibilityRuleDoesNotBlock(): void
    {
        $this->config->set('schemaspy.compatibility', []);

        [$code] = $this->execute($this->runner(new FakeProcessRunner(), '1.8.0'), $this->params());

        $this->assertSame(0, $code);
    }

    public function testSchemaSpyProgressIsWrittenToLogFile(): void
    {
        $process = new FakeProcessRunner(array_merge(
            ["INFO  - Starting schema analysis\n", "..", "INFO  - Connected to Oracle\n"],
            self::NOISE,
            ["\n", "INFO  - Wrote 3 pages\n"]
        ));

        $this->execute($this->runner($process), $this->params());

        $log = file_get_contents($this->logFile);
        $this->assertStringContainsString('[SCHEMASPY] INFO  - Starting schema analysis', $log);
        $this->assertStringContainsString('[SCHEMASPY] INFO  - Connected to Oracle', $log);
        $this->assertStringContainsString('[SCHEMASPY] INFO  - Wrote 3 pages', $log);
        $this->assertSame(2, substr_count($log, 'cell size too small') + substr_count($log, 'in label of node'), 'avertissements masqués journalisés une seule fois chacun');
        $this->assertDoesNotMatchRegularExpression('/\[SCHEMASPY\] \.+\s*$/m', $log, 'les points de progression seuls ne sont pas journalisés');
    }

    public function testVerboseModeAlsoLogsProgressWithoutDuplicates(): void
    {
        $this->logger->setVerbose(true);

        $this->execute($this->runner(new FakeProcessRunner(["INFO  - Une seule fois\n"])), $this->params());

        $this->assertSame(1, substr_count(file_get_contents($this->logFile), '[SCHEMASPY] INFO  - Une seule fois'));
    }

    public function testVizJsEngineTriggersASlownessWarningBeforeRunning(): void
    {
        [$code, $output] = $this->execute($this->runner(new FakeProcessRunner()), $this->params(['useVizJs' => true]));

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Moteur viz.js : très lent', $output);
        $this->assertStringContainsString('--engine=graphviz', $output);
        $this->assertLessThan(
            strpos($output, 'Documentation générée'),
            strpos($output, 'Moteur viz.js'),
            'l\'avertissement précède la génération'
        );
    }

    public function testNoVizJsWarningWithGraphviz(): void
    {
        [, $output] = $this->execute($this->runner(new FakeProcessRunner()), $this->params(['useVizJs' => false]));

        $this->assertStringNotContainsString('Moteur viz.js', $output);
    }

    // --- connexion vérifiée par PDO (faux PDO) -----------------------------

    private function postgresqlParams(): array
    {
        $this->tmp->file('jdbc/pg.jar');
        $this->config->set('jdbc.postgresql', ['type' => 'pgsql11', 'port' => 5432, 'driver' => 'pg.jar', 'version' => '1']);

        return $this->params(['dbType' => 'postgresql', 'dbConfig' => $this->config->getDatabase('postgresql')]);
    }

    public function testSuccessfulPdoConnectionIsAnnouncedAndIdentifiersAreChecked(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql requis (sans lui seul le test TCP est possible).');
        }
        $stub = new StubPdo();
        $params = $this->postgresqlParams();

        [$code, $output] = $this->execute($this->runner(new FakeProcessRunner(), pdoFactory: fn() => $stub), $params);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Connexion réussie', $output);
        $this->assertStringNotContainsString('identifiants non testés', $output, 'avec PDO, les identifiants sont vérifiés');
        $this->assertSame(['SELECT 1'], $stub->queries);
    }

    public function testPdoAuthenticationFailureStopsBeforeLaunchingSchemaSpy(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql requis.');
        }
        $process = new FakeProcessRunner();
        $params = $this->postgresqlParams();
        $refused = function (): \PDO {
            throw new \PDOException('SQLSTATE[08006] authentification refusée');
        };

        [$code, $output] = $this->execute($this->runner($process, pdoFactory: $refused), $params);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Impossible de se connecter', $output);
        $this->assertNull($process->command, 'SchemaSpy ne doit pas être lancé');
    }
}
