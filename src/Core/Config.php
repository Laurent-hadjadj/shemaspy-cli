<?php

/**
 *  Ma-Moulinette
 *  --------------
 *  Copyright (c) 2021-2026.
 *  Laurent HADJADJ <laurent_h@me.com>.
 *  Licensed Creative Common  CC-BY-NC-SA 4.0.
 *  ---
 *  Vous pouvez obtenir une copie de la licence à l'adresse suivante :
 *  http://creativecommons.org/licenses/by-nc-sa/4.0/
 */

namespace SchemaSpyCli\Core;

use SchemaSpyCli\Exceptions\ConfigException;

/**
 * [Description Config]
 * Gestion de la configuration
*/
final class Config
{
    private array $config = [];
    private ?string $basePath = null;

    // Chemins par défaut
    private const DEFAULT_PATHS = [
        'jdbc_folder' => 'jdbc',
        'output_folder' => 'report',
        'java_folder' => 'jdk17',
        'graphviz_folder' => 'graphviz-2.38'
    ];

    /**
     * [Description for load]
     *
     * @param string $file
     *
     * @return void
     *
     * Created at: 05/10/2026 08:49:36 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function load(string $file): void
    {
        if (!file_exists($file)) {
            throw new ConfigException("Fichier de configuration introuvable: {$file}");
        }

    $content = file_get_contents($file);

    // json_decode() retourne null en cas de JSON invalide OR pour la chaîne "null".
    // On valide donc explicitement avant l'affectation (la propriété est typée array).
    if (trim($content) === '') {
        throw new ConfigException("Erreur de parsing du fichier: {$file} (fichier vide)");
    }

    $decoded = json_decode($content, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new ConfigException(
        "Erreur de parsing du fichier: {$file} (" . json_last_error_msg() . ")"
    );
    }
    if (!is_array($decoded)) {
        throw new ConfigException("Structure de configuration invalide: {$file} (racine non objet)");
    }

    $this->config = $decoded;

        // Vérification de la structure minimale
        if (!isset($this->config['paths'])) {
            $this->config['paths'] = [];
        }

        if (!isset($this->config['jdbc'])) {
            throw new ConfigException("Structure de configuration invalide: 'jdbc' manquant");
        }

        // Fusionner avec les valeurs par défaut
        foreach (self::DEFAULT_PATHS as $key => $default) {
            if (!isset($this->config['paths'][$key])) {
                $this->config['paths'][$key] = $default;
            }
        }
    }

    /**
     * [Description for get]
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     *
     * Created at: 05/10/2026 08:49:40 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * [Description for has]
     *
     * @param string $key
     *
     * @return bool
     *
     * Created at: 05/10/2026 08:49:44 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * [Description for set]
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     *
     * Created at: 05/10/2026 08:49:47 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$this->config;

        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }

        $ref = $value;
    }

    /**
     * [Description for getBasePath]
     *
     * @return string|null
     *
     * Created at: 05/10/2026 08:49:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * [Description for setBasePath]
     *
     * @param string $path
     *
     * @return void
     *
     * Created at: 05/10/2026 08:49:54 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * [Description for getPath]
     *
     * @param string $key
     *
     * @return string
     *
     * Created at: 05/10/2026 08:49:57 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getPath(string $key): string
    {
        $path = $this->get("paths.{$key}");

        if ($path === null) {
            // Utiliser les valeurs par défaut
            if (isset(self::DEFAULT_PATHS[$key])) {
                $path = self::DEFAULT_PATHS[$key];
            } else {
                throw new ConfigException("Chemin non défini: {$key}");
            }
        }

        // Normaliser les slashes
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if ($this->basePath !== null) {
            return $this->basePath . DIRECTORY_SEPARATOR . $path;
        }

        return $path;
    }

    /**
     * [Description for getAbsolutePath]
     *
     * @param string $path
     *
     * @return string
     *
     * Created at: 05/10/2026 08:50:00 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getAbsolutePath(string $path): string
    {
        // Si le chemin est déjà absolu
        if ((new Environment())->isPathAbsolute($path)) {
            return $path;
        }

        if ($this->basePath !== null) {
            return $this->basePath . DIRECTORY_SEPARATOR . $path;
        }

        return $path;
    }

    /**
     * [Description for getDatabases]
     *
     * @return array
     *
     * Created at: 05/10/2026 08:50:05 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDatabases(): array
    {
        return $this->get('jdbc', []);
    }

    /**
     * [Description for getDatabase]
     *
     * @param string $type
     *
     * @return array|null
     *
     * Created at: 05/10/2026 08:50:08 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getDatabase(string $type): ?array
    {
        return $this->get("jdbc.{$type}");
    }

    /**
     * [Description for getApplicationName]
     *
     * @return string
     *
     * Created at: 05/10/2026 08:50:11 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getApplicationName(): string
    {
        return $this->get('application.name', 'ShemaSpy-Cli');
    }

    /**
     * [Description for getApplicationVersion]
     *
     * @return string
     *
     * Created at: 05/10/2026 08:50:15 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getApplicationVersion(): string
    {
        return $this->get('application.version', '1.0.0');
    }

    /**
     * [Description for getSchemaspyVersion]
     *
     * @return string
     *
     * Created at: 05/10/2026 08:50:19 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getSchemaspyVersion(): string
    {
        return $this->get('schemaspy.version', '7.0.2');
    }

    /**
     * [Description for getSchemaspyJar]
     *
     * @return string
     *
     * Created at: 05/10/2026 08:50:27 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getSchemaspyJar(): string
    {
        $version = $this->getSchemaspyVersion();
        $jar = $this->get('schemaspy.jar', "schemaspy-{$version}.jar");
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jar);
    }

    /**
     * [Description for getAll]
     *
     * @return array
     *
     * Created at: 05/10/2026 08:50:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * [Description for getValidationConfig]
     *
     * @return array
     *
     * Created at: 05/10/2026 08:50:35 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getValidationConfig(): array
    {
        return $this->get('jdbc_validation', [
            'enabled' => true,
            'check_checksum' => false,
            'check_version' => true,
            'warn_on_extra' => true,
            'strict_mode' => false,
            'warn_obsolete' => true,
            'cleanup_unused' => false
        ]);
    }
}
