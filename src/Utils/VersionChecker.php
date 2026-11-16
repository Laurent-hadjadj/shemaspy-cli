<?php

/**
 *  Ma-Moulinette - ShemaSpy-Cli
 *  --------------
 *  Copyright (c) 2015-2026.
 *  Laurent HADJADJ <laurent_h@me.com>.
 *  Licensed GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
 *  ---
 *  Vous pouvez obtenir une copie de la licence à l'adresse suivante :
 *  https://www.gnu.org/licenses/gpl-3.0.html
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
     * Version minimale de chaque branche PHP supportée (correctif de sécurité critique du 21 octobre 2026).
     * Une branche plus récente que la dernière listée est acceptée ; une branche plus ancienne est refusée.
     */
    public const PHP_MINIMUM_BY_BRANCH = [
        '8.2' => '8.2.35',
        '8.3' => '8.3.36',
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    ];

    /** Version cible recommandée. */
    public const PHP_RECOMMENDED = '8.5.12';

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
     * @copyright  Licensed Ma-Moulinette - GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
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
     * @copyright  Licensed Ma-Moulinette - GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
     */
    /** Ex. « 8.2.35, 8.3.36, 8.4.27 ou 8.5.12 ». */
    public static function requiredPhpLabel(): string
    {
        $list = array_values(self::PHP_MINIMUM_BY_BRANCH);
        $last = array_pop($list);

        return implode(', ', $list) . ' ou ' . $last;
    }

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
     * @copyright  Licensed Ma-Moulinette - GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
     */
    public function checkRequiredPhpVersion(): bool
    {
        $version = $this->checkPhpVersion();
        $branch = implode('.', array_slice(explode('.', $version), 0, 2));

        if (isset(self::PHP_MINIMUM_BY_BRANCH[$branch])) {
            return version_compare($version, self::PHP_MINIMUM_BY_BRANCH[$branch], '>=');
        }

        // Branche absente de la liste : acceptée seulement si plus récente que la dernière branche listée
        return version_compare($branch, (string) array_key_last(self::PHP_MINIMUM_BY_BRANCH), '>');
    }

    /**
     * [Description for checkExtensions]
     *
     * @return array
     *
     * Created at: 05/10/2026 09:10:02 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
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
     * Pilotes PDO nécessaires au test de connexion de ces types de base, répartis en présents et
     * absents (ex: pgsql, oci, mysql, sqlsrv). Un pilote absent n'est pas bloquant : la connexion est
     * alors limitée à un test réseau, l'authentification étant validée par SchemaSpy via JDBC.
     *
     * @param list<string> $dbTypes clés de « jdbc » dans config.json (postgresql, oracle, oracle_service...)
     * @return array{available: list<string>, missing: list<string>} sans doublon, dans l'ordre d'apparition
     */
    public function checkPdoDrivers(array $dbTypes): array
    {
        $isLoaded = $this->extensionLoaded ?? extension_loaded(...);
        $report = ['available' => [], 'missing' => []];

        foreach ($dbTypes as $dbType) {
            $driver = \SchemaSpyCli\Database\Connection::pdoDriverFor((string) $dbType);
            if ($driver === null || in_array($driver, $report['available'], true) || in_array($driver, $report['missing'], true)) {
                continue;
            }
            $report[$isLoaded('pdo_' . $driver) ? 'available' : 'missing'][] = $driver;
        }

        return $report;
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
     * @copyright  Licensed Ma-Moulinette - GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
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
     * @copyright  Licensed Ma-Moulinette - GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
     */
    public function checkCompatibility(array $params): array
    {
        $issues = [];

        // Vérifier la version PHP
        if (!$this->checkRequiredPhpVersion()) {
            $issues[] = "PHP " . self::requiredPhpLabel() . " minimum requis (actuel: " . $this->checkPhpVersion() . ")";
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
