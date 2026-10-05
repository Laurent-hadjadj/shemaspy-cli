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

namespace SchemaSpyCli\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Database\DriverManager;
use SchemaSpyCli\Exceptions\FileNotFoundException;
use SchemaSpyCli\Tests\Support\TempDir;

/**
 * [Description DriverManagerTest]
 * Tests unitaires pour DriverManager (détection des JAR JDBC, validation des versions)
 */
final class DriverManagerTest extends TestCase
{
    private TempDir $tmp;
    private Config $config;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->logger = new Logger(false);
        $this->logger->setQuiet(true);

        $this->config = new Config();
        $this->config->setBasePath($this->tmp->path);
        $this->config->set('paths.jdbc_folder', 'jdbc');
        $this->config->set('jdbc', [
            'postgresql' => ['driver' => 'postgresql-42.7.13.jar', 'version' => '42.7.13', 'class' => 'org.postgresql.Driver', 'download_url' => 'https://example.invalid/pg.jar'],
            'mysql'      => ['driver' => 'mysql-connector-j-26.7.0.jar', 'version' => '26.7.0', 'download_url' => 'https://example.invalid/mysql.jar'],
            'mariadb'    => ['driver' => 'mariadb-java-client-3.5.10.jar', 'version' => '3.5.10'],
            'oracle'     => ['driver' => 'ojdbc11-23.26.3.0.0.jar', 'version' => '23.26.3.0.0'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    private function manager(): DriverManager
    {
        return new DriverManager($this->config, $this->logger);
    }

    // --- extraction de version -----------------------------------------

    /** @dataProvider versionCases */
    public function testVersionIsExtractedFromFileName(string $file, string $expected, bool $matches): void
    {
        $method = new \ReflectionMethod(DriverManager::class, 'checkVersion');
        $this->tmp->dir('jdbc');

        $this->assertSame($matches, $method->invoke($this->manager(), $file, $expected));
    }

    public static function versionCases(): array
    {
        return [
            'postgresql ok'               => ['postgresql-42.7.13.jar', '42.7.13', true],
            'postgresql ancienne version' => ['postgresql-42.7.5.jar', '42.7.13', false],
            'mysql-connector-j'           => ['mysql-connector-j-26.7.0.jar', '26.7.0', true],
            'mysql-connector-java'        => ['mysql-connector-java-5.1.49.jar', '5.1.49', true],
            'mariadb (point final)'       => ['mariadb-java-client-3.5.10.jar', '3.5.10', true],
            'mariadb autre version'       => ['mariadb-java-client-3.5.9.jar', '3.5.10', false],
            'mssql jre11'                 => ['mssql-jdbc-13.6.0.jre11.jar', '13.6.0', true],
            'mssql jre8'                  => ['mssql-jdbc-13.6.0.jre8.jar', '13.6.0', true],
            'ojdbc versionné'             => ['ojdbc11-23.26.3.0.0.jar', '23.26.3.0.0', true],
            'ojdbc historique'            => ['ojdbc6.jar', '6', true],
            'sqljdbc'                     => ['sqljdbc42.jar', '42', true],
            'nom inconnu : non bloquant'  => ['custom-driver.jar', '1.0', true],
        ];
    }

    // --- détection et validation ---------------------------------------

    public function testDriversAreDetectedAndResolved(): void
    {
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');
        $this->tmp->file('jdbc/readme.txt');

        $manager = $this->manager();

        $this->assertSame(['postgresql-42.7.13.jar'], $manager->getAvailableDrivers());
        $this->assertTrue($manager->hasDriver('postgresql'));
        $this->assertSame('postgresql-42.7.13.jar', $manager->getDriver('postgresql'));
        $this->assertStringEndsWith('postgresql-42.7.13.jar', $manager->getDriverPath('postgresql'));
        $this->assertSame('org.postgresql.Driver', $manager->getDriverClass('postgresql'));
        $this->assertFalse($manager->isJdbcDirectoryEmpty());
        $this->assertIsArray($manager->getDriverInfo('postgresql-42.7.13.jar'));
        $this->assertNull($manager->getDriverInfo('absent.jar'));
    }

    public function testMissingDriverIsReported(): void
    {
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');

        $manager = $this->manager();

        $this->assertFalse($manager->hasDriver('mysql'));
        $this->assertNull($manager->getDriverPath('mysql'));
        $this->assertNull($manager->getDriver('inconnu'));

        $missing = array_column($manager->getMissingDrivers(), 'driver', 'db_type');
        $this->assertSame([
            'mysql' => 'mysql-connector-j-26.7.0.jar',
            'mariadb' => 'mariadb-java-client-3.5.10.jar',
            'oracle' => 'ojdbc11-23.26.3.0.0.jar',
        ], $missing);
    }

    public function testEmptyAndMissingJdbcFolder(): void
    {
        $this->assertTrue($this->manager()->isJdbcDirectoryEmpty(), 'dossier jdbc absent');

        $this->tmp->dir('jdbc');
        $this->assertTrue($this->manager()->isJdbcDirectoryEmpty(), 'dossier jdbc vide');
    }

    public function testValidationSummaryCountsMissingAndWrongVersions(): void
    {
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');
        $this->tmp->file('jdbc/mariadb-java-client-3.5.10.jar');
        $this->tmp->file('jdbc/mysql-connector-j-26.7.0.jar');
        // oracle absent

        $summary = $this->manager()->getValidationSummary();

        $this->assertStringContainsString('4 drivers, 1 manquants, 0 versions incorrectes', $summary);
        $this->assertStringContainsString('ojdbc11-23.26.3.0.0.jar: manquant', $summary);
        $this->assertStringContainsString('postgresql-42.7.13.jar: OK', $summary);
    }

    public function testVersionMismatchIsReportedInSummary(): void
    {
        $this->config->set('jdbc.postgresql.version', '42.7.99');
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');

        $manager = $this->manager();
        $results = $manager->getValidationResults();

        $this->assertFalse($results['postgresql-42.7.13.jar']['version_match']);
        $this->assertStringContainsString('1 versions incorrectes', $manager->getValidationSummary());
    }

    public function testChecksumValidation(): void
    {
        $file = $this->tmp->file('jdbc/postgresql-42.7.13.jar', 'contenu');
        $this->config->set('jdbc_validation', ['enabled' => true, 'check_checksum' => true, 'check_version' => false]);

        $this->config->set('jdbc.postgresql.checksum', 'sha256:' . hash_file('sha256', $file));
        $this->assertTrue($this->manager()->getValidationResults()['postgresql-42.7.13.jar']['checksum_match']);

        $this->config->set('jdbc.postgresql.checksum', 'md5:' . md5_file($file));
        $this->assertTrue($this->manager()->getValidationResults()['postgresql-42.7.13.jar']['checksum_match']);

        $this->config->set('jdbc.postgresql.checksum', 'sha256:deadbeef');
        $this->assertFalse($this->manager()->getValidationResults()['postgresql-42.7.13.jar']['checksum_match']);

        $this->config->set('jdbc.postgresql.checksum', 'crc32:1234');
        $this->assertFalse($this->manager()->getValidationResults()['postgresql-42.7.13.jar']['checksum_match']);
    }

    public function testValidationCanBeDisabled(): void
    {
        $this->config->set('jdbc_validation', ['enabled' => false]);
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');

        $this->assertSame([], $this->manager()->getValidationResults());
    }

    public function testStrictModeFailsOnMissingDriver(): void
    {
        $this->config->set('jdbc_validation', ['enabled' => true, 'check_version' => false, 'strict_mode' => true]);
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('mysql-connector-j-26.7.0.jar');
        $this->manager();
    }

    public function testClasspathUsesJdbcFolder(): void
    {
        $this->assertStringEndsWith('jdbc/*', str_replace('\\', '/', $this->manager()->getClasspath()));
    }

    public function testEmptyConfigIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        new DriverManager(new Config(), $this->logger);
    }

    public function testTypesSharingTheSameJarAreReportedOnce(): void
    {
        $this->config->set('jdbc.oracle_service', $this->config->get('jdbc.oracle'));

        $manager = $this->manager();
        $missingOracle = array_filter($manager->getMissingDrivers(), fn($d) => $d['driver'] === 'ojdbc11-23.26.3.0.0.jar');

        $this->assertCount(1, $missingOracle, 'le JAR partagé n\'est listé qu\'une fois');
        $this->assertSame(4, substr_count($manager->getValidationSummary(), "\n") , '4 JAR distincts, pas 5');
    }

    public function testTypesSharingTheSameJarResolveTheSameDriver(): void
    {
        $this->config->set('jdbc.oracle_service', $this->config->get('jdbc.oracle'));
        $this->tmp->file('jdbc/ojdbc11-23.26.3.0.0.jar');

        $manager = $this->manager();

        $this->assertTrue($manager->hasDriver('oracle'));
        $this->assertTrue($manager->hasDriver('oracle_service'));
        $this->assertSame($manager->getDriverPath('oracle'), $manager->getDriverPath('oracle_service'));
    }

    // --- compléments de couverture ----------------------------------------

    public function testConfigEntryWithoutDriverIsIgnored(): void
    {
        $this->config->set('jdbc.sans_driver', ['type' => 'x', 'port' => 1]);
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');

        $manager = $this->manager();

        $this->assertArrayNotHasKey('', $manager->getValidationResults());
        $this->assertNull($manager->getDriver('sans_driver'));
        foreach ($manager->getMissingDrivers() as $missing) {
            $this->assertNotSame('sans_driver', $missing['db_type']);
        }
    }

    public function testUnconfiguredJarIsReportedAsExtra(): void
    {
        $logFile = $this->tmp->path . '/dm.log';
        $logger = new Logger(false, $logFile);
        $logger->setQuiet(true);
        $this->tmp->file('jdbc/postgresql-42.7.13.jar');
        $this->tmp->file('jdbc/ojdbc6.jar');

        new DriverManager($this->config, $logger);

        $this->assertStringContainsString('Driver supplémentaire trouvé: ojdbc6.jar', (string) file_get_contents($logFile));
    }

    public function testExtraJarWarningCanBeDisabled(): void
    {
        $this->config->set('jdbc_validation', ['enabled' => true, 'warn_on_extra' => false, 'check_version' => false]);
        $logFile = $this->tmp->path . '/dm.log';
        $logger = new Logger(false, $logFile);
        $logger->setQuiet(true);
        $this->tmp->file('jdbc/ojdbc6.jar');

        new DriverManager($this->config, $logger);

        $this->assertStringNotContainsString('Driver supplémentaire', (string) file_get_contents($logFile));
    }

    public function testChecksumOfAnUnknownOrVanishedDriverIsFalse(): void
    {
        $file = $this->tmp->file('jdbc/postgresql-42.7.13.jar', 'x');
        $manager = $this->manager();
        $check = new \ReflectionMethod(DriverManager::class, 'checkChecksum');

        $this->assertFalse($check->invoke($manager, 'inconnu.jar', 'sha256:' . str_repeat('0', 64)), 'driver non listé');

        unlink($file); // listé au démarrage, supprimé ensuite
        $this->assertFalse($check->invoke($manager, 'postgresql-42.7.13.jar', 'sha256:' . hash('sha256', 'x')));
    }
}
