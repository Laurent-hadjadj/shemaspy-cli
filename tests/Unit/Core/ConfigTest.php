<?php
/**
 * Tests unitaires pour la classe Config
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Exceptions\ConfigException;

final class ConfigTest extends TestCase
{
    private Config $config;
    private string $tempConfigFile;

    protected function setUp(): void
    {
        $this->config = new Config();
        $this->tempConfigFile = sys_get_temp_dir() . '/test_config.json';
        
        // Créer un fichier de configuration de test
        $testConfig = [
            'application' => [
                'name' => 'Test App',
                'version' => '1.0.0',
                'company' => 'Test Company',
            ],
            'schemaspy' => [
                'version' => '7.0.2',
                'jar' => 'test.jar',
            ],
            'paths' => [
                'root_folder' => 'test_env',
                'tools_folder' => 'test_tools',
            ],
            'jdbc' => [
                'postgresql' => [
                    'type' => 'pgsql11',
                    'port' => 5432,
                    'driver' => 'postgresql-42.7.5.jar',
                ],
            ],
            'defaults' => [
                'host' => 'localhost',
                'timeout' => 10,
            ],
        ];
        
        file_put_contents($this->tempConfigFile, json_encode($testConfig));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfigFile)) {
            unlink($this->tempConfigFile);
        }
    }

    public function testLoadConfig(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $this->assertEquals('Test App', $this->config->get('application.name'));
        $this->assertEquals('1.0.0', $this->config->get('application.version'));
    }

    public function testLoadConfigFileNotFound(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Fichier de configuration introuvable');
        
        $this->config->load('/path/to/nonexistent/config.json');
    }

    public function testLoadInvalidJson(): void
    {
        $invalidFile = sys_get_temp_dir() . '/invalid_config.json';
        file_put_contents($invalidFile, '{invalid json}');
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Erreur de parsing');
        
        $this->config->load($invalidFile);
        
        unlink($invalidFile);
    }

    public function testGetWithDefault(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $this->assertEquals('default', $this->config->get('nonexistent.key', 'default'));
        $this->assertNull($this->config->get('nonexistent.key'));
    }

    public function testGetNestedKey(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $this->assertEquals('Test App', $this->config->get('application.name'));
        $this->assertEquals('pgsql11', $this->config->get('jdbc.postgresql.type'));
    }

    public function testSet(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $this->config->set('application.name', 'New App Name');
        $this->assertEquals('New App Name', $this->config->get('application.name'));
        
        $this->config->set('new.key', 'value');
        $this->assertEquals('value', $this->config->get('new.key'));
    }

    public function testGetBasePath(): void
    {
        // setBasePath normalise vers le séparateur du système (DIRECTORY_SEPARATOR)
        $this->config->setBasePath('/test/path');
        $this->assertEquals(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, '/test/path'),
            $this->config->getBasePath()
        );
    }

    public function testGetPath(): void
    {
        $this->config->load($this->tempConfigFile);
        $this->config->setBasePath('/base/path');

        $path = $this->config->getPath('root_folder');
        $expected = '/base/path/test_env';
        $this->assertEquals(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $expected),
            $path
        );
    }

    public function testGetPathNotFound(): void
    {
        $this->config->load($this->tempConfigFile);
        $this->config->setBasePath('/base/path');
        
        $this->expectException(ConfigException::class);
        $this->config->getPath('nonexistent');
    }

    public function testGetDatabases(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $databases = $this->config->getDatabases();
        $this->assertArrayHasKey('postgresql', $databases);
        $this->assertEquals('pgsql11', $databases['postgresql']['type']);
    }

    public function testGetDatabase(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $postgresql = $this->config->getDatabase('postgresql');
        $this->assertNotNull($postgresql);
        $this->assertEquals('pgsql11', $postgresql['type']);
        
        $this->assertNull($this->config->getDatabase('nonexistent'));
    }

    public function testGetApplicationName(): void
    {
        $this->config->load($this->tempConfigFile);
        $this->assertEquals('Test App', $this->config->getApplicationName());
    }

    public function testGetSchemaspyVersion(): void
    {
        $this->config->load($this->tempConfigFile);
        $this->assertEquals('7.0.2', $this->config->getSchemaspyVersion());
    }

    public function testGetSchemaspyJar(): void
    {
        $this->config->load($this->tempConfigFile);
        $this->assertEquals('test.jar', $this->config->getSchemaspyJar());
    }

    public function testGetAll(): void
    {
        $this->config->load($this->tempConfigFile);
        
        $all = $this->config->getAll();
        $this->assertArrayHasKey('application', $all);
        $this->assertArrayHasKey('schemaspy', $all);
        $this->assertArrayHasKey('paths', $all);
        $this->assertArrayHasKey('jdbc', $all);
        $this->assertArrayHasKey('defaults', $all);
    }

    // --- getAbsolutePath -------------------------------------------------

    /** @dataProvider absolutePaths */
    public function testAbsolutePathIsReturnedUnchanged(string $path): void
    {
        $config = new Config();
        $config->setBasePath('/base');

        $this->assertSame($path, $config->getAbsolutePath($path));
    }

    public static function absolutePaths(): array
    {
        return [
            'unix'                       => ['/usr/lib/jvm'],
            'Windows antislash'          => ['C:\\tools\\jdk17'],
            'Windows slash (courant en PHP)' => ['C:/tools/jdk17'],
            'lecteur en minuscule'       => ['d:/data'],
            'UNC'                        => ['\\\\serveur\\partage'],
        ];
    }

    public function testRelativePathIsResolvedAgainstTheBasePath(): void
    {
        $config = new Config();
        $config->setBasePath('/base');

        $this->assertSame($config->getBasePath() . DIRECTORY_SEPARATOR . 'tools/jdk17', $config->getAbsolutePath('tools/jdk17'));
    }

    public function testRelativePathIsLeftAsIsWithoutBasePath(): void
    {
        $this->assertSame('tools/jdk17', (new Config())->getAbsolutePath('tools/jdk17'));
    }

    // --- chargement : erreurs et valeurs par défaut ----------------------

    private function loadJson(string $content): Config
    {
        $file = sys_get_temp_dir() . '/cfg_test_' . uniqid() . '.json';
        file_put_contents($file, $content);
        try {
            $config = new Config();
            $config->load($file);
            return $config;
        } finally {
            @unlink($file);
        }
    }

    /** @dataProvider brokenConfigs */
    public function testBrokenConfigurationFilesAreRejected(string $content, string $messagePart): void
    {
        $this->expectException(\SchemaSpyCli\Exceptions\ConfigException::class);
        $this->expectExceptionMessage($messagePart);
        $this->loadJson($content);
    }

    public static function brokenConfigs(): array
    {
        return [
            'fichier vide'          => ['', 'fichier vide'],
            'espaces seulement'     => ["  \n\t ", 'fichier vide'],
            'JSON invalide'         => ['{ "jdbc": ', 'Erreur de parsing'],
            'JSON null'             => ['null', 'racine non objet'],
            'nombre'                => ['42', 'racine non objet'],
            'chaîne'                => ['"texte"', 'racine non objet'],
            'jdbc manquant'         => ['{"paths": {}}', "'jdbc' manquant"],
        ];
    }

    public function testMissingFileIsReported(): void
    {
        $this->expectException(\SchemaSpyCli\Exceptions\ConfigException::class);
        $this->expectExceptionMessage('introuvable');
        (new Config())->load(sys_get_temp_dir() . '/absent_' . uniqid() . '.json');
    }

    public function testMissingPathsSectionGetsTheDefaults(): void
    {
        $config = $this->loadJson('{"jdbc": {"postgresql": {}}}');

        $this->assertSame('jdbc', $config->getPath('jdbc_folder'));
        $this->assertSame('report', $config->getPath('output_folder'));
        $this->assertSame('jdk17', $config->getPath('java_folder'));
        $this->assertSame('graphviz-2.38', $config->getPath('graphviz_folder'));
    }

    public function testConfiguredPathsOverrideTheDefaultsAndOthersAreCompleted(): void
    {
        $config = $this->loadJson('{"jdbc": {}, "paths": {"java_folder": "tools/jdk21"}}');

        $this->assertSame('tools' . DIRECTORY_SEPARATOR . 'jdk21', $config->getPath('java_folder'));
        $this->assertSame('jdbc', $config->getPath('jdbc_folder'), 'valeur par défaut complétée');
    }

    // --- has / valeurs par défaut -----------------------------------------

    public function testHasReportsExistingAndMissingKeys(): void
    {
        $config = $this->loadJson('{"jdbc": {"oracle": {"port": 1521}}, "application": {"name": "App"}}');

        $this->assertTrue($config->has('application'));
        $this->assertTrue($config->has('application.name'));
        $this->assertTrue($config->has('jdbc.oracle.port'));
        $this->assertFalse($config->has('application.version'));
        $this->assertFalse($config->has('jdbc.oracle.port.inexistant'));
        $this->assertFalse($config->has('absent'));
        $this->assertFalse($config->has('absent.profond.encore'));
    }

    public function testApplicationVersionDefault(): void
    {
        $this->assertSame('1.0.0', (new Config())->getApplicationVersion());
        $this->assertSame('3.2.1', $this->loadJson('{"jdbc": {}, "application": {"version": "3.2.1"}}')->getApplicationVersion());
    }

    public function testValidationConfigDefaultsAndOverride(): void
    {
        $default = (new Config())->getValidationConfig();

        $this->assertTrue($default['enabled']);
        $this->assertFalse($default['check_checksum']);
        $this->assertTrue($default['check_version']);
        $this->assertTrue($default['warn_on_extra']);
        $this->assertFalse($default['strict_mode']);
        $this->assertTrue($default['warn_obsolete']);
        $this->assertFalse($default['cleanup_unused']);

        $custom = $this->loadJson('{"jdbc": {}, "jdbc_validation": {"enabled": false}}')->getValidationConfig();
        $this->assertSame(['enabled' => false], $custom);
    }
}
