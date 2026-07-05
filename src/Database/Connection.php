<?php
/**
 * Gestion des connexions aux bases de données
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Database;

use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Exceptions\ConnectionException;

final class Connection
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly array $params,
        private readonly Logger $logger
    ) {
    }

    public function test(): bool
    {
        try {
            $dsn = $this->buildDSN();
            $timeout = $this->params['timeout'] ?? 5;

            $this->pdo = new \PDO($dsn, $this->params['user'], $this->params['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => $timeout,
            ]);

            // Tester une requête simple
            $this->pdo->query('SELECT 1');

            return true;
        } catch (\PDOException $e) {
            // Message brut du driver PDO : laissé tel quel, c'est à l'appelant
            // (Runner::checkConnection) de décider comment le présenter à l'utilisateur.
            throw new ConnectionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function buildDSN(): string
    {
        $host = $this->params['host'];
        $port = $this->params['port'];
        $db = $this->params['database'];

        return match($this->params['dbType']) {
            'postgresql' => "pgsql:host={$host};port={$port};dbname={$db}",
            'oracle'     => "oci:dbname=//{$host}:{$port}/{$db}",
            'mysql'      => "mysql:host={$host};port={$port};dbname={$db}",
            default      => throw new ConnectionException("Type de base de données non supporté: {$this->params['dbType']}"),
        };
    }

    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }

    public function getDriverInfo(): string
    {
        if ($this->pdo === null) {
            return 'Non connecté';
        }
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function getServerInfo(): string
    {
        if ($this->pdo === null) {
            return 'Non connecté';
        }
        return $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }
}
