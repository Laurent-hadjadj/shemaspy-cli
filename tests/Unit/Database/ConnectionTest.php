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
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Tests\Support\StubPdo;
use SchemaSpyCli\Database\Connection;
use SchemaSpyCli\Exceptions\ConnectionException;

/**
 * [Description ConnectionTest]
 * Tests unitaires pour Connection (choix du driver PDO, repli TCP)
 */
final class ConnectionTest extends TestCase
{
    private function connection(string $dbType, string $host = '127.0.0.1', int $port = 1): Connection
    {
        return new Connection([
            'dbType' => $dbType, 'host' => $host, 'port' => $port, 'database' => 'x',
            'user' => 'u', 'password' => 'p', 'timeout' => 1,
        ], new Logger(true));
    }

    /** @dataProvider pdoDrivers */
    public function testPdoDriverMapping(string $dbType, ?string $expected): void
    {
        $this->assertSame($expected, $this->connection($dbType)->getPdoDriverName());
    }

    public static function pdoDrivers(): array
    {
        return [
            ['postgresql', 'pgsql'], ['oracle', 'oci'], ['mysql', 'mysql'], ['mariadb', 'mysql'],
            ['sqlserver_jre8', 'sqlsrv'], ['sqlserver_jre11', 'sqlsrv'], ['inconnu', null],
        ];
    }

    public function testMissingExtensionFallsBackToTcpAndFailsOnUnreachableServer(): void
    {
        if (extension_loaded('pdo_oci')) {
            $this->markTestSkipped('pdo_oci est installée : pas de repli TCP.');
        }

        $connection = $this->connection('oracle');
        try {
            $connection->test();
            $this->fail('Un serveur injoignable doit lever ConnectionException');
        } catch (ConnectionException) {
            $this->assertSame(Connection::MODE_TCP, $connection->getMode());
        }
    }

    public function testMissingExtensionFallsBackToTcpAndSucceedsOnReachableServer(): void
    {
        if (extension_loaded('pdo_oci')) {
            $this->markTestSkipped('pdo_oci est installée : pas de repli TCP.');
        }

        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $connection = $this->connection('oracle', '127.0.0.1', $port);
        $this->assertTrue($connection->test());
        $this->assertSame(Connection::MODE_TCP, $connection->getMode());
        fclose($server);
    }

    /** @dataProvider dsnCases */
    public function testDsnIsBuiltPerDriver(string $dbType, string $expected): void
    {
        $connection = new Connection([
            'dbType' => $dbType, 'host' => 'h', 'port' => 1234, 'database' => 'db',
            'user' => 'u', 'password' => 'p',
        ], new Logger(true));

        $method = new \ReflectionMethod(Connection::class, 'buildDSN');
        $this->assertSame($expected, $method->invoke($connection));
    }

    public static function dsnCases(): array
    {
        return [
            'postgresql' => ['postgresql', 'pgsql:host=h;port=1234;dbname=db'],
            'oracle'     => ['oracle', 'oci:dbname=//h:1234/db;charset=AL32UTF8'],
            'mysql'      => ['mysql', 'mysql:host=h;port=1234;dbname=db'],
            'mariadb'    => ['mariadb', 'mysql:host=h;port=1234;dbname=db'],
            'sqlserver'  => ['sqlserver_jre11', 'sqlsrv:Server=h,1234;Database=db;TrustServerCertificate=1'],
        ];
    }

    public function testUnsupportedDatabaseTypeHasNoDsn(): void
    {
        $connection = $this->connection('inconnu');
        $method = new \ReflectionMethod(Connection::class, 'buildDSN');

        $this->expectException(ConnectionException::class);
        $method->invoke($connection);
    }

    /** @dataProvider queryCases */
    public function testProbeQueryDependsOnDatabase(string $dbType, string $expected): void
    {
        $method = new \ReflectionMethod(Connection::class, 'testQuery');
        $this->assertSame($expected, $method->invoke($this->connection($dbType)));
    }

    public static function queryCases(): array
    {
        return [
            'oracle exige FROM DUAL' => ['oracle', 'SELECT 1 FROM DUAL'],
            'postgresql'             => ['postgresql', 'SELECT 1'],
            'sqlserver'              => ['sqlserver_jre8', 'SELECT 1'],
        ];
    }

