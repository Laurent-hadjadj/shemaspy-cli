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

namespace SchemaSpyCli\Tests\Support;

/**
 * [Description TempDir]
 * Dossier temporaire jetable pour les tests.
 */
final class TempDir
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = str_replace('\\', '/', sys_get_temp_dir()) . '/schemaspy_cli_test_' . uniqid('', true);
        mkdir($this->path, 0777, true);
    }

    /** Crée un fichier (et ses dossiers parents) et retourne son chemin. */
    public function file(string $relative, string $content = ''): string
    {
        $file = $this->path . '/' . $relative;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
        return $file;
    }

    public function dir(string $relative): string
    {
        $dir = $this->path . '/' . $relative;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public function remove(): void
    {
        self::rm($this->path);
    }

    private static function rm(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::rm($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }
}
