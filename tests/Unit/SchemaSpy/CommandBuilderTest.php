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
use SchemaSpyCli\SchemaSpy\CommandBuilder;
use SchemaSpyCli\Tests\Support\TempDir;

/**
 * [Description CommandBuilderTest]
 * Tests unitaires pour CommandBuilder (ligne de commande Java lancée pour SchemaSpy).
 */
final class CommandBuilderTest extends TestCase
{
    private TempDir $tmp;
    private Config $config;
    private string $logFile;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->logFile = $this->tmp->path . '/cmd.log';
        $this->tmp->file('jdbc/ojdbc11.jar');

        $this->config = new Config();
        $this->config->setBasePath($this->tmp->path);
        $this->config->set('paths.jdbc_folder', 'jdbc');
        $this->config->set('jdbc', [
            'oracle' => ['type' => 'orathin', 'port' => 1521, 'driver' => 'ojdbc11.jar', 'version' => '11'],
            'mysql'  => ['type' => 'mysql', 'port' => 3306, 'driver' => 'mysql-absent.jar', 'version' => '1'],
        ]);
        $this->config->set('jdbc_validation', ['enabled' => false]);
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    private function builder(bool $verbose = false): CommandBuilder
    {
        $logger = new Logger(false, $this->logFile);
        $logger->setQuiet(true);
        $logger->setVerbose($verbose);

        return new CommandBuilder($this->config, new Environment(), $logger, new DriverManager($this->config, $logger));
    }

    private function log(): string
    {
        return (string) file_get_contents($this->logFile);
    }

    public function testCommandRunsTheJarWithItsConfigurationFile(): void
    {
        $command = $this->builder()->build('C:\\jdk 17\\bin\\java.exe', 'jar/s.jar', 'C:\\tmp\\p.properties', ['dbType' => 'oracle']);

        $this->assertStringStartsWith(escapeshellarg('C:\\jdk 17\\bin\\java.exe') . ' -jar ' . escapeshellarg('jar/s.jar'), $command);
        $this->assertStringContainsString('-configFile ' . escapeshellarg('C:\\tmp\\p.properties'), $command);
    }

    public function testJdbcDriverIsPassedWithDp(): void
    {
        $command = $this->builder()->build('java', 'x.jar', 'p.properties', ['dbType' => 'oracle']);

        $this->assertMatchesRegularExpression('/ -dp .*ojdbc11\.jar/', $command);
        $this->assertStringNotContainsString('-debug', $command);
    }

    public function testVerboseModeAddsDebugAndLogsTheDriver(): void
    {
        $command = $this->builder(verbose: true)->build('java', 'x.jar', 'p.properties', ['dbType' => 'oracle']);

        $this->assertStringEndsWith(' -debug', $command);
        $this->assertStringContainsString('Driver JDBC: ojdbc11.jar', $this->log());
    }

    public function testNoDriverOptionWhenTheTypeHasNoDriverFile(): void
    {
        $command = $this->builder()->build('java', 'x.jar', 'p.properties', ['dbType' => 'mysql']);

        $this->assertStringNotContainsString('-dp', $command, 'jar mysql absent du dossier');
    }

    public function testUnknownDatabaseTypeAddsNoDriver(): void
    {
        $command = $this->builder()->build('java', 'x.jar', 'p.properties', ['dbType' => 'inconnu']);

        $this->assertStringNotContainsString('-dp', $command);
    }

    public function testDriverDeletedAfterScanIsReportedAndSkipped(): void
    {
        $builder = $this->builder(); // le DriverManager a déjà listé ojdbc11.jar
        unlink($this->tmp->path . '/jdbc/ojdbc11.jar');

        $command = $builder->build('java', 'x.jar', 'p.properties', ['dbType' => 'oracle']);

        $this->assertStringNotContainsString('-dp', $command);
        $this->assertStringContainsString('Driver non trouvé', $this->log());
    }
}
