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

    /**
     * Détecte le JDK à utiliser, dans l'ordre : système (JAVA_HOME/PATH),
     * dossier embarqué (paths.java_folder), chemin explicite (paths.java_home).
     *
     * @return array{home: string, executable: string, version: ?string, source: string}|null
     */
    public function detectJava(): ?array
    {
        // 1. Système : JAVA_HOME ou PATH (logique centralisée dans Environment)
        $home = $this->environment->getJavaHome();
        if ($home !== null) {
            $exe = $this->environment->getJavaExecutable($home);
            if (file_exists($exe)) {
                return $this->describeJava($home, $exe, 'système');
            }
        }

        // 2. Dossier embarqué (ex: tools/jdk17)
        $embeddedHome = $this->config->getPath('java_folder');
        $embeddedExe = $this->environment->getJavaExecutable($embeddedHome);
        if (file_exists($embeddedExe)) {
            return $this->describeJava($embeddedHome, $embeddedExe, 'embarqué');
        }

        // 3. Chemin explicite déclaré dans config.json (spécifique à l'OS)
        $explicitHome = $this->config->get('paths.java_home.' . $this->osConfigKey());
        if (!empty($explicitHome)) {
            $explicitExe = $this->environment->getJavaExecutable($explicitHome);
            if (file_exists($explicitExe)) {
                return $this->describeJava($explicitHome, $explicitExe, 'config');
            }
        }

        $this->logger?->warning("❌ JDK introuvable (système, tools/, config.json)");
        return null;
    }

    /**
     * Détecte Graphviz, dans le même ordre que detectJava(). Optionnel :
     * un retour null bascule simplement sur le rendu viz.js.
     *
     * @return array{path: string, executable: string, version: ?string, source: string}|null
     */
    public function detectGraphviz(): ?array
    {
        // 1. Système : PATH
        $exeFromPath = $this->environment->findGraphvizInPath();
        if ($exeFromPath !== null) {
            return $this->describeGraphviz(dirname($exeFromPath, 2), $exeFromPath, 'système');
        }

        // 2. Dossier embarqué (ex: tools/graphviz-2.38)
        $embeddedPath = $this->config->getPath('graphviz_folder');
        $embeddedExe = $this->environment->getDotExecutable($embeddedPath);
        if (file_exists($embeddedExe)) {
            return $this->describeGraphviz($embeddedPath, $embeddedExe, 'embarqué');
        }

        // 3. Chemin explicite déclaré dans config.json (spécifique à l'OS)
        $explicitPath = $this->config->get('paths.graphviz_home.' . $this->osConfigKey());
        if (!empty($explicitPath)) {
            $explicitExe = $this->environment->getDotExecutable($explicitPath);
            if (file_exists($explicitExe)) {
                return $this->describeGraphviz($explicitPath, $explicitExe, 'config');
            }
        }

        $this->logger?->debug("❌ Graphviz introuvable (système, tools/, config.json) — repli sur viz.js");
        return null;
    }

    private function describeJava(string $home, string $executable, string $source): array
    {
        $version = $this->environment->getJavaVersion($executable);
        $this->logger?->debug("✅ JDK trouvé ({$source}): {$executable}" . ($version !== null ? " [{$version}]" : ''));
        return ['home' => $home, 'executable' => $executable, 'version' => $version, 'source' => $source];
    }

    private function describeGraphviz(string $path, string $executable, string $source): array
    {
        $version = $this->environment->getGraphvizVersion($executable);
        $this->logger?->debug("✅ Graphviz trouvé ({$source}): {$executable}" . ($version !== null ? " [{$version}]" : ''));
        return ['path' => $path, 'executable' => $executable, 'version' => $version, 'source' => $source];
    }

    private function osConfigKey(): string
    {
        return $this->environment->isWindows() ? 'windows' : 'unix';
    }
}
