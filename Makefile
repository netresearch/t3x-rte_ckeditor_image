# Makefile for rte_ckeditor_image TYPO3 Extension

# Shared targets: help, install, update, cgl, cgl-fix, phpstan, phpstan-baseline,
# rector, rector-fix, lint, quality, test, test-unit, test-functional, ci, clean
-include .Build/vendor/netresearch/typo3-ci-workflows/Makefile.include

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
# Extension-Specific Test Commands
# ===================================

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
