# Suggested Commands

## Development Workflow

### Code Quality & Formatting
```bash
# Fix code style (PHP-CS-Fixer)
composer ci:cgl

# Check code style (dry-run)
composer ci:test:php:cgl

# Lint PHP files
composer ci:test:php:lint

# Static analysis (PHPStan level 6)
composer ci:test:php:phpstan

# Generate PHPStan baseline
composer ci:test:php:phpstan:baseline

# Apply Rector refactoring rules
composer ci:rector

# Check Rector rules (dry-run)
composer ci:test:php:rector

# Run all tests (lint + phpstan + rector + cgl)
composer ci:test
```

### Git Workflow
```bash
# Check status
git status
git branch

# Create feature branch
git checkout -b feature/your-feature-name

# View recent commits
git log --oneline -20

# View changes
git diff
```

### Common System Commands (Linux)
```bash
# List directory contents
ls -la

# Find files
find . -name "*.php" -type f

# Search in files
grep -r "pattern" .

# Navigate directories
cd /path/to/directory
pwd
```

## Task Completion Checklist
When finishing a task, run:
```bash
composer ci:test
```
This ensures:
- ✅ PHP syntax is valid
- ✅ Code follows PSR-12/PER-CS2.0 standards
- ✅ PHPStan level 6 passes
- ✅ Rector refactoring rules are satisfied