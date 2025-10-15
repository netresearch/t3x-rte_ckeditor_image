# Task Completion Checklist

## Before Committing Code

### 1. Run Quality Checks
```bash
# Run full test suite (REQUIRED)
composer ci:test
```

This command runs:
- ✅ PHP Lint (syntax validation)
- ✅ PHPStan Level 6 (static analysis)
- ✅ Rector (refactoring rules check)
- ✅ PHP-CS-Fixer (code style check)

### 2. Fix Any Issues

#### Code Style Issues
```bash
# Auto-fix code style
composer ci:cgl
```

#### Rector Issues
```bash
# Apply rector refactoring
composer ci:rector
```

### 3. Verify Changes
```bash
# Review all changes
git diff

# Check file status
git status
```

## Code Modification Guidelines

### PHP Files
- ✅ Add `declare(strict_types=1);`
- ✅ Include proper PHPDoc header with license
- ✅ Use type hints for all parameters and return types
- ✅ Follow PSR-12/PER-CS2.0 standards
- ✅ Import classes, functions, constants globally

### JavaScript Files
- ✅ Use ES6 modules
- ✅ Follow CKEditor 5 plugin patterns
- ✅ Include proper JSDoc comments for complex functions
- ✅ Test in browser console if modifying CKEditor integration

### Configuration Files (YAML, TypoScript)
- ✅ Maintain consistent indentation
- ✅ Follow TYPO3 conventions
- ✅ Document non-obvious configuration decisions

## Testing Workflow

### Manual Testing Checklist (if applicable)
1. ✅ Test image selection in RTE
2. ✅ Test image editing (double-click)
3. ✅ Test style drop-down functionality
4. ✅ Test image with link
5. ✅ Verify frontend rendering
6. ✅ Check backend preview

### Automated Testing
```bash
# Run specific test suite (when available)
# Currently: Functional tests for data handling
.build/bin/phpunit -c Build/phpunit/FunctionalTests.xml
```

## Git Commit Guidelines

### Branch Strategy
- Main branch: `main`
- Feature branches: `feature/descriptive-name`
- Bugfix branches: `bugfix/issue-description`

### Commit Message Format
```
[TYPE] Short description (50 chars max)

Longer explanation if needed (wrap at 72 chars).
Include context, reasoning, and impact.

Closes #123 (if fixing an issue)
```

**Types**: FEATURE, BUGFIX, TASK, DOCS, REFACTOR

### Example Commit Messages
```
[BUGFIX] Add GeneralHtmlSupport to required plugins

The style drop-down was not working with typo3image elements
because GeneralHtmlSupport was missing from plugin dependencies.

[FEATURE] Add CSS class field to image dialog

Allows editors to add custom CSS classes to images
directly from the CKEditor image dialog.
```

## When Task is Truly Complete
- ✅ Code quality checks pass (`composer ci:test`)
- ✅ Manual testing completed (if applicable)
- ✅ Git status clean (no unexpected changes)
- ✅ Commit message descriptive and clear
- ✅ Branch pushed to remote (if collaborating)
- ✅ Documentation updated (if public API changed)

## Checklist Summary
```
[ ] composer ci:test → All green
[ ] git diff → Changes reviewed
[ ] git status → Only intended files modified
[ ] Manual testing done (if needed)
[ ] Commit message written
[ ] Ready to commit/push
```