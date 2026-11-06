DC ?= docker compose
PHP = $(DC) exec app

export HOST_UID ?= $(shell id -u)
export HOST_GID ?= $(shell id -g)

.DEFAULT_GOAL := help

.PHONY: help init env build up down restart ps logs shell composer artisan migrate test lint fix analyse check

help: ## Show available commands
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

init: env build up ## First start: .env, images, containers, dependencies, migrations
	$(PHP) composer install --no-interaction
	$(PHP) php artisan key:generate --ansi
	$(PHP) php artisan migrate --force
	$(DC) restart scheduler
	@echo "Ready: http://localhost:$$(grep -E '^APP_PORT=' .env | cut -d= -f2)"

env: ## Create .env from .env.example (if missing)
	@test -f .env || cp .env.example .env

build: ## Build images
	$(DC) build

up: ## Start containers
	$(DC) up -d

down: ## Stop containers
	$(DC) down

restart: down up ## Restart containers

ps: ## Container status
	$(DC) ps

logs: ## Follow container logs
	$(DC) logs -f

shell: ## Shell inside the PHP container
	$(PHP) sh

composer: ## Run composer: make composer c="require foo/bar"
	$(PHP) composer $(c)

artisan: ## Run artisan: make artisan c="migrate:status"
	$(PHP) php artisan $(c)

migrate: ## Run migrations
	$(PHP) php artisan migrate

test: ## Run the test suite
	$(PHP) composer test

lint: ## Check code style (PSR-12)
	$(PHP) composer lint

fix: ## Fix code style
	$(PHP) composer fix

analyse: ## Static analysis (PHPStan)
	$(PHP) composer analyse

check: lint analyse test ## Lint + static analysis + tests
