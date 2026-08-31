# Publinza — local development and deployment tasks.
.DEFAULT_GOAL := help
SHELL := /bin/bash

DC      := docker compose
PHP     := $(DC) exec -T php
ARTISAN := $(PHP) php artisan

.PHONY: help
help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## ---------------------------------------------------------------------------
## Setup
## ---------------------------------------------------------------------------

.PHONY: setup
setup: ## First-run setup: env, containers, deps, key, migrate, seed, search index
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")
	@sed -i.bak -e "s/^UID=.*/UID=$$(id -u)/" -e "s/^GID=.*/GID=$$(id -g)/" .env && rm -f .env.bak
	$(DC) build
	$(DC) up -d
	$(PHP) composer install
	$(ARTISAN) key:generate
	$(MAKE) migrate
	$(ARTISAN) db:seed
	$(MAKE) search-index
	npm install
	npm run build
	@echo ""
	@echo "Add to /etc/hosts:  127.0.0.1  publinza.localhost app.publinza.localhost"
	@echo "Marketing  http://publinza.localhost"
	@echo "Advertiser http://app.publinza.localhost"
	@echo "Admin      http://publinza.localhost/asylogin"
	@echo "Mailpit    http://localhost:8025"

.PHONY: dev
dev: ## Start the stack and the Vite dev server
	$(DC) up -d
	npm run dev

.PHONY: up
up: ## Start containers in the background
	$(DC) up -d

.PHONY: down
down: ## Stop containers
	$(DC) down

.PHONY: shell
shell: ## Open a shell in the php container
	$(DC) exec php bash

.PHONY: logs
logs: ## Tail container logs
	$(DC) logs -f --tail=100

## ---------------------------------------------------------------------------
## Database and search
## ---------------------------------------------------------------------------

.PHONY: migrate
migrate: ## Run migrations
	$(ARTISAN) migrate --force

.PHONY: fresh
fresh: ## Drop everything, migrate and reseed
	$(ARTISAN) migrate:fresh --seed
	$(MAKE) search-index

.PHONY: search-index
search-index: ## Push Scout index settings and reindex the catalog
	$(ARTISAN) scout:sync-index-settings
	$(ARTISAN) scout:import "App\Domain\Catalog\Models\Site"

## ---------------------------------------------------------------------------
## Quality
## ---------------------------------------------------------------------------

.PHONY: test
test: ## Run every check: Pest, PHPStan, Pint, ESLint, Prettier, tsc, bundle isolation
	$(PHP) ./vendor/bin/pest --parallel
	$(PHP) ./vendor/bin/phpstan analyse --memory-limit=1G
	$(PHP) ./vendor/bin/pint --test
	npm run lint
	npm run format:check
	npm run typecheck
	npm run build
	npm run verify:bundles

.PHONY: pest
pest: ## Run the PHP test suite only
	$(PHP) ./vendor/bin/pest --parallel

.PHONY: stan
stan: ## Static analysis (PHPStan level 6)
	$(PHP) ./vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: fix
fix: ## Auto-fix PHP and JS formatting
	$(PHP) ./vendor/bin/pint
	npm run lint:fix
	npm run format

## ---------------------------------------------------------------------------
## Deploy
## ---------------------------------------------------------------------------

.PHONY: build
build: ## Build production assets (all three surface bundles)
	npm ci
	npm run build

.PHONY: deploy
deploy: ## Deploy to $(ENV) — usage: make deploy ENV=production
	@test -n "$(ENV)" || (echo "Set ENV, e.g. make deploy ENV=production" && exit 1)
	$(MAKE) build
	$(ARTISAN) down --render="errors::503"
	$(ARTISAN) migrate --force
	$(ARTISAN) config:cache
	$(ARTISAN) route:cache
	$(ARTISAN) view:cache
	$(ARTISAN) event:cache
	$(ARTISAN) scout:sync-index-settings
	$(ARTISAN) horizon:terminate
	$(ARTISAN) up
	@echo "Deployed to $(ENV)."

.PHONY: clear
clear: ## Clear every cache
	$(ARTISAN) optimize:clear
