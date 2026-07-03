<?php
/**
 * Tests d'intégration pour SchemaSpy Runner
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.1.0
 */

namespace SchemaSpyCli\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Database\DriverManager;
use SchemaSpyCli\SchemaSpy\Runner;
use SchemaSpyCli\Utils\PathFinder;

final class SchemaSpyRunnerTest extends TestCase
{
    private Config $config;
    private Logger $logger;
    private Environment $environment;
    private PathFinder $pathFinder;
    private DriverManager $driverManager;
    private Runner $runner;
    private string $tempJdbcDir;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->logger = new Logger();
        $this->logger->setQuiet(true);
        $this->environment = new Environment();
        $this->pathFinder = new PathFinder($this->config, $this->logger);

        // Dossier JDBC temporaire (vide) pour isoler le DriverManager
        $this->tempJdbcDir = sys_get_temp_dir() . '/schemaspy_jdbc_' . uniqid();
        if (!is_dir($this->tempJdbcDir)) {
            mkdir($this->tempJdbcDir, 0755, true);
        }

        // Charger la configuration de test, puis forcer le dossier JDBC
        // vers un répertoire temporaire maîtrisé.
        $configFile = __DIR__ . '/../Fixtures/valid_config.json';
        if (file_exists($configFile)) {
            $this->config->load($configFile);
            $this->config->set('paths.jdbc_folder', $this->tempJdbcDir);
        }

        $this->driverManager = new DriverManager($this->config, $this->logger);

        $this->runner = new Runner(
            $this->config,
            $this->logger,
            $this->environment,
            $this->pathFinder,
            $this->driverManager
        );
    }

    protected function tearDown(): void
    {
        // Nettoyer le dossier JDBC temporaire
        if (is_dir($this->tempJdbcDir)) {
            @rmdir($this->tempJdbcDir);
        }
    }

    public function testRunnerInstantiation(): void
    {
        $this->assertInstanceOf(Runner::class, $this->runner);
    }

    public function testExecuteWithMissingDriver(): void
    {
        // Aucun driver dans le dossier JDBC temporaire : execute() doit échouer
        // proprement (code d'erreur) sans lever d'exception non gérée.
        $params = [
            'dbType' => 'postgresql',
            'dbConfig' => [
                'type' => 'pgsql11',
                'port' => 5432,
                'driver' => 'postgresql-42.7.5.jar',
            ],
            'host' => 'invalid-host',
            'database' => 'testdb',
            'schema' => 'public',
            'port' => 5432,
            'user' => 'testuser',
            'password' => 'testpass',
            'output' => 'test_output',
            'useVizJs' => true,
        ];

        $result = $this->runner->execute($params);
        $this->assertNotEquals(0, $result);
    }
}
