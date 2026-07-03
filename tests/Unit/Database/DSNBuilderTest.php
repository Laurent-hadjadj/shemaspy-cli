<?php
/**
 * Tests unitaires pour la classe DSNBuilder
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Database\DSNBuilder;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Exceptions\ConnectionException;

final class DSNBuilderTest extends TestCase
{
    private Config $config;
    private DSNBuilder $dsnBuilder;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->config->set('connprops', [
            'useSSL' => 'false',
            'serverTimezone' => 'Europe/Paris',
        ]);
        
        $this->dsnBuilder = new DSNBuilder($this->config);
    }

    public function testBuildPostgresqlDSN(): void
    {
        $params = [
            'dbType' => 'postgresql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'testdb',
        ];

        $dsn = $this->dsnBuilder->build($params);
        
        $this->assertStringContainsString('pgsql:', $dsn);
        $this->assertStringContainsString('host=localhost', $dsn);
        $this->assertStringContainsString('port=5432', $dsn);
        $this->assertStringContainsString('dbname=testdb', $dsn);
        $this->assertStringContainsString('useSSL=false', $dsn);
        $this->assertStringContainsString('serverTimezone=Europe/Paris', $dsn);
    }

    public function testBuildPostgresqlDSNWithDefaultPort(): void
    {
        $params = [
            'dbType' => 'postgresql',
            'host' => 'localhost',
            'database' => 'testdb',
        ];

        $dsn = $this->dsnBuilder->build($params);
        
        $this->assertStringContainsString('port=5432', $dsn);
    }

    public function testBuildOracleDSN(): void
    {
        $params = [
            'dbType' => 'oracle',
            'host' => 'oracle.example.com',
            'port' => 1521,
            'database' => 'XE',
        ];

        $dsn = $this->dsnBuilder->build($params);
        
        $this->assertStringContainsString('oci:', $dsn);
        $this->assertStringContainsString('//oracle.example.com:1521/XE', $dsn);
    }

    public function testBuildMySQLDSN(): void
    {
        $params = [
            'dbType' => 'mysql',
            'host' => 'mysql.example.com',
            'port' => 3306,
            'database' => 'testdb',
        ];

        $dsn = $this->dsnBuilder->build($params);
        
        $this->assertStringContainsString('mysql:', $dsn);
        $this->assertStringContainsString('host=mysql.example.com', $dsn);
        $this->assertStringContainsString('port=3306', $dsn);
        $this->assertStringContainsString('dbname=testdb', $dsn);
    }

    public function testBuildUnsupportedType(): void
    {
        $params = [
            'dbType' => 'unsupported',
            'host' => 'localhost',
            'database' => 'testdb',
        ];

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Type de base de données non supporté');
        
        $this->dsnBuilder->build($params);
    }

    public function testGetDriverName(): void
    {
        $this->assertEquals('pdo_pgsql', $this->dsnBuilder->getDriverName('postgresql'));
        $this->assertEquals('pdo_oci', $this->dsnBuilder->getDriverName('oracle'));
        $this->assertEquals('pdo_mysql', $this->dsnBuilder->getDriverName('mysql'));
        $this->assertNull($this->dsnBuilder->getDriverName('unsupported'));
    }

    public function testGetDefaultPort(): void
    {
        $this->assertEquals(5432, $this->dsnBuilder->getDefaultPort('postgresql'));
        $this->assertEquals(1521, $this->dsnBuilder->getDefaultPort('oracle'));
        $this->assertEquals(3306, $this->dsnBuilder->getDefaultPort('mysql'));
        $this->assertNull($this->dsnBuilder->getDefaultPort('unsupported'));
    }

    public function testGetTestQuery(): void
    {
        $this->assertEquals('SELECT 1', $this->dsnBuilder->getTestQuery('postgresql'));
        $this->assertEquals('SELECT 1 FROM DUAL', $this->dsnBuilder->getTestQuery('oracle'));
        $this->assertEquals('SELECT 1', $this->dsnBuilder->getTestQuery('mysql'));
        $this->assertNull($this->dsnBuilder->getTestQuery('unsupported'));
    }

    public function testSupportsType(): void
    {
        $this->assertTrue($this->dsnBuilder->supportsType('postgresql'));
        $this->assertTrue($this->dsnBuilder->supportsType('oracle'));
        $this->assertTrue($this->dsnBuilder->supportsType('mysql'));
        $this->assertFalse($this->dsnBuilder->supportsType('unsupported'));
    }

    public function testGetAllSupportedTypes(): void
    {
        $types = $this->dsnBuilder->getAllSupportedTypes();
        
        $this->assertContains('postgresql', $types);
        $this->assertContains('oracle', $types);
        $this->assertContains('mysql', $types);
        $this->assertContains('sqlsrv', $types);
    }

    public function testValidateDSN(): void
    {
        $validDSN = 'pgsql:host=localhost;port=5432;dbname=testdb';
        $this->assertTrue($this->dsnBuilder->validateDSN($validDSN, 'postgresql'));
        
        $invalidDSN = 'invalid:dsn';
        $this->assertFalse($this->dsnBuilder->validateDSN($invalidDSN, 'postgresql'));
    }

    public function testGetConnectionOptions(): void
    {
        $params = [
            'dbType' => 'postgresql',
            'timeout' => 15,
        ];

        $options = $this->dsnBuilder->getConnectionOptions($params);
        
        $this->assertArrayHasKey(\PDO::ATTR_ERRMODE, $options);
        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $options[\PDO::ATTR_ERRMODE]);
        $this->assertEquals(15, $options[\PDO::ATTR_TIMEOUT]);
        $this->assertEquals(\PDO::FETCH_ASSOC, $options[\PDO::ATTR_DEFAULT_FETCH_MODE]);
        $this->assertTrue($options[\PDO::ATTR_AUTOCOMMIT]);
    }

    public function testGetConnectionOptionsMySQL(): void
    {
        // Les constantes spécifiques à MySQL n'existent que si l'extension pdo_mysql
        // est chargée. On adapte le test à l'environnement courant.
        if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $this->markTestSkipped("Extension pdo_mysql non disponible : constantes MySQL absentes.");
        }

        $params = [
            'dbType' => 'mysql',
        ];

        $options = $this->dsnBuilder->getConnectionOptions($params);

        $this->assertArrayHasKey(\PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertEquals("SET NAMES 'UTF8'", $options[\PDO::MYSQL_ATTR_INIT_COMMAND]);
        $this->assertTrue($options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]);
    }

    public function testParseDSN(): void
    {
        $dsn = 'pgsql:host=localhost;port=5432;dbname=testdb;useSSL=false';
        
        $parsed = $this->dsnBuilder->parseDSN($dsn);
        
        $this->assertEquals('pgsql', $parsed['driver']);
        $this->assertEquals('localhost', $parsed['host']);
        $this->assertEquals('5432', $parsed['port']);
        $this->assertEquals('testdb', $parsed['dbname']);
        $this->assertEquals('false', $parsed['useSSL']);
    }

    public function testGetExampleDSN(): void
    {
        $this->assertEquals(
            'pgsql:host=localhost;port=5432;dbname=mydb',
            $this->dsnBuilder->getExampleDSN('postgresql')
        );
        
        $this->assertEquals(
            'oci:dbname=//localhost:1521/XE',
            $this->dsnBuilder->getExampleDSN('oracle')
        );
        
        $this->assertEquals(
            'mysql:host=localhost;port=3306;dbname=mydb',
            $this->dsnBuilder->getExampleDSN('mysql')
        );
    }
}
