<?php
/**
 * SchemaSpy CLI - Bootstrap
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
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
    die("❌ Autoloader introuvable. Exécutez 'composer install'.\n");
}

use SchemaSpyCli\Cli\Application;

// Exécution de l'application
exit((new Application())->run());
