#!/usr/bin/env php
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

/**
 * Script de vérification des drivers JDBC
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Database\DriverManager;

$logger = new Logger();
$config = new Config();

try {
    $configFile = $argv[1] ?? 'config/config.json';
    $config->load($configFile);

    $driverManager = new DriverManager($config, $logger);

    $logger->info("📦 Vérification des drivers JDBC", 'cyan');
    $logger->info(str_repeat("═", 50), 'gray');

    if ($driverManager->isJdbcDirectoryEmpty()) {
        $logger->warning("❌ Le dossier JDBC est vide !");
        $logger->info("📁 Chemin: " . $config->getPath('jdbc_folder'), 'gray');
        exit(1);
    }

    $logger->info("📁 Dossier JDBC: " . $config->getPath('jdbc_folder'), 'gray');
    $logger->info("");

    // Afficher le résumé
    $summary = $driverManager->getValidationSummary();
    $logger->info($summary, 'default');

    // Afficher les détails
    $results = $driverManager->getValidationResults();
    foreach ($results as $driver => $result) {
        if ($result['exists']) {
            $size = $result['found']['size'] ?? 0;
            $sizeFormatted = $size > 0 ? round($size / 1024, 2) . ' KB' : 'N/A';
            $status = $result['version_match'] ? '✅' : '⚠️';
            $logger->info("  {$status} {$driver} ({$sizeFormatted})",
                $result['version_match'] ? 'green' : 'yellow');
        } else {
            $logger->info("  ❌ {$driver} (MANQUANT)", 'red');
        }
    }

    // Afficher les drivers supplémentaires
    $available = $driverManager->getAvailableDrivers();
    $configured = array_keys($results);
    $extra = array_diff($available, $configured);
    if (!empty($extra)) {
        $logger->info("");
        $logger->info("📌 Drivers supplémentaires non configurés:", 'yellow');
        foreach ($extra as $driver) {
            $logger->info("  - {$driver}", 'gray');
        }
    }

} catch (\Exception $e) {
    $logger->error("Erreur: " . $e->getMessage());
    exit(1);
}
