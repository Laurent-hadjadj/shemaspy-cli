# SchemaSpy CLI Makefile
# 
# @author  Laurent HADJADJ - maMoulinette
# @version 3.0.0

.PHONY: help install update test clean coverage docker-build docker-test

# Variables
PHP = php
COMPOSER = composer
PHPUNIT = ./vendor/bin/phpunit

help:
	@echo "SchemaSpy CLI - Commandes disponibles:"
	@echo ""
	@echo "  make install         - Installe les dépendances Composer"
	@echo "  make update          - Met à jour les dépendances Composer"
	@echo "  make test            - Exécute les tests unitaires"
	@echo "  make coverage        - Exécute les tests avec couverture de code"
	@echo "  make clean           - Nettoie les fichiers temporaires"
	@echo "  make docker-build    - Construit l'image Docker"
	@echo "  make docker-test     - Exécute les tests avec Docker"
	@echo "  make run             - Exécute SchemaSpy"
	@echo ""

install:
	@echo "📦 Installation des dépendances..."
	$(COMPOSER) install --optimize-autoloader
	@chmod +x bin/schemaspy
	@echo "✅ Installation terminée"

update:
	@echo "🔄 Mise à jour des dépendances..."
	$(COMPOSER) update
	@echo "✅ Mise à jour terminée"

test:
	@echo "🧪 Exécution des tests..."
	$(PHPUNIT)
	@echo "✅ Tests terminés"

coverage:
	@echo "📊 Exécution des tests avec couverture de code..."
	$(PHPUNIT) --coverage-html coverage/
	@echo "✅ Couverture générée dans coverage/"

clean:
	@echo "🧹 Nettoyage..."
	rm -rf vendor/
	rm -rf coverage/
	rm -f composer.lock
	rm -f schemaspy.last.json
	rm -f /tmp/schemaspy_*.properties
	@echo "✅ Nettoyage terminé"

docker-build:
	@echo "🐳 Construction de l'image Docker..."
	docker-compose build
	@echo "✅ Image construite"

docker-test:
	@echo "🐳 Exécution des tests avec Docker..."
	docker-compose up --abort-on-container-exit
	@echo "✅ Tests terminés"

run:
	@echo "🚀 Exécution de SchemaSpy..."
	./bin/schemaspy
