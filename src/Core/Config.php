<?php
/**
 * Gestion de la configuration
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Core;

use SchemaSpyCli\Exceptions\ConfigException;

final class Config
{
		private array $config = [];
		private ?string $basePath = null;
		
		// 🔥 Chemins par défaut
		private const DEFAULT_PATHS = [
				'jdbc_folder' => 'jdbc',
				'root_folder' => 'environnement',
				'tools_folder' => 'tools',
				'schemaspy_folder' => 'SchemaSpy7',
				'output_folder' => 'SCHEMA',
				'java_folder' => 'jdk17',
				'graphviz_folder' => 'graphviz-2.38'
		];

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

		public function getBasePath(): ?string
		{
				return $this->basePath;
		}

		public function setBasePath(string $path): void
		{
				$this->basePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		}

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

		public function getAbsolutePath(string $path): string
		{
				// Si le chemin est déjà absolu
				if (preg_match('/^[A-Z]:\\\\|^\//', $path)) {
						return $path;
				}
				
				if ($this->basePath !== null) {
						return $this->basePath . DIRECTORY_SEPARATOR . $path;
				}
				
				return $path;
		}

		public function getDatabases(): array
		{
				return $this->get('jdbc', []);
		}

		public function getDatabase(string $type): ?array
		{
				return $this->get("jdbc.{$type}");
		}

		public function getApplicationName(): string
		{
				return $this->get('application.name', 'MaMoulinette');
		}

		public function getApplicationVersion(): string
		{
				return $this->get('application.version', '3.0.0');
		}

		public function getSchemaspyVersion(): string
		{
				return $this->get('schemaspy.version', '7.0.2');
		}

		public function getSchemaspyJar(): string
		{
				$version = $this->getSchemaspyVersion();
				$jar = $this->get('schemaspy.jar', "schemaspy-{$version}.jar");
				return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jar);
		}

		public function getSchemaspyJarPath(): string
		{
				$jarName = $this->getSchemaspyJar();
				$schemaspyPath = $this->getPath('schemaspy_folder');
				$fullPath = $schemaspyPath . DIRECTORY_SEPARATOR . $jarName;
				return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
		}

		public function getAll(): array
		{
				return $this->config;
		}

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
