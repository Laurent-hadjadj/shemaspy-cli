<?php
/**
 * Opérations sur le système de fichiers
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Exceptions\FileNotFoundException;

final class FileSystem
{
    public function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new FileNotFoundException("Impossible de créer le dossier: {$path}");
            }
        }
    }

    public function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        return rmdir($path);
    }

    public function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new FileNotFoundException("Dossier source introuvable: {$source}");
        }

        $this->ensureDirectory($destination);

        $files = array_diff(scandir($source), ['.', '..']);
        foreach ($files as $file) {
            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }

    public function getFileSize(string $file): string
    {
        if (!file_exists($file)) {
            return '0 B';
        }

        $size = filesize($file);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    public function getFileHash(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        return hash_file('sha256', $file);
    }

    public function getFileExtension(string $file): string
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    public function getFileName(string $file): string
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }

    public function getFileBasename(string $file): string
    {
        return pathinfo($file, PATHINFO_BASENAME);
    }

    public function getFileDirname(string $file): string
    {
        return pathinfo($file, PATHINFO_DIRNAME);
    }

    public function isJarFile(string $file): bool
    {
        return $this->getFileExtension($file) === 'jar';
    }

    public function isPropertiesFile(string $file): bool
    {
        return $this->getFileExtension($file) === 'properties';
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function getFileMtime(string $file): ?int
    {
        if (!file_exists($file)) {
            return null;
        }
        return filemtime($file);
    }

    public function getFileCtime(string $file): ?int
    {
        if (!file_exists($file)) {
            return null;
        }
        return filectime($file);
    }

    public function getFilePermissions(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        return substr(sprintf('%o', fileperms($file)), -4);
    }
}
