<?php

/**
 *  Ma-Moulinette - ShemaSpy-Cli
 *  --------------
 *  Copyright (c) 2015-2026.
 *  Laurent HADJADJ <laurent_h@me.com>.
 *  Licensed GNU GPL 3.0 ou ultérieure (GPL-3.0-or-later).
 *  ---
 *  Vous pouvez obtenir une copie de la licence à l'adresse suivante :
 *  https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * SchemaSpy CLI - Bootstrap
 */

declare(strict_types=1);

// Autoloading avec Composer
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

$found = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $found = true;
        break;
    }
}

if (!$found) {
    // Sortie d'erreur + code non nul : die("texte") se terminait avec le code 0, qu'un script ou
    // un pipeline prenait pour un succès
    fwrite(STDERR, "❌ Autoloader introuvable. Exécutez 'composer install'.\n");
    exit(1);
}

use SchemaSpyCli\Cli\Application;

// Exécution de l'application
exit((new Application())->run());
