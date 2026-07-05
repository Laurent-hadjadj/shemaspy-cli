<?php
/**
 * Application principale
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Cli;

use SchemaSpyCli\Core\{Config, Logger, Environment};
use SchemaSpyCli\Database\{Connection, DriverManager};
use SchemaSpyCli\SchemaSpy\Runner;
use SchemaSpyCli\Utils\{FileSystem, PathFinder, Validator, VersionChecker};
use SchemaSpyCli\Exceptions\{ConfigException, ConnectionException};

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
..   __  __             __  __             _ _            _   _
    |  \/  | __ _      |  \/  | ___  _   _| (_)_ __   ___| |_| |_ ___
    | |\/| |/ _` |_____| |\/| |/ _ \| | | | | | '_ \ / _ \ __| __/ _ \
    | |  | | (_| |_____| |  | | (_) | |_| | | | | | |  __/ |_| ||  __/
    |_|  |_|\__,_|     |_|  |_|\___/ \__,_|_|_|_| |_|\___|\__|\__\___|

    Laurent HADJADJ
    https://github.com/Laurent-hadjadj/ma-moulinette
    © 2015-2026 - CC BY-SA-NC 4.0
BANNER;

    public function __construct()
    {
        // Services sans dépendances
        $this->logger = new Logger();
        $this->argumentParser = new ArgumentParser();
        $this->environment = new Environment();
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

            // 3. Afficher la bannière
            if (!$quietMode) {
                $this->showBanner();
            }

            // 4. Charger la configuration
            $this->loadConfiguration();

            // 5. Initialiser les services dépendants
            $this->initializeServices();

            // 6. Vérifier l'environnement
            if (!$quietMode) {
                $this->checkEnvironment();
            }

            // 7. Vérifier les drivers JDBC
            $this->checkJdbcDrivers();

            // 8. Trouver le chemin de l'environnement
            $this->setupBasePath();

            // 9. Collecter les paramètres
            $params = $this->collectParameters();

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
     * Charge la configuration depuis le fichier
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
                $this->logger->debug("✅ Configuration chargée depuis: {$path}");
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
     * Initialise les services qui dépendent de la configuration
     */
    private function initializeServices(): void
    {
        // Alimenter le Validator avec les types de bases réellement configurés
        // (par défaut il n'en connaît que 3 ; la config en supporte jusqu'à 6).
        $this->validator->setValidDatabaseTypes(array_keys($this->config->getDatabases()));

        $this->pathFinder = new PathFinder($this->config, $this->logger);
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
            $this->driverManager
        );
    }

    /**
     * Vérifie l'environnement (PHP, Java, extensions)
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
            $this->logger->warning("JDK introuvable (système, tools/, config.json). Java est obligatoire pour exécuter SchemaSpy.");
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

        $this->logger->info("🗄️  SchemaSpy configuré: " . $this->config->getSchemaspyVersion(), 'gray');

        // Vérifier les extensions PHP
        $missingExtensions = $this->versionChecker->checkExtensions();
        if (!empty($missingExtensions)) {
            $this->logger->warning(
                "Extensions PHP manquantes: " . implode(', ', $missingExtensions)
            );
            $this->logger->warning(
                "Installez: " . implode(' ', array_map(fn($e) => "php-{$e}", $missingExtensions))
            );
        }
    }

    /**
     * Configure le chemin de base : la racine de l'application elle-même
     * (jar/, jdbc/, tools/, report/ y sont tous relatifs). Plus de scan de
     * lecteurs/dossiers externes : l'application est auto-contenue.
     */
    private function setupBasePath(): void
    {
        $basePath = dirname(__DIR__, 2);
        $this->config->setBasePath($basePath);
        $this->logger->debug("📁 Base path: {$basePath}");
    }

    /**
     * Collecte les paramètres (interactif ou non)
     */
    private function collectParameters(): array
    {
        if ($this->argumentParser->isQuiet() || $this->argumentParser->hasParams()) {
            $params = $this->nonInteractiveMode->collect($this->argumentParser->getParams());
            $this->logger->debug("📝 Mode non-interactif");
        } else {
            $params = $this->interactiveMode->collect();
            if ($params === null) {
                $this->logger->info("Opération annulée.", 'yellow');
                exit(0);
            }
            $this->interactiveMode->saveLastParams($params);
            $this->logger->debug("📝 Mode interactif");
        }

        return $params;
    }

    /**
     * Affiche la bannière
     */
    private function showBanner(): void
    {
        $this->logger->info(self::BANNER, 'cyan');
        $this->logger->info(
            "Version: " . $this->config->get('application.version', '3.0.0'),
            'yellow'
        );
        $this->logger->info(
            "Application: " . $this->config->get('application.name', 'MaMoulinette'),
            'yellow'
        );
        $this->logger->info(
            "SchemaSpy: " . $this->config->get('schemaspy.version', '7.0.2'),
            'yellow'
        );

        // Afficher le nombre de drivers JDBC trouvés
        if ($this->driverManager !== null) {
            $driverCount = count($this->driverManager->getAvailableDrivers());
            if ($driverCount > 0) {
                $this->logger->info(
                    "Drivers JDBC: {$driverCount} trouvé(s)",
                    'yellow'
                );
            }
        }

        $company = $this->config->get('application.company');
        if ($company) {
            $this->logger->info("Société: {$company}", 'yellow');
        }
        $this->logger->blankLine();
    }

    /**
     * Vérifie les drivers JDBC
     */
    private function checkJdbcDrivers(): void
    {
        if ($this->driverManager === null) {
            return;
        }

        // Vérifier si le dossier JDBC est vide
        if ($this->driverManager->isJdbcDirectoryEmpty()) {
            $this->logger->warning("⚠️  Le dossier JDBC est vide !");
            $this->logger->warning("   Veuillez télécharger les drivers JDBC dans:");
            $this->logger->warning("   " . $this->config->getPath('jdbc_folder'));
            $this->logger->warning("");
            $this->logger->warning("   Drivers requis:");

            $jdbcConfig = $this->config->get('jdbc', []);
            foreach ($jdbcConfig as $dbType => $dbConfig) {
                if (isset($dbConfig['driver'], $dbConfig['download_url'])) {
                    $this->logger->warning("   - {$dbConfig['driver']} (pour {$dbType})");
                    $this->logger->warning("     {$dbConfig['download_url']}");
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
            $this->logger->warning("");
            $this->logger->warning("📥 Drivers manquants à télécharger:");
            foreach ($missing as $driver) {
                $this->logger->warning("   - {$driver['driver']} (pour {$driver['db_type']})");
                if ($driver['download_url']) {
                    $this->logger->warning("     Télécharger: {$driver['download_url']}", 'gray');
                }
            }
        }
    }

    /**
     * Affiche le pied de page
     */
    private function showFooter(): void
    {
        $company = $this->config->get('application.company');
        $contact = $this->config->get('application.contact');
        $docsUrl = $this->config->get('docs.url', 'https://github.com/schemaspy/schemaspy');

        $this->logger->separator('═', 50);
        $this->logger->info("   Merci d'avoir utilisé SchemaSpy", 'white');
        $this->logger->separator('═', 50);
        if ($company) {
            $this->logger->info("  {$company}", 'gray');
        }
        if ($contact) {
            $this->logger->info("  Contact: {$contact}", 'gray');
        }
        $this->logger->info("  Documentation: {$docsUrl}", 'gray');
    }
}
