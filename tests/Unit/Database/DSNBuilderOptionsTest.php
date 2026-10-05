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
use SchemaSpyCli\Database\DSNBuilder;

/**
 * [Description DSNBuilderOptionsTest]
 * Options de connexion PDO par type de base (indépendantes des extensions installées).
 */
final class DSNBuilderOptionsTest extends TestCase
{
    private DSNBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DSNBuilder(new Config());
    }

    public function testBaseOptionsApplyToEveryType(): void
    {
        foreach (['postgresql', 'oracle', 'mysql', 'mariadb', 'inconnu'] as $type) {
            $options = $this->builder->getConnectionOptions(['dbType' => $type]);

            $this->assertSame(\PDO::ERRMODE_EXCEPTION, $options[\PDO::ATTR_ERRMODE], $type);
            $this->assertSame(5, $options[\PDO::ATTR_TIMEOUT], "{$type} : délai par défaut");
            $this->assertSame(\PDO::FETCH_ASSOC, $options[\PDO::ATTR_DEFAULT_FETCH_MODE], $type);
        }
    }

    public function testTimeoutIsConfigurable(): void
    {
        $this->assertSame(30, $this->builder->getConnectionOptions(['dbType' => 'postgresql', 'timeout' => 30])[\PDO::ATTR_TIMEOUT]);
    }

    public function testPostgresqlAndOracleAutoCommit(): void
    {
        $this->assertTrue($this->builder->getConnectionOptions(['dbType' => 'postgresql'])[\PDO::ATTR_AUTOCOMMIT]);
        $this->assertTrue($this->builder->getConnectionOptions(['dbType' => 'oracle'])[\PDO::ATTR_AUTOCOMMIT]);
    }

    public function testMissingTypeDefaultsToPostgresql(): void
    {
        $this->assertTrue($this->builder->getConnectionOptions([])[\PDO::ATTR_AUTOCOMMIT]);
    }

    public function testUnknownTypeOnlyGetsTheBaseOptions(): void
    {
        $options = $this->builder->getConnectionOptions(['dbType' => 'inconnu']);

        $this->assertSame([\PDO::ATTR_ERRMODE, \PDO::ATTR_TIMEOUT, \PDO::ATTR_DEFAULT_FETCH_MODE], array_keys($options));
    }

    /** @dataProvider mysqlTypes */
    public function testMysqlFamilyUsesDriverSpecificOptionsOnlyWhenTheExtensionDefinesThem(string $type): void
    {
        $options = $this->builder->getConnectionOptions(['dbType' => $type]);

        // Sans pdo_mysql les constantes n'existent pas : aucune erreur, simplement pas d'option
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $this->assertSame("SET NAMES 'UTF8'", $options[\PDO::MYSQL_ATTR_INIT_COMMAND]);
        } else {
            $this->assertCount(3, $options);
        }
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $this->assertTrue($options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]);
        }
        $this->assertArrayNotHasKey(\PDO::ATTR_AUTOCOMMIT, $options);
    }

    public static function mysqlTypes(): array
    {
        return [['mysql'], ['mariadb']];
    }
}
