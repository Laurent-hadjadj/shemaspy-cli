# Changelog

Toutes les modifications notables de SchemaSpy CLI seront documentées dans ce fichier.

## [Non publié]

### Ajouté

- Options de génération : `--engine=auto|graphviz|vizjs`, `--markdown`, `--no-html`, `--no-orphans`, `--no-views`, `--no-rows`, `--no-implied`, `--degree=1|2`, `--include=REGEX`, `--exclude=REGEX`. Valeurs par défaut dans le bloc `generation` de `config.json`.
- Sortie de SchemaSpy : les avertissements Graphviz répétitifs (`cell size too small for content`, `in label of node`) sont masqués et résumés en fin d'exécution (« N avertissement(s) Graphviz sans incidence masqué(s) »). Trace complète dans `logs/schemaspy-cli.log` (lignes `[SCHEMASPY]`) ou à l'écran avec `--verbose`.
- Tests : 120 tests ajoutés (options de génération, filtre de sortie, exécuteur de processus, sélection JDK/Graphviz, PropertiesGenerator, Runner, DriverManager, Connection, mode interactif, fusion des options). Couverture de lignes : 26 % -> 64 %.
- Mode interactif : nouvelle étape « Options de génération » (barre de progression sur 4 étapes), préremplie par `config.json` et les options CLI ; le récapitulatif affiche moteur, sorties et options.
- Export Markdown/Mermaid via le fork de SchemaSpy (`schemaspy.markdown_supported` dans `config.json`).
- Création automatique de `dot.exe` depuis `dot_builtins.exe` pour les archives Graphviz Windows récentes (15/16) qui ne le fournissent plus.

### Modifié

