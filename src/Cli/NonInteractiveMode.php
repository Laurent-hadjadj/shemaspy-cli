<?php
/**
 * Mode non-interactif de l'application
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Cli;

use SchemaSpyCli\Core\Config;
use SchemaSpyCli\Core\Logger;
use SchemaSpyCli\Utils\Validator;
use SchemaSpyCli\Utils\OutputNameGenerator;
use SchemaSpyCli\Exceptions\ValidationException;

final class NonInteractiveMode
{
    private Config $config;
    private Logger $logger;
    private Validator $validator;
    private OutputNameGenerator $outputNameGenerator;

    public function __construct(Config $config, Logger $logger, Validator $validator)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->outputNameGenerator = new OutputNameGenerator($config);
    }

    public function collect(array $cliParams): array
    {
        // 🔥 Valider les paramètres requis
        $this->validateRequiredParams($cliParams);

        // 🔥 Déterminer le type de base de données
        $dbType = $cliParams['db'] ?? 'postgresql';
        $dbConfig = $this->config->getDatabase($dbType);

        if ($dbConfig === null) {
            $availableTypes = implode(', ', array_keys($this->config->getDatabases()));
            throw new ValidationException(
                "Type de base de données inconnu : {$dbType}. Types disponibles : {$availableTypes}"
            );
        }

        // 🔥 Valider les paramètres individuels
        $this->validateParams($cliParams, $dbConfig);

        // 🔥 Valider la cohérence globale (avec le bon nom de paramètre)
        $this->validateConsistency($cliParams);

        // 🔥 Générer le nom de sortie
        $output = $cliParams['output'] ?? $this->outputNameGenerator->generate($dbType, $cliParams['schema']);

        // 🔥 Valider le nom de sortie
        $this->validator->validateOutputName($output);

        // 🔥 Construire le tableau de paramètres final
        return $this->buildParams($dbType, $dbConfig, $cliParams, $output);
    }

    /**
     * Valide les paramètres requis
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
     * Valide les paramètres individuels
     */
    private function validateParams(array $params, array $dbConfig): void
    {
        $this->validator->validateDatabaseType($params['db']);
        $this->validator->validateHost($params['host']);

        $port = $params['port'] ?? $dbConfig['port'];
        $this->validator->validatePort($port);

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
     * 🔥 Valide la cohérence des paramètres (version corrigée)
     */
    private function validateConsistency(array $params): void
    {
        // Vérifier que le type de base de données existe (paramètre 'db' en mode non-interactif)
        if (!isset($params['db']) || empty($params['db'])) {
            throw new ValidationException("Le type de base de données est requis (--db)");
        }

        // Vérifier que le host est valide
        if ($params['host'] === 'localhost') {
            // localhost est valide
        }

        // Vérifier que le schéma n'est pas réservé
        $reservedSchemas = ['', 'information_schema', 'pg_catalog'];
        if (isset($params['schema']) && in_array(strtolower($params['schema']), $reservedSchemas)) {
            // On autorise quand même, juste un warning
            $this->logger->warning("Le schéma '{$params['schema']}' est un schéma système");
        }
    }

    /**
     * Construit le tableau de paramètres final
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
