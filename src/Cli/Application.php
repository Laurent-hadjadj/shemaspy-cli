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

use SchemaSpyCli\Core\{Config, Logger, Environment};
use SchemaSpyCli\Database\{Connection, DriverManager};
use SchemaSpyCli\SchemaSpy\{GenerationOptions, Runner};
use SchemaSpyCli\Utils\{FileSystem, PathFinder, ProcessRunner, Validator, VersionChecker};
use SchemaSpyCli\Exceptions\{ConfigException, ConnectionException, ValidationException};

/**
 * [Description Application]
 * Application principale
 */
final class Application
{
    private readonly Config $config;
    private readonly Logger $logger;
    private readonly Environment $environment;
    private readonly ArgumentParser $argumentParser;
    private ?PathFinder $pathFinder = null;
    private readonly Validator $validator;
    private readonly VersionChecker $versionChecker;
    private ?InteractiveMode $interactiveMode = null;
    private ?NonInteractiveMode $nonInteractiveMode = null;
    private ?Runner $runner = null;
    private ?DriverManager $driverManager = null;
    private readonly FileSystem $fileSystem;

    private const BANNER = <<<'BANNER'

.__  __             __  __             _ _            _   _
|  \/  | __ _      |  \/  | ___  _   _| (_)_ __   ___| |_| |_ ___
| |\/| |/ _` |_____| |\/| |/ _ \| | | | | | '_ \ / _ \ __| __/ _ \
| |  | | (_| |_____| |  | | (_) | |_| | | | | | |  __/ |_| ||  __/
|_|  |_|\__,_|     |_|  |_|\___/ \__,_|_|_|_| |_|\___|\__|\__\___|

Present : ShemaSpy-cli

Laurent HADJADJ
https://github.com/Laurent-hadjadj/ma-moulinette
© 2015-2026 - CC BY-SA-NC 4.0

BANNER;

    /**
     * [Description for __construct]
     * Les paramètres sont facultatifs et servent aux tests : par défaut l'application
     * journalise dans logs/, s'ancre à la racine du dépôt et lance le vrai processus Java.
     */
    public function __construct(
        ?Logger $logger = null,
        private readonly ?string $basePath = null,
        private readonly ?ProcessRunner $processRunner = null,
        ?Environment $environment = null
    ) {
        // Services sans dépendances
        $this->logger = $logger ?? new Logger(false, dirname(__DIR__, 2) . '/logs/schemaspy-cli.log');
        $this->argumentParser = new ArgumentParser();
        $this->environment = $environment ?? new Environment();
        $this->validator = new Validator();
        $this->versionChecker = new VersionChecker($this->logger);
        $this->fileSystem = new FileSystem();

        // Config chargé plus tard (lors du run())
        $this->config = new Config();

        // Services dépendants de la config
        $this->pathFinder = null;
        $this->driverManager = null;
        $this->interactiveMode = null;
        $this->nonInteractiveMode = null;
        $this->runner = null;
    }

    /**
     * [Description for run]
     * Démarrage de l'application
     *
     * @return int
     *
     * Created at: 05/07/2026 08:58:16 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function run(): int
    {
        try {
            // 1. Parse des arguments
            $this->argumentParser->parse();
            $quietMode = $this->argumentParser->isQuiet();
            $verboseMode = $this->argumentParser->isVerbose();

            // 2. Configuration du logger
            $this->logger->setQuiet($quietMode);
            $this->logger->setVerbose($verboseMode);

            // 3. Charger la configuration
            $this->loadConfiguration();

            // 4. Ancrer les chemins relatifs de la config (jdbc/, tools/, jar/, report/) à la racine
            //    de l'application, avant toute détection : indépendant du répertoire courant
            $this->setupBasePath();

            // 5. Initialiser les services dépendants
            $this->initializeServices();

            // 6. Afficher la bannière (après la config : version SchemaSpy, nombre de drivers)
            if (!$quietMode) {
                $this->showBanner();
            }

            // 7. Vérifier l'environnement
            if (!$quietMode) {
                $this->checkEnvironment();
            }

            // 8. Vérifier les drivers JDBC
            $this->checkJdbcDrivers();

            // 9. Collecter les paramètres (null = opération annulée en mode interactif)
            $params = $this->collectParameters();
            if ($params === null) {
                return 0;
            }

            // 10. Exécuter SchemaSpy
            return $this->runner->execute($params);

        } catch (ConfigException $e) {
            $this->logger->error("Erreur de configuration: " . $e->getMessage());
            if ($this->logger->isVerbose()) {
                $this->logger->debug($e->getTraceAsString());
            }
            return 1;
        } catch (ConnectionException $e) {
            $this->logger->error("Erreur de connexion: " . $e->getMessage());
            return 1;
        } catch (ValidationException $e) {
            $this->logger->error("Paramètre invalide: " . $e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $this->logger->error("Erreur inattendue: " . $e->getMessage());
            if ($this->logger->isVerbose()) {
                $this->logger->debug($e->getTraceAsString());
            }
            return 1;
        } finally {
            if (!$this->argumentParser->isQuiet()) {
                $this->showFooter();
            }
        }
    }

    /**
     * [Description for loadConfiguration]
     * Charge la configuration depuis le fichier
     *
     * @return void
     *
     * Created at: 05/07/2026 09:03:02 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function loadConfiguration(): void
    {
        $configFile = $this->argumentParser->getConfigFile() ?? 'config/config.json';

        // Chemins possibles
        $configPaths = [
            $configFile,
            __DIR__ . '/../../' . $configFile,
            getcwd() . '/' . $configFile,
            'config/config.json',
        ];

        $loaded = false;
        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                $this->config->load($path);
                $loaded = true;
                $this->logger->debug("Configuration chargée depuis: {$path}");
                break;
            }
        }

        if (!$loaded) {
            throw new ConfigException(
                "Fichier de configuration introuvable. Recherché dans: " . implode(', ', $configPaths)
            );
        }
    }

    /**
     * [Description for initializeServices]
     * Initialise les services qui dépendent de la configuration
     *
     * @return void
     *
     * Created at: 05/07/2026 09:03:27 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function initializeServices(): void
    {
        // Alimenter le Validator avec les types de bases réellement configurés
        // (par défaut il n'en connaît que 3 ; la config en supporte jusqu'à 6).
        $this->validator->setValidDatabaseTypes(array_keys($this->config->getDatabases()));

        $this->pathFinder = new PathFinder($this->config, $this->logger, $this->environment);
        $this->driverManager = new DriverManager($this->config, $this->logger);
        $this->interactiveMode = new InteractiveMode(
            $this->config,
            $this->logger,
            $this->validator
        );
        $this->nonInteractiveMode = new NonInteractiveMode(
            $this->config,
            $this->logger,
            $this->validator
        );
        $this->runner = new Runner(
            $this->config,
            $this->logger,
            $this->environment,
            $this->pathFinder,
            $this->driverManager,
            $this->processRunner ?? new ProcessRunner()
        );
    }

    /**
     * [Description for checkEnvironment]
     * Vérifie l'environnement (PHP, Java, extensions)
     * @return void
     *
     * Created at: 05/07/2026 09:04:04 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function checkEnvironment(): void
    {
        // Vérifier PHP
        if (!$this->versionChecker->checkRequiredPhpVersion()) {
            $this->logger->warning(
                "PHP 8.1 ou supérieur recommandé (actuel: " . PHP_VERSION . ")"
            );
        }

        // Vérifier le JDK (système, tools/ embarqué, puis config.json)
        $java = $this->pathFinder?->detectJava();
        $schemaspyMajor = explode('.', $this->config->getSchemaspyVersion())[0];
        $requiredJavaMajor = $this->config->get("schemaspy.compatibility.{$schemaspyMajor}");

        if ($java === null) {
            $this->logger->error("JDK introuvable (système, tools/, config.json). Java est obligatoire pour exécuter SchemaSpy.");
        } else {
            $this->logger->info("☕ JDK détecté ({$java['source']}): " . ($java['version'] ?? 'version inconnue'), 'gray');
            if ($requiredJavaMajor !== null && $java['version'] !== null && !version_compare($java['version'], $requiredJavaMajor, '>=')) {
                $this->logger->warning(
                    "SchemaSpy {$schemaspyMajor}.x requiert un JDK {$requiredJavaMajor}+ (JDK détecté: {$java['version']})."
                );
            }
        }

        // Vérifier Graphviz (optionnel, repli automatique sur viz.js)
        $graphviz = $this->pathFinder?->detectGraphviz();
        if ($graphviz !== null) {
            $this->logger->info("📊 Graphviz détecté ({$graphviz['source']}): " . ($graphviz['version'] ?? 'version inconnue'), 'gray');
        } else {
            $this->logger->debug("Graphviz non trouvé, utilisation de viz.js");
        }

        $this->logger->info("🗄️SchemaSpy configuré: " . $this->config->getSchemaspyVersion(), 'gray');

        // Vérifier les extensions PHP
        $missingExtensions = $this->versionChecker->checkExtensions();
        if (!empty($missingExtensions)) {
            $this->logger->warning(
                "Extensions PHP manquantes: " . implode(', ', $missingExtensions)
            );
            $this->logger->info(
                "Installez: " . implode(' ', array_map(fn($e) => "php-{$e}", $missingExtensions))
            );
        }
    }

    /**
     * [Description for setupBasePath]
     * Configure le chemin de base : la racine de l'application elle-même
     * (jar/, jdbc/, tools/, report/ y sont tous relatifs). Plus de scan de
     * lecteurs/dossiers externes : l'application est auto-contenue.
     *
     * @return void
     *
     * Created at: 05/07/2026 09:09:12 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function setupBasePath(): void
    {
        $basePath = $this->basePath ?? dirname(__DIR__, 2);
        $this->config->setBasePath($basePath);
        $this->logger->debug("📁 Base path: {$basePath}");
    }

    /**
     * [Description for collectParameters]
     * Collecte les paramètres (interactif ou non)
     *
     * @return array
     *
     * Created at: 05/07/2026 09:09:30 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function collectParameters(): ?array
    {
        if ($this->argumentParser->isQuiet() || $this->argumentParser->hasParams()) {
            $params = $this->nonInteractiveMode->collect($this->argumentParser->getParams());
            $this->logger->debug("📝 Mode non-interactif");
        } else {
            $params = $this->interactiveMode->collect($this->generationDefaults());
            if ($params === null) {
                $this->logger->warning("Opération annulée.");
                return null;
            }
            $this->interactiveMode->saveLastParams($params);
            $this->logger->debug("📝 Mode interactif");
        }

        return $this->applyGenerationOptions($params);
    }

    /**
     * [Description for generationDefaults]
     * Fusionne les options de génération (config.json "generation" puis ligne de
     * commande) dans les paramètres, et en déduit le moteur de rendu.
     *
     * @return array
     *
     * Created at: 05/10/2026 08:42:04 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function generationDefaults(): array
    {
        return array_merge($this->config->get('generation', []), $this->argumentParser->getOptions());
    }

    /**
     * [Description for applyGenerationOptions]
     *
     * @param array $params
     *
     * @return array
     *
     * Created at: 05/10/2026 08:42:31 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function applyGenerationOptions(array $params): array
    {
        // En mode interactif, les options ont déjà été validées et complétées à l'écran
        $options = $params['options'] ?? GenerationOptions::fromArrays($this->generationDefaults());

        if ($options->markdown && !$this->config->get('schemaspy.markdown_supported', false)) {
            throw new ConfigException(
                "--markdown nécessite le fork de SchemaSpy avec export Markdown : " .
                "renseignez schemaspy.jar / schemaspy.version et schemaspy.markdown_supported=true dans config.json."
            );
        }

        $params['options'] = $options;
        if ($options->engine !== GenerationOptions::ENGINE_AUTO) {
            $params['useVizJs'] = $options->engine === GenerationOptions::ENGINE_VIZJS;
        }

        return $params;
    }

    /**
     * [Description for showBanner]
     * Affiche la bannière
     *
     * @return void
     *
     * Created at: 05/07/2026 09:09:58 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function showBanner(): void
    {
        $this->logger->info(self::BANNER, 'white');
        $this->logger->info(
            "Version: " . $this->config->get('application.version', '1.0.0'),
            'cyan'
        );
        $this->logger->info(
            "Application: " . $this->config->get('application.name', 'ShemaSpy-Cli'), 'cyan'
        );
        $this->logger->info(
            "SchemaSpy: " . $this->config->get('schemaspy.version', '7.0.2'), 'cyan'
        );

        // Afficher le nombre de drivers JDBC trouvés
        if ($this->driverManager !== null) {
            $driverCount = count($this->driverManager->getAvailableDrivers());
            if ($driverCount > 0) {
                $this->logger->info(
                    "Drivers JDBC: {$driverCount} trouvé(s)",
                    'cyan'
                );
            }
        }

        $company = $this->config->get('application.company');
        if ($company) {
            $this->logger->info("Société: {$company}", 'cyan');
        }

        if ($this->logger->getLogFile() !== null) {
            $this->logger->info("📝 Log: " . $this->logger->getLogFile(), 'gray');
        }

        $this->logger->blankLine();
    }

    /**
     * [Description for checkJdbcDrivers]
     * Vérifie les drivers JDBC
     *
     * @return void
     *
     * Created at: 05/07/2026 09:11:13 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function checkJdbcDrivers(): void
    {
        if ($this->driverManager === null) {
            return;
        }

        // Vérifier si le dossier JDBC est vide
        if ($this->driverManager->isJdbcDirectoryEmpty()) {
            $this->logger->warning("Le dossier JDBC est vide !");
            $this->logger->info("Veuillez télécharger les drivers JDBC dans:");
            $this->logger->info(" " . $this->config->getPath('jdbc_folder'));
            $this->logger->blankLine();
            $this->logger->warning("Drivers requis:");

            $jdbcConfig = $this->config->get('jdbc', []);
            foreach ($jdbcConfig as $dbType => $dbConfig) {
                if (isset($dbConfig['driver'], $dbConfig['download_url'])) {
                    $this->logger->info(" - {$dbConfig['driver']} (pour {$dbType})");
                    $this->logger->info("   {$dbConfig['download_url']}");
                }
            }
            return;
        }

        // Afficher le résumé de validation
        if (!$this->logger->isQuiet()) {
            $this->logger->blankLine();
            $summary = $this->driverManager->getValidationSummary();
            $this->logger->info($summary, 'default');
        }

        // Vérifier les drivers manquants
        $missing = $this->driverManager->getMissingDrivers();
        if (!empty($missing)) {
            $this->logger->blankLine();
            $this->logger->warning("Drivers manquants à télécharger:");
            foreach ($missing as $driver) {
                $this->logger->info("  - 📥 {$driver['driver']} (pour {$driver['db_type']})");
                if ($driver['download_url']) {
                    $this->logger->info(" Télécharger: {$driver['download_url']}");
                }
            }
        }
    }

    /**
     * [Description for showFooter]
     * Affiche le pied de page
     *
     * @return void
     *
     * Created at: 05/07/2026 09:48:19 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function showFooter(): void
    {
        $company = $this->config->get('application.company');
        $contact = $this->config->get('application.contact');
        $docsUrl = $this->config->get('docs.url', 'https://github.com/Laurent-hadjadj/shemaspy-cli');

        $this->logger->blankLine();
        $this->logger->separator('═', 50);
        $this->logger->info("   Merci d'avoir utilisé SchemaSpy-Cli", 'white');
        $this->logger->separator('═', 50);
        if ($company) {
            $this->logger->info("{$company}", 'gray');
        }
        if ($contact) {
            $this->logger->info("Contact: {$contact}", 'gray');
        }
        $this->logger->info("Documentation: {$docsUrl}", 'gray');
        $this->logger->blankLine();

    }
}