- Lanceurs : un seul point d'entrée `bin/schemaspy` et un lanceur Windows `bin/schemaspy.bat`. Suppression de `bin/schemaspy.sh`, `schemaspy.bat` (racine) et `shemapspy.ps1`.
- Drivers JDBC : PostgreSQL 42.7.13, MySQL Connector/J 26.7.0, MariaDB 3.5.10, SQL Server 13.6.0 (jre8/jre11), Oracle ojdbc11 23.26.3.0.0. Les anciens JAR sont rangés dans `jdbc/old/` (ignoré).
- Les erreurs de validation des paramètres et les erreurs inattendues sont affichées sans `--verbose`.
- `PathFinder` mémorise les détections JDK/Graphviz (un seul `java -version` par exécution au lieu d'un par appel).
- `Application` : constructeur à paramètres facultatifs (logger, base path, processus, environnement) pour les tests ; l'annulation du mode interactif retourne 0 au lieu d'appeler `exit()` ; le dernier jeu de paramètres (`schemaspy.last.json`) suit le base path.
- Tests de `Application::run()` de bout en bout (13 scénarios : succès, mode silencieux, options, erreurs de config/paramètres/connexion, code de retour SchemaSpy, mode interactif, annulation). 227 tests au total.
- Exécution de SchemaSpy : `proc_open` en priorité, repli automatique sur `popen` s'il est refusé (Windows, terminal intégré de VS Code : « proc_open(): Command conversion failed »), erreur explicite si aucun des deux ne démarre.
- SchemaSpy : fork `7.0.3-lh.2` (export Markdown, diagrammes de résumé corrects avec Graphviz 16.1.0).
- Choix du JDK : le premier JDK satisfaisant la version requise par SchemaSpy est retenu (un JDK 8 système ne masque plus `tools/jdk17`).

### Corrigé

- Chemins relatifs de `config.json` (`jdbc/`, `tools/`...) : le base path est défini avant la détection du JDK et le scan des drivers, l'outil ne dépend plus du répertoire courant.
- `DriverManager` : un bloc `jdbc_validation` partiel dans `config.json` provoquait « Undefined array key » ; les clés absentes prennent maintenant leur valeur par défaut.
- `Environment` : `trim(null)` (dépréciation PHP 8.1+) quand `where`/`which` ne trouvent rien.
- `LoggerTest::testDebugInVerboseMode` alignée sur le format actuel des messages de debug.
- La bannière s'affichait avant le chargement de la config (version SchemaSpy et nombre de drivers erronés).
- Oracle : la requête de test `SELECT 1` échouait (`FROM DUAL` requis) et le test était contourné en dur. Le test de connexion utilise désormais la bonne requête, et se limite à un test TCP quand l'extension PDO du SGBD est absente (`pdo_oci`, etc.) au lieu de bloquer.
- Le test de connexion supporte maintenant MariaDB et SQL Server (type non géré auparavant).
- Extraction de la version des drivers : le point final était inclus (`3.5.9.`) et `mysql-connector-j` / `mssql-jdbc` / `ojdbc11-x.y` n'étaient pas reconnus.
- Propriété SchemaSpy `schemaspy.render` (inexistante) remplacée par `schemaspy.renderer`, vide par défaut (le moteur `cairo` n'est plus fourni avec Graphviz 15+ sous Windows).

## [1.0.0] - 2026-06-28

### Architecture

- ✅ Refactorisation complète en architecture modulaire
- ✅ Séparation des responsabilités en classes spécialisées
- ✅ Autoloading avec Composer (PSR-4)
- ✅ Structure de projet professionnelle

### Ajouté

- ✅ Support de PosteGreSQL, Oracle et MySQL
- ✅ Fichier de configuration JSON centralisé
- ✅ Mode interactif et non-interactif
- ✅ Validation des paramètres
- ✅ Sauvegarde des paramètres
- ✅ Gestion des exceptions
- ✅ Support de l'internationalisation (préparation)
- ✅ Dockerfile et docker-compose pour les tests
- ✅ Makefile pour faciliter l'utilisation
- ✅ Script d'installation pour Linux/Mac
- ✅ Détection automatique du JDK et de Graphviz en 3 niveaux : système
  (JAVA_HOME/PATH), dossier embarqué (`tools/`), puis chemin explicite dans
  `config.json` (par OS)
- ✅ Affichage des versions détectées (JDK, Graphviz, SchemaSpy) au démarrage
- ✅ Vérification de compatibilité SchemaSpy/JDK déclarée dans `config.json`
  (`schemaspy.compatibility`) — SchemaSpy 6.x nécessite un JDK 11+, 7.x un
  JDK 17+ ; le JDK et le JAR SchemaSpy restent obligatoires dans tous les cas
- ✅ Fichier de log (`logs/schemaspy-cli.log`, écrasé à chaque exécution) :
  trace complète (emojis compris) indépendante du mode quiet/verbose de la
  console — utile quand le terminal n'affiche pas correctement les emojis

### Modifié

- 🔄 Suppression des constantes codées en dur
- 🔄 Utilisation de l'autoloading Composer
- 🔄 Meilleure gestion des erreurs
- 🔄 Structure de fichiers réorganisée
- 🔄 Application auto-contenue : la racine du projet (jar/, jdbc/, tools/,
  report/) sert de base à tous les chemins, suppression du scan des
  lecteurs/dossiers externes (`environnement/tools`)
- 🔄 Consolidation de la détection Java/Graphviz dans `PathFinder` (au lieu
  d'une logique dupliquée entre `Runner` et `PathFinder`)

### Corrigé

- Chemins relatifs de `config.json` (`jdbc/`, `tools/`...) : le base path est défini avant la détection du JDK et le scan des drivers, l'outil ne dépend plus du répertoire courant.
- `DriverManager` : un bloc `jdbc_validation` partiel dans `config.json` provoquait « Undefined array key » ; les clés absentes prennent maintenant leur valeur par défaut.
- `Environment` : `trim(null)` (dépréciation PHP 8.1+) quand `where`/`which` ne trouvent rien.
- `LoggerTest::testDebugInVerboseMode` alignée sur le format actuel des messages de debug.
- 🐛 Un appel de debug oublié (`dd()`) rendait `Environment::getJavaHome()`
  inutilisable
- 🐛 La résolution du chemin SchemaSpy dépendait d'une clé de configuration
  supprimée (`schemaspy_folder`), cassant la génération de documentation
- 🐛 La détection de version JDK ne reconnaissait pas le format historique
  JDK 8 (`1.8.0_231`), seulement le format JDK 9+ (`17.0.9`)
- 🐛 `.gitignore` ignorait `rapport/` alors que le dossier de sortie réel est
  `report/` (config.json) — le dossier de sortie n'était donc pas exclu

### Supprimé

- 🗑️ Code mort : `Config::getSchemaspyJarPath()`, `PathFinder::findSchemaSpyJar()`,
  `PathFinder::findFile()/findDirectory()/findFileUpwards()/isExecutableAvailable()`,
  jamais appelés dans le reste du code
