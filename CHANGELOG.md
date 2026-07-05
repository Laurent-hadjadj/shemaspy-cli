# Changelog

Toutes les modifications notables de SchemaSpy CLI seront documentées dans ce fichier.

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
