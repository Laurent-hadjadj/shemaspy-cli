<?php
/**
 * Bootstrap des tests unitaires
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

// Autoloading
require_once __DIR__ . '/../vendor/autoload.php';

// Configuration des tests
define('TEST_ROOT', __DIR__);
define('TEST_FIXTURES', TEST_ROOT . '/Fixtures');

// Création du dossier des fixtures
if (!is_dir(TEST_FIXTURES)) {
    mkdir(TEST_FIXTURES, 0755, true);
}

// Fonction pour créer des fichiers de test
function createTestConfig(array $config): string
{
    $file = TEST_FIXTURES . '/config_' . uniqid() . '.json';
    file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));
    return $file;
}

function cleanupTestFiles(): void
{
    $files = glob(TEST_FIXTURES . '/*.json');
    foreach ($files as $file) {
        unlink($file);
    }
}
