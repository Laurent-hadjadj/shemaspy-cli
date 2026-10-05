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
use SchemaSpyCli\Exceptions\ConnectionException;

/**
 * [Description DSNBuilder]
 * Construction des DSN pour les connexions aux bases de données
 *
 */
final class DSNBuilder
{
    private array $dsnTemplates = [];

    public function __construct(private readonly Config $config)
    {
        $this->initializeTemplates();
    }

    /**
     * [Description for initializeTemplates]
     *
     * @return void
     *
     * Created at: 05/10/2026 08:55:03 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function initializeTemplates(): void
    {
        $this->dsnTemplates = [
            'postgresql' => [
                'template' => 'pgsql:host={host};port={port};dbname={database}',
                'driver' => 'pdo_pgsql',
                'default_port' => 5432,
                'test_query' => 'SELECT 1',
            ],
            'oracle' => [
                'template' => 'oci:dbname=//{host}:{port}/{database}',
                'driver' => 'pdo_oci',
                'default_port' => 1521,
                'test_query' => 'SELECT 1 FROM DUAL',
            ],
            'mysql' => [
                'template' => 'mysql:host={host};port={port};dbname={database}',
                'driver' => 'pdo_mysql',
                'default_port' => 3306,
                'test_query' => 'SELECT 1',
            ],
            'mariadb' => [
                'template' => 'mysql:host={host};port={port};dbname={database}',
                'driver' => 'pdo_mysql',
                'default_port' => 3306,
                'test_query' => 'SELECT 1',
            ],
            'sqlsrv' => [
                'template' => 'sqlsrv:Server={host},{port};Database={database}',
                'driver' => 'pdo_sqlsrv',
                'default_port' => 1433,
                'test_query' => 'SELECT 1',
            ],
            'sqlserver' => [
                'template' => 'sqlsrv:Server={host},{port};Database={database}',
                'driver' => 'pdo_sqlsrv',
                'default_port' => 1433,
                'test_query' => 'SELECT 1',
            ],
        ];
    }

    /**
     * [Description for build]
     *
     * @param array $params
     *
     * @return string
     *
     * Created at: 05/10/2026 08:55:12 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function build(array $params): string
    {
        $dbType = $params['dbType'] ?? 'postgresql';

        if (!isset($this->dsnTemplates[$dbType])) {
            throw new ConnectionException("Type de base de données non supporté: {$dbType}");
        }

        $template = $this->dsnTemplates[$dbType]['template'];

        // Remplacer les placeholders
        $dsn = str_replace(
            ['{host}', '{port}', '{database}'],
            [
                $params['host'],
                $params['port'] ?? $this->dsnTemplates[$dbType]['default_port'],
                $params['database'],
            ],
            $template
        );

        // Ajouter les propriétés de connexion supplémentaires.
        // On assemble manuellement (sans http_build_query) pour éviter l'URL-encodage
        // de caractères légitimes comme le '/' de "Europe/Paris".
        $connprops = $this->config->get('connprops', []);
        if (!empty($connprops)) {
            $pairs = [];
            foreach ($connprops as $key => $value) {
                $pairs[] = $key . '=' . $value;
            }
            $dsn .= ';' . implode(';', $pairs);
        }

        return $dsn;
    }

    /**
     * [Description for getDriverName]
     *
     * @param string $dbType
     *
     * @return string|null
     *
     * Created at: 05/10/2026 08:55:18 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDriverName(string $dbType): ?string
    {
        return $this->dsnTemplates[$dbType]['driver'] ?? null;
    }

    /**
     * [Description for getDefaultPort]
     *
     * @param string $dbType
     *
     * @return int|null
     *
     * Created at: 05/10/2026 08:55:21 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDefaultPort(string $dbType): ?int
    {
        return $this->dsnTemplates[$dbType]['default_port'] ?? null;
    }

    /**
     * [Description for getTestQuery]
     *
     * @param string $dbType
     *
     * @return string|null
     *
     * Created at: 05/10/2026 08:55:25 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getTestQuery(string $dbType): ?string
    {
        return $this->dsnTemplates[$dbType]['test_query'] ?? null;
    }

    /**
     * [Description for supportsType]
     *
     * @param string $dbType
     *
     * @return bool
     *
     * Created at: 05/10/2026 08:55:27 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function supportsType(string $dbType): bool
    {
        return isset($this->dsnTemplates[$dbType]);
    }

    /**
     * [Description for getAllSupportedTypes]
     *
     * @return array
     *
     * Created at: 05/10/2026 08:55:30 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getAllSupportedTypes(): array
    {
        return array_keys($this->dsnTemplates);
    }

    /**
     * [Description for validateDSN]
     *
     * @param string $dsn
     * @param string $dbType
     *
     * @return bool
     *
     * Created at: 05/10/2026 08:55:32 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateDSN(string $dsn, string $dbType): bool
    {
        // Validation basique : vérifier que le DSN contient les éléments essentiels
        $requiredParts = ['host', 'port', 'dbname'];

        foreach ($requiredParts as $part) {
            if (!str_contains($dsn, $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * [Description for getConnectionOptions]
     *
     * @param array $params
     *
     * @return array
     *
     * Created at: 05/10/2026 08:55:35 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getConnectionOptions(array $params): array
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => $params['timeout'] ?? 5,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        // Options spécifiques par type de base de données.
        // On protège les constantes spécifiques à pdo_mysql qui peuvent être absentes
        // si l'extension n'est pas chargée dans l'environnement courant.
        $dbType = $params['dbType'] ?? 'postgresql';

        switch ($dbType) {
            case 'postgresql':
                $options[\PDO::ATTR_AUTOCOMMIT] = true;
                break;
            case 'mysql':
            case 'mariadb':
                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'UTF8'";
                }
                if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                    $options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                }
                break;
            case 'oracle':
                $options[\PDO::ATTR_AUTOCOMMIT] = true;
                break;
        }

        return $options;
    }

    /**
     * [Description for parseDSN]
     *
     * @param string $dsn
     *
     * @return array
     *
     * Created at: 05/10/2026 08:55:39 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function parseDSN(string $dsn): array
    {
        $parts = [];

        // Extraire le driver
        if (preg_match('/^([a-z]+):/', $dsn, $matches)) {
            $parts['driver'] = $matches[1];
        }

        // Extraire les paramètres
        $params = explode(';', substr($dsn, strpos($dsn, ':') + 1));

        foreach ($params as $param) {
            if (str_contains($param, '=')) {
                [$key, $value] = explode('=', $param, 2);
                $parts[$key] = $value;
            }
        }

        return $parts;
    }

    /**
     * [Description for getExampleDSN]
     *
     * @param string $dbType
     *
     * @return string
     *
     * Created at: 05/10/2026 08:55:42 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getExampleDSN(string $dbType): string
    {
        $examples = [
            'postgresql' => 'pgsql:host=localhost;port=5432;dbname=mydb',
            'oracle' => 'oci:dbname=//localhost:1521/XE',
            'mysql' => 'mysql:host=localhost;port=3306;dbname=mydb',
            'sqlsrv' => 'sqlsrv:Server=localhost,1433;Database=mydb',
        ];

        return $examples[$dbType] ?? 'pgsql:host=localhost;port=5432;dbname=mydb';
    }
}
