<?php
/**
 * Recherche de chemins et de fichiers
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Core\Logger;

final class PathFinder
{
    private readonly Environment $environment;

    public function __construct(
        private readonly Config $config,
        private readonly ?Logger $logger = null,
        ?Environment $environment = null
    ) {
        $this->environment = $environment ?? new Environment();
    }

    public function findEnvironmentPath(): ?string
    {
        $rootFolder = $this->config->get('paths.root_folder', 'environnement');

        // Windows: parcourir les lettres de lecteur
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (range('C', 'Z') as $letter) {
                $path = "{$letter}:/" . $rootFolder;
                if (is_dir($path)) {
                    return $path;
                }
            }
        }

        // Unix: chercher dans les emplacements courants
        $unixPaths = [
            '/opt/' . $rootFolder,
            getenv('HOME') . '/' . $rootFolder,
            './' . $rootFolder,
        ];

        foreach ($unixPaths as $path) {
            if (is_dir($path)) {
                return realpath($path);
            }
        }

        return null;
    }

    public function findJavaHome(): ?string
    {
        // Vérifier JAVA_HOME
        if (getenv('JAVA_HOME')) {
            $path = getenv('JAVA_HOME');
            $this->logger?->debug("✅ JAVA_HOME trouvé: {$path}");
            return $path;
        }

        // Vérifier dans le PATH (logique centralisée dans Environment)
        $javaFromPath = $this->environment->getJavaHome();
        if ($javaFromPath !== null) {
            $this->logger?->debug("✅ Java trouvé dans le PATH: {$javaFromPath}");
            return $javaFromPath;
        }

        // Vérifier dans le dossier embarqué
        $javaPath = $this->config->getPath('java_folder');
        if (is_dir($javaPath)) {
            $this->logger?->debug("✅ Java trouvé dans le dossier embarqué: {$javaPath}");
            return $javaPath;
        }

        $this->logger?->warning("❌ Java non trouvé");
        return null;
    }

    public function findGraphviz(): ?string
    {
        // Vérifier dans le PATH (logique centralisée dans Environment)
        $dotFromPath = $this->environment->findGraphvizInPath();
        if ($dotFromPath !== null) {
            $this->logger?->debug("✅ Graphviz trouvé dans le PATH: {$dotFromPath}");
            return $dotFromPath;
        }

        // Vérifier dans le dossier embarqué
        $graphvizPath = $this->config->getPath('graphviz_folder');
        $dotExe = PHP_OS_FAMILY === 'Windows'
            ? "{$graphvizPath}/bin/dot.exe"
            : "{$graphvizPath}/bin/dot";

        if (file_exists($dotExe)) {
            $this->logger?->debug("✅ Graphviz trouvé dans le dossier embarqué: {$dotExe}");
            return $dotExe;
        }

        $this->logger?->debug("❌ Graphviz non trouvé");
        return null;
    }

    public function findSchemaSpyJar(): ?string
    {
        $schemaspyPath = $this->config->getPath('schemaspy_folder');
        $jarName = $this->config->getSchemaspyJar();
        $jarPath = $schemaspyPath . '/' . $jarName;

        if (file_exists($jarPath)) {
            $this->logger?->debug("✅ SchemaSpy JAR trouvé: {$jarPath}");
            return $jarPath;
        }

        $this->logger?->warning("❌ SchemaSpy JAR non trouvé: {$jarPath}");
        return null;
    }

    public function findFile(string $path, string $filename): ?string
    {
        $fullPath = $path . '/' . $filename;
        if (file_exists($fullPath)) {
            return $fullPath;
        }
        return null;
    }

    public function findDirectory(string $path, string $dirname): ?string
    {
        $fullPath = $path . '/' . $dirname;
        if (is_dir($fullPath)) {
            return $fullPath;
        }
        return null;
    }

    /**
     * 🔥 Recherche un fichier dans les dossiers parents
     */
    public function findFileUpwards(string $filename, string $startDir): ?string
    {
        $current = realpath($startDir);
        while ($current !== false && $current !== DIRECTORY_SEPARATOR) {
            $fullPath = $current . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
            $current = dirname($current);
        }
        return null;
    }

    /**
     * 🔥 Vérifie si un exécutable est disponible (délègue à Environment)
     */
    public function isExecutableAvailable(string $executable): bool
    {
        return $this->environment->isCommandAvailable($executable);
    }

    /**
     * 🔥 Récupère le chemin de l'exécutable Java
     */
    public function findJavaExecutable(): ?string
    {
        // JAVA_HOME en priorité
        $javaHome = $this->environment->getJavaHome();
        if ($javaHome !== null) {
            $exe = $this->environment->getJavaExecutable($javaHome);
            if (file_exists($exe)) {
                return $exe;
            }
        }

        // Recherche dans le PATH via where/which
        $command = PHP_OS_FAMILY === 'Windows' ? 'where java 2>nul' : 'which java 2>/dev/null';
        $output = shell_exec($command);

        if (!empty(trim((string) $output))) {
            return trim((string) $output);
        }
        return null;
    }
}
