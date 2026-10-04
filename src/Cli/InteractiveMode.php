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

namespace SchemaSpyCli\Cli;

use SchemaSpyCli\Core\{Config, Logger};
use SchemaSpyCli\Utils\{Validator, PathFinder};
use SchemaSpyCli\Utils\OutputNameGenerator;
use SchemaSpyCli\Exceptions\ValidationException;
use SchemaSpyCli\SchemaSpy\GenerationOptions;

/**
 * [Description InteractiveMode]
 * Mode interactif de l'application
 */
final class InteractiveMode
{
    private PathFinder $pathFinder;
    private OutputNameGenerator $outputNameGenerator;
    private array $lastParams = [];
    private array $optionDefaults = [];

    // Nombre d'étapes affichées par la barre de progression
    private const STEPS = 4;

    // Couleurs pour l'affichage
    private const COLORS = [
        'highlight' => 'cyan',
        'label' => 'gray',
        'value' => 'white',
        'success' => 'green',
        'warning' => 'yellow',
        'error' => 'red',
        'critical' => 'red',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly Validator $validator
    ) {
        $this->pathFinder = new PathFinder($config, $logger);
        $this->outputNameGenerator = new OutputNameGenerator($config);
        $this->loadLastParams();
    }

    /**
     * [Description for collect]
     * Collecte des données saisies par l'utilisateur
     *
     * @return array|null
     *
     * Created at: 05/07/2026 10:30:45 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function collect(array $optionDefaults = []): ?array
    {
        $this->optionDefaults = $optionDefaults;
        $this->logger->blankLine();

        // Sélection du type de base de données
        $dbType = $this->selectDatabaseType();
        $dbConfig = $this->config->getDatabase($dbType);

        // Collecte des paramètres
        $params = $this->collectConnectionParams($dbType, $dbConfig);

        // Options de génération (étape 3)
        $params['options'] = $this->collectGenerationOptions();

        // Afficher le récapitulatif
        $this->showSummary($params);

        // Demander confirmation
        if (!$this->confirm()) {
            $this->logger->warning("Opération annulée.");
            return null;
        }

        return $params;
    }

    /**
     * [Description for selectDatabaseType]
     * On choisi la base de données
     *
     * @return string
     *
     * Created at: 05/07/2026 10:32:02 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function selectDatabaseType(): string
    {
        $this->logger->title("Type de base de données");
        $this->showCollectProgress('Étape', 1);

        $databases = $this->config->getDatabases();
        $dbTypes = array_keys($databases);
        $defaultDb = $this->lastParams['dbType'] ?? 'postgresql';
        $defaultIndex = array_search($defaultDb, $dbTypes) + 1;

        // Afficher les types avec plus d'informations
        foreach ($dbTypes as $i => $type) {
            $selected = ($i + 1 === $defaultIndex) ? ' (défaut)' : '';
            $driver = $databases[$type]['driver'] ?? '';
            $version = $databases[$type]['version'] ?? '?';
            $dbVersion = $databases[$type]['db_version'] ?? '?';

            $this->logger->info(
                sprintf("  [%d] %s%s (%s) v%s - SGBD %s",
                    $i + 1,
                    $type,
                    $selected,
                    $driver,
                    $version,
                    $dbVersion
                ),
                $selected ? self::COLORS['highlight'] : 'default'
            );
        }

        do {
            $choice = (int) $this->logger->prompt(
                "\nChoix (1-" . count($dbTypes) . ")",
                (string) $defaultIndex
            );
        } while ($choice < 1 || $choice > count($dbTypes));

        $selectedType = $dbTypes[$choice - 1];
        $this->logger->success("Type sélectionné: {$selectedType}");
        $this->logger->blankLine();

        return $selectedType;
    }

    private function collectConnectionParams(string $dbType, array $dbConfig): array
    {
        $this->logger->title("Informations de connexion ({$dbType})");
        $this->showCollectProgress('Étape', 2);

        // Charger les valeurs par défaut depuis les derniers paramètres
        $defaultHost = $this->lastParams['host'] ?? $this->config->get('defaults.host', 'localhost');
        $defaultPort = $this->lastParams['port'] ?? $dbConfig['port'];
        $defaultDatabase = $this->lastParams['database'] ?? '';
        $defaultSchema = $this->lastParams['schema'] ?? '';
        $defaultUser = $this->lastParams['user'] ?? '';

        // Afficher les informations du driver
        $this->logger->info("📦 Driver: " . ($dbConfig['driver'] ?? 'N/A'), 'gray');
        $this->logger->info("📌 Version SGBD: " . ($dbConfig['db_version'] ?? 'N/A'), 'gray');
        $this->logger->blankLine();

        // Collecte avec validation
        $host = $this->promptWithValidation("Nom du host", $defaultHost, 'validateHost');
        $port = (int) $this->promptWithValidation("Port", (string) $defaultPort, 'validatePort');
        $database = $this->promptWithValidation("Nom de la base", $defaultDatabase, 'validateDatabase');
        $schema = $this->promptWithValidation("Nom du schéma", $defaultSchema, 'validateSchema');
        $user = $this->promptWithValidation("Utilisateur", $defaultUser, 'validateUser');
        $password = $this->logger->promptPassword("Mot de passe");

        // Détection automatique de viz.js
        $useVizJs = $this->detectVizJs();

        // Génération du nom de sortie
        $output = $this->outputNameGenerator->generate($dbType, $schema);

        return [
            'dbType'   => $dbType,
            'dbConfig' => $dbConfig,
            'host'     => $host,
            'database' => $database,
            'schema'   => $schema,
            'port'     => $port,
            'user'     => $user,
            'password' => $password,
            'output'   => $output,
            'useVizJs' => $useVizJs,
        ];
    }

    /**
     * [Description for promptWithValidation]
     * Prompt avec validation
     *
     * @param string $message
     * @param string $default
     * @param string $validationMethod
     *
     * @return string
     *
     * Created at: 05/07/2026 10:34:59 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function promptWithValidation(string $message, string $default, string $validationMethod): string
    {
        $maxAttempts = 3;
        $attempts = 0;

        do {
            $value = $this->logger->prompt($message, $default);
            $attempts++;

            try {
                $this->validator->$validationMethod($value);
                return $value;
            } catch (\Exception $e) {
                $this->logger->warning($e->getMessage());
                if ($attempts >= $maxAttempts) {
                    $this->logger->warning("Nombre maximum d'essais atteint. Utilisation de la valeur par défaut: {$default}");
                    return $default;
                }
                $this->logger->info("Veuillez réessayer ({$attempts}/{$maxAttempts})", 'gray');
            }
        } while (true);
    }

    /**
     * [Description for detectVizJs]
     * Choix du moteur de plot : graphviz ? ou viz.js
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:36:02 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function detectVizJs(): bool
    {
        $graphviz = $this->pathFinder->detectGraphviz();
        $useVizJs = $graphviz === null;

        if ($useVizJs) {
            $this->logger->info("Graphviz non trouvé, utilisation de viz.js", 'gray');
        } else {
            $version = $graphviz['version'] ?? 'version inconnue';
            $this->logger->success("Graphviz trouvé ({$graphviz['source']}): {$version}");
        }

        return $useVizJs;
    }

    private function showSummary(array $params): void
    {
        $this->logger->title("Récapitulatif");
        $this->showCollectProgress('Étape', 4);

        // Tableau des informations
        $rows = [
            ['Type          ', $params['dbType']],
            ['Driver        ', $params['dbConfig']['driver'] ?? 'N/A'],
            ['Host          ', "{$params['host']}:{$params['port']}"],
            ['Base          ', $params['database']],
            ['Schéma        ', $params['schema']],
            ['Utilisateur   ', $params['user']],
            ['Dossier sortie', $params['output']],
            ['Moteur        ', $this->describeEngine($params)],
            ['Sorties       ', $this->describeOutputs($params['options'])],
            ['Options       ', $this->describeFilters($params['options'])],
        ];

        foreach ($rows as $row) {
            $this->logger->info(
                sprintf("  %-12s: %s", $row[0], $row[1]),
                'gray'
            );
        }
    }


    /**
     * [Description for collectGenerationOptions]
     * Étape 3 : options de génération. Les valeurs proposées viennent de
     * config.json ("generation") puis des options passées en ligne de commande ;
     * l'utilisateur peut les garder (Entrée) ou les personnaliser une par une.
     *
     * @return GenerationOptions
     *
     * Created at: 04/10/2026 22:32:15 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function collectGenerationOptions(): GenerationOptions
    {
        $this->logger->title("Options de génération");
        $this->showCollectProgress('Étape', 3);

        $current = GenerationOptions::fromArrays($this->optionDefaults);
        $this->logger->info("Options actuelles : " . $this->describeOutputs($current) . ' / ' . $this->describeFilters($current), 'gray');

        if (!$this->logger->promptConfirmation("Personnaliser les options de génération ?", false)) {
            return $current;
        }

        $answers = [];

        $graphviz = $this->pathFinder->detectGraphviz();
        $this->logger->info("Moteur des diagrammes :", self::COLORS['highlight']);
        $this->logger->info("  [1] auto (" . ($graphviz !== null ? 'Graphviz ' . ($graphviz['version'] ?? '?') . ' détecté' : 'Graphviz introuvable, viz.js') . ")");
        $this->logger->info("  [2] graphviz (natif, repli sur viz.js si introuvable)");
        $this->logger->info("  [3] vizjs (rendu intégré, sans Graphviz)");
        $engines = [1 => GenerationOptions::ENGINE_AUTO, 2 => GenerationOptions::ENGINE_GRAPHVIZ, 3 => GenerationOptions::ENGINE_VIZJS];
        $default = (string) array_search($current->engine, $engines, true);
        do {
            $choice = (int) $this->logger->prompt("Choix (1-3)", $default);
        } while (!isset($engines[$choice]));
        $answers['engine'] = $engines[$choice];

        $answers['html'] = $this->logger->promptConfirmation("Générer le site HTML ?", $current->html);

        if ($this->config->get('schemaspy.markdown_supported', false)) {
            $answers['markdown'] = $this->logger->promptConfirmation("Générer la documentation Markdown (Mermaid) ?", $current->markdown);
        } else {
            $this->logger->info("Markdown indisponible avec ce JAR SchemaSpy (voir schemaspy.markdown_supported).", 'gray');
            $answers['markdown'] = false;
        }

        if (!$answers['html'] && !$answers['markdown']) {
            $this->logger->warning("Aucune sortie sélectionnée : le site HTML est conservé.");
            $answers['html'] = true;
        }

        $answers['orphans'] = !$this->logger->promptConfirmation("Exclure les tables orphelines des diagrammes ?", !$current->orphans);
        $answers['views'] = !$this->logger->promptConfirmation("Exclure les vues ?", !$current->views);
        $answers['rows'] = $this->logger->promptConfirmation("Compter les lignes de chaque table (plus lent) ?", $current->rows);
        $answers['implied'] = $this->logger->promptConfirmation("Rechercher les relations implicites ?", $current->implied);
        $answers['degree'] = $this->promptDegree($current->degree ?? 2);
        $answers['include'] = $this->promptRegex("Ne garder que les tables correspondant à (regex, Entrée = toutes)", $current->include);
        $answers['exclude'] = $this->promptRegex("Exclure les tables correspondant à (regex, Entrée = aucune)", $current->exclude);

        return GenerationOptions::fromArrays($this->optionDefaults, $answers);
    }

    private function promptDegree(int $default): int
    {
        do {
            $value = (int) $this->logger->prompt("Degré de séparation des diagrammes de table (1 ou 2)", (string) $default);
        } while (!in_array($value, [1, 2], true));
        return $value;
    }

    private function promptRegex(string $message, ?string $default): ?string
    {
        do {
            $value = $this->logger->prompt($message, $default ?? '');
            try {
                new GenerationOptions(include: $value !== '' ? $value : null);
                return $value !== '' ? $value : null;
            } catch (ValidationException $e) {
                $this->logger->warning($e->getMessage());
            }
        } while (true);
    }

    /**
     * [Description for describeEngine]
     *
     * @param array $params
     *
     * @return string
     *
     * Created at: 04/10/2026 22:32:38 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function describeEngine(array $params): string
    {
        $engine = $params['options']->engine;
        if ($engine === GenerationOptions::ENGINE_AUTO) {
            return $params['useVizJs'] ? 'auto -> viz.js (Graphviz introuvable)' : 'auto -> Graphviz';
        }
        return $engine;
    }

    /**
     * [Description for describeOutputs]
     *
     * @param GenerationOptions $options
     *
     * @return string
     *
     * Created at: 04/10/2026 22:32:42 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function describeOutputs(GenerationOptions $options): string
    {
        return implode(' + ', array_filter([$options->html ? 'HTML' : null, $options->markdown ? 'Markdown' : null]));
    }

    /**
     * [Description for describeFilters]
     *
     * @param GenerationOptions $options
     *
     * @return string
     *
     * Created at: 04/10/2026 22:32:44 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function describeFilters(GenerationOptions $options): string
    {
        $parts = array_filter([
            !$options->orphans ? 'sans orphelines' : null,
            !$options->views ? 'sans vues' : null,
            !$options->rows ? 'sans comptage' : null,
            !$options->implied ? 'sans relations implicites' : null,
            $options->degree !== null ? "degré {$options->degree}" : null,
            $options->include !== null ? "inclure {$options->include}" : null,
            $options->exclude !== null ? "exclure {$options->exclude}" : null,
        ]);
        return $parts ? implode(', ', $parts) : 'par défaut';
    }

    /**
     * [Description for confirm]
     * Confirmation de l'utilisateur ?
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:37:34 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function confirm(): bool
    {
        $this->logger->blankLine();
        return $this->logger->promptConfirmation("\nConfirmer la génération ?", true);
    }

    /**
     * [Description for loadLastParams]
     * Chargement des derniers paramètres ?
     *
     * @return void
     *
     * Created at: 05/07/2026 10:37:57 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function loadLastParams(): void
    {
        $lastPath = dirname(__DIR__, 2) . '/' . 'schemaspy.last.json';
        if (file_exists($lastPath)) {
            $content = file_get_contents($lastPath);
            $this->lastParams = json_decode($content, true) ?? [];

            // Affiche les derniers paramètres si disponibles
            if (!empty($this->lastParams) && isset($this->lastParams['last_used'])) {
                $this->logger->debug(
                    "📂 Derniers paramètres du " . $this->lastParams['last_used']
                );
            }
        }
    }

    /**
     * [Description for saveLastParams]
     * Enregistre les paramètres saisies par l'utilisateur pour les réutiliser.
     *
     * @param array $params
     *
     * @return void
     *
     * Created at: 05/07/2026 10:38:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function saveLastParams(array $params): void
    {
        $save = [
            'host' => $params['host'],
            'user' => $params['user'],
            'port' => $params['port'],
            'schema' => $params['schema'],
            'database' => $params['database'],
            'dbType' => $params['dbType'],
            'last_used' => date('Y-m-d H:i:s'),
        ];

        $lastPath = dirname(__DIR__, 2) . '/' . 'schemaspy.last.json';
        file_put_contents(
            $lastPath,
            json_encode($save, JSON_PRETTY_PRINT)
        );

        $this->logger->debug("💾 Paramètres sauvegardés");
    }

    /**
     * [Description for showCollectProgress]
     * Méthode utilitaire pour afficher une progression pendant la collecte
     *
     * @param string $step
     * @param int $current
     * @param int $total
     *
     * @return void
     *
     * Created at: 05/07/2026 10:39:45 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function showCollectProgress(string $step, int $current, int $total = self::STEPS): void
    {
        $this->logger->blankLine();
        $this->logger->progressBar($current, $total, "{$step} {$current}/{$total}");
        $this->logger->blankLine();
        $this->logger->blankLine();

    }
}
