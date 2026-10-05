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

namespace SchemaSpyCli\SchemaSpy;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Database\DriverManager;

/**
 * [Description CommandBuilder]
 * Construction des commandes SchemaSpy
 */
final class CommandBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly Environment $environment,
        private readonly Logger $logger,
        private readonly DriverManager $driverManager
    ) {
    }

    /**
     * [Description for build]
     *
     * @param string $javaExe
     * @param string $jarFile
     * @param string $propertiesFile
     * @param array $params
     *
     * @return string
     *
     * Created at: 05/10/2026 08:59:16 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
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
