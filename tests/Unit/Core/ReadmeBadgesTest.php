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
 * [Description ReadmeBadgesTest]
 * Les badges du README ne doivent pas affirmer autre chose que le projet : version de l'application, PHP
 * minimal, version du fork SchemaSpy, Java et Graphviz sont comparés à config.json et composer.json.
 * (Nombre de tests, assertions et couverture ne sont pas vérifiables ici : seul leur format l'est.)
 */
final class ReadmeBadgesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** @var array<string, array{message: string, color: string, link: string}> */
    private static array $badges = [];
    private static array $config = [];
    private static array $composer = [];

    public static function setUpBeforeClass(): void
    {
        $readme = (string) file_get_contents(self::ROOT . '/README.md');
        self::$config = json_decode((string) file_get_contents(self::ROOT . '/config/config.json'), true, 512, JSON_THROW_ON_ERROR);
        self::$composer = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        preg_match_all('#^\[!\[([^\]]+)\]\(https://img\.shields\.io/badge/([^)?]+)[^)]*\)\]\(([^)]+)\)$#m', $readme, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $alt, $path, $link]) {
            [, $message, $color] = self::decode($path);
            self::$badges[$alt] = ['message' => $message, 'color' => $color, 'link' => $link];
        }
    }

    /**
     * Décode « label-message-couleur » d'une URL shields.io : « -- » = tiret, « __ » = souligné,
     * « _ » = espace, %xx = caractère encodé.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function decode(string $path): array
    {
        $parts = preg_split('/(?<!-)-(?!-)/', $path);
        $parts = array_map(
            static fn(string $p): string => str_replace(['--', '__', '_'], ['-', "\0", ' '], rawurldecode($p)),
            $parts
        );
        $parts = array_map(static fn(string $p): string => str_replace("\0", '_', $p), $parts);

        return [$parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? ''];
    }

    private function badge(string $name): array
    {
        $this->assertArrayHasKey($name, self::$badges, "badge « {$name} » absent du README");

        return self::$badges[$name];
    }

    public function testExpectedBadgesArePresent(): void
    {
        foreach (['Version', 'PHP', 'PHP testé', 'SchemaSpy', 'Java', 'Graphviz', 'PostgreSQL', 'Oracle', 'MySQL', 'MariaDB',
                  'SQL Server', 'Tests', 'Assertions', 'Coverage', 'Licence'] as $name) {
            $this->assertArrayHasKey($name, self::$badges, "badge « {$name} » absent du README");
        }
    }

    public function testVersionBadgeMatchesTheApplicationVersion(): void
    {
        $this->assertSame(self::$config['application']['version'], $this->badge('Version')['message']);
    }

    public function testSchemaSpyBadgeMatchesTheConfiguredFork(): void
    {
        $this->assertSame(self::$config['schemaspy']['version'], $this->badge('SchemaSpy')['message']);
    }

    public function testPhpBadgeMatchesTheComposerConstraint(): void
    {
        preg_match('/(\d+(?:\.\d+)*)/', self::$composer['require']['php'], $m);

        $this->assertStringContainsString($m[1], $this->badge('PHP')['message']);
        $this->assertStringContainsString('≥', $this->badge('PHP')['message'], 'contrainte « >= » dans composer.json');

        // Version sur laquelle les tests sont exécutés (« 8.5.5 NTS » : x.y.z puis NTS ou ZTS) : jamais sous le minimum
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+ (NTS|ZTS)$/', $this->badge('PHP testé')['message']);
        $this->assertTrue(
            version_compare((string) strtok($this->badge('PHP testé')['message'], ' '), $m[1], '>='),
            'la version testée ne peut pas être inférieure au minimum de composer.json'
        );
    }

    public function testJavaBadgeMatchesTheRequirementOfTheConfiguredSchemaSpy(): void
    {
        $major = explode('.', self::$config['schemaspy']['version'])[0];

        $this->assertSame(self::$config['schemaspy']['compatibility'][$major] . '+', $this->badge('Java')['message']);
    }

    public function testGraphvizBadgeMatchesTheEmbeddedVersion(): void
    {
        preg_match('/graphviz-([\d.]+)$/', self::$config['paths']['graphviz_folder'], $m);

        $this->assertSame($m[1], $this->badge('Graphviz')['message']);
    }

    public function testLicenceBadgeMatchesTheProjectLicence(): void
    {
        $this->assertStringContainsString('CC BY-NC-SA 4.0', $this->badge('Licence')['message']);
        $this->assertStringContainsString('CC-BY-NC-SA 4.0', (string) (self::$config['application']['copyright'] ?? ''));
    }

    public function testTestFiguresHaveAValidFormat(): void
    {
        $this->assertMatchesRegularExpression('/^\d+ passed$/', $this->badge('Tests')['message']);
        $this->assertMatchesRegularExpression('/^\d+$/', $this->badge('Assertions')['message']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?%$/', $this->badge('Coverage')['message']);
        $this->assertLessThanOrEqual(100.0, (float) $this->badge('Coverage')['message']);
        $this->assertGreaterThanOrEqual(
            (int) $this->badge('Tests')['message'],
            (int) $this->badge('Assertions')['message'],
            'au moins une assertion par test'
        );
    }

    public function testEveryDatabaseTypeOfTheConfigurationHasABadgeFamily(): void
    {
        $badgeText = strtolower(implode(' ', array_keys(self::$badges)));
        foreach (array_keys(self::$config['jdbc']) as $type) {
            $family = match (true) {
                str_starts_with($type, 'sqlserver') => 'sql server',
                str_starts_with($type, 'oracle')    => 'oracle',
                default                             => $type,
            };
            $this->assertStringContainsString($family, $badgeText, "type « {$type} » sans badge");
        }
    }

    public function testLocalBadgeLinksExist(): void
    {
        foreach (self::$badges as $name => $badge) {
            if (str_starts_with($badge['link'], 'http')) {
                continue;
            }
            $this->assertFileExists(self::ROOT . '/' . $badge['link'], "lien local du badge « {$name} »");
        }
    }

    public function testNoInternalHostNameInTheBadges(): void
    {
        $this->assertDoesNotMatchRegularExpression('/franceagrimer/i', (string) file_get_contents(self::ROOT . '/README.md'));
    }
}
