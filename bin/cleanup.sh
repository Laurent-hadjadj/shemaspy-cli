#!/bin/bash

"/**
 *  Ma-Moulinette - ShemaSpy-Cli
 *  --------------
 *  Copyright (c) 2015-2026.
 *  Laurent HADJADJ <laurent_h@me.com>.
 *  Licensed Creative Common  CC-BY-NC-SA 4.0.
 *  ---
 *  Vous pouvez obtenir une copie de la licence à l'adresse suivante :
 *  http://creativecommons.org/licenses/by-nc-sa/4.0/
 */"

# Nettoyage rapide des drivers obsolètes

echo "🧹 Nettoyage des drivers JDBC obsolètes"
echo ""

JDBC_DIR="jdbc"

if [ ! -d "$JDBC_DIR" ]; then
    echo "❌ Dossier '$JDBC_DIR' introuvable"
    exit 1
fi

cd "$JDBC_DIR" || exit

echo "📦 Drivers à conserver:"
echo "  ✅ postgresql-42.7.13.jar"
echo "  ✅ ojdbc11-23.26.3.0.0.jar"
echo "  ✅ mysql-connector-j-26.7.0.jar"
echo "  ✅ mariadb-java-client-3.5.10.jar"
echo "  ✅ mssql-jdbc-13.6.0.jre11.jar"
echo "  ✅ mssql-jdbc-13.6.0.jre8.jar"
echo ""

echo "🗑️  Drivers à supprimer:"
echo "  ❌ postgresql-42.7.5.jar"
echo "  ❌ ojdbc11.jar"
echo "  ❌ mariadb-java-client-2.2.1.jar"
echo "  ❌ mariadb-java-client-2.7.9.jar"
echo "  ❌ mariadb-java-client-3.5.9.jar"
echo "  ❌ mysql-connector-java-5.1.38-bin.jar"
echo "  ❌ mysql-connector-java-6.0.4.jar"
echo "  ❌ mysql-connector-j-8.0.33.jar"
echo "  ❌ mssql-jdbc-13.4.0.jre11.jar"
echo "  ❌ mssql-jdbc-13.4.0.jre8.jar"
echo "  ❌ ojdbc6.jar"
echo "  ❌ sqljdbc41.jar"
echo "  ❌ sqljdbc42.jar"
echo "  ❌ junixsocket-common-2.0.4.jar"
echo "  ❌ junixsocket-mysql-2.0.4.jar"
echo ""

read -p "Voulez-vous supprimer ces fichiers ? (o/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Oo]$ ]]; then
    rm -f mariadb-java-client-2.2.1.jar
    rm -f mariadb-java-client-2.7.9.jar
    rm -f mariadb-java-client-3.5.9.jar
    rm -f mysql-connector-java-5.1.38-bin.jar
    rm -f mysql-connector-java-6.0.4.jar
    rm -f mysql-connector-j-8.0.33.jar
    rm -f mssql-jdbc-13.4.0.jre11.jar
    rm -f mssql-jdbc-13.4.0.jre8.jar
    rm -f postgresql-42.7.5.jar
    rm -f ojdbc6.jar
    rm -f ojdbc11.jar
    rm -f sqljdbc41.jar
    rm -f sqljdbc42.jar
    rm -f junixsocket-common-2.0.4.jar
    rm -f junixsocket-mysql-2.0.4.jar
    echo "✅ Nettoyage terminé !"
else
    echo "ℹ️  Annulé"
fi

cd ..
