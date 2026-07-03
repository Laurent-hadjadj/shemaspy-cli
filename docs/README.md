# SchemaSpy CLI

Application PHP (`mamoulinette/schemaspy-cli`) qui pilote [SchemaSpy](https://github.com/schemaspy/schemaspy) 7.0.2 (Java) pour générer de la documentation de bases de données. Elle propose un mode interactif (guidé, avec prompts) et un mode non-interactif piloté par des options en ligne de commande, pensé pour être appelé depuis un pipeline CI/CD.

Ce document décrit l'état réel du code au 2026-07-03. Une section [Limitations connues](#limitations-connues) liste les écarts identifiés entre ce qui est censé fonctionner et ce qui fonctionne effectivement aujourd'hui — à lire avant de déployer.

## Sommaire

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Comment l'application trouve ses fichiers](#comment-lapplication-trouve-ses-fichiers)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Gestion des drivers JDBC](#gestion-des-drivers-jdbc)
- [Tests](#tests)
- [Docker](#docker)
- [Architecture du code](#architecture-du-code)
- [Dépannage](#dépannage)
- [Limitations connues](#limitations-connues)

## Prérequis

| Composant | Version minimale | Rôle |
|---|---|---|
| PHP | 8.1 (CLI) | Exécute l'application |
| Extensions PHP | `pdo`, `json`, + le(s) driver(s) PDO du/des SGBD ciblé(s) (`pdo_pgsql`, `pdo_mysql`, `pdo_oci`...) | Test de connexion avant de lancer SchemaSpy |
| Java (JRE/JDK) | 11 | Exécute le JAR SchemaSpy |
| Composer | — | Installation des dépendances PHP |
| Graphviz | 2.38+ (optionnel) | Diagrammes de relations en image native ; à défaut, l'application bascule automatiquement sur viz.js (rendu SVG côté navigateur) |

Ces prérequis sont vérifiés automatiquement au lancement (hors mode `--quiet`) et signalés sous forme d'avertissements — l'application ne bloque pas dessus, sauf pour la connexion à la base de données elle-même.

## Installation

```bash
composer install --optimize-autoloader

# Linux/Mac
chmod +x bin/schemaspy.sh

# vérifier que tout est en place
php src/bootstrap.php --help
```

Voir [Limitations connues](#limitations-connues) au sujet de la commande `bin/schemaspy` déclarée dans `composer.json` (elle n'existe pas encore dans le dépôt).

## Comment l'application trouve ses fichiers

Au démarrage, `PathFinder::findEnvironmentPath()` cherche un dossier `paths.root_folder` (`environnement/tools` par défaut, voir `config/config.json`) :

- **Windows** : parcourt les lettres de lecteur `C:` à `Z:` et retient la première où `<lettre>:/environnement/tools` existe.
- **Unix/Linux** : cherche dans `/opt/environnement/tools`, `$HOME/environnement/tools`, puis `./environnement/tools`.

Le dossier trouvé devient le **base path**. Tous les chemins relatifs de `config/config.json` (`schemaspy_folder`, `output_folder`, `java_folder`, `graphviz_folder`) sont ensuite résolus par rapport à ce base path — **pas** par rapport à la racine du dépôt Git. C'est une convention de déploiement : elle suppose qu'à côté du dépôt applicatif existe une arborescence partagée du type :

```
<base path>/
├── SchemaSpy7/
│   ├── schemaspy-7.0.2.jar
│   └── SCHEMA/              # rapports générés
├── jdk17/
└── graphviz-2.38/
```

Si aucun dossier `environnement/tools` n'est trouvé, l'application se rabat sur le répertoire courant (avec un avertissement).

Le fichier de configuration lui-même est cherché dans cet ordre : le chemin donné par `--config=`, puis `<dossier de bootstrap.php>/../../<chemin>`, puis `<cwd>/<chemin>`, puis `config/config.json` par défaut.

## Configuration

`config/config.json` centralise tout. Sections principales :

| Clé | Rôle |
|---|---|
| `application` | Nom, version, société, contact affichés dans la bannière |
| `schemaspy.version` / `schemaspy.jar` | Version et JAR SchemaSpy à utiliser |
| `paths.*` | Sous-dossiers résolus par rapport au base path (voir ci-dessus) |
| `jdbc.<type>` | Un bloc par SGBD supporté : `driver` (nom de fichier attendu), `version`, `port` par défaut, `class` JDBC, `download_url` |
| `jdbc_validation` | Active/désactive la validation des drivers au démarrage (versions, checksums, mode strict) |
| `obsolete_drivers` | Motifs de drivers à signaler/supprimer via `bin/cleanup-drivers.php` |
| `defaults` | Host par défaut, police, format d'image, format du nom de sortie (`{dbType}_{schema}_{timestamp}` par défaut) |
| `connprops` | Propriétés de connexion JDBC additionnelles (ex. `serverTimezone`) |

Les types de bases de données proposés en mode interactif et acceptés en mode non-interactif (`--db=`) sont **dérivés dynamiquement** des clés de la section `jdbc` — ajouter un SGBD ne demande pas de modifier le code PHP.

Aucun secret n'est stocké dans `config/config.json` (les identifiants de connexion sont fournis à chaque exécution, en interactif ou via `--user`/`--password`).

## Utilisation

### Mode interactif

```bash
php src/bootstrap.php
```

Guide l'utilisateur : choix du SGBD (avec les derniers paramètres utilisés en valeurs par défaut, mémorisés dans `schemaspy.last.json`), host, port, base, schéma, utilisateur, mot de passe (saisie masquée sous Unix), puis récapitulatif et confirmation avant génération.

### Mode non-interactif (CI/CD)

Déclenché dès que `--quiet` est présent ou qu'au moins un paramètre `--xxx=` est fourni.

```bash
php src/bootstrap.php --quiet \
  --db=postgresql --host=db.internal --database=ma_base \
  --schema=public --user=ci_reader --password="$DB_PASSWORD"
```

### Options disponibles

| Option | Description | Défaut |
|---|---|---|
| `--help`, `-h` | Affiche l'aide et quitte | — |
| `--quiet`, `-q` | Mode silencieux, force le mode non-interactif | désactivé |
| `--verbose`, `-v` | Affiche les logs `[DEBUG]` | désactivé |
| `--config=FICHIER` | Fichier de configuration à utiliser | `config/config.json` |
| `--db=TYPE` | Type de SGBD (clé de `jdbc` dans la config) | `postgresql` |
| `--host=HOST` | Hôte de la base | — (requis) |
| `--port=PORT` | Port | port par défaut du SGBD choisi |
| `--database=NOM` | Nom de la base | — (requis) |
| `--schema=NOM` | Nom du schéma | — (requis) |
| `--user=USER` | Utilisateur | — (requis) |
| `--password=PASS` | Mot de passe | — (requis) |
| `--vizjs=true\|false` | Forcer viz.js plutôt que Graphviz natif | détection automatique |
| `--output=DOSSIER` | Nom du dossier de sortie | généré (`{dbType}_{schema}_{timestamp}`) |

En mode non-interactif, `host`, `database`, `schema`, `user` et `password` sont obligatoires ; leur absence fait échouer la validation avec un message listant les champs manquants.

### Exemples par SGBD

```bash
# PostgreSQL
php src/bootstrap.php --quiet --db=postgresql --host=localhost --database=demo --schema=public --user=postgres --password=xxx

# MySQL
php src/bootstrap.php --quiet --db=mysql --host=localhost --database=demo --schema=demo --user=root --password=xxx

# Oracle
php src/bootstrap.php --quiet --db=oracle --host=localhost --database=XE --schema=SYSTEM --user=system --password=xxx

# Config personnalisée + verbose
php src/bootstrap.php --verbose --config=config/prod.json --db=postgresql --host=localhost --database=demo --schema=public --user=postgres --password=xxx
```

Sur Windows, `bin\schemaspy.bat` fait la même chose (`php src\bootstrap.php %*`), pareil pour `bin/schemaspy.sh` sous Unix (qui lance en plus `composer install --no-dev` si `vendor/` est absent).

Le rapport généré est disponible dans `<schemaspy_folder résolu>/<output_folder>/<nom de sortie>/index.html`.

## Gestion des drivers JDBC

Les JAR JDBC vont dans le dossier `jdbc_folder` défini par la config (`jdbc/` par défaut, à la racine du dépôt). Au démarrage, `DriverManager` :

- liste les `.jar` présents ;
- pour chaque SGBD configuré dans `jdbc.*`, vérifie que le driver attendu est présent et que sa version correspond (best-effort, par extraction du numéro de version depuis le nom de fichier) ;
- signale les drivers présents mais non déclarés dans la config ;
- en mode `jdbc_validation.strict_mode`, bloque le lancement si un driver obligatoire manque.

Scripts utilitaires (`php bin/<script>.php [chemin/vers/config.json]`) :

| Script | Rôle |
|---|---|
| `bin/check-drivers.php` | Affiche l'état de validation des drivers (identique au résumé montré au lancement de l'app) |
| `bin/check-driver-versions.php` | Détaille, driver par driver, la version détectée vs. attendue |
| `bin/cleanup-drivers.php [--dry-run]` | Supprime les drivers marqués obsolètes dans `config.json` → `obsolete_drivers` ; `--dry-run` simule sans supprimer |
| `bin/check-jdbc.sh` / `bin/cleanup.sh` | Équivalents shell pour environnements Unix |

## Tests

```bash
composer test              # ou : vendor/bin/phpunit -c tests/phpunit.xml
composer test-coverage     # rapport HTML dans tests/coverage/
vendor/bin/phpunit tests/Unit/Core/ConfigTest.php   # un fichier précis
```

58 tests (unitaires + un test d'intégration sur `Runner`), organisés sous `tests/Unit/` et `tests/Integration/`. Le test `DSNBuilderTest::testGetConnectionOptionsMySQL` est ignoré automatiquement si l'extension `pdo_mysql` n'est pas chargée localement.

## Docker

Un `DockerFile` (PHP 8.2-cli + Java 17 + Graphviz) et un `docker-compose.yml` (Postgres/MySQL/Oracle de test + service applicatif) existent dans le dépôt, mais **ne sont pas fonctionnels en l'état** — voir [Limitations connues](#limitations-connues).

## Architecture du code

```
src/
├── Cli/            Point d'entrée CLI : Application (orchestrateur), ArgumentParser,
│                    InteractiveMode, NonInteractiveMode
├── Core/            Config (config.json + résolution de chemins), Logger (couleurs ANSI,
│                    prompts), Environment (détection OS/Java)
├── Database/        Connection (test PDO), DSNBuilder, DriverManager (validation JDBC)
├── SchemaSpy/       CommandBuilder (commande java -jar ...), PropertiesGenerator
│                    (fichier .properties temporaire), Runner (orchestration de l'exécution)
├── Utils/           FileSystem, OutputNameGenerator, PathFinder, Validator, VersionChecker
├── Exceptions/       ConfigException, ConnectionException, FileNotFoundException,
│                    SchemaSpyException, ValidationException
└── bootstrap.php    Autoload Composer + point d'entrée (`new Application())->run()`)
```

Injection de dépendances manuelle (pas de conteneur), assemblée dans `Cli\Application::__construct()`/`initializeServices()`. PHP 8.1+ : propriétés en lecture seule (`readonly`) et promotion de propriétés de constructeur sur les dépendances injectées.

Le fichier `.properties` généré pour SchemaSpy (qui contient le mot de passe en clair) est écrit dans le dossier temporaire système et systématiquement supprimé après exécution, y compris en cas d'erreur (`finally` dans `Runner::execute()`).

## Dépannage

| Symptôme | Piste |
|---|---|
| `Dossier JDBC introuvable` / drivers à 0 | Le `jdbc_folder` résolu ne correspond pas à l'endroit où sont vos JAR — voir [Comment l'application trouve ses fichiers](#comment-lapplication-trouve-ses-fichiers) |
| `Fichier introuvable: .../schemaspy-7.0.2.jar` | Le `schemaspy_folder` résolu ne pointe pas vers un dossier contenant le JAR ; ajustez `config.json` ou placez le JAR au bon endroit |
| `Échec de la connexion: ...` | Vérifiez l'extension PDO du SGBD ciblé (`php -m`), l'accessibilité réseau de l'hôte, et les identifiants |
| `Java non trouvé dans le PATH` | Installez un JDK/JRE 11+ et vérifiez `JAVA_HOME` / `PATH`, ou placez-le dans le dossier `java_folder` configuré |
| Pas de diagrammes / erreurs Graphviz | Sans `dot` détecté, l'app bascule sur viz.js automatiquement — sinon forcez avec `--vizjs=true` |

Le mode `--verbose` affiche les chemins résolus, les commandes exécutées et la trace complète en cas d'erreur inattendue.

## Limitations connues

Points identifiés lors d'une revue de code (2026-07-03) :

- ~~`bin/schemaspy` manquant~~ — **corrigé** : le fichier existe désormais (point d'entrée PHP minimal qui charge `src/bootstrap.php`), `composer`'s bin-linking, `make run` et le build Docker peuvent le référencer normalement.
- ~~`docker-compose.yml` invalide~~ — **corrigé** : le fichier contenait un titre Markdown et un bloc de code (\`\`\`yaml ... \`\`\`) au lieu de YAML pur ; nettoyé.
- **Chemin du JAR SchemaSpy potentiellement introuvable hors déploiement type** (non corrigé) : sur un simple `git clone`, le dossier `jdbc/` du dépôt est bien détecté (grâce à l'ordre d'initialisation, avant que le base path ne soit appliqué), mais `schemaspy_folder` (résolu par rapport au base path auto-détecté, voir plus haut) suppose l'existence d'une arborescence `SchemaSpy7/` en dehors du dépôt. Sans cette arborescence en place, l'exécution échoue à l'étape de génération avec `Fichier introuvable`. Correction possible mais qui implique un choix de déploiement (config à adapter selon l'environnement cible) — à traiter à part.
- **`composer.lock`** (non corrigé) : la contrainte PHP de `composer.json` a été relevée à `>=8.1` sans que `composer.lock` ait pu être régénéré dans l'environnement ayant fait ce changement (pas de binaire `composer` disponible) — lancez `composer update` une fois pour resynchroniser.

## Changelog

Voir [`CHANGELOG.md`](CHANGELOG.md).
