<?php
/**
 * Gestion des drivers JDBC
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Database;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Exceptions\FileNotFoundException;

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

    public function validateDrivers(): array
    {
        $this->validationResults = [];
        $jdbcConfig = $this->config->get('jdbc', []);
        $validationConfig = $this->config->get('jdbc_validation', [
            'enabled' => true,
            'check_checksum' => false,
            'check_version' => true,
            'warn_on_extra' => true,
            'strict_mode' => false,
        ]);

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

    private function checkVersion(string $driverName, string $expectedVersion): bool
    {
        // Extraction de la version depuis le nom du fichier
        $versionPatterns = [
            '/postgresql-([0-9.]+)\.jar/',
            '/mysql-connector-java-([0-9.]+)/',
            '/mariadb-java-client-([0-9.]+)/',
            '/ojdbc([0-9]+)\.jar/',
            '/sqljdbc([0-9]+)\.jar/',
            '/junixsocket-mysql-([0-9.]+)/',
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

    public function getDriverPath(string $dbType): ?string
    {
        $driver = $this->getDriver($dbType);
        if ($driver === null) {
            return null;
        }

        return $this->drivers[$driver]['path'] ?? null;
    }

    public function getDriverClass(string $dbType): ?string
    {
        $dbConfig = $this->config->getDatabase($dbType);
        return $dbConfig['class'] ?? null;
    }

    public function getClasspath(): string
    {
        $jdbcPath = $this->config->getPath('jdbc_folder');

        return $jdbcPath . '/*';
    }

    public function hasDriver(string $dbType): bool
    {
        return $this->getDriver($dbType) !== null;
    }

    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }

    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

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

    public function isJdbcDirectoryEmpty(): bool
    {
        return empty($this->drivers);
    }

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

    public function getDriverInfo(string $driverName): ?array
    {
        return $this->drivers[$driverName] ?? null;
    }
}
