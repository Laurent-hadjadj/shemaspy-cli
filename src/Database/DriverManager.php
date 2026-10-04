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

namespace SchemaSpyCli\Database;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Exceptions\FileNotFoundException;

/**
 * [Description DriverManager]
 * Gestion des drivers JDBC
 */
final class DriverManager
{
    private array $drivers = [];
    private array $validationResults = [];

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger
    ) {
        // Vérifier que la config est chargée
        if (empty($this->config->getAll())) {
            throw new \RuntimeException("La configuration n'a pas été chargée avant d'initialiser DriverManager");
        }

        $this->loadDrivers();
        $this->validateDrivers();
    }

    /**
     * [Description for loadDrivers]
     *
     * @return void
     *
     * Created at: 04/10/2026 22:44:04 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function loadDrivers(): void
    {
        $jdbcPath = $this->config->getPath('jdbc_folder');

        if (!is_dir($jdbcPath)) {
            $this->logger->warning("Dossier JDBC introuvable: {$jdbcPath}");
            return;
        }

        $files = scandir($jdbcPath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'jar') {
                $this->drivers[$file] = [
                    'path' => $jdbcPath . '/' . $file,
                    'size' => filesize($jdbcPath . '/' . $file),
                    'mtime' => filemtime($jdbcPath . '/' . $file),
                ];
            }
        }

        if (empty($this->drivers)) {
            $this->logger->warning("⚠️  Aucun driver JDBC trouvé dans: {$jdbcPath}");
            $this->logger->warning("   Veuillez placer vos drivers JDBC dans ce dossier.");
            $this->logger->warning("   Drivers requis: postgresql-42.7.5.jar, ojdbc11.jar, mysql-connector-j-8.0.33.jar");
        } else {
            $this->logger->debug("📦 " . count($this->drivers) . " drivers JDBC chargés");
        }
    }

    /**
     * [Description for validateDrivers]
     *
     * @return array
     *
     * Created at: 04/10/2026 22:44:13 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateDrivers(): array
    {
        $this->validationResults = [];
        $jdbcConfig = $this->config->get('jdbc', []);
        // Les clés absentes de config.json retombent sur ces valeurs par défaut
        $validationConfig = array_merge([
            'enabled' => true,
            'check_checksum' => false,
            'check_version' => true,
            'warn_on_extra' => true,
            'strict_mode' => false,
        ], $this->config->get('jdbc_validation', []));

        if (!$validationConfig['enabled']) {
            return $this->validationResults;
        }

        // 1. Vérifier que tous les drivers configurés existent
        foreach ($jdbcConfig as $dbType => $dbConfig) {
            $driverName = $dbConfig['driver'] ?? null;
            if ($driverName === null) {
                continue;
            }

            $exists = isset($this->drivers[$driverName]);
            $this->validationResults[$driverName] = [
                'db_type' => $dbType,
                'expected_version' => $dbConfig['version'] ?? 'unknown',
                'exists' => $exists,
                'found' => $exists ? $this->drivers[$driverName] : null,
                'version_match' => false,
                'checksum_match' => false,
            ];

            if (!$exists) {
                $this->logger->warning("❌ Driver manquant: {$driverName} pour {$dbType}");
                $this->logger->warning("   Télécharger depuis: " . ($dbConfig['download_url'] ?? 'URL non spécifiée'));
                continue;
            }

            // Vérifier la version si activé
            if ($validationConfig['check_version']) {
                $this->validationResults[$driverName]['version_match'] = $this->checkVersion($driverName, $dbConfig['version'] ?? 'unknown');
                if (!$this->validationResults[$driverName]['version_match']) {
                    $this->logger->warning("⚠️  Version du driver {$driverName} ne correspond pas à la version configurée");
                }
            }

            // Vérifier le checksum si activé
            if ($validationConfig['check_checksum'] && isset($dbConfig['checksum'])) {
                $this->validationResults[$driverName]['checksum_match'] = $this->checkChecksum($driverName, $dbConfig['checksum']);
                if (!$this->validationResults[$driverName]['checksum_match']) {
                    $this->logger->warning("⚠️  Checksum du driver {$driverName} ne correspond pas");
                }
            }
        }

        // 2. Vérifier les drivers supplémentaires non configurés
        if ($validationConfig['warn_on_extra']) {
            $configuredDrivers = array_column($jdbcConfig, 'driver');
            foreach ($this->drivers as $driverName => $info) {
                if (!in_array($driverName, $configuredDrivers)) {
                    $this->logger->warning("ℹ️  Driver supplémentaire trouvé: {$driverName} (non configuré dans config.json)");
                }
            }
        }

        // 3. Mode strict : erreur si drivers manquants
        if ($validationConfig['strict_mode']) {
            $missing = array_filter($this->validationResults, fn($r) => !$r['exists']);
            if (!empty($missing)) {
                $missingNames = array_keys($missing);
                throw new FileNotFoundException(
                    "Drivers manquants en mode strict: " . implode(', ', $missingNames)
                );
            }
        }

        return $this->validationResults;
    }

    /**
     * [Description for checkVersion]
     *
     * @param string $driverName
     * @param string $expectedVersion
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:44:21 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function checkVersion(string $driverName, string $expectedVersion): bool
    {
        // Extraction de la version depuis le nom du fichier
        // Numéro de version = chiffres séparés par des points (sans point final)
        $versionPatterns = [
            '/postgresql-(\d+(?:\.\d+)*)\.jar/',
            '/mysql-connector-(?:java|j)-(\d+(?:\.\d+)*)\.jar/',
            '/mariadb-java-client-(\d+(?:\.\d+)*)\.jar/',
            '/mssql-jdbc-(\d+(?:\.\d+)*)\.jre\d+\.jar/',
            '/ojdbc\d+-(\d+(?:\.\d+)*)\.jar/',
            '/ojdbc(\d+)\.jar/',
            '/sqljdbc(\d+)\.jar/',
            '/junixsocket-mysql-(\d+(?:\.\d+)*)/',
        ];
        $foundVersion = null;
        foreach ($versionPatterns as $pattern) {
            if (preg_match($pattern, $driverName, $matches)) {
                $foundVersion = $matches[1];
                break;
            }
        }

        if ($foundVersion === null) {
            $this->logger->debug("Impossible d'extraire la version de: {$driverName}");
            return true; // On ne bloque pas si on ne peut pas extraire
        }

        return $foundVersion === $expectedVersion;
    }

    /**
     * [Description for checkChecksum]
     *
     * @param string $driverName
     * @param string $expectedChecksum
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:44:28 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function checkChecksum(string $driverName, string $expectedChecksum): bool
    {
        if (!isset($this->drivers[$driverName])) {
            return false;
        }

        $filePath = $this->drivers[$driverName]['path'];
        if (!file_exists($filePath)) {
            return false;
        }

        // Extraire l'algorithme et la valeur
        if (str_starts_with($expectedChecksum, 'sha256:')) {
            $expected = substr($expectedChecksum, 7);
            $actual = hash_file('sha256', $filePath);
            return $actual === $expected;
        }

        if (str_starts_with($expectedChecksum, 'md5:')) {
            $expected = substr($expectedChecksum, 4);
            $actual = md5_file($filePath);
            return $actual === $expected;
        }

        return false;
    }

    /**
     * [Description for getDriver]
     *
     * @param string $dbType
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:44:31 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDriver(string $dbType): ?string
    {
        $dbConfig = $this->config->getDatabase($dbType);
        if ($dbConfig === null) {
            return null;
        }

        $driverName = $dbConfig['driver'] ?? null;
        if ($driverName === null) {
            return null;
        }

        // Vérifier si le driver existe
        if (!isset($this->drivers[$driverName])) {
            $this->logger->warning("❌ Driver {$driverName} non trouvé pour {$dbType}");
            return null;
        }

        return $driverName;
    }

    /**
     * [Description for getDriverPath]
     *
     * @param string $dbType
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:44:34 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDriverPath(string $dbType): ?string
    {
        $driver = $this->getDriver($dbType);
        if ($driver === null) {
            return null;
        }

        return $this->drivers[$driver]['path'] ?? null;
    }

    /**
     * [Description for getDriverClass]
     *
     * @param string $dbType
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:44:41 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDriverClass(string $dbType): ?string
    {
        $dbConfig = $this->config->getDatabase($dbType);
        return $dbConfig['class'] ?? null;
    }

    /**
     * [Description for getClasspath]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:44:44 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getClasspath(): string
    {
        $jdbcPath = $this->config->getPath('jdbc_folder');

        return $jdbcPath . '/*';
    }

    /**
     * [Description for hasDriver]
     *
     * @param string $dbType
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:44:47 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function hasDriver(string $dbType): bool
    {
        return $this->getDriver($dbType) !== null;
    }

    /**
     * [Description for getAvailableDrivers]
     *
     * @return array
     *
     * Created at: 04/10/2026 22:44:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * [Description for getValidationResults]
     *
     * @return array
     *
     * Created at: 04/10/2026 22:44:53 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

    /**
     * [Description for getValidationSummary]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:44:55 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getValidationSummary(): string
    {
        $summary = [];
        $total = count($this->validationResults);
        $missing = 0;
        $versionMismatch = 0;

        foreach ($this->validationResults as $driver => $result) {
            if (!$result['exists']) {
                $missing++;
                $summary[] = "❌ {$driver}: manquant";
            } elseif (isset($result['version_match']) && !$result['version_match']) {
                $versionMismatch++;
                $summary[] = "⚠️  {$driver}: version incorrecte (attendue: {$result['expected_version']})";
            } else {
                $summary[] = "✅ {$driver}: OK";
            }
        }

        $status = "📊 Validation JDBC: {$total} drivers, {$missing} manquants, {$versionMismatch} versions incorrectes";
        return $status . "\n" . implode("\n", $summary);
    }

    /**
     * [Description for isJdbcDirectoryEmpty]
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:44:59 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isJdbcDirectoryEmpty(): bool
    {
        return empty($this->drivers);
    }

    /**
     * [Description for getMissingDrivers]
     *
     * @return array
     *
     * Created at: 04/10/2026 22:45:01 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getMissingDrivers(): array
    {
        $missing = [];
        $jdbcConfig = $this->config->get('jdbc', []);

        foreach ($jdbcConfig as $dbType => $dbConfig) {
            $driverName = $dbConfig['driver'] ?? null;
            if ($driverName === null) {
                continue;
            }
            if (!isset($this->drivers[$driverName])) {
                $missing[] = [
                    'db_type' => $dbType,
                    'driver' => $driverName,
                    'download_url' => $dbConfig['download_url'] ?? null,
                ];
            }
        }

        return $missing;
    }

    /**
     * [Description for getDriverInfo]
     *
     * @param string $driverName
     *
     * @return array|null
     *
     * Created at: 04/10/2026 22:45:05 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDriverInfo(string $driverName): ?array
    {
        return $this->drivers[$driverName] ?? null;
    }
}