    public function testNotConnectedByDefault(): void
    {
        $connection = $this->connection('postgresql');

        $this->assertNull($connection->getPdo());
        $this->assertSame('Non connecté', $connection->getDriverInfo());
        $this->assertSame('Non connecté', $connection->getServerInfo());
    }

    public function testPdoAvailabilityFollowsLoadedExtensions(): void
    {
        $this->assertSame(extension_loaded('pdo_pgsql'), $this->connection('postgresql')->isPdoAvailable());
        $this->assertFalse($this->connection('inconnu')->isPdoAvailable());
    }

    public function testUnreachableServerThroughPdoRaisesConnectionException(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql non disponible');
        }

        $connection = $this->connection('postgresql', '127.0.0.1', 1);
        try {
            $connection->test();
            $this->fail('ConnectionException attendue');
        } catch (ConnectionException) {
            $this->assertSame(Connection::MODE_PDO, $connection->getMode());
        }
    }

    /** @dataProvider oracleTypes */
    public function testEveryOracleTypeUsesPdoOciAndDualProbe(string $dbType): void
    {
        $connection = $this->connection($dbType);
        $probe = new \ReflectionMethod(Connection::class, 'testQuery');
        $dsn = new \ReflectionMethod(Connection::class, 'buildDSN');

        $this->assertSame('oci', $connection->getPdoDriverName());
        $this->assertSame('SELECT 1 FROM DUAL', $probe->invoke($connection));
        $this->assertSame('oci:dbname=//127.0.0.1:1/x;charset=AL32UTF8', $dsn->invoke($connection));
    }

    public static function oracleTypes(): array
    {
        return [['oracle'], ['oracle_service']];
    }

    // --- connexion réussie / échouée via une fabrique PDO injectée (faux PDO, aucun pilote requis)

    private function pgsqlConnection(\Closure $factory): Connection
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql requis : sans lui la connexion se limite à un test TCP.');
        }

        return new Connection([
            'dbType' => 'postgresql', 'host' => 'db.local', 'port' => 5432, 'database' => 'demo',
            'user' => 'scott', 'password' => 'tiger', 'timeout' => 7,
        ], new Logger(true), $factory);
    }

    public function testSuccessfulConnectionExposesThePdoAndServerInformation(): void
    {
        $received = [];
        $stub = new StubPdo();
        $connection = $this->pgsqlConnection(function (string $dsn, string $user, string $password, array $options) use (&$received, $stub): \PDO {
            $received = compact('dsn', 'user', 'password', 'options');
            return $stub;
        });

        $this->assertTrue($connection->test());

        $this->assertSame(Connection::MODE_PDO, $connection->getMode());
        $this->assertSame($stub, $connection->getPdo());
        $this->assertSame('stub', $connection->getDriverInfo());
        $this->assertSame('9.9.9-stub', $connection->getServerInfo());
        $this->assertSame(['SELECT 1'], $stub->queries, 'requête de test PostgreSQL');
        $this->assertSame('pgsql:host=db.local;port=5432;dbname=demo', $received['dsn']);
        $this->assertSame('scott', $received['user']);
        $this->assertSame('tiger', $received['password']);
        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $received['options'][\PDO::ATTR_ERRMODE]);
        $this->assertSame(7, $received['options'][\PDO::ATTR_TIMEOUT], 'le délai configuré est transmis');
    }

    public function testDriverErrorsBecomeConnectionExceptions(): void
    {
        $connection = $this->pgsqlConnection(function (): \PDO {
            throw new \PDOException('SQLSTATE[08006] connexion refusée', 7);
        });

        try {
            $connection->test();
            $this->fail('ConnectionException attendue');
        } catch (ConnectionException $e) {
            $this->assertStringContainsString('connexion refusée', $e->getMessage());
            $this->assertSame(7, $e->getCode());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    public function testProbeQueryFailureIsAlsoAConnectionError(): void
    {
        $connection = $this->pgsqlConnection(fn(): \PDO => new StubPdo(failOnQuery: true));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('requête de test refusée');
        $connection->test();
    }
}
