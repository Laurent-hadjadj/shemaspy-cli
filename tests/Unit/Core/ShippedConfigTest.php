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

namespace SchemaSpyCli\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * [Description ShippedConfigTest]
 * Cohérence du config/config.json livré (types JDBC, drivers, versions).
 */
final class ShippedConfigTest extends TestCase
{
    private static array $config;

    public static function setUpBeforeClass(): void
    {
        self::$config = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../config/config.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testEveryJdbcTypeIsFullyDescribed(): void
    {
        foreach (self::$config['jdbc'] as $name => $entry) {
            foreach (['type', 'port', 'driver', 'version', 'class', 'download_url'] as $key) {
                $this->assertArrayHasKey($key, $entry, "jdbc.{$name} : clé « {$key} » manquante");
            }
            $this->assertIsInt($entry['port'], "jdbc.{$name}.port doit être un entier");
            $this->assertStringEndsWith('.jar', $entry['driver']);
            $this->assertStringContainsString($entry['version'], $entry['driver'], "jdbc.{$name} : version et nom du JAR incohérents");
            $this->assertStringEndsWith($entry['driver'], $entry['download_url'], "jdbc.{$name} : l'URL ne télécharge pas le JAR annoncé");
        }
    }

    public function testOracleSidAndServiceTypesAreBothAvailable(): void
    {
        $jdbc = self::$config['jdbc'];

        $this->assertSame('orathin', $jdbc['oracle']['type'], 'oracle = SID');
        $this->assertSame('orathin-service', $jdbc['oracle_service']['type'], 'oracle_service = nom de service');
        $this->assertSame($jdbc['oracle']['driver'], $jdbc['oracle_service']['driver'], 'même JAR ojdbc11');
        $this->assertSame($jdbc['oracle']['class'], $jdbc['oracle_service']['class']);
        $this->assertSame(1521, $jdbc['oracle_service']['port']);
    }

    public function testConfiguredSchemaSpyJarFollowsTheVersion(): void
    {
        $this->assertStringContainsString(self::$config['schemaspy']['version'], self::$config['schemaspy']['jar']);
    }

    public function testNoInternalHostNameInTheShippedConfig(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/franceagrimer/i',
            (string) file_get_contents(__DIR__ . '/../../../config/config.json'),
            'un nom d\'hôte interne ne doit jamais figurer dans un fichier publié'
        );
    }
}
