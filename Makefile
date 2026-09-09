# Publinza — local development and deployment tasks.
.DEFAULT_GOAL := help
SHELL := /bin/bash

# Where this Makefile lives, which is the repository root.
#
# Every recipe below cds here first. `make` does not change directory on its
# own: run it through `make -C`, `make -f`, a panel that sets its own working
# directory, or simply from a subfolder, and `npm ci` looks for package.json in
# whatever directory the caller happened to be in and reports it missing. The
# file is in the repository; npm was somewhere else.
ROOT := $(patsubst %/,%,$(dir $(realpath $(firstword $(MAKEFILE_LIST)))))

# Every command below is anchored to ROOT. `npm` needs it to find package.json,
# and `docker compose` needs it to find docker-compose.yml.
IN_ROOT := cd $(ROOT) &&

NPM := $(IN_ROOT) npm
DC  := $(IN_ROOT) docker compose

# Artisan, in a container or on the host.
#
# The local stack runs PHP in Docker; a server that clones the repository and
# runs PHP natively has no such container, and `docker compose exec` there fails
# with a message about a service rather than about the deploy. Resolved once, at
# parse time, so every target below works in both places.
DOCKER_PHP := $(shell cd $(ROOT) && $(DC) ps --status=running --services 2>/dev/null | grep -qx php && echo yes)

ifeq ($(DOCKER_PHP),yes)
PHP     := $(DC) exec -T php
ARTISAN := $(PHP) php artisan
else
PHP     := $(IN_ROOT)
ARTISAN := $(IN_ROOT) php artisan
endif

.PHONY: help
help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## ---------------------------------------------------------------------------
## Setup
## ---------------------------------------------------------------------------

.PHONY: setup
setup: ## First-run setup: env, containers, deps, key, migrate, seed, search index
	@$(IN_ROOT) test -f .env || ($(IN_ROOT) cp .env.example .env && echo "Created .env from .env.example")
	@$(IN_ROOT) sed -i.bak -e "s/^UID=.*/UID=$$(id -u)/" -e "s/^GID=.*/GID=$$(id -g)/" .env && $(IN_ROOT) rm -f .env.bak
	$(DC) build
	$(DC) up -d
	$(PHP) composer install
	$(ARTISAN) key:generate
	$(MAKE) migrate
	$(ARTISAN) db:seed
	$(MAKE) search-index
	$(NPM) install
	$(NPM) run build
	@echo ""
	@echo "Add to /etc/hosts:  127.0.0.1  publinza.localhost app.publinza.localhost"
	@echo "Marketing  http://publinza.localhost"
	@echo "Advertiser http://app.publinza.localhost"
	@echo "Admin      http://publinza.localhost/asylogin"
	@echo "Mailpit    http://localhost:8025"

.PHONY: dev
dev: ## Start the stack and the Vite dev server
	$(DC) up -d
	$(NPM) run dev

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

# Every model with Scout's Searchable trait. The global palette reads all four,
# so a deploy that imports only the catalog leaves three of its groups silently
# empty — there is no error for a search against an index that was never filled.
SEARCHABLE := \
	"App\Domain\Catalog\Models\Website" \
	"App\Domain\Projects\Models\Project" \
	"App\Domain\Posts\Models\Post" \
	"App\Domain\Messaging\Models\Conversation"

.PHONY: search-index
search-index: ## Push Scout index settings and reindex every searchable model
	$(ARTISAN) scout:sync-index-settings
	@for model in $(SEARCHABLE); do $(ARTISAN) scout:import "$$model"; done

## ---------------------------------------------------------------------------
## Quality
## ---------------------------------------------------------------------------

.PHONY: test
test: ## Run every check: Pest, PHPStan, Pint, ESLint, Prettier, tsc, bundle isolation
	$(PHP) ./vendor/bin/pest --parallel
	$(PHP) ./vendor/bin/phpstan analyse --memory-limit=1G
	$(PHP) ./vendor/bin/pint --test
	$(NPM) run lint
	$(NPM) run format:check
	$(NPM) run typecheck
	$(NPM) run build
	$(NPM) run verify:bundles
	$(NPM) run verify:tokens
	$(NPM) run verify:scroll
	$(NPM) run verify:headings

.PHONY: pest
pest: ## Run the PHP test suite only
	$(PHP) ./vendor/bin/pest --parallel

.PHONY: stan
stan: ## Static analysis (PHPStan level 6)
	$(PHP) ./vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: fix
fix: ## Auto-fix PHP and JS formatting
	$(PHP) ./vendor/bin/pint
	$(NPM) run lint:fix
	$(NPM) run format

## ---------------------------------------------------------------------------
## Deploy
## ---------------------------------------------------------------------------

.PHONY: preflight
preflight: ## Check the deploy has what it needs before it takes the site down
	@echo "Repository root: $(ROOT)"
	@test -f "$(ROOT)/package.json" || { \
		echo ""; \
		echo "  package.json is not in $(ROOT)."; \
		echo ""; \
		echo "  It is committed at the repository root, so the checkout here is"; \
		echo "  partial. Compare this path with the one npm printed: its ENOENT"; \
		echo "  names the directory it looked in, and that is the whole answer."; \
		echo ""; \
		exit 1; }
	@command -v npm >/dev/null || { echo "  npm is not on PATH for this shell. A login shell may have it; a deploy hook may not."; exit 1; }
	@test -f "$(ROOT)/.env" || { echo "  No .env in $(ROOT). Copy .env.example and fill it in before deploying."; exit 1; }
	@echo "Node $$(node -v), npm $$(npm -v)"
	@echo "Artisan: $(if $(filter yes,$(DOCKER_PHP)),docker compose exec php,php on the host)"
	@echo "Preflight passed."

.PHONY: build
build: preflight ## Build production assets (all three surface bundles)
	$(NPM) ci
	$(NPM) run build
	@#
	@# public/build is gitignored, so a server only ever has the assets this
	@# step just made. A build that half-failed leaves no manifest and the app
	@# answers every request with "Vite manifest not found" — a 500 on every
	@# page, discovered by a visitor rather than by the deploy.
	@test -f "$(ROOT)/public/build/manifest.json" || { \
		echo ""; \
		echo "  The build produced no public/build/manifest.json."; \
		echo "  Every page would 500 with \"Vite manifest not found\". Stopping here."; \
		echo ""; \
		exit 1; }
	@echo "Assets built: $$(ls "$(ROOT)/public/build/assets" | wc -l) files."

.PHONY: deploy
deploy: ## Deploy to $(ENV) — usage: make deploy ENV=production
	@test -n "$(ENV)" || (echo "Set ENV, e.g. make deploy ENV=production" && exit 1)
	@# Built before the site goes down, so a failing build costs no downtime.
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
