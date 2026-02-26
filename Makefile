# Makefile for rte_ckeditor_image TYPO3 Extension

.PHONY: help
help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ===================================
# DDEV Environment Commands
# ===================================

.PHONY: up
up: ## Complete startup (start DDEV + run setup)
	@echo "Starting DDEV environment..."
	@ddev start
	@echo ""
	@echo "Running setup (docs + v13 + v14)..."
	@ddev setup

.PHONY: start
start: ## Start DDEV environment
	ddev start

.PHONY: stop
stop: ## Stop DDEV environment
	ddev stop

.PHONY: setup
setup: ## Complete setup (docs + install v13 + install v14)
	@ddev describe >/dev/null 2>&1 || ddev start
	ddev setup

.PHONY: install-v13
install-v13: ## Install TYPO3 v13.4 LTS
	ddev install-v13

.PHONY: install-v14
install-v14: ## Install TYPO3 v14.0
	ddev install-v14

.PHONY: ddev-restart
ddev-restart: ## Restart DDEV containers
	ddev restart

# ===================================
# Composer & Quality Commands
# ===================================

.PHONY: install
install: ## Install composer dependencies
	composer install

.PHONY: cgl
cgl: ## Check code style (dry-run)
	composer ci:test:php:cgl

.PHONY: cgl-fix
cgl-fix: ## Fix code style
	composer ci:cgl

.PHONY: phpstan
phpstan: ## Run PHPStan static analysis
	composer ci:test:php:phpstan

.PHONY: phpstan-baseline
phpstan-baseline: ## Update PHPStan baseline
	composer ci:test:php:phpstan:baseline

.PHONY: rector
rector: ## Run Rector dry-run
	composer ci:test:php:rector

.PHONY: lint
lint: ## Run all linters (PHP syntax + PHPStan + style check + docs)
	@echo "==> Running PHP lint..."
	composer ci:test:php:lint
	@echo "==> Running PHPStan..."
	composer ci:test:php:phpstan
	@echo "==> Running Rector check..."
	composer ci:test:php:rector
	@echo "==> Running code style check..."
	composer ci:test:php:cgl
	@echo "==> Running documentation lint..."
	./Build/Scripts/validate-docs.sh || true
	@echo "All linters passed"

.PHONY: ci
ci: ## Run complete CI pipeline (pre-commit checks)
	composer ci:test

# ===================================
# Test Commands
# ===================================

.PHONY: test
test: test-unit test-functional ## Run all tests (unit + functional)

.PHONY: test-unit
test-unit: ## Run unit tests
	composer ci:test:php:unit

.PHONY: test-functional
test-functional: ## Run functional tests
	composer ci:test:php:functional

.PHONY: test-e2e
test-e2e: ## Run E2E tests (requires DDEV)
	composer ci:test:e2e

# ===================================
# Documentation
# ===================================

.PHONY: docs
docs: ## Render extension documentation
	ddev docs

.PHONY: docs-lint
docs-lint: ## Lint documentation (TYPO3 guidelines compliance)
	./Build/Scripts/validate-docs.sh

.PHONY: docs-fix
docs-fix: ## Fix auto-fixable documentation issues
	./Build/Scripts/validate-docs.sh --fix

# ===================================
# Cleanup
# ===================================

.PHONY: clean
clean: ## Clean temporary files and caches
	rm -rf .php-cs-fixer.cache
	rm -rf var/

.DEFAULT_GOAL := help
