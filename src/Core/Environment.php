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
        return $this->executablePath($javaHome, $this->isWindows() ? 'java.exe' : 'java');
    }

    /**
     * [Description for executablePath]
     * <dossier>/bin/<exécutable>, séparateurs normalisés, sans séparateur doublé quand le
     * dossier se termine déjà par un « / » ou un « \ » (JAVA_HOME en a souvent un sous Windows).
     *
     * @param string $home
     * @param string $executable
     * 
     * @return string
     * 
     * Created at: 05/10/2026 21:37:18 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com> 
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0. 
     */
    private function executablePath(string $home, string $executable): string
    {
        $home = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $home), DIRECTORY_SEPARATOR);

        return $home . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $executable;
    }

    /**
     * Exécute une commande shell et renvoie sa sortie ('' si elle n'en produit aucune).
     * Point d'extension pour les tests : aucun processus réel n'est nécessaire.
     */
    protected function runCommand(string $command): string
    {
        return (string) shell_exec($command);
    }

    /** Première ligne non vide d'une sortie (« where java » en liste une par installation). */
    private function firstLine(string $output): string
    {
        $lines = preg_split('/\R/', trim($output));

        return trim($lines[0] ?? '');
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
        return $this->executablePath($graphvizDir, $this->isWindows() ? 'dot.exe' : 'dot');
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

        // JAVA_HOME : tolère les guillemets et espaces parasites (fréquents sous Windows)
        $configured = trim((string) getenv('JAVA_HOME'), " \t\"'");
        if ($configured !== '') {
            $this->javaHomeCache = $this->normalizePath($configured);
            return $this->javaHomeCache;
        }

        // Vérifier dans le PATH : seule la première installation trouvée compte
        $javaPath = $this->firstLine($this->runCommand($this->isWindows() ? 'where java 2>nul' : 'which java 2>/dev/null'));
        if ($javaPath !== '') {
            // Remonter de deux niveaux (bin/java -> dossier JAVA_HOME)
            $this->javaHomeCache = dirname(dirname($this->normalizePath($javaPath)));
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
        // « where dot » liste toutes les installations : on ne garde que la première
        $dotPath = $this->firstLine($this->runCommand($this->isWindows() ? 'where dot 2>nul' : 'which dot 2>/dev/null'));

        return $dotPath !== '' ? $this->normalizePath($dotPath) : null;
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
        $output = $this->runCommand(escapeshellarg($javaExe ?? 'java') . ' -version 2>&1');

        // Formats reconnus : « 1.8.0_231 » (JDK 8 et antérieurs, suffixe de build ignoré),
        // « 17.0.9 », « 11 », et les builds à suffixe comme « 22-ea » ou « 21.0.1+12 ».
        if (preg_match('/version "(\d+(?:\.\d+)*)(?:[-+_][^"]*)?"/', $output, $matches)) {
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
        $output = $this->runCommand(escapeshellarg($dotExe ?? 'dot') . ' -V 2>&1');
        if (preg_match('/graphviz version ([0-9.]+)/i', $output, $matches)) {
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
    public function isJavaVersionCompatible(string $minVersion = '11'): bool
    {
        // '11' et non '11.0' : version_compare('11', '11.0', '>=') est faux en PHP, ce qui
        // aurait refusé un JDK qui s'annonce simplement « 11 ».
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
        // « C:\x », « c:/x » (lettre de lecteur, / ou \), « \\serveur\partage » (UNC) et « /x »
        return preg_match('#^(?:[A-Za-z]:[\\\\/]|\\\\\\\\|/)#', $path) === 1;
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
        $name = escapeshellarg($command);
        $check = $this->isWindows()
            ? "where {$name} 2>nul"
            : "command -v {$name} 2>/dev/null";

        return trim($this->runCommand($check)) !== '';
    }
}
