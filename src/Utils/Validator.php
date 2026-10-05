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

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Exceptions\ValidationException;

/**
 * [Description Validator]
 * Validation des paramètres
 */
final class Validator
{
    /**
     * Types de base de données valides.
     * Valeur par défaut (3 types) conservée pour la rétrocompatibilité ;
     * peut être remplie dynamiquement depuis Config::getDatabases() via
     * setValidDatabaseTypes().
     */
    private array $validDatabaseTypes = ['postgresql', 'oracle', 'mysql'];

    // Noms de schémas réservés

    /**
     * [Description for setValidDatabaseTypes]
     * Définit dynamiquement la liste des types de bases de données valides,
     * typiquement à partir des clés de la section "jdbc" du fichier de config.
     *
     * @param string[] $types
     *
     * @return void
     *
     * Created at: 05/10/2026 09:06:01 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function setValidDatabaseTypes(array $types): void
    {
        $this->validDatabaseTypes = array_values($types);
    }

    /**
     * [Description for getValidDatabaseTypes]
     * Retourne la liste des types de bases de données actuellement valides.
     *
     * @return string[]
     *
     * Created at: 05/10/2026 09:06:39 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getValidDatabaseTypes(): array
    {
        return $this->validDatabaseTypes;
    }

    /**
     * [Description for validateDatabaseType]
     *
     * @param string $type
     *
     * @return void
     *
     * Created at: 05/10/2026 09:06:59 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateDatabaseType(string $type): void
    {
        if (!in_array($type, $this->validDatabaseTypes)) {
            throw new ValidationException(
                "Type de base de données invalide: {$type}. " .
                "Types acceptés: " . implode(', ', $this->validDatabaseTypes)
            );
        }
    }

    /**
     * [Description for validateHost]
     *
     * @param string $host
     *
     * @return void
     *
     * Created at: 05/10/2026 09:07:08 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateHost(string $host): void
    {
        if (empty($host)) {
            throw new ValidationException("Le nom d'hôte ne peut pas être vide");
        }

        // IP ou hostname simple
        if (!preg_match('/^[a-zA-Z0-9\-\.]+$/', $host)) {
            throw new ValidationException("Nom d'hôte invalide: {$host}");
        }
    }

    /**
     * [Description for validatePort]
     *
     * @param int $port
     *
     * @return void
     *
     * Created at: 05/10/2026 09:07:14 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validatePort(int $port): void
    {
        if ($port < 1 || $port > 65535) {
            throw new ValidationException("Port invalide: {$port}. Doit être entre 1 et 65535");
        }
    }

    /**
     * [Description for validateDatabase]
     *
     * @param string $database
     *
     * @return void
     *
     * Created at: 05/10/2026 09:07:30 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateDatabase(string $database): void
    {
        if (empty($database)) {
            throw new ValidationException("Le nom de la base de données ne peut pas être vide");
        }

        // Lettres, chiffres, _ - et . : un nom de service Oracle porte souvent un domaine
        // (ex: FONDS.exemple.fr). Le point ne peut ni commencer ni finir le nom, ni être doublé.
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $database)
            || str_starts_with($database, '.')
            || str_ends_with($database, '.')
            || str_contains($database, '..')
        ) {
            throw new ValidationException("Nom de base de données invalide: {$database}");
        }

        // Vérifier la longueur
        if (strlen($database) > 64) {
            throw new ValidationException("Le nom de la base de données est trop long (max 64 caractères)");
        }
    }

    /**
     * [Description for validateSchema]
     *
     * @param string $schema
     *
     * @return void
     *
     * Created at: 05/10/2026 09:07:35 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateSchema(string $schema): void
    {
        if (empty($schema)) {
            throw new ValidationException("Le nom du schéma ne peut pas être vide");
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $schema)) {
            throw new ValidationException("Nom de schéma invalide: {$schema}");
        }

        // Vérifier la longueur
        if (strlen($schema) > 63) {
            throw new ValidationException("Le nom du schéma est trop long (max 63 caractères)");
        }
    }

    /**
     * [Description for validateUser]
     *
     * @param string $user
     *
     * @return void
     *
     * Created at: 05/10/2026 09:07:58 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateUser(string $user): void
    {
        if (empty($user)) {
            throw new ValidationException("Le nom d'utilisateur ne peut pas être vide");
        }

        // Vérifier la longueur
        if (strlen($user) > 63) {
            throw new ValidationException("Le nom d'utilisateur est trop long (max 63 caractères)");
        }

        // Vérifier les caractères autorisés
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $user)) {
            throw new ValidationException("Nom d'utilisateur invalide: {$user}");
        }
    }

    /**
     * [Description for validatePassword]
     *
     * @param string $password
     *
     * @return void
     *
     * Created at: 05/10/2026 09:08:01 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validatePassword(string $password): void
    {
        // Pas de validation stricte pour le mot de passe
        // Mais on vérifie qu'il n'est pas vide si nécessaire
        if (empty($password)) {
            throw new ValidationException("Le mot de passe ne peut pas être vide");
        }
    }

    /**
     * [Description for validateOutputDir]
     *
     * @param string $outputDir
     *
     * @return void
     *
     * Created at: 05/10/2026 09:08:06 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateOutputDir(string $outputDir): void
    {
        if (empty($outputDir)) {
            throw new ValidationException("Le dossier de sortie ne peut pas être vide");
        }

        // Vérifier les caractères autorisés
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $outputDir)) {
            throw new ValidationException("Nom de dossier de sortie invalide: {$outputDir}");
        }
    }

    /**
     * [Description for validateJdbcFile]
     *
     * @param string $file
     *
     * @return void
     *
     * Created at: 05/10/2026 09:08:08 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateJdbcFile(string $file): void
    {
        if (!file_exists($file)) {
            throw new ValidationException("Fichier JDBC introuvable: {$file}");
        }

        if (pathinfo($file, PATHINFO_EXTENSION) !== 'jar') {
            throw new ValidationException("Le fichier JDBC doit être un JAR: {$file}");
        }
    }

    /**
     * [Description for validateJavaVersion]
     *
     * @param string $javaVersion
     *
     * @return void
     *
     * Created at: 05/10/2026 09:08:11 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateJavaVersion(string $javaVersion): void
    {
        // Vérifier que la version est au moins Java 11
        if (version_compare($javaVersion, '11', '<')) {
            throw new ValidationException(
                "Java version {$javaVersion} détectée. SchemaSpy nécessite Java 11 ou supérieur"
            );
        }
    }

    /**
     * [Description for validateConsistency]
     * Valide que les paramètres sont cohérents entre eux
     *
     * @param array $params
     *
     * @return void
     *
     * Created at: 05/10/2026 09:08:14 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateConsistency(array $params): void
    {
        // Vérifier que le type de base de données existe
        if (!isset($params['dbType']) || empty($params['dbType'])) {
            throw new ValidationException("Le type de base de données est requis");
        }

        // Un schéma réservé (public, information_schema...) et un hôte « localhost » sont valides :
        // ce ne sont pas des erreurs de cohérence.
    }

    /**
     * [Description for validateOutputName]
     * Valide que le nom de sortie est sécurisé
     *
     * @param string $outputName
     *
     * @return void
     *
     * Created at: 05/10/2026 09:08:27 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateOutputName(string $outputName): void
    {
        if (empty($outputName)) {
            throw new ValidationException("Le nom de sortie ne peut pas être vide");
        }

        // Ne pas autoriser les chemins relatifs dangereux
        if (str_contains($outputName, '..')) {
            throw new ValidationException("Le nom de sortie ne peut pas contenir '..'");
        }

        // Ne pas autoriser les caractères dangereux
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $outputName)) {
            throw new ValidationException("Nom de sortie invalide: {$outputName}");
        }
    }


    /**
     * [Description for validateUrl]
     * Vérifie si une chaîne est une URL valide (pour les téléchargements)
     *
     * @param string $url
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:08:40 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * [Description for validateFilename]
     * Vérifie si une chaîne est un nom de fichier sécurisé
     *
     * @param string $filename
     *
     * @return bool
     *
     * Created at: 05/10/2026 09:08:55 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function validateFilename(string $filename): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename) === 1;
    }
}
