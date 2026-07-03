#!/bin/bash
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
    echo "  - postgresql-42.7.5.jar (PostgreSQL)"
    echo "  - ojdbc11.jar (Oracle)"
    echo "  - mysql-connector-java-8.0.33.jar (MySQL)"
    echo "  - mariadb-java-client-2.2.1.jar (MariaDB)"
    echo "  - sqljdbc42.jar (SQL Server)"
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
