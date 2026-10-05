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

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Utils\Validator;
use SchemaSpyCli\Utils\OutputNameGenerator;
use SchemaSpyCli\Exceptions\ValidationException;

/**
 * [Description NonInteractiveMode]
 * Mode non-interactif de l'application
 */
final class NonInteractiveMode
{
    private OutputNameGenerator $outputNameGenerator;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly Validator $validator
    ) {
        $this->outputNameGenerator = new OutputNameGenerator($config);
    }

    /**
     * [Description for collect]
     *
     * @param array $cliParams
     *
     * @return array
     *
     * Created at: 05/10/2026 08:47:13 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function collect(array $cliParams): array
    {
        // Valider les paramètres requis
        $this->validateRequiredParams($cliParams);

        // Déterminer le type de base de données
        $dbType = $cliParams['db'] ?? 'postgresql';
        $cliParams['db'] = $dbType;
        $dbConfig = $this->config->getDatabase($dbType);

        if ($dbConfig === null) {
            $availableTypes = implode(', ', array_keys($this->config->getDatabases()));
            throw new ValidationException(
                "Type de base de données inconnu : {$dbType}. Types disponibles : {$availableTypes}"
            );
        }

        // Valider les paramètres individuels
        $this->validateParams($cliParams, $dbConfig);

        // Valider la cohérence globale (avec le bon nom de paramètre)
        $this->validateConsistency($cliParams);

        // Générer le nom de sortie
        $output = $cliParams['output'] ?? $this->outputNameGenerator->generate($dbType, $cliParams['schema']);

        // Valider le nom de sortie
        $this->validator->validateOutputName($output);

        // Construire le tableau de paramètres final
        return $this->buildParams($dbType, $dbConfig, $cliParams, $output);
    }

    /**
     * [Description for validateRequiredParams]
     * Valide les paramètres requis
     *
     * @param array $params
     *
     * @return void
     *
     * Created at: 05/10/2026 08:47:39 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function validateRequiredParams(array $params): void
    {
        $required = ['host', 'database', 'schema', 'user', 'password'];
        $missing = [];

        foreach ($required as $field) {
            if (!isset($params[$field]) || $params[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new ValidationException(
                "Paramètres requis manquants : " . implode(', ', $missing) . "\n" .
                "Utilisez --help pour voir la liste complète des options."
            );
        }

        $this->logger->debug("✅ Tous les paramètres requis sont présents");
    }

    /**
     * [Description for validateParams]
     * Valide les paramètres individuels
     *
     * @param array $params
     * @param array $dbConfig
     *
     * @return void
     *
     * Created at: 05/10/2026 08:47:57 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function validateParams(array $params, array $dbConfig): void
    {
        $this->validator->validateDatabaseType($params['db']);
        $this->validator->validateHost($params['host']);

        $port = $params['port'] ?? $dbConfig['port'];
        if (!is_int($port) && !(is_string($port) && ctype_digit($port))) {
            throw new ValidationException(
                'Port invalide : ' . (is_scalar($port) ? (string) $port : gettype($port)) .
                '. Doit être un nombre entre 1 et 65535'
            );
        }
        $this->validator->validatePort((int) $port);

        $this->validator->validateDatabase($params['database']);
        $this->validator->validateSchema($params['schema']);
        $this->validator->validateUser($params['user']);
        $this->validator->validatePassword($params['password']);

        // Valider vizjs si présent
        if (isset($params['vizjs'])) {
            $value = strtolower($params['vizjs']);
            if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                throw new ValidationException(
                    "vizjs doit être 'true' ou 'false'. Valeur actuelle : {$params['vizjs']}"
                );
            }
        }

        // Valider le nom de sortie s'il est spécifié
        if (isset($params['output'])) {
            $this->validator->validateOutputName($params['output']);
        }

        $this->logger->debug("✅ Tous les paramètres sont valides");
    }

    /**
     * [Description for validateConsistency]
     * Valide la cohérence des paramètres (version corrigée)
     *
     * @param array $params
     *
     * @return void
     *
     * Created at: 05/10/2026 08:48:14 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function validateConsistency(array $params): void
    {
        // (le type de base est déjà validé par collect() : inconnu ou vide => exception)

        // Un schéma système est autorisé, avec un simple avertissement
        $reservedSchemas = ['', 'information_schema', 'pg_catalog'];
        if (isset($params['schema']) && in_array(strtolower($params['schema']), $reservedSchemas)) {
            $this->logger->warning("Le schéma '{$params['schema']}' est un schéma système");
        }
    }

    /**
     * [Description for buildParams]
     * Construit le tableau de paramètres final
     *
     * @param string $dbType
     * @param array $dbConfig
     * @param array $params
     * @param string $output
     *
     * @return array
     *
     * Created at: 05/10/2026 08:48:29 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function buildParams(string $dbType, array $dbConfig, array $params, string $output): array
    {
        $useVizJs = isset($params['vizjs']) && in_array(strtolower($params['vizjs']), ['true', '1', 'yes']);

        $finalParams = [
            'dbType'   => $dbType,
            'dbConfig' => $dbConfig,
            'host'     => $params['host'],
            'database' => $params['database'],
            'schema'   => $params['schema'],
            'port'     => (int) ($params['port'] ?? $dbConfig['port']),
            'user'     => $params['user'],
            'password' => $params['password'],
            'output'   => $output,
            'useVizJs' => $useVizJs,
        ];

        $this->logger->debug("📝 Paramètres collectés: " . $this->getParamsSummary($finalParams));

        return $finalParams;
    }

    /**
     * [Description for getParamsSummary]
     *
     * @param array $params
     *
     * @return string
     *
     * Created at: 05/10/2026 08:48:44 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function getParamsSummary(array $params): string
    {
        $summary = [];
        foreach ($params as $key => $value) {
            if ($key === 'password') {
                $value = '******';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = 'Array';
            }
            $summary[] = "{$key}={$value}";
        }
        return implode(' ', $summary);
    }
}
