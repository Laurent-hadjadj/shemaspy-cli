<?php
/**
 * Construction des commandes SchemaSpy
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\SchemaSpy;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Database\DriverManager;

final class CommandBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly Environment $environment,
        private readonly Logger $logger,
        private readonly DriverManager $driverManager
    ) {
    }

    public function build(string $javaExe, string $jarFile, string $propertiesFile, array $params): string
    {
        // ✅ Utiliser -jar (Spring Boot JAR)
        $command = sprintf(
            '%s -jar %s -configFile %s',
            escapeshellarg($javaExe),
            escapeshellarg($jarFile),
            escapeshellarg($propertiesFile)
        );

        // Ajouter le driver JDBC avec -dp
        $driverPath = $this->driverManager->getDriverPath($params['dbType']);
        if ($driverPath !== null) {
            // Normaliser les slashes pour Windows
            $driverPath = str_replace('/', DIRECTORY_SEPARATOR, $driverPath);

            // Vérifier que le fichier existe
            if (!file_exists($driverPath)) {
                $this->logger->warning("⚠️ Driver non trouvé: {$driverPath}");
            } else {
                if ($this->logger->isVerbose()) {
                    $this->logger->debug("🔗 Driver JDBC: " . basename($driverPath));
                    $this->logger->debug("📦 Chemin complet: {$driverPath}");
                }
                $command .= ' -dp ' . escapeshellarg($driverPath);
            }
        }

        // Ajouter le debug si en mode verbose
        if ($this->logger->isVerbose()) {
            $command .= ' -debug';
        }

        return $command;
    }
}
