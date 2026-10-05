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
}
