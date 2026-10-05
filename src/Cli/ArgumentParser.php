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

/**
 * [Description ArgumentParser]
 * Parsing des arguments en ligne de commande
 */
final class ArgumentParser
{
    private array $params = [];
    private bool $quiet = false;
    private bool $verbose = false;
    private bool $help = false;
    private ?string $configFile = null;

    // Constantes pour les options
    private const OPTIONS = [
        'quiet'     => ['--quiet', '-q'],
        'verbose'   => ['--verbose', '-v'],
        'help'      => ['--help', '-h'],
        'config'    => '--config=',
    ];

    private array $options = [];

    // Options de génération : drapeau => [clé GenerationOptions, valeur]
    private const GENERATION_FLAGS = [
        '--markdown'   => ['markdown', true],
        '--no-html'    => ['html', false],
        '--no-orphans' => ['orphans', false],
        '--no-views'   => ['views', false],
        '--no-rows'    => ['rows', false],
        '--no-implied' => ['implied', false],
    ];

    private const GENERATION_VALUES = ['engine', 'degree', 'include', 'exclude'];

    // Définition des paramètres attendus
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

    /**
     * [Description for parse]
     * Découverte des informations passées
     *
     * @return void
     *
     * Created at: 05/07/2026 10:12:31 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
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

            // Options de génération (--markdown, --no-html, --engine=...)
            if ($this->parseGenerationOption($arg)) {
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

        // Valide les paramètres après le parsing
        $this->validateParams();
    }

    /**
     * [Description for parseGenerationOption]
     * Options de génération : drapeaux (--markdown, --no-html, ...) et valeurs
     * (--engine=, --degree=, --include=, --exclude=). Stockées à part des
     * paramètres de connexion pour ne pas basculer en mode non-interactif.
     *
     * @param string $arg
     *
     * @return bool true si l'argument a été reconnu
     *
     * Created at: 04/10/2026 22:29:00 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function parseGenerationOption(string $arg): bool
    {
        if (isset(self::GENERATION_FLAGS[$arg])) {
            [$key, $value] = self::GENERATION_FLAGS[$arg];
            $this->options[$key] = $value;
            return true;
        }

        foreach (self::GENERATION_VALUES as $name) {
            $prefix = "--{$name}=";
            if (str_starts_with($arg, $prefix)) {
                $this->options[$name] = trim(substr($arg, strlen($prefix)));
                return true;
            }
        }

        return false;
    }

    /**
     * [Description for getOptions]
     * Options de génération saisies en ligne de commande (clés de GenerationOptions::fromArrays).
     * Le paramètre historique --vizjs=true équivaut à --engine=vizjs.
     *
     * @return array
     *
     * Created at: 04/10/2026 22:28:26 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getOptions(): array
    {
        $options = $this->options;
        if (!isset($options['engine']) && isset($this->params['vizjs'])
            && in_array(strtolower($this->params['vizjs']), ['true', '1', 'yes'], true)) {
            $options['engine'] = 'vizjs';
        }
        return $options;
    }

    /**
     * [Description for parseParameter]
     * Parse un paramètre au format --key=value
     *
     * @param string $arg
     *
     * @return void
     *
     * Created at: 05/07/2026 10:13:13 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function parseParameter(string $arg): void
    {
        $parts = explode('=', substr($arg, 2), 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Vérifie si le paramètre est attendu
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
     * [Description for isOption]
     * Vérifie si une option correspond à une liste d'aliases
     *
     * @param string $arg
     * @param array $aliases
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:14:27 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function isOption(string $arg, array $aliases): bool
    {
        return in_array($arg, $aliases, true);
    }

    /**
     * [Description for validateParams]
     * Valide les paramètres après le parsing
     *
     * @return void
     *
     * Created at: 05/07/2026 10:14:44 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
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

    /**
     * [Description for getParams]
     * Récupère les options et les fusionnent avec les paramètres par défaut
     *
     * @return array
     *
     * Created at: 05/07/2026 10:14:58 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getParams(): array
    {
        // Fusionne avec les valeurs par défaut
        return array_merge(self::EXPECTED_PARAMS, array_filter($this->params, fn($v) => $v !== null));
    }

    /**
     * [Description for getParam]
     * Récupère les paramètre
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     *
     * Created at: 05/07/2026 10:15:56 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? self::EXPECTED_PARAMS[$key] ?? $default;
    }

    /**
     * [Description for hasParams]
     * Retourne un true si le tableau de paramètres n'est pas vide
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:16:15 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    /**
     * [Description for isQuiet]
     * Retourne true si le mode quiet est activé
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:16:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    /**
     * [Description for isVerbose]
     * Retourne true si le mode verbose est activé
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:17:25 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * [Description for isHelp]
     * Retourne true si l'aide est demandée
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:19:28 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isHelp(): bool
    {
        return $this->help;
    }

    /**
     * [Description for getConfigFile]
     * Retourne le chemin du fichier de configuration : config/config.json
     *
     * @return string|null
     *
     * Created at: 05/07/2026 10:19:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getConfigFile(): ?string
    {
        return $this->configFile;
    }

    /**
     * [Description for showWarning]
     * Fonction interne pour affiche un message sans passer par le logger
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 05/07/2026 10:22:28 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function showWarning(string $message): void
    {
        // Utiliser error_log pour ne pas interférer avec la sortie standard
        error_log("[ERROR] ❌  " . $message);
    }

    /**
     * [Description for showHelp]
     * Affiche l'aide en ligne
     *
     * @return void
     *
     * Created at: 05/07/2026 10:23:12 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
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
  --vizjs=true|false      (déprécié) équivaut à --engine=vizjs
  --output=DIR            Dossier de sortie personnalisé (généré automatiquement si non spécifié)

Options de génération:
  --engine=auto|graphviz|vizjs  Moteur des diagrammes [défaut: auto = Graphviz si détecté, sinon viz.js]
  --markdown              Génère aussi une documentation Markdown (<sortie>/markdown/)
  --no-html               Ne génère pas le site HTML (à combiner avec --markdown)
  --no-orphans            Exclut les tables orphelines des diagrammes de relations
  --no-views              Exclut les vues
  --no-rows               N'interroge pas le nombre de lignes des tables (plus rapide)
  --no-implied            Ne cherche pas les relations implicites
  --degree=1|2            Degré de séparation des diagrammes de table [défaut: 2]
  --include=REGEX         Ne garde que les tables correspondant à l'expression
  --exclude=REGEX         Exclut les tables correspondant à l'expression

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
     * [Description for getParamsSummary]
     * Récupère les paramètres sous forme de tableau associatif pour affichage
     *
     * @return string
     *
     * Created at: 05/07/2026 10:24:56 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
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
     * [Description for hasParam]
     * Vérifie si un paramètre spécifique est présent
     *
     * @param string $key
     *
     * @return bool
     *
     * Created at: 05/07/2026 10:25:12 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function hasParam(string $key): bool
    {
        return isset($this->params[$key]);
    }

    /**
     * [Description for validateConsistency]
     * Valide que les paramètres sont cohérents
     *
     * @return array
     *
     * Created at: 05/07/2026 10:27:39 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateConsistency(): array
    {
        $errors = [];

        // Si vizjs est spécifié, vérifie la valeur
        if (isset($this->params['vizjs'])) {
            $value = strtolower($this->params['vizjs']);
            if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                $errors[] = "vizjs doit être true ou false";
            }
        }

        // Vérifie le port
        if (isset($this->params['port'])) {
            $port = (int) $this->params['port'];
            if ($port < 1 || $port > 65535) {
                $errors[] = "Le port doit être compris entre 1 et 65535";
            }
        }

        return $errors;
    }
}
