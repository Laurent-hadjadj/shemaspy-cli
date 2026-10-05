# ShemaSpy CLI

┏┓┏┓┏┓━┏━━┓━┏━━━┓\
┃┃┃┃┃┃━┗┫┣┛━┃┏━┓┃\
┃┃┃┃┃┃━━┃┃━━┃┗━┛┃\
┃┗┛┗┛┃━━┃┃━━┃┏━━┛\
┗┓┏┓┏┛━┏┫┣┓━┃┃\
━┗┛┗┛━━┗━━┛━┗┛  NeXt 1.1.0 Release on October 2026 !

ShemaSpy-cli est une application PHP qui pilote l'application [SchemaSpy](https://github.com/schemaspy/schemaspy) 7.0.2 (Java) pour générer de la documentation de bases de données. Elle propose un mode interactif (guidé, avec prompts) et un mode non-interactif piloté par des options en ligne de commande, pensé pour être appelé depuis un pipeline CI/CD.

## Sommaire

- [1️⃣ Prérequis](#1️⃣-prérequis)
- [2️⃣ Installation](#2️⃣-installation)
- [3️⃣ Comment l'application trouve ses fichiers](#3️⃣-comment-lapplication-trouve-ses-fichiers)
- [4️⃣ Configuration](#4️⃣-configuration)
- [5️⃣ Utilisation](#5️⃣-utilisation)
- [6️⃣ Graphviz](#6️⃣-graphviz)
- [7️⃣ Gestion des drivers JDBC](#7️⃣-gestion-des-drivers-jdbc)
- [8️⃣ Tests](#8️⃣-tests)
- [9️⃣ Docker](#9️⃣-docker)
- [🔟 Architecture du code](#-architecture-du-code)
- [🔟+1️⃣ Dépannage](#1️⃣-dépannage)
- [🔟+2️⃣ Limitations connues](#2️⃣limitations-connues)

## 1️⃣ Prérequis

> [!NOTE]
> Cette version de shameSpy-cli est conçue pour fonctionner avec SchemaSpy 7.0.2 (Java 17) et la version 7.0.3-lh-2. La version 7.0.3-lh-2 est un fork. Elle corrige des bugs de génération de diagrammes sur Windows et ajoute l'option Markdown.

| Composant | Version minimale | Rôle |
| --- | --- | --- |
| PHP | 8.5 (CLI) | Exécute l'application |
| Extensions PHP | `pdo`, `json`, + le(s) driver(s) PDO du/des SGBD ciblé(s) (`pdo_pgsql`, `pdo_mysql`, `pdo_oci`...) | Test de connexion avant de lancer SchemaSpy |
| Java (JRE/JDK) | 17 (JDK 8/11 du système ignoré au profit de `tools/jdk17`) | Exécute le JAR SchemaSpy |
| Composer | — | Installation des dépendances PHP |
| [Graphviz](#6️⃣-graphviz) | 2.38/16.1.0 | Génération des graphiques |

> [!NOTE]
> Ces prérequis sont vérifiés automatiquement au lancement (hors mode `--quiet`) et signalés sous forme d'avertissements — l'application ne bloque pas dessus, sauf pour la connexion à la base de données elle-même.

## 2️⃣ Installation

> [!TIP]
> Un seul point d'entrée : `bin/schemaspy` (PHP).\
> `bin\schemaspy.bat` n'est qu'un lanceur Windows de deux lignes qui l'appelle.

```bash
composer install --optimize-autoloader

# Linux/Mac
chmod +x bin/schemaspy

# vérifier que tout est en place
bin/schemaspy --help          # Linux/Mac
bin\schemaspy.bat --help      # Windows (ou : php bin/schemaspy --help)
```

## 3️⃣ Comment l'application trouve ses fichiers

Au démarrage, `PathFinder::findEnvironmentPath()` cherche un dossier `paths.root_folder` (`environnement/tools` par défaut, voir `config/config.json`) :

- **Windows** : parcourt les lettres de lecteur `C:` à `Z:` et retient la première où `<lettre>:/environnement/tools` existe.
- **Unix/Linux** : cherche dans `/opt/environnement/tools`, `$HOME/environnement/tools`, puis `./environnement/tools`.

Le dossier trouvé devient le **base path**. Tous les chemins relatifs de `config/config.json` (`schemaspy_folder`, `output_folder`, `java_folder`, `graphviz_folder`) sont ensuite résolus par rapport à ce base path — **pas** par rapport à la racine du dépôt Git.

C'est une convention de déploiement : elle suppose qu'à côté du dépôt applicatif existe une arborescence partagée du type :

```plaintext
<base path>/
├── bin/                             # Les commandes cli pour windows | Linux/Mac.
├── config/                          # Dossier des fichiers de configuration.
├── jar/                             # Le dossier contenant schemaspy.
    ├── schemaspy-7.0.2.jar
    ├── schemaspy-7.0.3-lh-2.jar
├── jdbc/
    ├── mariadb-java-client-3.5.10.jar        # Driver pour MariaDB.
    ├── mssql-jdbc-13.6.0.jre8.jar            # Driver pour SQLServer (java8).
    ├── mssql-jdbc-13.6.0.jre11.jar           # Driver pour SQLServer (java11+).
    ├── mysql-connector-j-26.7.0.jar          # Driver pour MySQL.
    ├── ojdbc11-23.26.3.0.0.jar               # Driver pour OracleDB.
    ├── postgresql-42.7.13.jar                # Driver pour PostgreSQL.
├── rapport/                         # Dossier des rapports générés.
├── ressources                       # Logo personnalisé.
├── src                              # Source de l'application.
├── tests                            # Dossier des tests unitaires.
├── tools                            # Dossier des dépendances windows partagées.
    ├── jdk17/                       # Optionnel : JDK17 pour windows
    ├── graphviz-2.38/               # Optionnel : graphviz Legacy pour Windows
    ├── graphviz-16.1.0/             # Optionnel : graphviz pour Windows
└── vendor                           # Dossier des dépendances php partagées.
```

Le fichier de configuration lui-même est cherché dans cet ordre :

- le chemin donné par `--config=`,
- puis `<dossier de bootstrap.php>/../../<chemin>`,
- puis `<cwd>/<chemin>`,
- puis `config/config.json` par défaut.

## 4️⃣ Configuration

> [!TIP]
> `config/config.json` centralise tout.

Sections principales :

| Clé | Rôle |
| --- | --- |
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

## 5️⃣ Utilisation

### Mode interactif

```bash
php bin/schemaspy
```

Le parcours est guidé en 4 étapes, avec une barre de progression (`Étape n/4`) :

1. **Type de base** (le dernier utilisé est proposé par défaut, mémorisé dans `schemaspy.last.json`).
2. **Connexion** : host, port, base, schéma, utilisateur, mot de passe (saisie masquée sous Unix).
3. **Options de génération** : les options actuelles (bloc `generation` de `config.json` + options passées en ligne de commande, ex. `bin/schemaspy --no-orphans`) sont affichées ; `Personnaliser ? (o/N)` — Entrée les conserve, `o` pose les questions une à une (moteur, HTML, Markdown, orphelines, vues, comptage des lignes, relations implicites, degré, filtres regex). L'option Markdown n'est proposée que si le JAR SchemaSpy la supporte.
4. **Récapitulatif** (avec moteur, sorties et options retenues) et confirmation avant génération.

### Mode non-interactif (CI/CD)

Déclenché dès que `--quiet` est présent ou qu'au moins un paramètre `--xxx=` est fourni.

```bash
php src/bootstrap.php --quiet \
  --db=postgresql --host=db.internal --database=ma_base \
  --schema=public --user=ci_reader --password="$DB_PASSWORD"
```

### Options disponibles

| Option | Description | Défaut |
| --- | --- | --- |
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
| `--vizjs=true\|false` | (déprécié) équivaut à `--engine=vizjs` | — |
| `--output=DOSSIER` | Nom du dossier de sortie | généré (`{dbType}_{schema}_{timestamp}`) |

#### Options de génération

Ces options s'appliquent aux modes interactif et non-interactif et peuvent aussi être fixées par défaut dans le bloc `generation` de `config/config.json` (la ligne de commande a priorité).

| Option | Effet SchemaSpy | Défaut |
| --- | --- | --- |
| `--engine=auto\|graphviz\|vizjs` | Moteur des diagrammes : Graphviz natif ou viz.js | `auto` (Graphviz s'il est détecté, sinon viz.js) |
| `--markdown` | Génère `markdown/overview.md`, `er-diagram.md` (Mermaid) et `tables.md` dans le dossier de sortie. Nécessite le fork de SchemaSpy (voir ci-dessous) | désactivé |
| `--no-html` | `-nohtml` : pas de site HTML (le XML est conservé). À combiner avec `--markdown` | HTML généré |
| `--no-orphans` | `--no-orphans` : retire les tables sans relation des diagrammes | orphelines incluses |
| `--no-views` | `-noviews` | vues incluses |
| `--no-rows` | `-norows` : ne compte pas les lignes (plus rapide) | comptage actif |
| `--no-implied` | `-noimplied` : pas de relations déduites des noms de colonnes | relations implicites actives |
| `--degree=1\|2` | `-degree` : degré de séparation des diagrammes de table | `2` |
| `--include=REGEX` / `--exclude=REGEX` | `-i` / `-I` : tables à garder / à écarter | toutes |

**Markdown** : SchemaSpy 7.0.2 n'a pas d'export Markdown. L'option est fournie par le fork `7.0.3-lh.x` (option `-markdown`).
Une fois ce JAR placé dans `jar/`, renseignez dans `config.json` : `schemaspy.jar`, `schemaspy.version` et `"markdown_supported": true`. Le fork `7.0.3-lh.3` (JAR `*-app.jar`, à renommer `schemaspy-7.0.3-lh.3.jar`) est la configuration livrée. Avec le JAR officiel 7.0.2, remettez `markdown_supported` à `false` : `--markdown` s'arrête alors avec un message explicite.

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

Sur Windows, `bin\schemaspy.bat` appelle simplement `bin/schemaspy` (et force la console en UTF-8).

Le rapport généré est disponible dans `<schemaspy_folder résolu>/<output_folder>/<nom de sortie>/index.html`.

## 6️⃣ Graphviz

L'application cherche Graphviz dans l'ordre : `PATH` système, `tools/graphviz-16.1.0` (`paths.graphviz_folder`), puis `paths.graphviz_home` de `config.json`.

- Les archives ZIP Windows de Graphviz 15/16 ne contiennent plus `dot.exe`, seulement `dot_builtins.exe`. SchemaSpy appelant `<graphviz>/bin/dot`, l'application crée automatiquement `dot.exe` à partir de `dot_builtins.exe` dans le dossier embarqué `tools/`.
- Le renderer `cairo` n'existe plus dans ces builds : `defaults.renderer` est vide par défaut (SchemaSpy choisit lui-même). Renseignez-le (`"gd"`, `"cairo"`...) seulement si votre Graphviz l'embarque.
- **viz.js (`--engine=vizjs`)** : rendu JavaScript embarqué dans Java, utile sans Graphviz mais très lent et gourmand en mémoire sur un grand schéma (observé : un schéma Oracle de 300 tables, diagramme de relations non terminé après 30 minutes, 8 Go de mémoire). Préférez Graphviz natif au-delà de quelques dizaines de tables.
- **Avertissements Graphviz** : Graphviz >= 15 émet un avertissement par table (`cell size too small for content`, `in label of node`) sans conséquence sur les diagrammes. Ils sont masqués et comptés en fin d'exécution ; `--verbose` les affiche, et le fichier de log les conserve.
- **Graphviz 16.1.0 et SchemaSpy 7.0.2 (Windows)** : avec le JAR officiel 7.0.2, les diagrammes de résumé (`diagrams/summary/relationships.*.svg`) ne sont pas générés (`dot: can't open ... .dot: Permission denied`). Le fork `7.0.3-lh.x` n'a pas ce défaut ; avec le JAR 7.0.2, utilisez `--engine=vizjs`.

## 7️⃣ Gestion des drivers JDBC

Les JAR JDBC vont dans le dossier `jdbc_folder` défini par la config (`jdbc/` par défaut, à la racine du dépôt). Au démarrage, `DriverManager` :

- liste les `.jar` présents ;
- pour chaque SGBD configuré dans `jdbc.*`, vérifie que le driver attendu est présent et que sa version correspond (best-effort, par extraction du numéro de version depuis le nom de fichier) ;
- signale les drivers présents mais non déclarés dans la config ;
- en mode `jdbc_validation.strict_mode`, bloque le lancement si un driver obligatoire manque.

Scripts utilitaires (`php bin/<script>.php [chemin/vers/config.json]`) :

| Script | Rôle |
| --- | --- |
| `bin/check-drivers.php` | Affiche l'état de validation des drivers (identique au résumé montré au lancement de l'app) |
| `bin/check-driver-versions.php` | Détaille, driver par driver, la version détectée vs. attendue |
| `bin/cleanup-drivers.php [--dry-run]` | Supprime les drivers marqués obsolètes dans `config.json` → `obsolete_drivers` ; `--dry-run` simule sans supprimer |
| `bin/check-jdbc.sh` / `bin/cleanup.sh` | Équivalents shell pour environnements Unix |

## 8️⃣ Tests

```bash
composer test              # ou : vendor/bin/phpunit -c tests/phpunit.xml
composer test-coverage     # rapport HTML dans tests/coverage/
vendor/bin/phpunit tests/Unit/Core/ConfigTest.php   # un fichier précis
```

58 tests (unitaires + un test d'intégration sur `Runner`), organisés sous `tests/Unit/` et `tests/Integration/`. Le test `DSNBuilderTest::testGetConnectionOptionsMySQL` est ignoré automatiquement si l'extension `pdo_mysql` n'est pas chargée localement.

## 9️⃣ Docker

Todo : Le Dockerfile fourni est un exemple de conteneurisation pour CI/CD, mais il n'est pas officiellement supporté. Il installe PHP 8.5, les extensions PDO nécessaires, Java 17 et Graphviz, puis copie l'application et ses dépendances.

## 🔟 Architecture du code

```plaintext
src/
├── Cli/             # Point d'entrée CLI : Application (orchestrateur), ArgumentParser,
│                      InteractiveMode, NonInteractiveMode
├── Core/            # Config (config.json + résolution de chemins), Logger (couleurs ANSI,
│                    prompts), Environment (détection OS/Java)
├── Database/        # Connection (test PDO), DSNBuilder, DriverManager (validation JDBC)
├── SchemaSpy/       # CommandBuilder (commande java -jar ...), PropertiesGenerator
│                      (fichier .properties temporaire), Runner (orchestration de l'exécution)
├── Utils/           # FileSystem, OutputNameGenerator, PathFinder, Validator, VersionChecker
├── Exceptions/        ConfigException, ConnectionException, FileNotFoundException,
│                      SchemaSpyException, ValidationException
└── bootstrap.php    # Autoload Composer + point d'entrée (`new Application())->run()`)
```

Injection de dépendances manuelle (pas de conteneur), assemblée dans `Cli\Application::__construct()`/`initializeServices()`. PHP 8.5+ : propriétés en lecture seule (`readonly`) et promotion de propriétés de constructeur sur les dépendances injectées.

Le fichier `.properties` généré pour SchemaSpy (qui contient le mot de passe en clair) est écrit dans le dossier temporaire système et systématiquement supprimé après exécution, y compris en cas d'erreur (`finally` dans `Runner::execute()`).

## 🔟+1️⃣ Dépannage

| Symptôme | Piste |
| --- | --- |
| [Comment l'application trouve ses fichiers](#3️⃣-comment-lapplication-trouve-ses-fichiers) | `config.json` |
| `Fichier introuvable: .../schemaspy-7.0.2.jar` | Le `schemaspy_folder` résolu ne pointe pas vers un dossier contenant le JAR ; ajustez `config.json` ou placez le JAR au bon endroit |
| `Impossible de se connecter à ...` | Vérifiez l'accessibilité réseau de l'hôte/port et les identifiants (détail avec `--verbose`) |
| `Extension PHP pdo_xxx absente : serveur joignable, identifiants non testés` | Pas bloquant : sans l'extension PDO du SGBD (typiquement `pdo_oci` pour Oracle), l'application ne teste que l'accessibilité TCP ; l'authentification est validée par SchemaSpy via JDBC |
| `Java non trouvé dans le PATH` | Installez un JDK/JRE 11+ et vérifiez `JAVA_HOME` / `PATH`, ou placez-le dans le dossier `java_folder` configuré |
| [Graphviz](#6️⃣-graphviz) | un outil de graphisme de type [DOT](https://graphviz.org/), pour le graphique des relations de tables |

> [!NOTE]
> Le mode `--verbose` affiche les chemins résolus, les commandes exécutées et la trace complète en cas d'erreur inattendue.

## 🔟+2️⃣Limitations connues

Une base de données Oracle avec des tables partitionnées peut générer des diagrammes incomplets (tables manquantes dans les diagrammes de relations). Le problème est connu sur SchemaSpy 7.0.2 et 7.0.3-lh-2, et n'a pas de solution simple côté ShemaSpy-cli.

## Changelog

Voir [`CHANGELOG.md`](CHANGELOG.md).
