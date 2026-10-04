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

namespace SchemaSpyCli\SchemaSpy;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Database\DriverManager;
use SchemaSpyCli\Utils\PathFinder;

/**
 * [Description PropertiesGenerator]
 * Génération du fichier properties pour SchemaSpy
 */
final class PropertiesGenerator
{
    private PathFinder $pathFinder;

    public function __construct(
        private readonly Config $config,
        private readonly Environment $environment,
        private readonly Logger $logger,
        private readonly DriverManager $driverManager,
        ?PathFinder $pathFinder = null
    ) {
        $this->pathFinder = $pathFinder ?? new PathFinder($config, null);
    }

    public function generate(array $params, string $outputDir): string
    {
        $properties = $this->buildProperties($params, $outputDir);
        $content = $this->buildContent($properties, $params);

        $filename = "schemaspy_{$params['output']}.properties";
        $filepath = sys_get_temp_dir() . '/' . $filename;

        // Création du fichier avec permissions restrictives (lecture/écriture
        // propriétaire uniquement) car il contient le mot de passe de la BDD.
        // Sur Windows, le mode n'a pas d'effet mais ne génère pas d'erreur.
        file_put_contents($filepath, $content);
        @chmod($filepath, 0600);

        return $filepath;
    }

    private function buildProperties(array $params, string $outputDir): array
    {
        $date = $this->environment->getDate();
        $appName = $this->config->getApplicationName();
        $company = $this->config->get('application.company', 'MaMoulinette');
        $copyright = $this->config->get('application.copyright', '© MaMoulinette - 2026');

        // Titre et description pour SchemaSpy 7
        $title = "{$appName} - {$params['database']} ({$params['dbType']})";
        $desc = "{$copyright} - {$company} - Documentation généré le {$date}";

        $properties = [
            'schemaspy.t' => $params['dbConfig']['type'],
            'schemaspy.host' => $params['host'],
            'schemaspy.port' => $params['port'],
            'schemaspy.db' => $params['database'],
            'schemaspy.s' => $params['schema'],
            'schemaspy.u' => $params['user'],
            'schemaspy.p' => $params['password'],
            'schemaspy.o' => $outputDir,

            // catalogue parfois requis quand l'auto détection ne fonctionne pas sur Oracle.
            'schemaspy.cat' => $params['schema'],

            // Titre et description (SchemaSpy 7)
            'schemaspy.title' => $title,
            'schemaspy.desc' => $desc,

            // Options d'affichage
            'schemaspy.noads' => $this->config->get('defaults.noads', true) ? 'true' : 'false',
            'schemaspy.nologo' => $this->config->get('defaults.nologo', true) ? 'true' : 'false',
            'schemaspy.font' => $this->config->get('defaults.font', 'Arial'),
        ];

        // Ajouter Graphviz ou viz.js
        $this->addGraphvizOptions($properties, $params);

        // Options de génération (HTML/Markdown, orphelines, filtres...)
        if (isset($params['options'])) {
            $properties = array_merge($properties, $params['options']->toProperties());
        }

        // Ajouter favicon et logo
        $this->addResources($properties);

        // Ajouter les propriétés de connexion
        $this->addConnectionProperties($properties);

        return $properties;
    }

    private function addGraphvizOptions(array &$properties, array $params): void
    {
        $graphviz = $this->pathFinder->detectGraphviz();

        if ($graphviz !== null && !$params['useVizJs']) {
            // SchemaSpy attend le dossier racine de Graphviz (il ajoute lui-même /bin/dot) :
            // $graphviz['path'] est déjà cette racine, ne pas reprendre dirname(executable)
            // qui pointe vers bin/ et provoquerait un chemin .../bin/bin/dot.
            $properties['schemaspy.gv'] = $graphviz['path'];
            $properties['schemaspy.imageformat'] = $this->config->get('defaults.image_format', 'svg');

            // Renderer facultatif (ex: "cairo", "gd"). Vide par défaut : les builds récents de
            // Graphviz (>= 15, Windows) n'embarquent plus le plugin cairo.
            $renderer = (string) $this->config->get('defaults.renderer', '');
            if ($renderer !== '') {
                $properties['schemaspy.renderer'] = ':' . ltrim($renderer, ':');
            }
            return;
        }

        if ($graphviz === null && ($params['options']->engine ?? 'auto') === 'graphviz') {
            $this->logger->warning("Graphviz demandé (--engine=graphviz) mais introuvable : repli sur viz.js");
        }
        $properties['schemaspy.vizjs'] = 'true';
        $properties['schemaspy.imageformat'] = $this->config->get('defaults.image_format', 'svg');
    }

    private function addResources(array &$properties): void
    {
        $baseDir = $this->config->getBasePath() ?? getcwd();

        // Logo personnalisé - chercher dans plusieurs dossiers
        $logoPaths = [
            $baseDir . '/ressources/logo.png',
            $baseDir . '/ressources/logo.svg',
            $baseDir . '/resources/logo.png',
            $baseDir . '/resources/logo.svg',
            $baseDir . '/logo.png',
            $baseDir . '/logo.svg',
        ];

        $logoFound = false;
        foreach ($logoPaths as $logoPath) {
            if (file_exists($logoPath)) {
                // Normaliser les slashes pour Windows
                $logoPath = str_replace('\\', '/', $logoPath);
                $properties['schemaspy.logo'] = $logoPath;
                $properties['schemaspy.nologo'] = 'false';
                $logoFound = true;
                $this->logger->debug("✅ Logo trouvé: {$logoPath}");
                break;
            }
        }

        if (!$logoFound) {
            $this->logger->warning("❌ Logo non trouvé dans les dossiers: ressources/, resources/, racine");
            $properties['schemaspy.nologo'] = 'true';
        }

        // Favicon
        $faviconPath = $baseDir . '/favicon.ico';
        if (file_exists($faviconPath)) {
            $properties['schemaspy.favicon'] = $faviconPath;
        }
    }

    private function addConnectionProperties(array &$properties): void
    {
        $connprops = $this->config->get('connprops', []);
        foreach ($connprops as $key => $value) {
            $properties["schemaspy.connprops.{$key}"] = $value;
        }
    }

    private function buildContent(array $properties, array $params): string
    {
        // Ne jamais écrire le mot de passe en clair dans l'en-tête de debug.
        // On travaille sur une copie masquée des paramètres.
        $safeParams = $params;
        if (isset($safeParams['password'])) {
            $safeParams['password'] = '***';
        }
        if (isset($safeParams['dbConfig'])) {
            unset($safeParams['dbConfig']);
        }

        $content = "# SchemaSpy Properties\n";
        $content .= "# Généré le " . $this->environment->getDate() . "\n";
        $content .= "# Version: " . $this->config->get('application.version', '3.0.0') . "\n";
        $content .= "# Application: " . $this->config->getApplicationName() . "\n";
        $content .= "# Paramètres: " . json_encode($safeParams) . "\n\n";

        // Ajouter les informations sur le driver
        $driverPath = $this->driverManager->getDriverPath($params['dbType']);
        if ($driverPath) {
            $content .= "# Driver JDBC: " . basename($driverPath) . "\n";
            $content .= "# Chemin: {$driverPath}\n";
            $content .= "# Taille: " . $this->formatSize(filesize($driverPath)) . "\n";
        }
        $content .= "\n";

        foreach ($properties as $key => $value) {
            $content .= "{$key}={$value}\n";
        }

        return $content;
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
