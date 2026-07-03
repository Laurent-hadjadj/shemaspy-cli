#!/usr/bin/env bash
# SchemaSpy CLI - Script d'exécution
#
# @author  Laurent HADJADJ - maMoulinette
# @version 3.0.0

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Vérifier PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP n'est pas installé"
    exit 1
fi

# Vérifier l'existence de l'autoloader
if [ ! -f "$PROJECT_DIR/vendor/autoload.php" ]; then
    echo "⚠️  Composer n'a pas été exécuté. Lancement de composer install..."
    composer install --no-dev --optimize-autoloader
fi

# Exécuter l'application
php "$PROJECT_DIR/src/bootstrap.php" "$@"
