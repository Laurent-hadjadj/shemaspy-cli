# Changelog

Toutes les modifications notables de SchemaSpy CLI seront documentées dans ce fichier.

## [1.1.0] - 2026-10-05

### Ajouté

- ✅ Badges dans le README (version, PHP, SchemaSpy, Java, Graphviz, bases supportées, tests, assertions, couverture, licence). `ReadmeBadgesTest` vérifie que les badges de version, de PHP, de SchemaSpy, de Java et de Graphviz restent cohérents avec `config.json` et `composer.json`, et que leurs liens locaux existent ; les nombres de tests, d'assertions et la couverture se mettent à jour à la main.
- ✅ `Connection::pdoDriverFor()` (type de base -> pilote PDO : `pgsql`, `oci`, `mysql`, `sqlsrv`) et `VersionChecker::checkPdoDrivers()` : rapport des pilotes PDO présents et absents pour une liste de types. `PdoDriversTest` donne un test par pilote requis par `config.json` : réussi s'il est installé, ignoré avec un message explicite sinon (la connexion est alors limitée à un test réseau).
- Paramètres facultatifs d'injection, pour les tests, sans changer les valeurs par défaut : famille d'OS (`Environment`, `Logger`), fabrique PDO (`Connection`, `Runner`), `PathFinder` (`InteractiveMode`), `VersionChecker` (`Application`).
- ✅ Couverture des tests : 98,3 % des lignes (722 tests, sur 26 % au départ de la session). Lignes non couvertes restantes : `exit()` de `--help` et `bootstrap.php` (tous deux testés en sous-processus, que l'outil de couverture ne mesure pas), branche `stty` de `Logger::promptPassword()` (Unix interactif), repli `WT_SESSION` (PHP Windows sans `sapi_windows_vt100_support`), options `pdo_mysql` (selon l'extension installée), suppression d'un lien symbolique de dossier sous Windows (droit requis).
- ✅ Type `oracle_service` (SchemaSpy `orathin-service`, même JAR `ojdbc11`) pour les bases Oracle identifiées par un nom de service (`FONDS.exemple.fr`) ; `oracle` reste le type SID. Le test de connexion (`pdo_oci`, `SELECT 1 FROM DUAL`) s'applique à tout type dont le nom commence par `oracle`. Un JAR partagé par plusieurs types n'est validé et signalé comme manquant qu'une seule fois. `ShippedConfigTest` vérifie la cohérence de `config/config.json` (types, JAR, versions, URL de téléchargement).
- ✅ Le fichier de log reçoit la progression de SchemaSpy (lignes `[SCHEMASPY]`), plus seulement les avertissements masqués : une analyse longue se suit dans `logs/schemaspy-cli.log`.
- ✅ Avertissement viz.js : dans le menu du moteur, dans le récapitulatif interactif et avant toute exécution utilisant viz.js (choix explicite, `--vizjs=true` ou repli faute de Graphviz) : très lent et gourmand en mémoire sur les grands schémas.
- ✅ Options de génération : `--engine=auto|graphviz|vizjs`, `--markdown`, `--no-html`, `--no-orphans`, `--no-views`, `--no-rows`, `--no-implied`, `--degree=1|2`, `--include=REGEX`, `--exclude=REGEX`. Valeurs par défaut dans le bloc `generation` de `config.json`.
- ✅ Sortie de SchemaSpy : les avertissements Graphviz répétitifs (`cell size too small for content`, `in label of node`) sont masqués et résumés en fin d'exécution (« N avertissement(s) Graphviz sans incidence masqué(s) »). Trace complète dans `logs/schemaspy-cli.log` (lignes `[SCHEMASPY]`) ou à l'écran avec `--verbose`.
- ✅ Tests : 120 tests ajoutés (options de génération, filtre de sortie, exécuteur de processus, sélection JDK/Graphviz, PropertiesGenerator, Runner, DriverManager, Connection, mode interactif, fusion des options). Couverture de lignes : 26 % -> 64 %.
- ✅ Mode interactif : nouvelle étape « Options de génération » (barre de progression sur 4 étapes), préremplie par `config.json` et les options CLI ; le récapitulatif affiche moteur, sorties et options.
- ✅ Export Markdown/Mermaid via le fork de SchemaSpy (`schemaspy.markdown_supported` dans `config.json`).
- ✅ Création automatique de `dot.exe` depuis `dot_builtins.exe` pour les archives Graphviz Windows récentes (15/16) qui ne le fournissent plus.

