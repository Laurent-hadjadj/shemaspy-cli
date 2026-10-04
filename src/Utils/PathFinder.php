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

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Core\Logger;

/**
 * [Description PathFinder]
 * Recherche de chemins et de fichiers
 */
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
     * [Description for detectJava]
     * Détecte le JDK à utiliser parmi : système (JAVA_HOME/PATH), dossier
     * embarqué (paths.java_folder), chemin explicite (paths.java_home).
     *
     * Retourne le premier JDK satisfaisant la version requise par SchemaSpy
     * (schemaspy.compatibility) ; à défaut, le premier JDK trouvé, pour que
     * l'appelant puisse signaler l'incompatibilité. Ainsi un JDK 8 système ne
     * masque plus un JDK 17 embarqué dans tools/.
     *
     * @return array{home: string, executable: string, version: ?string, source: string}|null
     *
     * Created at: 04/10/2026 22:52:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function detectJava(): ?array
    {
        $required = $this->requiredJavaVersion();
        $first = null;

        foreach ($this->javaCandidates() as [$home, $source]) {
            $exe = $this->environment->getJavaExecutable($home);
            if (!file_exists($exe)) {
                continue;
            }

            $java = $this->describeJava($home, $exe, $source);
            if ($required === null || $java['version'] === null || version_compare($java['version'], $required, '>=')) {
                return $java;
            }

            $this->logger?->debug("JDK {$java['version']} ({$source}) ignoré : {$required}+ requis par SchemaSpy");
            $first ??= $java;
        }

        if ($first === null) {
            $this->logger?->warning("❌ JDK introuvable (système, tools/, config.json)");
        }
        return $first;
    }

    /**
     * [Description for requiredJavaVersion]
     * Version minimale du JDK pour la version majeure de SchemaSpy configurée
     * (ex: SchemaSpy 7.x -> 17), ou null si aucune règle n'est définie.
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:53:10 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function requiredJavaVersion(): ?string
    {
        $major = explode('.', $this->config->getSchemaspyVersion())[0];
        return $this->config->get("schemaspy.compatibility.{$major}");
    }

    /**
     * [Description for javaCandidates]
     *
     * @return list<array{0: string, 1: string}> [home, source] dans l'ordre de priorité     *
     * Created at: 04/10/2026 22:53:29 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function javaCandidates(): array
    {
        $candidates = [];

        $systemHome = $this->environment->getJavaHome();
        if ($systemHome !== null) {
            $candidates[] = [$systemHome, 'système'];
        }

        $candidates[] = [$this->config->getPath('java_folder'), 'embarqué'];

        $explicitHome = $this->config->get('paths.java_home.' . $this->osConfigKey());
        if (!empty($explicitHome)) {
            $candidates[] = [$explicitHome, 'config'];
        }

        return $candidates;
    }

    /**
     * [Description for detectGraphviz]
      * Détecte Graphviz, dans le même ordre que detectJava(). Optionnel :
     * un retour null bascule simplement sur le rendu viz.js.
     *
     * @return array{path: string, executable: string, version: ?string, source: string}|null
     *
     * Created at: 04/10/2026 22:53:49 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
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
        $this->provisionDotExecutable($embeddedExe);
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

    /**
     * [Description for provisionDotExecutable]
     * Les archives ZIP Windows de Graphviz récentes (>= 15) ne contiennent plus
     * dot.exe mais seulement dot_builtins.exe (même binaire, plugins intégrés).
     * SchemaSpy appelle <gv>/bin/dot : on crée donc dot.exe à partir de
     * dot_builtins.exe, uniquement dans le dossier embarqué (tools/).
     *
     * @param string $dotExe
     *
     * @return void
     *
     * Created at: 04/10/2026 22:54:09 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function provisionDotExecutable(string $dotExe): void
    {
        if (file_exists($dotExe)) {
            return;
        }

        $dir = dirname($dotExe);
        $suffix = $this->environment->isWindows() ? '.exe' : '';
        $builtins = $dir . DIRECTORY_SEPARATOR . 'dot_builtins' . $suffix;
        if (!file_exists($builtins)) {
            return;
        }

        if (@copy($builtins, $dotExe)) {
            @chmod($dotExe, 0755);
            $this->logger?->debug("dot absent : créé à partir de dot_builtins ({$dotExe})");
        } else {
            $this->logger?->warning("Impossible de créer {$dotExe} à partir de dot_builtins");
        }
    }

    /**
     * [Description for describeJava]
     *
     * @param string $home
     * @param string $executable
     * @param string $source
     *
     * @return array
     *
     * Created at: 04/10/2026 22:54:24 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function describeJava(string $home, string $executable, string $source): array
    {
        $version = $this->environment->getJavaVersion($executable);
        $this->logger?->debug("✅ JDK trouvé ({$source}): {$executable}" . ($version !== null ? " [{$version}]" : ''));
        return ['home' => $home, 'executable' => $executable, 'version' => $version, 'source' => $source];
    }

    /**
     * [Description for describeGraphviz]
     *
     * @param string $path
     * @param string $executable
     * @param string $source
     *
     * @return array
     *
     * Created at: 04/10/2026 22:54:27 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function describeGraphviz(string $path, string $executable, string $source): array
    {
        $version = $this->environment->getGraphvizVersion($executable);
        $this->logger?->debug("✅ Graphviz trouvé ({$source}): {$executable}" . ($version !== null ? " [{$version}]" : ''));
        return ['path' => $path, 'executable' => $executable, 'version' => $version, 'source' => $source];
    }

    /**
     * [Description for osConfigKey]
     *
     * @return string
     *
     * Created at: 04/10/2026 22:54:30 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function osConfigKey(): string
    {
        return $this->environment->isWindows() ? 'windows' : 'unix';
    }
}
