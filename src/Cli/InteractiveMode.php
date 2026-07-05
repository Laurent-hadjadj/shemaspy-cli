<?php
/**
 * Mode interactif de l'application
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Cli;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Utils\Validator;
use SchemaSpyCli\Utils\PathFinder;
use SchemaSpyCli\Utils\OutputNameGenerator;

final class InteractiveMode
{
    private PathFinder $pathFinder;
    private OutputNameGenerator $outputNameGenerator;
    private array $lastParams = [];

    // 🔥 Couleurs pour l'affichage
    private const COLORS = [
        'highlight' => 'cyan',
        'label' => 'gray',
        'value' => 'white',
        'success' => 'green',
        'warning' => 'yellow',
        'error' => 'red',
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

    public function collect(): ?array
    {
        $this->logger->blankLine();

        // Sélection du type de base de données
        $dbType = $this->selectDatabaseType();
        $dbConfig = $this->config->getDatabase($dbType);

        // Collecte des paramètres
        $params = $this->collectConnectionParams($dbType, $dbConfig);

        // Afficher le récapitulatif
        $this->showSummary($params);

        // Demander confirmation
        if (!$this->confirm()) {
            $this->logger->info("Opération annulée.", 'yellow');
            return null;
        }

        return $params;
    }

    private function selectDatabaseType(): string
    {
        $this->logger->title("Type de base de données");

        $databases = $this->config->getDatabases();
        $dbTypes = array_keys($databases);
        $defaultDb = $this->lastParams['dbType'] ?? 'postgresql';
        $defaultIndex = array_search($defaultDb, $dbTypes) + 1;

        // 🔥 Afficher les types avec plus d'informations
        foreach ($dbTypes as $i => $type) {
            $selected = ($i + 1 === $defaultIndex) ? ' [✓]' : '';
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
        $this->logger->info("✅ Type sélectionné: {$selectedType}", 'green');
        $this->logger->blankLine();

        return $selectedType;
    }

    private function collectConnectionParams(string $dbType, array $dbConfig): array
    {
        $this->logger->title("Informations de connexion ({$dbType})");

        // 🔥 Charger les valeurs par défaut depuis les derniers paramètres
        $defaultHost = $this->lastParams['host'] ?? $this->config->get('defaults.host', 'localhost');
        $defaultPort = $this->lastParams['port'] ?? $dbConfig['port'];
        $defaultDatabase = $this->lastParams['database'] ?? '';
        $defaultSchema = $this->lastParams['schema'] ?? '';
        $defaultUser = $this->lastParams['user'] ?? '';

        // 🔥 Afficher les informations du driver
        $this->logger->info("📦 Driver: " . ($dbConfig['driver'] ?? 'N/A'), 'gray');
        $this->logger->info("📌 Version SGBD: " . ($dbConfig['db_version'] ?? 'N/A'), 'gray');
        $this->logger->blankLine();

        // 🔥 Collecte avec validation
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
     * 🔥 Prompt avec validation
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
                $this->logger->warning("⚠️  " . $e->getMessage());
                if ($attempts >= $maxAttempts) {
                    $this->logger->warning("Nombre maximum d'essais atteint. Utilisation de la valeur par défaut: {$default}");
                    return $default;
                }
                $this->logger->info("Veuillez réessayer ({$attempts}/{$maxAttempts})", 'gray');
            }
        } while (true);
    }

    private function detectVizJs(): bool
    {
        $graphviz = $this->pathFinder->detectGraphviz();
        $useVizJs = $graphviz === null;

        if ($useVizJs) {
            $this->logger->info("ℹ️  Graphviz non trouvé, utilisation de viz.js", 'gray');
        } else {
            $version = $graphviz['version'] ?? 'version inconnue';
            $this->logger->info("✅ Graphviz trouvé ({$graphviz['source']}): {$version}", 'green');
        }

        return $useVizJs;
    }

    private function showSummary(array $params): void
    {
        $this->logger->title("Récapitulatif");

        // 🔥 Tableau des informations
        $rows = [
            ['Type', $params['dbType']],
            ['Driver', $params['dbConfig']['driver'] ?? 'N/A'],
            ['Host', "{$params['host']}:{$params['port']}"],
            ['Base', $params['database']],
            ['Schéma', $params['schema']],
            ['Utilisateur', $params['user']],
            ['Dossier sortie', $params['output']],
            ['Viz.js', $params['useVizJs'] ? 'Oui ✅' : 'Non ❌'],
        ];

        foreach ($rows as $row) {
            $this->logger->info(
                sprintf("  %-12s: %s", $row[0], $row[1]),
                'gray'
            );
        }
    }

    private function confirm(): bool
    {
        $this->logger->blankLine();
        return $this->logger->promptConfirmation("\nConfirmer la génération ?", true);
    }

    private function loadLastParams(): void
    {
        $lastPath = dirname(__DIR__, 2) . '/' . 'schemaspy.last.json';
        if (file_exists($lastPath)) {
            $content = file_get_contents($lastPath);
            $this->lastParams = json_decode($content, true) ?? [];

            // 🔥 Afficher les derniers paramètres si disponibles
            if (!empty($this->lastParams) && isset($this->lastParams['last_used'])) {
                $this->logger->debug(
                    "📂 Derniers paramètres du " . $this->lastParams['last_used'],
                    'gray'
                );
            }
        }
    }

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

        $this->logger->debug("💾 Paramètres sauvegardés", 'gray');
    }

    /**
     * 🔥 Méthode utilitaire pour afficher une progression pendant la collecte
     */
    public function showCollectProgress(string $step, int $current, int $total): void
    {
        $this->logger->progressBar($current, $total, "📥 {$step}");
    }
}
