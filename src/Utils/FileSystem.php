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

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Exceptions\FileNotFoundException;

/**
 * [Description FileSystem]
 * Opérations sur le système de fichiers
 */
final class FileSystem
{
    /**
     * [Description for ensureDirectory]
     *
     * @param string $path
     *
     * @return void
     *
     * Created at: 05/10/2026 09:01:26 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function ensureDirectory(string $path): void
    {
        // @ : l'échec est signalé par l'exception ci-dessous, pas par un avertissement PHP ;
        // is_dir() après coup tolère un dossier créé entre-temps par un autre processus.
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new FileNotFoundException("Impossible de créer le dossier: {$path}");
        }
    }

    /**
     * [Description for removeDirectory]
     *
     * @param string $path
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:01:29 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            if (is_link($filePath)) {
                // Ne jamais suivre un lien : on supprimerait le contenu de sa cible.
                // Sous Windows un lien vers un dossier se supprime avec rmdir().
                is_dir($filePath) && PHP_OS_FAMILY === 'Windows' ? rmdir($filePath) : unlink($filePath);
            } elseif (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                // Sous Windows, unlink() refuse une jonction de dossier (« Is a directory ») que PHP ne
                // reconnaît ni comme lien ni comme dossier : rmdir() la supprime sans toucher à sa cible.
                @unlink($filePath) || rmdir($filePath);
            }
        }

        return rmdir($path);
    }

    /**
     * [Description for copyDirectory]
     *
     * @param string $source
     * @param string $destination
     *
     * @return void
     *
     * Created at: 05/10/2026 09:01:32 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new FileNotFoundException("Dossier source introuvable: {$source}");
        }

        $this->ensureDirectory($destination);

        // Destination située dans la source (ex: copier a/ vers a/sauvegarde) : on l'ignore pendant
        // le parcours, sinon la copie se rappelle indéfiniment sur ses propres fichiers.
        $this->copyTree($source, $destination, (string) realpath($destination));
    }

    private function copyTree(string $source, string $destination, string $skip): void
    {
        $this->ensureDirectory($destination);

        $files = array_diff(scandir($source), ['.', '..']);
        foreach ($files as $file) {
            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                if ($skip !== '' && realpath($sourcePath) === $skip) {
                    continue;
                }
                $this->copyTree($sourcePath, $destPath, $skip);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }

    /**
     * [Description for getFileSize]
     *
     * @param string $file
     *
     * @return string
     *
     * Created at: 05/10/2026 09:01:42 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileSize(string $file): string
    {
        if (!is_file($file)) {
            return '0 B';
        }

        $size = filesize($file);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * [Description for getFileHash]
     *
     * @param string $file
     *
     * @return string|null
     *
     * Created at: 05/10/2026 09:01:45 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileHash(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $hash = hash_file('sha256', $file);
        return $hash === false ? null : $hash;
    }

    /**
     * [Description for getFileExtension]
     *
     * @param string $file
     *
     * @return string
     *
     * Created at: 05/10/2026 09:01:48 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileExtension(string $file): string
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * [Description for getFileName]
     *
     * @param string $file
     *
     * @return string
     *
     * Created at: 05/10/2026 09:01:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileName(string $file): string
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }

    /**
     * [Description for getFileBasename]
     *
     * @param string $file
     *
     * @return string
     *
     * Created at: 05/10/2026 09:01:55 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileBasename(string $file): string
    {
        return pathinfo($file, PATHINFO_BASENAME);
    }

    /**
     * [Description for getFileDirname]
     *
     * @param string $file
     *
     * @return string
     *
     * Created at: 05/10/2026 09:01:58 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileDirname(string $file): string
    {
        return pathinfo($file, PATHINFO_DIRNAME);
    }

    /**
     * [Description for isJarFile]
     *
     * @param string $file
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:02:00 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isJarFile(string $file): bool
    {
        return strtolower($this->getFileExtension($file)) === 'jar';
    }

    /**
     * [Description for isPropertiesFile]
     *
     * @param string $file
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:02:03 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isPropertiesFile(string $file): bool
    {
        return strtolower($this->getFileExtension($file)) === 'properties';
    }

    /**
     * [Description for isDirectory]
     *
     * @param string $path
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:02:06 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * [Description for isFile]
     *
     * @param string $path
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:02:09 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * [Description for fileExists]
     *
     * @param string $path
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:02:12 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * [Description for getFileMtime]
     *
     * @param string $file
     *
     * @return int|null
     *
     * Created at: 05/10/2026 09:02:14 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileMtime(string $file): ?int
    {
        if (!file_exists($file)) {
            return null;
        }
        return filemtime($file);
    }

    /**
     * [Description for getFileCtime]
     *
     * @param string $file
     *
     * @return int|null
     *
     * Created at: 05/10/2026 09:02:17 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFileCtime(string $file): ?int
    {
        if (!file_exists($file)) {
            return null;
        }
        return filectime($file);
    }

    /**
     * [Description for getFilePermissions]
     *
     * @param string $file
     *
     * @return string|null
     *
     * Created at: 05/10/2026 09:02:20 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getFilePermissions(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        return substr(sprintf('%o', fileperms($file)), -4);
    }
}
