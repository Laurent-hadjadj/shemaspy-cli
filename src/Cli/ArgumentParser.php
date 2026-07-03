<?php
/**
 * Parsing des arguments en ligne de commande
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Cli;

final class ArgumentParser
{
    private array $params = [];
    private bool $quiet = false;
    private bool $verbose = false;
    private bool $help = false;
    private ?string $configFile = null;

    // 🔥 Constantes pour les options
    private const OPTIONS = [
        'quiet'     => ['--quiet', '-q'],
        'verbose'   => ['--verbose', '-v'],
        'help'      => ['--help', '-h'],
        'config'    => '--config=',
    ];

    // 🔥 Définition des paramètres attendus
    private const EXPECTED_PARAMS = [
        'db'       => 'postgresql',
        'host'     => null,
        'port'     => null,
        'database' => null,
        'schema'   => null,
        'user'     => null,
        'password' => null,
        'vizjs'    => 'false',
        'output'   => null,
    ];

    public function parse(): void
    {
        global $argv;

        for ($i = 1; $i < count($argv); $i++) {
            $arg = $argv[$i];

            // Options booléennes
            if ($this->isOption($arg, self::OPTIONS['quiet'])) {
                $this->quiet = true;
                continue;
            }

            if ($this->isOption($arg, self::OPTIONS['verbose'])) {
                $this->verbose = true;
                continue;
            }

            if ($this->isOption($arg, self::OPTIONS['help'])) {
                $this->help = true;
                $this->showHelp();
                exit(0);
            }

            // Options avec valeur
            if (str_starts_with($arg, self::OPTIONS['config'])) {
                $this->configFile = substr($arg, strlen(self::OPTIONS['config']));
                continue;
            }

            // Paramètres --key=value
            if (str_starts_with($arg, '--')) {
                $this->parseParameter($arg);
                continue;
            }

            // Si on arrive ici, c'est un paramètre inconnu
            $this->showWarning("Option inconnue: {$arg}");
        }

        // Valider les paramètres après le parsing
        $this->validateParams();
    }

    /**
     * Parse un paramètre au format --key=value
     */
    private function parseParameter(string $arg): void
    {
        $parts = explode('=', substr($arg, 2), 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // 🔥 Vérifier si le paramètre est attendu
            if (array_key_exists($key, self::EXPECTED_PARAMS)) {
                $this->params[$key] = $value;
            } else {
                $this->showWarning("Paramètre inconnu: {$key}");
            }
        } else {
            $this->showWarning("Format invalide: {$arg}. Utilisez --key=value");
        }
    }

    /**
     * Vérifie si une option correspond à une liste d'aliases
     */
    private function isOption(string $arg, array $aliases): bool
    {
        return in_array($arg, $aliases, true);
    }

    /**
     * Valide les paramètres après le parsing
     */
    private function validateParams(): void
    {
        // Si des paramètres sont présents, vérifier les requis
        if (!empty($this->params)) {
            $required = ['host', 'database', 'schema', 'user', 'password'];
            $missing = [];

            foreach ($required as $field) {
                if (!isset($this->params[$field]) || $this->params[$field] === '') {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                $this->showWarning(
                    "Paramètres requis manquants: " . implode(', ', $missing) . "\n" .
                    "Utilisez --help pour voir la liste complète des options."
                );
            }
        }
    }

    public function getParams(): array
    {
        // 🔥 Fusionner avec les valeurs par défaut
        return array_merge(self::EXPECTED_PARAMS, array_filter($this->params, fn($v) => $v !== null));
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? self::EXPECTED_PARAMS[$key] ?? $default;
    }

    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function isHelp(): bool
    {
        return $this->help;
    }

    public function getConfigFile(): ?string
    {
        return $this->configFile;
    }

    private function showWarning(string $message): void
    {
        // Utiliser error_log pour ne pas interférer avec la sortie standard
        error_log("⚠️  " . $message);
    }

    public function showHelp(): void
    {
        $help = <<<'HELP'
SchemaSpy CLI - Générateur de documentation de bases de données

Utilisation:
  php bin/schemaspy [options]

Options:
  --help, -h              Affiche cette aide
  --quiet, -q             Mode silencieux (pas de sortie interactive)
  --verbose, -v           Mode verbeux (affiche plus de détails)
  --config=FILE           Fichier de configuration (défaut: config/config.json)

  --db=TYPE               Type de base de données (postgresql, oracle, mysql) [défaut: postgresql]
  --host=HOST             Hôte de la base de données
  --port=PORT             Port de la base de données
  --database=DB           Nom de la base de données
  --schema=SCHEMA         Nom du schéma
  --user=USER             Utilisateur de la base de données
  --password=PASSWORD     Mot de passe de la base de données
  --vizjs=true|false      Utiliser viz.js (true) ou Graphviz (false) [défaut: false]
  --output=DIR            Dossier de sortie personnalisé (généré automatiquement si non spécifié)

Exemples:
  # Mode interactif
  php bin/schemaspy

  # Mode non-interactif - PostgreSQL
  php bin/schemaspy --quiet --db=postgresql --host=localhost --database=demo --schema=public --user=postgres --password=xxx

  # Mode non-interactif - MySQL
  php bin/schemaspy --quiet --db=mysql --host=localhost --database=demo --schema=demo --user=root --password=xxx

  # Mode non-interactif - Oracle
  php bin/schemaspy --quiet --db=oracle --host=localhost --database=XE --schema=SYSTEM --user=system --password=xxx

  # Avec configuration personnalisée
  php bin/schemaspy --config=config/prod.json

  # Avec mode verbeux pour le debug
  php bin/schemaspy --verbose --db=postgresql --host=localhost --database=demo --schema=public --user=postgres --password=xxx

HELP;
        echo $help;
    }

    /**
     * 🔥 Récupère les paramètres sous forme de tableau associatif pour affichage
     */
    public function getParamsSummary(): string
    {
        if (empty($this->params)) {
            return "Aucun paramètre spécifié (mode interactif)";
        }

        $summary = [];
        foreach ($this->params as $key => $value) {
            if ($key === 'password') {
                $value = '******';
            }
            $summary[] = "{$key}={$value}";
        }

        return implode(' ', $summary);
    }

    /**
     * 🔥 Vérifie si un paramètre spécifique est présent
     */
    public function hasParam(string $key): bool
    {
        return isset($this->params[$key]);
    }

    /**
     * 🔥 Valide que les paramètres sont cohérents
     */
    public function validateConsistency(): array
    {
        $errors = [];

        // Si vizjs est spécifié, vérifier la valeur
        if (isset($this->params['vizjs'])) {
            $value = strtolower($this->params['vizjs']);
            if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                $errors[] = "vizjs doit être true ou false";
            }
        }

        // Vérifier le port
        if (isset($this->params['port'])) {
            $port = (int) $this->params['port'];
            if ($port < 1 || $port > 65535) {
                $errors[] = "Le port doit être compris entre 1 et 65535";
            }
        }

        return $errors;
    }
}
