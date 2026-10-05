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
 * Script de nettoyage des drivers JDBC obsolètes
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
    $obsoletePatterns = $config->get('obsolete_drivers', []);
    $dryRun = in_array('--dry-run', $argv);

    $logger->info("🧹 Nettoyage des drivers JDBC obsolètes", 'cyan');
    $logger->info(str_repeat("═", 50), 'gray');
    $logger->info("📁 Dossier: {$jdbcPath}", 'gray');
    $logger->info("");

    if ($dryRun) {
        $logger->info("⚠️  Mode simulation (--dry-run) - Aucun fichier ne sera supprimé", 'yellow');
        $logger->info("");
    }

    $files = scandir($jdbcPath);
    $deleted = 0;
    $warned = 0;

    foreach ($files as $file) {
        if (!preg_match('/\.jar$/', $file)) {
            continue;
        }

        $filePath = $jdbcPath . '/' . $file;
        $matched = false;

        foreach ($obsoletePatterns as $pattern) {
            if (fnmatch($pattern['pattern'], $file)) {
                $matched = true;

                if ($pattern['action'] === 'delete') {
                    if (!$dryRun) {
                        unlink($filePath);
                        $logger->info("🗑️  Supprimé: {$file}", 'red');
                    } else {
                        $logger->info("📌 Serait supprimé: {$file}", 'yellow');
                    }
                    $deleted++;
                } elseif ($pattern['action'] === 'warn') {
                    $logger->info("⚠️  À vérifier: {$file}", 'yellow');
                    $logger->info("   {$pattern['reason']}", 'gray');
                    $warned++;
                }
                break;
            }
        }

        if (!$matched) {
            // Vérifier si le fichier est dans la configuration
            $jdbcConfig = $config->get('jdbc', []);
            $configured = false;
            foreach ($jdbcConfig as $dbConfig) {
                if (isset($dbConfig['driver']) && $dbConfig['driver'] === $file) {
                    $configured = true;
                    $logger->info("✅ Conservé: {$file} (configuré)", 'green');
                    break;
                }
            }

            if (!$configured) {
                $logger->info("ℹ️  Non configuré: {$file}", 'gray');
            }
        }
    }

    $logger->info("");
    $logger->info(str_repeat("═", 50), 'gray');
    $logger->info("📊 Résumé:", 'white');
    $logger->info("  - Supprimés: {$deleted}", $deleted > 0 ? 'red' : 'green');
    $logger->info("  - À vérifier: {$warned}", $warned > 0 ? 'yellow' : 'green');
    $logger->info("");

    if ($deleted > 0) {
        $logger->info("✅ Nettoyage terminé !", 'green');
    } else {
        $logger->info("ℹ️  Aucun fichier à supprimer", 'gray');
    }

} catch (\Exception $e) {
    $logger->error("Erreur: " . $e->getMessage());
    exit(1);
}
