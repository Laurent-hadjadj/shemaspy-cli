# Tests unitaires

## Installation des dépendances de test

```bash
composer install --dev

# Tous les tests
composer test

# Avec couverture de code
composer test-coverage

# Test spécifique
./vendor/bin/phpunit tests/Unit/Core/ConfigTest.php

# Avec verbose
./vendor/bin/phpunit --verbose

# Avec une configuration personnalisée
./vendor/bin/phpunit -c tests/phpunit.xml

✅ Architecture modulaire complète
23 classes organisées en 6 modules

Tests unitaires pour les composants clés

Configuration centralisée via JSON

Support multi-SGBD (PostgreSQL, Oracle, MySQL)

✅ Fonctionnalités clés
Mode interactif et non-interactif

Détection automatique de Java et Graphviz

Génération de rapports horodatés

Gestion professionnelle des erreurs

Support Docker pour les tests

🚀 Prochaines étapes pour tester
bash
# 1. Structure
mkdir -p schemaspy-cli/{src,config,tests,bin}
# Copier tous les fichiers fournis

# 2. Installer les dépendances
cd schemaspy-cli
composer install

# 3. Rendre exécutable
chmod +x bin/schemaspy

# 4. Tester
./bin/schemaspy --help

# 5. Lancer en mode interactif
./bin/schemaspy

# 6. Tests unitaires
composer test
💡 Améliorations futures possibles
Si vous voulez pousser encore plus loin :

Interface web - Une petite interface pour visualiser les rapports

Notifications - Envoyer un email/slack à la fin de la génération

Comparaison - Comparer deux schémas de bases de données

Planification - Génération automatique avec cron

Multi-environnements - Support de plusieurs fichiers de config (dev/prod)

🎯 Points d'attention
Pensez à télécharger les drivers JDBC dans environnement/tools/SchemaSpy7/JDBC/

Vérifiez que Java 11+ est installé

Les extensions PHP pdo_pgsql, pdo_mysql, pdo_oci doivent être activées

N'hésitez pas si vous avez des questions lors des tests ou si vous voulez ajouter d'autres fonctionnalités. Je suis là pour vous aider ! 🚀

# 1. Placer vos drivers dans le dossier jdbc/
cp *.jar jdbc/

# 2. Vérifier les drivers
./bin/check-drivers.php

# 3. Voir la vérification dans l'application
./bin/schemaspy

# 4. Mode verbeux pour plus de détails
./bin/schemaspy --verbose

✅ Centralisation - Tous les drivers dans un dossier jdbc/ à la racine

✅ Vérification du dossier vide - Message clair si aucun driver

✅ Validation des versions - Comparaison avec config.json

✅ Détection des drivers supplémentaires - Avertissement pour les drivers non configurés

✅ Mode strict - Option pour bloquer si drivers manquants

✅ Script de vérification - bin/check-drivers.php pour diagnostiquer

✅ Informations détaillées - Taille, version, chemin dans les logs

✅ Support de tous vos drivers - PostgreSQL, Oracle, MySQL, MariaDB, SQL Server, etc.

Le système est maintenant robuste et vous donne une visibilité complète sur vos drivers JDBC ! 🚀

 PostgreSQL Driver - postgresql-42.7.5.jar
Bonnes nouvelles :

Votre driver PostgreSQL est à jour ! La version 42.7.5 est la dernière version stable disponible . Elle supporte PostgreSQL 18 .

Un point important : la version 42.7.5 inclut une correction spécifique pour PostgreSQL 18 concernant la suppression du privilège RULE . C'est exactement le type de compatibilité que vous voulez avoir.

⚠️ Oracle Drivers - ojdbc11.jar et ojdbc6.jar
ojdbc11.jar - Version OK pour les bases Oracle récentes (19c, 21c, 23c) . Supporte Java 11, 17, 19 et 21 .

ojdbc6.jar - À considérer pour les anciennes versions Oracle 11g R2 uniquement . Si vous utilisez Oracle 12c ou plus récent, privilégiez ojdbc11.jar.

⚠️ MySQL Drivers - plusieurs versions
mysql-connector-java-8.0.33.jar - Version recommandée pour MySQL 8.0+

mysql-connector-java-6.0.4.jar - Version plus ancienne, à remplacer par la 8.0.33 si possible

mysql-connector-java-5.1.38-bin.jar - Pour MySQL 5.x uniquement

📌 MariaDB Driver - mariadb-java-client-2.2.1.jar
Version fonctionnelle mais ancienne (2.7.x disponible). Si vous utilisez MariaDB 10.6+, envisagez une mise à jour.

📌 SQL Server Drivers
sqljdbc42.jar - OK pour SQL Server 2012+ avec Java 8

sqljdbc41.jar - Pour Java 7, à remplacer par la 42 si vous avez Java 8+

🎯 Recommandations
Votre driver PostgreSQL est à jour et supporte bien PostgreSQL 18 . Aucun souci de ce côté.

✅ Ce qui est bon :
PostgreSQL 42.7.5 est la version recommandée 

Support explicite de PostgreSQL 18 

Correction pour le privilège RULE supprimé dans PostgreSQL 18 

🔄 À considérer :
Oracle : utilisez ojdbc11.jar pour Oracle 19c/21c/23c 

MySQL : privilégiez mysql-connector-java-8.0.33.jar pour MySQL 8.0+

Oracle 6 : à conserver uniquement si vous utilisez Oracle 11g R2 

Vous pouvez tester votre configuration avec le script bin/check-drivers.php que nous avons ajouté.


✅ Drivers à conserver (les plus récents)
Driver	Version	Compatibilité Java	Compatibilité SGBD	Statut
postgresql-42.7.5.jar	42.7.5	Java 8+	PostgreSQL 11-18 ✅	✅ À garder
ojdbc11.jar	21.9.0	Java 8/11+	Oracle 18c-23c ✅	✅ À garder
mysql-connector-j-8.0.33.jar	8.0.33	Java 8+	MySQL 8.0+ ✅	✅ À garder
mariadb-java-client-3.5.9.jar	3.5.9	Java 8+	MariaDB 10.6-11.6 ✅	✅ À garder
mssql-jdbc-13.4.0.jre11.jar	13.4.0	Java 11+	SQL Server 2016-2022 ✅	✅ À garder
mssql-jdbc-13.4.0.jre8.jar	13.4.0	Java 8	SQL Server 2016-2022 ✅	✅ À garder
⚠️ Drivers à considérer (anciennes versions)
Driver	Version	Problème	Action
mariadb-java-client-2.7.9.jar	2.7.9	Plus ancien que 3.5.9	❌ À supprimer
mariadb-java-client-2.2.1.jar	2.2.1	Très ancien	❌ À supprimer
mysql-connector-java-6.0.4.jar	6.0.4	Remplacé par 8.0.33	❌ À supprimer
mysql-connector-java-5.1.38-bin.jar	5.1.38	Très ancien, pour MySQL 5.x	❌ À supprimer
ojdbc6.jar	11.2.0.3	Pour Java 6, Oracle 11g	❌ À supprimer
sqljdbc41.jar	4.1	Pour Java 7	❌ À supprimer
sqljdbc42.jar	4.2	Pour Java 8, remplacé par 13.4.0	❌ À supprimer
junixsocket-common-2.0.4.jar	2.0.4	Dépendance de junixsocket-mysql	❌ À supprimer
junixsocket-mysql-2.0.4.jar	2.0.4	Ancienne version	❌ À supprimer

# 0. symfony composer install 

# 1. Vérifier les drivers
php bin/check-driver-versions.php

# 2. Faire une simulation du nettoyage
php bin/cleanup-drivers.php --dry-run

# 3. Nettoyer les drivers obsolètes
php bin/cleanup-drivers.php

# OU utiliser le script bash
./bin/cleanup.sh

✅ Clarté - Chaque driver a une raison d'être

✅ Complet - Informations sur Java et version SGBD

✅ Maintenable - Ajout facile de nouveaux drivers

✅ Nettoyage automatisé - Scripts pour supprimer les obsolètes

✅ Traçabilité - Notes sur chaque driver

✅ Compatibilité - Version Java et SGBD documentées

La configuration est maintenant propre, organisée et facile à maintenir ! 🚀


1️⃣ D'abord, vérifiez que tout est en place
bash
# Vérifier les drivers JDBC
php bin/check-driver-versions.php
Vous devriez voir quelque chose comme :

text
✅ postgresql-42.7.5.jar (configuré)
✅ ojdbc11.jar (configuré)  
✅ mysql-connector-j-8.0.33.jar (configuré)
✅ mariadb-java-client-3.5.9.jar (configuré)
✅ mssql-jdbc-13.4.0.jre11.jar (configuré)
✅ mssql-jdbc-13.4.0.jre8.jar (configuré)
2️⃣ Ensuite, testez l'application en mode interactif
bash
# Lancer l'application
./bin/schemaspy
Ou si vous êtes à la racine du projet :

bash
php src/bootstrap.php
3️⃣ Si tout fonctionne, essayez en mode non-interactif (plus rapide pour les tests)
bash
# PostgreSQL
./bin/schemaspy --quiet --db=postgresql --host=localhost --database=ma_base --schema=public --user=mon_user --password=mon_mot_de_passe

# MySQL  
./bin/schemaspy --quiet --db=mysql --host=localhost --database=ma_base --schema=ma_base --user=root --password=mon_mot_de_passe

# Oracle
./bin/schemaspy --quiet --db=oracle --host=localhost --database=XE --schema=SYSTEM --user=system --password=mon_mot_de_passe
📋 Checklist complète avant de lancer
✅ Vérification des prérequis
bash
# 1. Vérifier PHP
php -v
# Minimum PHP 7.4

# 2. Vérifier Java
java -version
# Minimum Java 11

# 3. Vérifier les extensions PHP
php -m | grep -E "pdo|pdo_pgsql|pdo_mysql|pdo_oci"
# Doit afficher: pdo, pdo_pgsql, pdo_mysql, pdo_oci

# 4. Vérifier SchemaSpy JAR
ls -la environnement/tools/SchemaSpy7/schemaspy-*.jar
# Doit afficher: schemaspy-7.0.2.jar
✅ Structure des dossiers
bash
# Vérifier la structure
tree -L 3
Vous devriez avoir :

text
.
├── bin/
│   ├── schemaspy
│   ├── check-driver-versions.php
│   └── cleanup-drivers.php
├── config/
│   └── config.json
├── jdbc/
│   ├── postgresql-42.7.5.jar
│   ├── ojdbc11.jar
│   ├── mysql-connector-j-8.0.33.jar
│   ├── mariadb-java-client-3.5.9.jar
│   ├── mssql-jdbc-13.4.0.jre11.jar
│   └── mssql-jdbc-13.4.0.jre8.jar
├── src/
│   └── ...
├── environnement/
│   └── tools/
│       └── SchemaSpy7/
│           ├── schemaspy-7.0.2.jar
│           └── SCHEMA/  # Dossier des rapports
└── vendor/  # Après composer install
🎯 Premier lancement recommandé
Je vous suggère de commencer par PostgreSQL car c'est généralement le plus simple :

bash
# 1. Mode interactif - PostgreSQL
./bin/schemaspy
# Choisissez [1] PostgreSQL
# Suivez les instructions

# 2. Si ça fonctionne, passez en mode non-interactif
./bin/schemaspy \
    --quiet \
    --db=postgresql \
    --host=localhost \
    --database=postgres \
    --schema=public \
    --user=postgres \
    --password=votre_mot_de_passe

# 3. Si vous voulez voir les détails
./bin/schemaspy \
    --verbose \
    --db=postgresql \
    --host=localhost \
    --database=postgres \
    --schema=public \
    --user=postgres \
    --password=votre_mot_de_passe
📊 Que faire après le lancement ?
Si tout fonctionne ✅
Le rapport sera généré dans :

text
environnement/tools/SchemaSpy7/SCHEMA/{dbType}_{schema}_{timestamp}/
Ouvrez le fichier index.html dans votre navigateur.

Si vous avez des erreurs ❌
bash
# 1. Mode verbeux pour voir les détails
./bin/schemaspy --verbose

# 2. Vérifier les logs
tail -f /tmp/schemaspy_*.properties.log

# 3. Tester la connexion séparément
php -r "
try {
    \$pdo = new PDO('pgsql:host=localhost;dbname=postgres', 'postgres', 'password');
    echo '✅ Connexion OK';
} catch (\Exception \$e) {
    echo '❌ Erreur: ' . \$e->getMessage();
}
"
💡 Astuces
Pour gagner du temps
Créez un alias dans votre .bashrc ou .zshrc :

bash
alias schemaspy='./bin/schemaspy --verbose'
Pour générer automatiquement un rapport
bash
#!/bin/bash
# gen-doc.sh - Génération automatique de documentation

./bin/schemaspy \
    --quiet \
    --db=postgresql \
    --host=localhost \
    --database=production \
    --schema=public \
    --user=admin \
    --password="$DB_PASSWORD"
Pour voir l'aide
bash
./bin/schemaspy --help
🎉 Prochaine étape
Une fois que vous aurez testé avec PostgreSQL, vous pourrez :

Tester avec les autres bases de données (MySQL, Oracle, etc.)

Personnaliser le rapport (logo, favicon, titre)

Automatiser la génération avec un cron job

Intégrer dans votre CI/CD

Quelle base de données voulez-vous tester en premier ? Je peux vous donner les commandes spécifiques. 🚀
