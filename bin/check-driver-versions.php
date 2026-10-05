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
 * Script de vérification des versions des drivers
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;

$logger = new Logger();
$config = new Config();

try {
    $configFile = $argv[1] ?? 'config/config.json';
    $config->load($configFile);

    $jdbcPath = $config->getPath('jdbc_folder');
    $jdbcConfig = $config->get('jdbc', []);

    $logger->info("📊 Vérification des versions des drivers", 'cyan');
    $logger->info(str_repeat("═", 50), 'gray');
    $logger->info("");

    // Créer un tableau des drivers configurés par nom
    $configuredDrivers = [];
    foreach ($jdbcConfig as $dbType => $dbConfig) {
        if (isset($dbConfig['driver'])) {
            $configuredDrivers[$dbConfig['driver']] = [
                'dbType' => $dbType,
                'config' => $dbConfig
            ];
        }
    }

    // Lire les fichiers dans le dossier JDBC
    $files = scandir($jdbcPath);
    $foundDrivers = [];

    foreach ($files as $file) {
        if (!preg_match('/\.jar$/', $file)) {
            continue;
        }

        $filePath = $jdbcPath . '/' . $file;
        $size = filesize($filePath);
        $sizeFormatted = round($size / 1024, 2) . ' KB';
        $mtime = date('Y-m-d H:i:s', filemtime($filePath));

        $foundDrivers[$file] = [
            'path' => $filePath,
            'size' => $sizeFormatted,
            'mtime' => $mtime,
            'configured' => isset($configuredDrivers[$file])
        ];
    }

    // Afficher le rapport
    $logger->info("📦 Fichiers trouvés:", 'white');
    foreach ($foundDrivers as $file => $info) {
        if ($info['configured']) {
            $dbType = $configuredDrivers[$file]['dbType'];
            $version = $configuredDrivers[$file]['config']['version'] ?? 'N/A';
            $javaVersion = $configuredDrivers[$file]['config']['java_version'] ?? 'N/A';
            $dbVersion = $configuredDrivers[$file]['config']['db_version'] ?? 'N/A';

            $logger->info("  ✅ {$file}", 'green');
            $logger->info("     Type: {$dbType}", 'gray');
            $logger->info("     Version: {$version}", 'gray');
            $logger->info("     Java: {$javaVersion}", 'gray');
            $logger->info("     SGBD: {$dbVersion}", 'gray');
            $logger->info("     Taille: {$info['size']}", 'gray');
            $logger->info("     Modifié: {$info['mtime']}", 'gray');
        } else {
            // Vérifier si c'est un driver obsolète
            $obsoletePatterns = $config->get('obsolete_drivers', []);
            $isObsolete = false;
            $obsoleteInfo = null;

            foreach ($obsoletePatterns as $pattern) {
                if (fnmatch($pattern['pattern'], $file)) {
                    $isObsolete = true;
                    $obsoleteInfo = $pattern;
                    break;
                }
            }

            if ($isObsolete) {
                $logger->info("  🗑️  {$file} - OBSOLÈTE", 'red');
                $logger->info("     {$obsoleteInfo['reason']}", 'gray');
                $logger->info("     Action: {$obsoleteInfo['action']}", 'yellow');
            } else {
                $logger->info("  ℹ️  {$file} - Non configuré", 'gray');
                $logger->info("     Taille: {$info['size']}", 'gray');
                $logger->info("     Modifié: {$info['mtime']}", 'gray');
            }
        }
        $logger->info("");
    }

    // Résumé
    $total = count($foundDrivers);
    $configured = count(array_filter($foundDrivers, fn($i) => $i['configured']));
    $obsolete = 0;
    $unconfigured = 0;

    foreach ($foundDrivers as $file => $info) {
        if (!$info['configured']) {
            $isObsolete = false;
            foreach ($obsoletePatterns as $pattern) {
                if (fnmatch($pattern['pattern'], $file)) {
                    $isObsolete = true;
                    break;
                }
            }
            if ($isObsolete) {
                $obsolete++;
            } else {
                $unconfigured++;
            }
        }
    }

    $logger->info(str_repeat("═", 50), 'gray');
    $logger->info("📊 Résumé:", 'white');
    $logger->info("  - Total drivers: {$total}", 'default');
    $logger->info("  - Configurés: {$configured}", 'green');
    $logger->info("  - Obsolètes: {$obsolete}", 'red');
    $logger->info("  - Non configurés: {$unconfigured}", 'yellow');

    if ($obsolete > 0) {
        $logger->info("");
        $logger->info("💡 Pour supprimer les drivers obsolètes:", 'cyan');
        $logger->info("   php bin/cleanup-drivers.php", 'gray');
        $logger->info("   php bin/cleanup-drivers.php --dry-run  # Simulation", 'gray');
    }

} catch (\Exception $e) {
    $logger->error("Erreur: " . $e->getMessage());
    exit(1);
}
