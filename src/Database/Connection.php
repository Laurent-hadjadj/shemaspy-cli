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

use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Exceptions\ConnectionException;

/**
 * [Description Connection]
 * Gestion des connexions aux bases de données
 *
 * Le test de connexion PHP est un confort (échec rapide et lisible). SchemaSpy
 * se connecte lui-même via JDBC : si l'extension PDO du SGBD n'est pas
 * installée côté PHP (cas courant d'Oracle : pdo_oci), on se rabat sur un
 * simple test d'accessibilité TCP au lieu de bloquer l'exécution.
 */
final class Connection
{
    public const MODE_PDO = 'pdo';
    public const MODE_TCP = 'tcp';

    private ?\PDO $pdo = null;
    private string $mode = self::MODE_PDO;

    public function __construct(
        private readonly array $params,
        private readonly Logger $logger
    ) {
    }

    /**
     * [Description for getPdoDriverName]
     * Extension PDO nécessaire pour le type de base (pgsql, oci, mysql, sqlsrv).
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:42:20 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getPdoDriverName(): ?string
    {
        $dbType = (string) $this->params['dbType'];

        return match (true) {
            $dbType === 'postgresql'            => 'pgsql',
            str_starts_with($dbType, 'oracle')   => 'oci',
            in_array($dbType, ['mysql', 'mariadb'], true) => 'mysql',
            str_starts_with($dbType, 'sqlserver') => 'sqlsrv',
            default                             => null,
        };
    }

    /**
     * [Description for isPdoAvailable]
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:42:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isPdoAvailable(): bool
    {
        $driver = $this->getPdoDriverName();
        return $driver !== null && extension_loaded('pdo_' . $driver);
    }

    /**
     * [Description for getMode]
     * Mode utilisé par le dernier test() : 'pdo' (requête réelle) ou 'tcp' (accessibilité seule).
     *
     * @return string
     *
     * Created at: 04/10/2026 22:42:35 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @throws ConnectionException si la base n'est pas joignable / authentification refusée
     */
    public function test(): bool
    {
        if (!$this->isPdoAvailable()) {
            $this->mode = self::MODE_TCP;
            $this->logger->debug(
                "Extension pdo_" . ($this->getPdoDriverName() ?? '?') . " absente : test TCP uniquement."
            );
            return $this->testTcp();
        }

        $this->mode = self::MODE_PDO;
        try {
            $timeout = (int) ($this->params['timeout'] ?? 5);

            $this->pdo = new \PDO($this->buildDSN(), $this->params['user'], $this->params['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => $timeout,
            ]);

            $this->pdo->query($this->testQuery());

            return true;
        } catch (\PDOException $e) {
            // Message brut du driver PDO : laissé tel quel, c'est à l'appelant
            // (Runner::checkConnection) de décider comment le présenter à l'utilisateur.
            throw new ConnectionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * [Description for testTcp]
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:42:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function testTcp(): bool
    {
        $timeout = (float) ($this->params['timeout'] ?? 5);
        $socket = @fsockopen((string) $this->params['host'], (int) $this->params['port'], $errno, $errstr, $timeout);
        if ($socket === false) {
            throw new ConnectionException("{$this->params['host']}:{$this->params['port']} injoignable ({$errstr})", (int) $errno);
        }
        fclose($socket);
        return true;
    }

    /**
     * [Description for testQuery]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:43:01 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function testQuery(): string
    {
        // Oracle n'accepte pas un SELECT sans FROM
        return str_starts_with((string) $this->params['dbType'], 'oracle') ? 'SELECT 1 FROM DUAL' : 'SELECT 1';
    }

    /**
     * [Description for buildDSN]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:43:04 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function buildDSN(): string
    {
        $host = $this->params['host'];
        $port = $this->params['port'];
        $db = $this->params['database'];

        return match($this->getPdoDriverName()) {
            'pgsql'  => "pgsql:host={$host};port={$port};dbname={$db}",
            'oci'    => "oci:dbname=//{$host}:{$port}/{$db};charset=AL32UTF8",
            'mysql'  => "mysql:host={$host};port={$port};dbname={$db}",
            'sqlsrv' => "sqlsrv:Server={$host},{$port};Database={$db};TrustServerCertificate=1",
            default  => throw new ConnectionException("Type de base de données non supporté: {$this->params['dbType']}"),
        };
    }

    /**
     * [Description for getPdo]
     *
     * @return \PDO|null
     *
     * Created at: 04/10/2026 22:43:07 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }

    /**
     * [Description for getDriverInfo]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:43:09 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDriverInfo(): string
    {
        if ($this->pdo === null) {
            return 'Non connecté';
        }
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * [Description for getServerInfo]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:43:12 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getServerInfo(): string
    {
        if ($this->pdo === null) {
            return 'Non connecté';
        }
        return $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }
}
