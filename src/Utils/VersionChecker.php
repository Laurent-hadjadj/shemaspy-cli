<?php
/**
 * Vérification des versions
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Core\Logger;

final class VersionChecker
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function checkJavaVersion(): ?string
    {
        $output = shell_exec('java -version 2>&1');
        
        if (preg_match('/version "([0-9.]+)"/', $output, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/openjdk version "([0-9.]+)"/', $output, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public function checkPhpVersion(): string
    {
        return PHP_VERSION;
    }

    public function checkRequiredPhpVersion(): bool
    {
        return version_compare(PHP_VERSION, '7.4.0', '>=');
    }

    public function checkExtensions(): array
    {
        $required = ['pdo', 'json', 'pdo_pgsql', 'pdo_mysql', 'pdo_oci'];
        $missing = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        return $missing;
    }

    public function getSchemaSpyVersion(string $jarFile): ?string
    {
        if (!file_exists($jarFile)) {
            return null;
        }

        // Essayer de lire le manifest du JAR
        $manifest = "META-INF/MANIFEST.MF";
        $zip = new \ZipArchive();
        if ($zip->open($jarFile) === true) {
            if ($stat = $zip->statName($manifest)) {
                $content = $zip->getFromName($manifest);
                if (preg_match('/Implementation-Version: (.*)/', $content, $matches)) {
                    $zip->close();
                    return trim($matches[1]);
                }
            }
            $zip->close();
        }

        return null;
    }

    public function checkCompatibility(array $params): array
    {
        $issues = [];
        
        // Vérifier la version PHP
        if (!$this->checkRequiredPhpVersion()) {
            $issues[] = "PHP 7.4 ou supérieur requis (actuel: " . PHP_VERSION . ")";
        }

        // Vérifier les extensions
        $missingExtensions = $this->checkExtensions();
        if (!empty($missingExtensions)) {
            $issues[] = "Extensions PHP manquantes: " . implode(', ', $missingExtensions);
        }

        // Vérifier Java
        $javaVersion = $this->checkJavaVersion();
        if ($javaVersion === null) {
            $issues[] = "Java non trouvé dans le PATH";
        } elseif (version_compare($javaVersion, '11.0', '<')) {
            $issues[] = "Java 11 ou supérieur requis (actuel: {$javaVersion})";
        }

        return $issues;
    }
}
