<?php
/**
 * Exécution de SchemaSpy
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\SchemaSpy;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Core\Environment;
use SchemaSpyCli\Database\Connection;
use SchemaSpyCli\Database\DriverManager;
use SchemaSpyCli\Utils\PathFinder;
use SchemaSpyCli\Exceptions\SchemaSpyException;

final class Runner
{
    private PropertiesGenerator $propertiesGenerator;
    private CommandBuilder $commandBuilder;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly Environment $environment,
        private readonly PathFinder $pathFinder,
        private readonly DriverManager $driverManager
    ) {
        $this->propertiesGenerator = new PropertiesGenerator($config, $environment, $logger, $driverManager);
        $this->commandBuilder = new CommandBuilder($config, $environment, $logger, $driverManager);
    }

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

            $this->logger->info("\n⚙️  Exécution de SchemaSpy...", 'green');
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

            // Exécuter la commande
            putenv("JAVA_HOME={$java['home']}");
            passthru($command, $exitCode);

            if ($exitCode === 0) {
                $this->logger->success("Documentation générée avec succès!");
                $this->logger->info("🌐 Ouvrir: {$outputDir}/index.html", 'cyan');
            } else {
                $this->logger->warning("SchemaSpy a retourné le code: {$exitCode}");
            }

            return $exitCode;
        } finally {
            // Sécurité : supprimer le fichier properties temporaire qui contient
            // le mot de passe de la base de données, quel que soit le résultat.
            $this->cleanupPropertiesFile($propertiesFile);
        }
    }

    /**
     * Supprime le fichier properties temporaire de manière sécurisée.
     */
    private function cleanupPropertiesFile(string $propertiesFile): void
    {
        if ($propertiesFile !== '' && is_file($propertiesFile)) {
            @unlink($propertiesFile);
        }
    }

    private function checkConnection(array $params): bool
    {
        $this->logger->progress("Vérification de la connexion...");

        try {
            $connection = new Connection($params, $this->logger);
            $connection->test();

            $this->logger->success("Connexion réussie");
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
     * Vérifie que le JDK détecté est compatible avec la version majeure de
     * SchemaSpy configurée (ex: SchemaSpy 6 -> JDK 11, SchemaSpy 7 -> JDK 17).
     * La table de correspondance vient de schemaspy.compatibility dans config.json.
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
