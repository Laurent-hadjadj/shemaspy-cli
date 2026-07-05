<?php
/**
 * Détection de l'environnement
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Core;

final class Environment
{
    private readonly bool $isWindows;
    private ?string $javaHomeCache = null;

    public function __construct()
    {
        $this->isWindows = PHP_OS_FAMILY === 'Windows';
    }

    public function getOsFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    public function isWindows(): bool
    {
        return $this->isWindows;
    }

    public function getClasspathSeparator(): string
    {
        return $this->isWindows() ? ';' : ':';
    }

    public function getJavaExecutable(string $javaHome): string
    {
        // Normaliser les slashes
        $javaHome = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $javaHome);
        $executable = $this->isWindows()
            ? "{$javaHome}/bin/java.exe"
            : "{$javaHome}/bin/java";
        return str_replace('/', DIRECTORY_SEPARATOR, $executable);
    }

    public function getDotExecutable(string $graphvizDir): string
    {
        $graphvizDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $graphvizDir);
        $executable = $this->isWindows()
            ? "{$graphvizDir}/bin/dot.exe"
            : "{$graphvizDir}/bin/dot";
        return str_replace('/', DIRECTORY_SEPARATOR, $executable);
    }

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

        if (!empty(trim($output))) {
            $javaPath = trim($output);
            // Normaliser les slashes
            $javaPath = str_replace('/', DIRECTORY_SEPARATOR, $javaPath);

            // Remonter d'un niveau pour avoir le dossier JAVA_HOME
            $this->javaHomeCache = dirname(dirname($javaPath));
            return $this->javaHomeCache;
        }

        return null;
    }

    public function findGraphvizInPath(): ?string
    {
        $command = $this->isWindows() ? 'where dot 2>nul' : 'which dot 2>/dev/null';
        $output = shell_exec($command);

        if (!empty(trim($output))) {
            $dotPath = trim($output);
            return str_replace('/', DIRECTORY_SEPARATOR, $dotPath);
        }

        return null;
    }

    public function getTimestamp(): string
    {
        return date("Ymd_His");
    }

    public function getDate(): string
    {
        return date('d/m/Y H:i:s');
    }

    // Méthodes supplémentaires utiles

    public function getJavaVersion(?string $javaExe = null): ?string
    {
        $output = shell_exec(escapeshellarg($javaExe ?? 'java') . ' -version 2>&1');

        // Format historique JDK 8 et antérieur : version "1.8.0_231" (suffixe de build ignoré).
        // Format JDK 9+ : version "17.0.9" ou "11".
        if (preg_match('/version "([0-9]+(?:\.[0-9]+)*)(?:_[0-9]+)?"/', (string) $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getGraphvizVersion(?string $dotExe = null): ?string
    {
        // Graphviz écrit sa version sur stderr : "dot - graphviz version 2.38.0 (...)"
        $output = shell_exec(escapeshellarg($dotExe ?? 'dot') . ' -V 2>&1');
        if (preg_match('/graphviz version ([0-9.]+)/i', (string) $output, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function isJavaVersionCompatible(string $minVersion = '11.0'): bool
    {
        $version = $this->getJavaVersion();

        if ($version === null) {
            return false;
        }
        return version_compare($version, $minVersion, '>=');
    }

    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    public function isPhpVersionCompatible(string $minVersion = '8.1.0'): bool
    {
        return version_compare(PHP_VERSION, $minVersion, '>=');
    }

    public function getOsFullName(): string
    {
        if ($this->isWindows()) {
            return 'Windows ' . php_uname('r');
        }
        return php_uname('s') . ' ' . php_uname('r');
    }

    public function getTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir();
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tempDir);
    }

    public function getCurrentDirectory(): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, getcwd());
    }

    public function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    public function isPathAbsolute(string $path): bool
    {
        return preg_match('/^[A-Z]:\\\\|^\//', $path) === 1;
    }

    public function isCommandAvailable(string $command): bool
    {
        $check = $this->isWindows()
            ? "where {$command} 2>nul"
            : "command -v {$command} 2>/dev/null";
        $output = shell_exec($check);
        return !empty(trim($output));
    }
}
