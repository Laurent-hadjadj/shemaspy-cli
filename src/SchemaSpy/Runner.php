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
use SchemaSpyCli\Database\Connection;
use SchemaSpyCli\Database\DriverManager;
use SchemaSpyCli\Utils\{PathFinder, ProcessRunner};
use SchemaSpyCli\Exceptions\SchemaSpyException;

/**
 * [Description Runner]
 * Exécution de SchemaSpy : vérifie la connexion, génère le fichier properties, lance le JAR et relaie sa sortie.
 */
final class Runner
{
    private PropertiesGenerator $propertiesGenerator;
    private CommandBuilder $commandBuilder;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly Environment $environment,
        private readonly PathFinder $pathFinder,
        private readonly DriverManager $driverManager,
        private readonly ProcessRunner $processRunner = new ProcessRunner(),
        private readonly ?\Closure $pdoFactory = null
    ) {
        $this->propertiesGenerator = new PropertiesGenerator($config, $environment, $logger, $driverManager, $pathFinder);
        $this->commandBuilder = new CommandBuilder($config, $environment, $logger, $driverManager);
    }

    /**
     * [Description for execute]
     *
     * @param array $params
     *
     * @return int
     *
     * Created at: 04/10/2026 22:51:09 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function execute(array $params): int
    {
        // Vérifier que le driver existe
        $driverPath = $this->driverManager->getDriverPath($params['dbType']);
        if ($driverPath === null) {
            $this->logger->error(
                "Driver JDBC non trouvé pour {$params['dbType']}. " .
                "Vérifiez que le fichier est présent dans le dossier JDBC."
            );
            return 1;
        }

        // Vérifier la connexion
        if (!$this->checkConnection($params)) {
            return 1;
        }

        // Préparer l'exécution
        $baseDir = $this->config->getBasePath() ?? getcwd();
        $jarFile = $baseDir . '/' . $this->config->getSchemaspyJar();
        $outputDir = $baseDir . '/' . $this->config->get('paths.output_folder', 'report') . '/' . $params['output'];

        if (!file_exists($jarFile)) {
            throw new SchemaSpyException("Fichier introuvable: {$jarFile}");
        }

        // Trouver Java (obligatoire : système, tools/ embarqué, puis config.json)
        $java = $this->pathFinder->detectJava();
        if ($java === null) {
            throw new SchemaSpyException(
                "JDK introuvable (système, dossier embarqué, config.json). " .
                "Java est obligatoire pour exécuter SchemaSpy."
            );
        }
        $this->checkSchemaspyCompatibility($java['version']);

        // Générer le fichier properties
        $propertiesFile = $this->propertiesGenerator->generate($params, $outputDir);

        try {
            // Construire la commande (le classpath est géré dans CommandBuilder)
            $command = $this->commandBuilder->build($java['executable'], $jarFile, $propertiesFile, $params);

            $this->logger->blankLine();
            $this->logger->info("⚙️ Exécution de SchemaSpy...", 'green');
            $this->logger->info("📁 Sortie: {$outputDir}", 'gray');

            if (!$this->logger->isQuiet()) {
                $this->logger->info(
                    "☕ JDK détecté ({$java['source']}): " . ($java['version'] ?? 'version inconnue'),
                    'gray'
                );
                $this->logger->info("🗄️  SchemaSpy: " . $this->config->getSchemaspyVersion(), 'gray');
                $this->logger->info("📝 Fichier de configuration: {$propertiesFile}", 'gray');
                $this->logger->info("🔗 Driver JDBC: {$params['dbConfig']['driver']}", 'gray');
                $this->logger->info("📦 Chemin du driver: {$driverPath}", 'gray');
            }

            // viz.js (JavaScript embarqué) est très lent sur les grands schémas : on prévient avant
            // de lancer une analyse qui pourrait durer des heures (cas observé : 300 tables, > 30 min).
            if ($params['useVizJs'] ?? false) {
                $this->logger->warning(
                    "Moteur viz.js : très lent et gourmand en mémoire au-delà de quelques dizaines de tables. " .
                    "Pour un grand schéma, utilisez Graphviz (--engine=graphviz, ou installez-le dans tools/)."
                );
            }

            // Exécuter la commande
            putenv("JAVA_HOME={$java['home']}");
            $exitCode = $this->runSchemaSpy($command);

            if ($exitCode === 0) {
                $this->logger->blankLine();
                $this->logger->success("Documentation générée avec succès!");
                $options = $params['options'] ?? null;
                if ($options === null || $options->html) {
                    $this->logger->info("🌐 Ouvrir: {$outputDir}/index.html", 'cyan');
                }
                if ($options !== null && $options->markdown) {
                    $this->logger->info("📝 Markdown: {$outputDir}/markdown/", 'cyan');
                }
            } else {
                $this->logger->blankLine();
                $this->logger->error("SchemaSpy a retourné le code: {$exitCode}");
            }

            return $exitCode;
        } finally {
            // Sécurité : supprimer le fichier properties temporaire qui contient
            // le mot de passe de la base de données, quel que soit le résultat.
            $this->cleanupPropertiesFile($propertiesFile);
        }
    }

    /**
     * [Description for runSchemaSpy]
     * Lance SchemaSpy en relayant sa sortie. Hors --verbose, le bruit répétitif des
     * avertissements Graphviz est masqué et résumé (trace complète dans le fichier de log).
     *
     * @param string $command
     *
     * @return int
     *
     * Created at: 04/10/2026 22:51:20 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function runSchemaSpy(string $command): int
    {
        $filter = $this->logger->isVerbose()
            ? null
            : new OutputFilter(fn(string $line) => $this->logger->logOnly($line));

        $exitCode = $this->processRunner->run(
            $command,
            function (string $text) use ($filter): void {
                $shown = $filter?->filter($text) ?? $text;
                echo $shown;

                // Trace de la progression dans le fichier de log (les lignes masquées par le filtre
                // y sont déjà écrites par lui) : permet de suivre une analyse longue sans la console.
                $line = trim($shown);
                if ($line !== '' && preg_match('/^\.+$/', $line) !== 1) {
                    $this->logger->logOnly($line);
                }
            }
        );

        $suppressed = $filter?->getSuppressedCount() ?? 0;
        if ($suppressed > 0) {
            $this->logger->blankLine();
            $this->logger->info(
                "{$suppressed} avertissement(s) Graphviz sans incidence masqué(s) " .
                "(détail dans le fichier de log ou avec --verbose).",
                'gray'
            );
        }

        return $exitCode;
    }

    /**
     * [Description for cleanupPropertiesFile]
     * Supprime le fichier properties temporaire de manière sécurisée.
     *
     * @param string $propertiesFile
     *
     * @return void
     *
     * Created at: 04/10/2026 22:51:36 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function cleanupPropertiesFile(string $propertiesFile): void
    {
        if ($propertiesFile !== '' && is_file($propertiesFile)) {
            @unlink($propertiesFile);
        }
    }

    /**
     * [Description for checkConnection]
     *
     * @param array $params
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:51:49 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function checkConnection(array $params): bool
    {
        $this->logger->progress("Vérification de la connexion...");

        try {
            $connection = new Connection($params, $this->logger, $this->pdoFactory);
            $connection->test();

            if ($connection->getMode() === Connection::MODE_TCP) {
                // Extension PDO absente (ex: pdo_oci) : seule l'accessibilité réseau est vérifiée,
                // l'authentification sera validée par SchemaSpy via JDBC.
                $this->logger->warning(
                    "Extension PHP pdo_" . $connection->getPdoDriverName() . " absente : " .
                    "serveur joignable, identifiants non testés (validés par SchemaSpy/JDBC)."
                );
            } else {
                $this->logger->success("Connexion réussie");
            }
            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                "Impossible de se connecter à {$params['host']}:{$params['port']} ({$params['dbType']}). " .
                "Vérifiez que le serveur est démarré et accessible."
            );
            // Détail technique (SQLSTATE, driver...) : visible en --verbose, toujours dans le fichier de log.
            $this->logger->debug("Détail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * [Description for checkSchemaspyCompatibility]
     * Vérifie que le JDK détecté est compatible avec la version majeure de
     * SchemaSpy configurée (ex: SchemaSpy 6 -> JDK 11, SchemaSpy 7 -> JDK 17).
     * La table de correspondance vient de schemaspy.compatibility dans config.json.
     *
     * @param string|null $javaVersion
     *
     * @return void
     *
     * Created at: 04/10/2026 22:52:02 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function checkSchemaspyCompatibility(?string $javaVersion): void
    {
        $schemaspyMajor = explode('.', $this->config->getSchemaspyVersion())[0];
        $requiredJavaMajor = $this->config->get("schemaspy.compatibility.{$schemaspyMajor}");

        if ($requiredJavaMajor === null || $javaVersion === null) {
            // Pas de règle connue pour cette version, ou version JDK non détectable : on ne bloque pas.
            return;
        }

        if (!version_compare($javaVersion, $requiredJavaMajor, '>=')) {
            throw new SchemaSpyException(
                "SchemaSpy {$schemaspyMajor}.x requiert un JDK {$requiredJavaMajor}+ " .
                "(JDK détecté: {$javaVersion})."
            );
        }
    }
}
