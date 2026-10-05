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
use SchemaSpyCli\Database\Connection;
use SchemaSpyCli\Utils\VersionChecker;

/**
 * [Description PdoDriversTest]
 * Pilotes PDO du PHP qui exécute les tests.
 *
 * Chaque pilote nécessaire aux types de bases de config/config.json donne un test : réussi si le pilote
 * est installé, IGNORÉ avec un message explicite sinon (visible dans le résumé de PHPUnit, comme les autres
 * tests ignorés). Un pilote absent n'est pas une erreur : la connexion est alors limitée à un test réseau.
 */
final class PdoDriversTest extends TestCase
{
    /** Types de bases déclarés dans la configuration livrée. */
    private static function configuredTypes(): array
    {
        $config = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../config/config.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return array_keys($config['jdbc']);
    }

    /** @return array<string, array{0: string, 1: list<string>}> pilote => [pilote, types qui en dépendent] */
    public static function requiredDrivers(): array
    {
        $byDriver = [];
        foreach (self::configuredTypes() as $type) {
            $driver = Connection::pdoDriverFor($type);
            if ($driver !== null) {
                $byDriver[$driver][] = $type;
            }
        }

        $cases = [];
        foreach ($byDriver as $driver => $types) {
            $cases["pdo_{$driver} ({$types[0]}" . (count($types) > 1 ? ', ...' : '') . ')'] = [$driver, $types];
        }

        return $cases;
    }

    /**
     * @dataProvider requiredDrivers
     * @param list<string> $types
     */
    public function testPdoDriverIsInstalled(string $driver, array $types): void
    {
        if (!extension_loaded("pdo_{$driver}")) {
            $this->markTestSkipped(sprintf(
                'pdo_%s absente de ce PHP : le test de connexion à [%s] se limite à l\'accessibilité réseau '
                . '(extension à activer dans php.ini si vous voulez tester aussi les identifiants).',
                $driver,
                implode(', ', $types)
            ));
        }

        $this->assertContains($driver, \PDO::getAvailableDrivers(), "pdo_{$driver} est chargée mais PDO ne la déclare pas");
    }

    /** L'extension « pdo » elle-même est indispensable : sans elle, aucun test de connexion n'est possible. */
    public function testPdoCoreExtensionIsInstalled(): void
    {
        $this->assertTrue(extension_loaded('pdo'), 'extension pdo indispensable');
        $this->assertTrue(class_exists(\PDO::class));
    }

    // --- cohérence de la correspondance type de base -> pilote ------------

    public function testEveryConfiguredDatabaseTypeMapsToAPdoDriver(): void
    {
        foreach (self::configuredTypes() as $type) {
            $this->assertNotNull(Connection::pdoDriverFor($type), "type « {$type} » sans pilote PDO connu");
        }
    }

    /** @dataProvider driverMapping */
    public function testDriverMapping(string $type, ?string $driver): void
    {
        $this->assertSame($driver, Connection::pdoDriverFor($type));
    }

    public static function driverMapping(): array
    {
        return [
            'postgresql'        => ['postgresql', 'pgsql'],
            'oracle (SID)'      => ['oracle', 'oci'],
            'oracle (service)'  => ['oracle_service', 'oci'],
            'mysql'             => ['mysql', 'mysql'],
            'mariadb'           => ['mariadb', 'mysql'],
            'sqlserver jre8'    => ['sqlserver_jre8', 'sqlsrv'],
            'sqlserver jre11'   => ['sqlserver_jre11', 'sqlsrv'],
            'type inconnu'      => ['sybase', null],
            'vide'              => ['', null],
        ];
    }

    // --- rapport « présents / absents » (logique, avec des extensions simulées) ---

    private function checker(array $loaded): VersionChecker
    {
        $logger = new Logger(false);
        $logger->setQuiet(true);

        return new VersionChecker($logger, null, fn(string $ext): bool => in_array($ext, $loaded, true));
    }

    public function testReportSplitsAvailableAndMissingDriversWithoutDuplicates(): void
    {
        $types = ['postgresql', 'oracle', 'oracle_service', 'mysql', 'mariadb', 'sqlserver_jre8', 'sqlserver_jre11'];

        $report = $this->checker(['pdo', 'pdo_pgsql', 'pdo_mysql'])->checkPdoDrivers($types);

        $this->assertSame(['pgsql', 'mysql'], $report['available']);
        $this->assertSame(['oci', 'sqlsrv'], $report['missing'], 'oracle et oracle_service partagent oci ; mysql et mariadb partagent mysql');
    }

    public function testReportWhenEverythingIsInstalled(): void
    {
        $report = $this->checker(['pdo_pgsql', 'pdo_oci', 'pdo_mysql', 'pdo_sqlsrv'])
            ->checkPdoDrivers(['postgresql', 'oracle', 'mysql', 'sqlserver_jre11']);

        $this->assertSame(['pgsql', 'oci', 'mysql', 'sqlsrv'], $report['available']);
        $this->assertSame([], $report['missing']);
    }

    public function testReportWhenNothingIsInstalled(): void
    {
        $report = $this->checker([])->checkPdoDrivers(['postgresql', 'oracle']);

        $this->assertSame([], $report['available']);
        $this->assertSame(['pgsql', 'oci'], $report['missing']);
    }

    public function testReportIgnoresUnknownTypesAndAcceptsAnEmptyList(): void
    {
        $this->assertSame(['available' => [], 'missing' => []], $this->checker([])->checkPdoDrivers([]));
        $this->assertSame(['available' => [], 'missing' => []], $this->checker([])->checkPdoDrivers(['sybase', '']));
    }

    /** Rapport sur le vrai PHP : cohérent avec ce que PDO déclare lui-même. */
    public function testReportMatchesTheRealPdoDrivers(): void
    {
        $logger = new Logger(false);
        $logger->setQuiet(true);

        $report = (new VersionChecker($logger))->checkPdoDrivers(self::configuredTypes());

        foreach ($report['available'] as $driver) {
            $this->assertContains($driver, \PDO::getAvailableDrivers());
        }
        foreach ($report['missing'] as $driver) {
            $this->assertNotContains($driver, \PDO::getAvailableDrivers());
        }
        $this->assertSame(
            count(array_unique(array_filter(array_map([Connection::class, 'pdoDriverFor'], self::configuredTypes())))),
            count($report['available']) + count($report['missing']),
            'chaque pilote requis est classé exactement une fois'
        );
    }
}
