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

# Script de vérification rapide des drivers JDBC

echo "🔍 Vérification des drivers JDBC..."
echo ""

JDBC_DIR="jdbc"

if [ ! -d "$JDBC_DIR" ]; then
    echo "❌ Dossier '$JDBC_DIR' introuvable"
    exit 1
fi

# Compter les fichiers JAR
JAR_COUNT=$(find "$JDBC_DIR" -name "*.jar" | wc -l)

if [ "$JAR_COUNT" -eq 0 ]; then
    echo "❌ Aucun fichier JAR trouvé dans '$JDBC_DIR'"
    echo ""
    echo "📥 Drivers requis:"
    echo "  - postgresql-42.7.13.jar (PostgreSQL)"
    echo "  - ojdbc11-23.26.3.0.0.jar (Oracle)"
    echo "  - mysql-connector-j-26.7.0.jar (MySQL)"
    echo "  - mariadb-java-client-3.5.10.jar (MariaDB)"
    echo "  - mssql-jdbc-13.6.0.jre11.jar (SQL Server)"
    echo "  - mssql-jdbc-13.6.0.jre8.jar (SQL Server)"
    exit 1
fi

echo "✅ $JAR_COUNT drivers JDBC trouvés"
echo ""
echo "📦 Liste des drivers:"

find "$JDBC_DIR" -name "*.jar" -exec basename {} \; | while read -r jar; do
    SIZE=$(du -h "$JDBC_DIR/$jar" | cut -f1)
    echo "  - $jar ($SIZE)"
done

echo ""
echo "ℹ️  Vérification des versions attendues dans config.json..."
php bin/check-drivers.php
