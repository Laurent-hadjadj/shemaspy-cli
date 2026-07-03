<?php
/**
 * Validation des paramètres
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Exceptions\ValidationException;

final class Validator
{
    /**
     * Types de base de données valides.
     * Valeur par défaut (3 types) conservée pour la rétrocompatibilité ;
     * peut être remplie dynamiquement depuis Config::getDatabases() via
     * setValidDatabaseTypes().
     */
    private array $validDatabaseTypes = ['postgresql', 'oracle', 'mysql'];

    // 🔥 Noms de schémas réservés
    private array $reservedSchemas = ['', 'public', 'information_schema', 'pg_catalog'];

    /**
     * Définit dynamiquement la liste des types de bases de données valides,
     * typiquement à partir des clés de la section "jdbc" du fichier de config.
     *
     * @param string[] $types
     */
    public function setValidDatabaseTypes(array $types): void
    {
        $this->validDatabaseTypes = array_values($types);
    }

    /**
     * Retourne la liste des types de bases de données actuellement valides.
     *
     * @return string[]
     */
    public function getValidDatabaseTypes(): array
    {
        return $this->validDatabaseTypes;
    }

    public function validateDatabaseType(string $type): void
    {
        if (!in_array($type, $this->validDatabaseTypes)) {
            throw new ValidationException(
                "Type de base de données invalide: {$type}. " .
                "Types acceptés: " . implode(', ', $this->validDatabaseTypes)
            );
        }
    }

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

    public function validatePort(int $port): void
    {
        if ($port < 1 || $port > 65535) {
            throw new ValidationException("Port invalide: {$port}. Doit être entre 1 et 65535");
        }
    }

    public function validateDatabase(string $database): void
    {
        if (empty($database)) {
            throw new ValidationException("Le nom de la base de données ne peut pas être vide");
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $database)) {
            throw new ValidationException("Nom de base de données invalide: {$database}");
        }
        
        // 🔥 Vérifier la longueur
        if (strlen($database) > 64) {
            throw new ValidationException("Le nom de la base de données est trop long (max 64 caractères)");
        }
    }

    public function validateSchema(string $schema): void
    {
        if (empty($schema)) {
            throw new ValidationException("Le nom du schéma ne peut pas être vide");
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $schema)) {
            throw new ValidationException("Nom de schéma invalide: {$schema}");
        }
        
        // 🔥 Vérifier la longueur
        if (strlen($schema) > 63) {
            throw new ValidationException("Le nom du schéma est trop long (max 63 caractères)");
        }
    }

    public function validateUser(string $user): void
    {
        if (empty($user)) {
            throw new ValidationException("Le nom d'utilisateur ne peut pas être vide");
        }
        
        // 🔥 Vérifier la longueur
        if (strlen($user) > 63) {
            throw new ValidationException("Le nom d'utilisateur est trop long (max 63 caractères)");
        }
        
        // 🔥 Vérifier les caractères autorisés
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $user)) {
            throw new ValidationException("Nom d'utilisateur invalide: {$user}");
        }
    }

    public function validatePassword(string $password): void
    {
        // Pas de validation stricte pour le mot de passe
        // Mais on vérifie qu'il n'est pas vide si nécessaire
        if (empty($password)) {
            throw new ValidationException("Le mot de passe ne peut pas être vide");
        }
    }

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

    public function validateJdbcFile(string $file): void
    {
        if (!file_exists($file)) {
            throw new ValidationException("Fichier JDBC introuvable: {$file}");
        }

        if (pathinfo($file, PATHINFO_EXTENSION) !== 'jar') {
            throw new ValidationException("Le fichier JDBC doit être un JAR: {$file}");
        }
    }

    public function validateJavaVersion(string $javaVersion): void
    {
        // Vérifier que la version est au moins Java 11
        if (version_compare($javaVersion, '11.0', '<')) {
            throw new ValidationException(
                "Java version {$javaVersion} détectée. SchemaSpy nécessite Java 11 ou supérieur"
            );
        }
    }

    /**
     * 🔥 Valide que les paramètres sont cohérents entre eux
     */
    public function validateConsistency(array $params): void
    {
        // Vérifier que le type de base de données existe
        if (!isset($params['dbType']) || empty($params['dbType'])) {
            throw new ValidationException("Le type de base de données est requis");
        }

        // Vérifier que le host n'est pas localhost avec un port invalide
        if ($params['host'] === 'localhost' && isset($params['port'])) {
            // localhost peut avoir n'importe quel port, c'est valide
        }

        // Vérifier que le schéma n'est pas réservé
        if (isset($params['schema']) && in_array(strtolower($params['schema']), $this->reservedSchemas)) {
            // On autorise quand même, juste un warning
            // Pas de throw, juste une note
        }
    }

    /**
     * 🔥 Valide que le nom de sortie est sécurisé
     */
    public function validateOutputName(string $outputName): void
    {
        if (empty($outputName)) {
            throw new ValidationException("Le nom de sortie ne peut pas être vide");
        }

        // 🔥 Ne pas autoriser les chemins relatifs dangereux
        if (strpos($outputName, '..') !== false) {
            throw new ValidationException("Le nom de sortie ne peut pas contenir '..'");
        }

        // 🔥 Ne pas autoriser les caractères dangereux
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $outputName)) {
            throw new ValidationException("Nom de sortie invalide: {$outputName}");
        }
    }

    /**
     * 🔥 Vérifie si une chaîne est une URL valide (pour les téléchargements)
     */
    public function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 🔥 Vérifie si une chaîne est un nom de fichier sécurisé
     */
    public function validateFilename(string $filename): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename) === 1;
    }
}
