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

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Core\Logger;

/**
 * [Description VersionChecker]
 * Vérification des versions
 */
final class VersionChecker
{
    public function __construct(private readonly Logger $logger)
    {
    }

    /**
     * [Description for checkJavaVersion]
     *
     * @return string|null
     *
     * Created at: 05/10/2026 09:09:49 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
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

    /**
     * [Description for checkPhpVersion]
     *
     * @return string
     *
     * Created at: 05/10/2026 09:09:57 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function checkPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * [Description for checkRequiredPhpVersion]
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:10:00 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function checkRequiredPhpVersion(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }

    /**
     * [Description for checkExtensions]
     *
     * @return array
     *
     * Created at: 05/10/2026 09:10:02 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
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

    /**
     * [Description for getSchemaSpyVersion]
     *
     * @param string $jarFile
     *
     * @return string|null
     *
     * Created at: 05/10/2026 09:10:05 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
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

    /**
     * [Description for checkCompatibility]
     *
     * @param array $params
     *
     * @return array
     *
     * Created at: 05/10/2026 09:10:10 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function checkCompatibility(array $params): array
    {
        $issues = [];

        // Vérifier la version PHP
        if (!$this->checkRequiredPhpVersion()) {
            $issues[] = "PHP 8.1 ou supérieur requis (actuel: " . PHP_VERSION . ")";
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
