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

use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Core\Logger;

/**
 * [Description VersionChecker]
 * Vérification des versions
 */
final class VersionChecker
{
    /**
     * Les trois derniers paramètres sont facultatifs et servent aux tests : par défaut le vrai
     * environnement (java du système, extensions chargées, PHP_VERSION).
     *
     * @param (\Closure(string): bool)|null $extensionLoaded
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly ?Environment $environment = null,
        private readonly ?\Closure $extensionLoaded = null,
        private readonly ?string $phpVersion = null
    ) {
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
        // Même logique que PathFinder : reconnaît « 1.8.0_231 » (JDK 8) comme « 17.0.8 »,
        // et renvoie null (sans avertissement PHP) quand java est absent.
        return ($this->environment ?? new Environment())->getJavaVersion();
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
        return $this->phpVersion ?? PHP_VERSION;
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
        return version_compare($this->checkPhpVersion(), '8.1.0', '>=');
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
        $isLoaded = $this->extensionLoaded ?? extension_loaded(...);

        foreach ($required as $ext) {
            if (!$isLoaded($ext)) {
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
        if (!is_file($jarFile) || !class_exists(\ZipArchive::class)) {
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
            $issues[] = "PHP 8.1 ou supérieur requis (actuel: " . $this->checkPhpVersion() . ")";
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
        } elseif (version_compare($javaVersion, '11', '<')) { // '11' et non '11.0' : "11" < "11.0" en PHP
            $issues[] = "Java 11 ou supérieur requis (actuel: {$javaVersion})";
        }

        return $issues;
    }
}
