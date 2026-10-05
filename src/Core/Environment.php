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

namespace SchemaSpyCli\Core;

/**
 * [Description Environment]
 * Détection de l'environnement
 */
class Environment
{
    private readonly bool $isWindows;
    private ?string $javaHomeCache = null;

    public function __construct()
    {
        $this->isWindows = PHP_OS_FAMILY === 'Windows';
    }

    /**
     * [Description for getOsFamily]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:33:18 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getOsFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    /**
     * [Description for isWindows]
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:33:20 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isWindows(): bool
    {
        return $this->isWindows;
    }

    /**
     * [Description for getClasspathSeparator]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:33:21 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getClasspathSeparator(): string
    {
        return $this->isWindows() ? ';' : ':';
    }

    /**
     * [Description for getJavaExecutable]
     *
     * @param string $javaHome
     *
     * @return string
     *
     * Created at: 04/10/2026 22:33:23 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getJavaExecutable(string $javaHome): string
    {
        // Normaliser les slashes
        $javaHome = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $javaHome);
        $executable = $this->isWindows()
            ? "{$javaHome}/bin/java.exe"
            : "{$javaHome}/bin/java";
        return str_replace('/', DIRECTORY_SEPARATOR, $executable);
    }

    /**
     * [Description for getDotExecutable]
     *
     * @param string $graphvizDir
     *
     * @return string
     *
     * Created at: 04/10/2026 22:33:26 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDotExecutable(string $graphvizDir): string
    {
        $graphvizDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $graphvizDir);
        $executable = $this->isWindows()
            ? "{$graphvizDir}/bin/dot.exe"
            : "{$graphvizDir}/bin/dot";
        return str_replace('/', DIRECTORY_SEPARATOR, $executable);
    }

    /**
     * [Description for getJavaHome]
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:33:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getJavaHome(): ?string
    {
        // Utiliser le cache si disponible
        if ($this->javaHomeCache !== null) {
            return $this->javaHomeCache;
        }

        // Vérifier JAVA_HOME
        if (getenv('JAVA_HOME')) {
            $this->javaHomeCache = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, getenv('JAVA_HOME'));
            return $this->javaHomeCache;
        }

        // Vérifier dans le PATH
        $command = $this->isWindows() ? 'where java 2>nul' : 'which java 2>/dev/null';
        $output = shell_exec($command);

        if (trim((string) $output) !== '') {
            $javaPath = trim((string) $output);
            // Normaliser les slashes
            $javaPath = str_replace('/', DIRECTORY_SEPARATOR, $javaPath);

            // Remonter d'un niveau pour avoir le dossier JAVA_HOME
            $this->javaHomeCache = dirname(dirname($javaPath));
            return $this->javaHomeCache;
        }

        return null;
    }

    /**
     * [Description for findGraphvizInPath]
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:33:39 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function findGraphvizInPath(): ?string
    {
        $command = $this->isWindows() ? 'where dot 2>nul' : 'which dot 2>/dev/null';
        $output = shell_exec($command);

        if (trim((string) $output) !== '') {
            $dotPath = trim((string) $output);
            return str_replace('/', DIRECTORY_SEPARATOR, $dotPath);
        }

        return null;
    }

    /**
     * [Description for getTimestamp]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:33:41 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getTimestamp(): string
    {
        return date("Ymd_His");
    }

    /**
     * [Description for getDate]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:33:44 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDate(): string
    {
        return date('d/m/Y H:i:s');
    }

    /**
     * [Description for getJavaVersion]
     *
     * @param string|null $javaExe
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:33:48 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getJavaVersion(?string $javaExe = null): ?string
    {
        $output = shell_exec(escapeshellarg($javaExe ?? 'java') . ' -version 2>&1');

        // Format historique JDK 8 et antérieur : version "1.8.0_231" (suffixe de build ignoré).
        // Format JDK 9+ : version "17.0.9" ou "11".
        if (preg_match('/version "(\d+(?:\.\d+)*)(?:_\d+)?"/', (string) $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * [Description for getGraphvizVersion]
     *
     * @param string|null $dotExe
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:34:00 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getGraphvizVersion(?string $dotExe = null): ?string
    {
        // Graphviz écrit sa version sur stderr : "dot - graphviz version 2.38.0 (...)"
        $output = shell_exec(escapeshellarg($dotExe ?? 'dot') . ' -V 2>&1');
        if (preg_match('/graphviz version ([0-9.]+)/i', (string) $output, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * [Description for isJavaVersionCompatible]
     *
     * @param string $minVersion
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:34:06 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isJavaVersionCompatible(string $minVersion = '11.0'): bool
    {
        $version = $this->getJavaVersion();

        if ($version === null) {
            return false;
        }
        return version_compare($version, $minVersion, '>=');
    }

    /**
     * [Description for getPhpVersion]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:34:09 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * [Description for isPhpVersionCompatible]
     *
     * @param string $minVersion
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:34:11 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isPhpVersionCompatible(string $minVersion = '8.1.0'): bool
    {
        return version_compare(PHP_VERSION, $minVersion, '>=');
    }

    /**
     * [Description for getOsFullName]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:34:14 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getOsFullName(): string
    {
        if ($this->isWindows()) {
            return 'Windows ' . php_uname('r');
        }
        return php_uname('s') . ' ' . php_uname('r');
    }

    /**
     * [Description for getTempDirectory]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:34:16 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir();
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tempDir);
    }

    /**
     * [Description for getCurrentDirectory]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:34:18 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getCurrentDirectory(): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, getcwd());
    }

    /**
     * [Description for normalizePath]
     *
     * @param string $path
     *
     * @return string
     *
     * Created at: 04/10/2026 22:34:20 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * [Description for isPathAbsolute]
     *
     * @param string $path
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:34:23 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isPathAbsolute(string $path): bool
    {
        return preg_match('/^[A-Z]:\\\\|^\//', $path) === 1;
    }

    /**
     * [Description for isCommandAvailable]
     *
     * @param string $command
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:34:25 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isCommandAvailable(string $command): bool
    {
        $check = $this->isWindows()
            ? "where {$command} 2>nul"
            : "command -v {$command} 2>/dev/null";
        $output = shell_exec($check);
        return trim((string) $output) !== '';
    }
}
