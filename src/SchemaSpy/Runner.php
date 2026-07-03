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
    private Config $config;
    private Logger $logger;
    private Environment $environment;
    private PathFinder $pathFinder;
    private DriverManager $driverManager;
    private PropertiesGenerator $propertiesGenerator;
    private CommandBuilder $commandBuilder;

    public function __construct(
        Config $config,
        Logger $logger,
        Environment $environment,
        PathFinder $pathFinder,
        DriverManager $driverManager
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->environment = $environment;
        $this->pathFinder = $pathFinder;
        $this->driverManager = $driverManager;
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
        $schemaSpyDir = $this->config->getPath('schemaspy_folder');
        $jarFile = $schemaSpyDir . '/' . $this->config->getSchemaspyJar();
        $outputDir = $schemaSpyDir . '/' . $this->config->get('paths.output_folder', 'SCHEMA') . '/' . $params['output'];

        if (!file_exists($jarFile)) {
            throw new SchemaSpyException("Fichier introuvable: {$jarFile}");
        }

        // Trouver Java
        $javaHome = $this->findJavaHome();
        $javaExe = $this->findJavaExecutable($javaHome);

        // Générer le fichier properties
        $propertiesFile = $this->propertiesGenerator->generate($params, $outputDir);

        try {
            // Construire la commande (le classpath est géré dans CommandBuilder)
            $command = $this->commandBuilder->build($javaExe, $jarFile, $propertiesFile, $params);

            $this->logger->info("\n⚙️  Exécution de SchemaSpy...", 'green');
            $this->logger->info("📁 Sortie: {$outputDir}", 'gray');

            if (!$this->logger->isQuiet()) {
                $this->logger->info("📝 Fichier de configuration: {$propertiesFile}", 'gray');
                $this->logger->info("🔗 Driver JDBC: {$params['dbConfig']['driver']}", 'gray');
                $this->logger->info("📦 Chemin du driver: {$driverPath}", 'gray');
            }

            // Exécuter la commande
            putenv("JAVA_HOME={$javaHome}");
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
            $this->logger->error("Échec de la connexion: " . $e->getMessage());
            return false;
        }
    }

    private function findJavaHome(): string
    {
        $javaHome = $this->environment->getJavaHome();

        if ($javaHome !== null) {
            return $javaHome;
        }

        // Vérifier dans le dossier embarqué
        $javaPath = $this->config->getPath('java_folder');
        if (is_dir($javaPath)) {
            return $javaPath;
        }

        throw new SchemaSpyException(
            "Java introuvable. Veuillez installer Java ou définir JAVA_HOME."
        );
    }

    private function findJavaExecutable(string $javaHome): string
    {
        $javaExe = $this->environment->getJavaExecutable($javaHome);

        if (file_exists($javaExe)) {
            return $javaExe;
        }

        // Fallback sur java du PATH
        return 'java';
    }
}
