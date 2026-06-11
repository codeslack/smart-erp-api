# Define variables
COMPOSER = composer
PHP=php
PHPUNIT = ./vendor/bin/phpunit
ARTISAN=$(PHP) artisan
LOG_DATE = $(shell date +'%Y-%m-%d')
LOG_FILE = storage/logs/laravel-$(LOG_DATE).log

.PHONY: help setup test clear routes logs logs-single

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

setup: ## Install dependencies and run migrations
	composer install
	cp .env.example .env
	$(ARTISAN) key:generate
	$(ARTISAN) migrate --seed

dump-autoload: ## Dump the autoloader
	composer dump-autoload

test: ## Run feature and unit tests
	$(ARTISAN) test

clear: ## Clear all application caches
	$(ARTISAN) optimize:clear
	$(ARTISAN) cache:clear
	$(ARTISAN) config:clear
	$(ARTISAN) route:clear
	@echo "All Laravel caches have been cleared!"

# Additional helpful commands
dev: ## Start the local development server
	$(ARTISAN) serve

fresh-db: ## Wipe database and re-run all migrations/seeds
	$(ARTISAN) migrate:fresh --seed

db-up: ## run migrations
	$(ARTISAN) migrate	

routes: ## List all registered routes
	$(ARTISAN) route:list

routes-api: ## List only API routes
	$(ARTISAN) route:list --path=api

routes-grep: ## Search for a specific route (usage: make routes-grep find=user)
	$(ARTISAN) route:list | grep $(find)

tinker: ## Enter the interactive shell
	$(ARTISAN) tinker

table: ## Create a new migration file (usage: make table name=users)
	$(ARTISAN) make:migration create_$(name)_table --create=$(name)

lint: ## Run Laravel Pint to fix code style
	./vendor/bin/pint

analyze: ## Run static analysis (if using Larastan/PHPStan)
	./vendor/bin/phpstan analyze

coverage: ## Run tests with coverage report
	$(PHPUNIT) --coverage-html reports/

logs-tail: ## Show today's logs (daily format)
	@if [ -f $(LOG_FILE) ]; then tail -f $(LOG_FILE); else echo "No log file found for today: $(LOG_FILE)"; fi

logs-tails-single: ## Show the standard single log file
	tail -f storage/logs/laravel.log

logs: ## Open today's log file in VS Code
	@if [ -f $(LOG_FILE) ]; then code $(LOG_FILE); else echo "No log file found for today: $(LOG_FILE)"; fi

logs-single: ## Open the standard single log file in VS Code
	code storage/logs/laravel.log
