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
use SchemaSpyCli\Cli\NonInteractiveMode;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Exceptions\ValidationException;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\Validator;

/**
 * [Description NonInteractiveModeTest]
 * Tests unitaires pour NonInteractiveMode : le chemin CI/CD (validation des paramètres de la
 * ligne de commande et construction du jeu de paramètres final).
 */
final class NonInteractiveModeTest extends TestCase
{
    private const VALID = [
        'db' => 'postgresql', 'host' => 'db.example.local', 'database' => 'demo', 'schema' => 'public',
        'user' => 'scott', 'password' => 'S3cret!', 'output' => 'run_1',
    ];

    private TempDir $tmp;
    private Config $config;
    private string $logFile;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->logFile = $this->tmp->path . '/test.log';

        $this->config = new Config();
        $this->config->set('jdbc', [
            'postgresql' => ['type' => 'pgsql11', 'port' => 5432, 'driver' => 'pg.jar'],
            'oracle'     => ['type' => 'orathin', 'port' => 1521, 'driver' => 'ojdbc.jar'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    private function mode(): NonInteractiveMode
    {
        $logger = new Logger(false, $this->logFile);
        $logger->setQuiet(true);

        $validator = new Validator();
        $validator->setValidDatabaseTypes(array_keys($this->config->getDatabases()));

        return new NonInteractiveMode($this->config, $logger, $validator);
    }

    private function collect(array $overrides = [], array $remove = []): array
    {
        $params = array_merge(self::VALID, $overrides);
        foreach ($remove as $key) {
            unset($params[$key]);
        }
        return $this->mode()->collect($params);
    }

    private function assertInvalid(array $overrides, string $messagePart, array $remove = []): void
    {
        try {
            $this->collect($overrides, $remove);
            $this->fail("ValidationException attendue contenant « {$messagePart} »");
        } catch (ValidationException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // --- construction des paramètres -----------------------------------

    public function testValidParametersProduceTheFinalParameterSet(): void
    {
        $params = $this->collect();

        $this->assertSame('postgresql', $params['dbType']);
        $this->assertSame(['type' => 'pgsql11', 'port' => 5432, 'driver' => 'pg.jar'], $params['dbConfig']);
        $this->assertSame('db.example.local', $params['host']);
        $this->assertSame('demo', $params['database']);
        $this->assertSame('public', $params['schema']);
        $this->assertSame('scott', $params['user']);
        $this->assertSame('S3cret!', $params['password']);
        $this->assertSame('run_1', $params['output']);
        $this->assertFalse($params['useVizJs']);
    }

    public function testPortDefaultsToTheDatabaseTypePortAsInteger(): void
    {
        $this->assertSame(5432, $this->collect()['port']);
        $this->assertSame(1521, $this->collect(['db' => 'oracle'])['port']);
    }

    public function testExplicitPortFromCommandLineIsConvertedToInteger(): void
    {
        $this->assertSame(15432, $this->collect(['port' => '15432'])['port']);
        $this->assertSame(1522, $this->collect(['port' => 1522])['port']);
    }

    public function testMissingDbKeyDefaultsToPostgresql(): void
    {
        $params = $this->collect([], ['db']);

        $this->assertSame('postgresql', $params['dbType']);
        $this->assertSame(5432, $params['port']);
    }

    public function testOutputNameIsGeneratedWhenNotProvided(): void
    {
        $params = $this->collect([], ['output']);

        $this->assertMatchesRegularExpression('/^postgresql_public_\d{8}_\d{6}$/', $params['output']);
    }

    public function testGeneratedOutputNameFollowsConfiguredFormat(): void
    {
        $this->config->set('defaults.output_name_format', 'doc_{schema}');

        $this->assertSame('doc_public', $this->collect([], ['output'])['output']);
    }

    // --- paramètres requis ---------------------------------------------

    /** @dataProvider requiredFields */
    public function testEachRequiredParameterIsEnforced(string $field): void
    {
        $this->assertInvalid([], $field, [$field]);
        $this->assertInvalid([$field => ''], $field);
    }

    public static function requiredFields(): array
    {
        return array_map(fn(string $f) => [$f], ['host', 'database', 'schema', 'user', 'password']);
    }

    public function testAllMissingParametersAreListedTogether(): void
    {
        try {
            $this->mode()->collect(['db' => 'postgresql']);
            $this->fail('ValidationException attendue');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('host, database, schema, user, password', $e->getMessage());
            $this->assertStringContainsString('--help', $e->getMessage());
        }
    }

    // --- type de base ---------------------------------------------------

    public function testUnknownDatabaseTypeListsTheAvailableTypes(): void
    {
        $this->assertInvalid(['db' => 'sybase'], 'Type de base de données inconnu : sybase');
        $this->assertInvalid(['db' => 'sybase'], 'postgresql, oracle');
    }

    public function testEmptyDatabaseTypeIsRejected(): void
    {
        $this->assertInvalid(['db' => ''], 'Type de base de données inconnu');
    }

    // --- validation des valeurs ----------------------------------------

    /** @dataProvider invalidValues */
    public function testInvalidValuesAreRejected(string $field, string $value, string $messagePart): void
    {
        $this->assertInvalid([$field => $value], $messagePart);
    }

    public static function invalidValues(): array
    {
        return [
            'hôte avec espace'      => ['host', 'bad host', "Nom d'hôte invalide"],
            'hôte avec injection'   => ['host', 'a;rm -rf', "Nom d'hôte invalide"],
            'base avec espace'      => ['database', 'my db', 'Nom de base de données invalide'],
            'base trop longue'      => ['database', str_repeat('a', 65), 'trop long'],
            'schéma avec caractère' => ['schema', 'pub lic', 'Nom de schéma invalide'],
            'schéma trop long'      => ['schema', str_repeat('s', 64), 'trop long'],
            'utilisateur invalide'  => ['user', 'sc ott', "Nom d'utilisateur invalide"],
            'sortie avec ..'        => ['output', '../evil', "ne peut pas contenir '..'"],
            'sortie avec espace'    => ['output', 'a b', 'Nom de sortie invalide'],
            'sortie avec slash'     => ['output', 'a/b', 'Nom de sortie invalide'],
        ];
    }

    /** @dataProvider invalidPorts */
    public function testInvalidPortsGiveAValidationErrorNotATypeError(string|int $port): void
    {
        $this->assertInvalid(['port' => $port], 'Port invalide');
    }

    public static function invalidPorts(): array
    {
        return [
            'texte'              => ['abc'],
            'texte avec chiffre' => ['54a2'],
            'négatif'            => ['-1'],
            'décimal'            => ['5432.5'],
            'zéro'               => ['0'],
            'trop grand'         => ['70000'],
            'entier hors plage'  => [99999],
        ];
    }

    public function testBoundaryPortsAreAccepted(): void
    {
        $this->assertSame(1, $this->collect(['port' => '1'])['port']);
        $this->assertSame(65535, $this->collect(['port' => '65535'])['port']);
    }

    // --- vizjs ----------------------------------------------------------

    /** @dataProvider vizjsValues */
    public function testLegacyVizjsParameter(string $value, bool $expected): void
    {
        $this->assertSame($expected, $this->collect(['vizjs' => $value])['useVizJs']);
    }

    public static function vizjsValues(): array
    {
        return [
            'true' => ['true', true], 'TRUE' => ['TRUE', true], '1' => ['1', true], 'yes' => ['yes', true],
            'false' => ['false', false], '0' => ['0', false], 'no' => ['no', false],
        ];
    }

    public function testInvalidVizjsValueIsRejected(): void
    {
        $this->assertInvalid(['vizjs' => 'peut-etre'], "vizjs doit être 'true' ou 'false'");
    }

    // --- cohérence et journalisation -----------------------------------

    /** @dataProvider systemSchemas */
    public function testSystemSchemaIsAllowedWithAWarning(string $schema): void
    {
        $params = $this->collect(['schema' => $schema]);

        $this->assertSame($schema, $params['schema']);
        $this->assertStringContainsString("schéma système", file_get_contents($this->logFile));
    }

    public static function systemSchemas(): array
    {
        return [['information_schema'], ['pg_catalog'], ['PG_CATALOG']];
    }

    public function testRegularSchemaProducesNoSystemWarning(): void
    {
        $this->collect();

        $this->assertStringNotContainsString('schéma système', file_get_contents($this->logFile));
    }

    public function testPasswordNeverAppearsInTheLog(): void
    {
        $this->collect(['password' => 'Un-Mot-De-Passe-Unique-123']);

        $log = file_get_contents($this->logFile);
        $this->assertStringContainsString('password=******', $log);
        $this->assertStringNotContainsString('Un-Mot-De-Passe-Unique-123', $log);
    }
}
