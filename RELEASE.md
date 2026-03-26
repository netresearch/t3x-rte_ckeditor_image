# Release Process

## Branch Strategy

| Branch | TYPO3 Version | PHP | Status |
|--------|--------------|-----|--------|
| `main` | ^13.4 / ^14.0 | ^8.2 | Active development |
| `TYPO3_12` | ^12.4 | 8.1–8.4 | Maintenance only |

## Creating a Release

### 1. Verify Changes

Check unreleased commits since the last tag:

```bash
# For TYPO3_12 branch:
git log v12.0.X..origin/TYPO3_12 --oneline

# For main branch:
git log v13.X.X..origin/main --oneline
```

### 2. Bump Version

Update `ext_emconf.php` with the new version number:

```php
'version' => '12.0.11',  // or 13.x.x for main
```

This is the **only** file that needs a version bump. Composer resolves version from the git tag.

### 3. Create a Pull Request

Branch protection requires changes go through a PR:

```bash
git checkout -b chore/bump-version-X.Y.Z
# edit ext_emconf.php
git add ext_emconf.php
git commit -m "chore: bump version to X.Y.Z"
git push -u origin chore/bump-version-X.Y.Z
gh pr create --base TYPO3_12 --title "chore: bump version to X.Y.Z" --body "Bump version for release"
```

Wait for CI to pass (build matrix: PHP 8.1/8.2/8.3/8.4 on TYPO3_12, PHP 8.2–8.5 on main), then merge:

```bash
gh pr merge <number> --merge --delete-branch
```

### 4. Create GitHub Release

```bash
gh release create vX.Y.Z --target <branch> --title "vX.Y.Z" --notes "release notes"
```

#### Release Notes Template

```markdown
## Bug Fixes

- **Short description** (#PR): Details. Fixes #issue.

## Features

- **Short description** (#PR): Details.

## CI/CD

- **Short description** (#PR): Details.

## Contributors

- @reporter — bug report (#issue)
- @author — description of contribution (#PR)
```

Always credit both **bug reporters** (from linked issues) and **code contributors** (from PRs).

### 5. Verify Distribution

After creating the GitHub release, verify availability:

- **Packagist**: https://packagist.org/packages/netresearch/rte-ckeditor-image (auto-syncs via webhook)
- **TER**: https://extensions.typo3.org/extension/rte_ckeditor_image/ (auto-syncs from GitHub tag)

Both should pick up the new version within minutes.

## Versioning Scheme

- **TYPO3_12 branch**: `12.0.X` (patch only, maintenance releases)
- **main branch**: `13.X.Y` (minor for features, patch for fixes)

## Checklist

- [ ] All CI checks pass on the branch
- [ ] `ext_emconf.php` version bumped via PR
- [ ] PR merged to target branch
- [ ] GitHub release created with tag `vX.Y.Z` targeting correct branch
- [ ] Release notes include bug reporters and contributors
- [ ] Packagist shows new version
- [ ] TER shows new version
