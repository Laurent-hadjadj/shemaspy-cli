<?php
/**
 * Tests unitaires pour la classe Environment
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Core\Environment;

final class EnvironmentTest extends TestCase
{
    private Environment $environment;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->environment = new Environment();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    /**
     * Crée un faux exécutable qui affiche $output sur sa sortie, pour simuler
     * `java -version` ou `dot -V` sans dépendre d'un vrai JDK/Graphviz installé.
     */
    private function fakeExecutable(string $output): string
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $path = sys_get_temp_dir() . '/fake_exe_' . uniqid() . ($isWindows ? '.bat' : '.sh');

        if ($isWindows) {
            file_put_contents($path, "@echo off\r\necho {$output}\r\n");
        } else {
            file_put_contents($path, "#!/bin/sh\necho '{$output}'\n");
            chmod($path, 0755);
        }

        $this->tempFiles[] = $path;
        return $path;
    }

    public function testGetJavaVersionLegacyFormatWithBuildSuffix(): void
    {
        // Format JDK 8 et antérieur : "1.8.0_231" (le suffixe de build est ignoré)
        $exe = $this->fakeExecutable('java version "1.8.0_231"');
        $this->assertEquals('1.8.0', $this->environment->getJavaVersion($exe));
    }

    public function testGetJavaVersionModernFormat(): void
    {
        // Format JDK 9+ : "17.0.9"
        $exe = $this->fakeExecutable('openjdk version "17.0.9" 2023-10-17');
        $this->assertEquals('17.0.9', $this->environment->getJavaVersion($exe));
    }

    public function testGetJavaVersionShortMajorFormat(): void
    {
        // Certaines distributions n'affichent que le majeur : "11"
        $exe = $this->fakeExecutable('openjdk version "11" 2018-09-25');
        $this->assertEquals('11', $this->environment->getJavaVersion($exe));
    }

    public function testGetJavaVersionUnparseableOutput(): void
    {
        $exe = $this->fakeExecutable('commande introuvable');
        $this->assertNull($this->environment->getJavaVersion($exe));
    }

    public function testGetGraphvizVersion(): void
    {
        $exe = $this->fakeExecutable('dot - graphviz version 2.38.0 (20140413.2041)');
        $this->assertEquals('2.38.0', $this->environment->getGraphvizVersion($exe));
    }

    public function testGetGraphvizVersionUnparseableOutput(): void
    {
        $exe = $this->fakeExecutable('commande introuvable');
        $this->assertNull($this->environment->getGraphvizVersion($exe));
    }
}