### Modifié

- 🔄 Lanceurs : un seul point d'entrée `bin/schemaspy` et un lanceur Windows `bin/schemaspy.bat`. Suppression de `bin/schemaspy.sh`, `schemaspy.bat` (racine) et `shemapspy.ps1`.
- 🔄 Drivers JDBC : PostgreSQL 42.7.13, MySQL Connector/J 26.7.0, MariaDB 3.5.10, SQL Server 13.6.0 (jre8/jre11), Oracle ojdbc11 23.26.3.0.0. Les anciens JAR sont rangés dans `jdbc/old/` (ignoré).
- 🔄 Les erreurs de validation des paramètres et les erreurs inattendues sont affichées sans `--verbose`.
- 🔄 `PathFinder` mémorise les détections JDK/Graphviz (un seul `java -version` par exécution au lieu d'un par appel).
- 🔄 `Application` : constructeur à paramètres facultatifs (logger, base path, processus, environnement) pour les tests ; l'annulation du mode interactif retourne 0 au lieu d'appeler `exit()` ; le dernier jeu de paramètres (`schemaspy.last.json`) suit le base path.
- 🔄 Tests de `Application::run()` de bout en bout (13 scénarios : succès, mode silencieux, options, erreurs de config/paramètres/connexion, code de retour SchemaSpy, mode interactif, annulation). 227 tests au total.
- 🔄 Exécution de SchemaSpy : `proc_open` en priorité, repli automatique sur `popen` s'il est refusé (Windows, terminal intégré de VS Code : « proc_open(): Command conversion failed »), erreur explicite si aucun des deux ne démarre.
- 🔄 SchemaSpy : fork `7.0.3-lh.2` (export Markdown, diagrammes de résumé corrects avec Graphviz 16.1.0).
- 🔄 Choix du JDK : le premier JDK satisfaisant la version requise par SchemaSpy est retenu (un JDK 8 système ne masque plus `tools/jdk17`).

### Corrigé

- 🛠️ `bootstrap.php` : sans `vendor/`, il se terminait avec le code 0 (`die("texte")`) : un script ou un pipeline y voyait un succès. Il écrit maintenant sur la sortie d'erreur et sort avec le code 1. `BootstrapTest` couvre l'ordre de recherche de l'autoloader (trois emplacements) et la propagation du code de retour.
- 🛠️ `Logger::progressBar()` : division par zéro avec un total nul, `ValueError` de `str_repeat()` avec un avancement supérieur au total ou négatif ; `Logger::table()` : les colonnes se décalaient avec les caractères accentués (largeur mesurée en octets).
- 🛠️ `Validator::validateJavaVersion()` rejetait un JDK `11` (même défaut `version_compare('11', '11.0')` que ailleurs). Code mort retiré de `Validator::validateConsistency()` (dont un accès à `$params['host']` sans vérification) et de `NonInteractiveMode`.
- 🛠️ `FileSystem::isJarFile()` / `isPropertiesFile()` sont insensibles à la casse (`OJDBC.JAR`).
- 🛠️ Les tests du mode interactif lisaient le vrai `schemaspy.last.json` du dépôt : ils utilisent maintenant un dossier jetable.
- 🛠️ `Environment` : `isPathAbsolute()` ne reconnaissait ni `C:/dossier` (écriture courante en PHP), ni `c:\dossier` (lecteur en minuscule), ni les chemins UNC `\serveur\partage` ; `Config::getAbsolutePath()` utilise désormais la même règle. `getJavaHome()` et `findGraphvizInPath()` prenaient la sortie entière de `where` (une ligne par installation) comme un seul chemin : seule la première est retenue. `JAVA_HOME` entre guillemets ou avec des espaces est toléré. Les chemins `bin/java` et `bin/dot` ne doublent plus le séparateur quand le dossier se termine par `/` ou `\`. `isJavaVersionCompatible()` refusait un JDK annoncé `11` (valeur par défaut `11.0`). `isCommandAvailable()` échappe le nom de la commande. `getJavaVersion()` reconnaît aussi les builds `22-ea` et `21.0.1+12`. Les commandes shell passent par `runCommand()` (extensible, utilisé par les tests). 89 tests ajoutés pour `Environment`, 5 pour `Config::getAbsolutePath()`.
- 🛠️ `VersionChecker` : un JDK annonçant simplement `11` était rejeté (`version_compare('11', '11.0', '<')` est vrai en PHP) ; la détection de Java délègue à `Environment` (reconnaît `1.8.0_231` du JDK 8, que l'ancienne expression ne lisait pas, et ne produit plus d'avertissement PHP quand `java` est absent) ; `getSchemaSpyVersion()` ne provoque plus d'erreur fatale sans l'extension zip. Le constructeur accepte, en option, un `Environment`, un détecteur d'extensions et une version PHP pour les tests. 41 tests ajoutés, dont un qui vérifie que le JAR configuré annonce bien la version déclarée dans `config.json`.
- 🛠️ `FileSystem` : `removeDirectory()` ne suit plus les liens symboliques ni les jonctions Windows (le contenu de leur cible était supprimé) ; `copyDirectory()` n'entre plus en récursion infinie quand la destination est dans la source ; `ensureDirectory()` n'émet plus d'avertissement PHP avant son exception ; `getFileHash()` et `getFileSize()` traitent un dossier comme un non-fichier (`null` / `0 B`) au lieu de provoquer un échec de `hash_file()`. 38 tests ajoutés.
- 🛠️ `--config=` (valeur vide) : message d'erreur incompréhensible (« fichier vide » pour le dossier du projet) ; équivaut maintenant à l'absence de l'option (`config/config.json`). Tests d'`ArgumentParser` (46 cas : drapeaux, paramètres, avertissements, valeurs par défaut, cohérence, aide et sortie de `--help`/`-h`).
- 🛠️ Validation du nom de base : le point est autorisé (`BASE.exemple.fr`), pour les noms de service Oracle avec domaine. Il ne peut ni commencer ni finir le nom, ni être doublé. Pour se connecter à un *service* (et non à un SID) il faut un type SchemaSpy `orathin-service` dans `config.json` ; le type `oracle` livré (`orathin`) attend un SID.
- 🛠️ Mode non-interactif : un port non numérique (`--port=abc`) provoquait un `TypeError` (« Erreur inattendue ») ; un port décimal (`5432.5`) était accepté en silence comme `5432`. Les deux donnent maintenant une erreur de validation claire. Une clé `db` absente de `collect()` retombe proprement sur `postgresql`.
- 🛠️ Tests de `NonInteractiveMode` (45 cas : paramètres requis, types de base, valeurs invalides, ports, `--vizjs`, schémas système, mot de passe absent du log).
- 🛠️ `bin/schemaspy.bat` ne fonctionnait pas (« 'SchemaSpy' n'est pas reconnu en tant que commande interne ou externe ») : fichier en LF avec des accents dans un `rem`. Réécrit en ASCII/CRLF, message clair si PHP est absent du PATH, code de retour propagé. `.gitattributes` fixe CRLF pour `*.bat` et LF pour `bin/schemaspy` ; `LauncherTest` verrouille ces règles.
- 🛠️ Chemins relatifs de `config.json` (`jdbc/`, `tools/`...) : le base path est défini avant la détection du JDK et le scan des drivers, l'outil ne dépend plus du répertoire courant.
- 🛠️ `DriverManager` : un bloc `jdbc_validation` partiel dans `config.json` provoquait « Undefined array key » ; les clés absentes prennent maintenant leur valeur par défaut.
- 🛠️ `Environment` : `trim(null)` (dépréciation PHP 8.1+) quand `where`/`which` ne trouvent rien.
- 🛠️ `LoggerTest::testDebugInVerboseMode` alignée sur le format actuel des messages de debug.
- 🛠️ La bannière s'affichait avant le chargement de la config (version SchemaSpy et nombre de drivers erronés).
- 🛠️ Oracle : la requête de test `SELECT 1` échouait (`FROM DUAL` requis) et le test était contourné en dur. Le test de connexion utilise désormais la bonne requête, et se limite à un test TCP quand l'extension PDO du SGBD est absente (`pdo_oci`, etc.) au lieu de bloquer.
- 🛠️ Le test de connexion supporte maintenant MariaDB et SQL Server (type non géré auparavant).
- 🛠️ Extraction de la version des drivers : le point final était inclus (`3.5.9.`) et `mysql-connector-j` / `mssql-jdbc` / `ojdbc11-x.y` n'étaient pas reconnus.
- 🛠️ Propriété SchemaSpy `schemaspy.render` (inexistante) remplacée par `schemaspy.renderer`, vide par défaut (le moteur `cairo` n'est plus fourni avec Graphviz 15+ sous Windows).

## [1.0.0] - 2026-06-28

### Architecture

- ✅ Refactorisation complète en architecture modulaire
- ✅ Séparation des responsabilités en classes spécialisées
- ✅ Autoloading avec Composer (PSR-4)
- ✅ Structure de projet professionnelle

### Ajouté

- ✅ Type `oracle_service` (SchemaSpy `orathin-service`, même JAR `ojdbc11`) pour les bases Oracle identifiées par un nom de service (`FONDS.exemple.fr`) ; `oracle` reste le type SID. Le test de connexion (`pdo_oci`, `SELECT 1 FROM DUAL`) s'applique à tout type dont le nom commence par `oracle`. Un JAR partagé par plusieurs types n'est validé et signalé comme manquant qu'une seule fois. `ShippedConfigTest` vérifie la cohérence de `config/config.json` (types, JAR, versions, URL de téléchargement).
- ✅ Le fichier de log reçoit la progression de SchemaSpy (lignes `[SCHEMASPY]`), plus seulement les avertissements masqués : une analyse longue se suit dans `logs/schemaspy-cli.log`.
- ✅ Avertissement viz.js : dans le menu du moteur, dans le récapitulatif interactif et avant toute exécution utilisant viz.js (choix explicite, `--vizjs=true` ou repli faute de Graphviz) : très lent et gourmand en mémoire sur les grands schémas.
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

- 🛠️ `--config=` (valeur vide) : message d'erreur incompréhensible (« fichier vide » pour le dossier du projet) ; équivaut maintenant à l'absence de l'option (`config/config.json`). Tests d'`ArgumentParser` (46 cas : drapeaux, paramètres, avertissements, valeurs par défaut, cohérence, aide et sortie de `--help`/`-h`).
- 🛠️Validation du nom de base : le point est autorisé (`FONDS.exemple.fr`), pour les noms de service Oracle avec domaine. Il ne peut ni commencer ni finir le nom, ni être doublé. Pour se connecter à un *service* (et non à un SID) il faut un type SchemaSpy `orathin-service` dans `config.json` ; le type `oracle` livré (`orathin`) attend un SID.
- 🛠️ Chemins relatifs de `config.json` (`jdbc/`, `tools/`...) : le base path est défini avant la détection du JDK et le scan des drivers, l'outil ne dépend plus du répertoire courant.
- 🛠️ `DriverManager` : un bloc `jdbc_validation` partiel dans `config.json` provoquait « Undefined array key » ; les clés absentes prennent maintenant leur valeur par défaut.
- 🛠️ `Environment` : `trim(null)` (dépréciation PHP 8.1+) quand `where`/`which` ne trouvent rien.
- 🛠️ `LoggerTest::testDebugInVerboseMode` alignée sur le format actuel des messages de debug.
- 🛠️ Un appel de debug oublié (`dd()`) rendait `Environment::getJavaHome()`
  inutilisable
- 🛠️ La résolution du chemin SchemaSpy dépendait d'une clé de configuration
  supprimée (`schemaspy_folder`), cassant la génération de documentation
- 🛠️ La détection de version JDK ne reconnaissait pas le format historique
  JDK 8 (`1.8.0_231`), seulement le format JDK 9+ (`17.0.9`)
- 🛠️ `.gitignore` ignorait `rapport/` alors que le dossier de sortie réel est
  `report/` (config.json) — le dossier de sortie n'était donc pas exclu

### Supprimé

- 🗑️ Code mort : `Config::getSchemaspyJarPath()`, `PathFinder::findSchemaSpyJar()`,
  `PathFinder::findFile()/findDirectory()/findFileUpwards()/isExecutableAvailable()`,
  jamais appelés dans le reste du code
