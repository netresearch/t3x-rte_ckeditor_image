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
git log $(git tag --list 'v12.0.*' --sort=-v:refname | head -n 1)..origin/TYPO3_12 --oneline

# For main branch:
git log $(git tag --list 'v13.*' --sort=-v:refname | head -n 1)..origin/main --oneline
```

### 2. Bump Version

Update `ext_emconf.php` with the new version number:

```php
'version' => '12.0.X',  // or 13.X.Y for main
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
gh pr create --base <branch> --title "chore: bump version to X.Y.Z" --body "Bump version for release"
```

Wait for CI to pass (build matrix: PHP 8.1/8.2/8.3/8.4 on TYPO3_12, PHP 8.2–8.5 on main), then merge:

```bash
gh pr merge <number> --merge --delete-branch
```

### 4. Tag, then publish the GitHub Release

**Do not use `gh release create` from the CLI** to mint a tag in one step with the release. That pattern is easy to get wrong (wrong target commit, duplicate tags, or conflicting release metadata). This repository uses a **tag-first** flow: tag the correct commit locally, push the tag, then attach the GitHub Release to that tag.

1. **Update local clone** to the merged bump commit on the **same branch you released from** (`TYPO3_12` or `main`; version in `ext_emconf.php` must match the tag).

2. **Create a signed annotated tag** on that commit:

```bash
git checkout <branch>          # e.g. TYPO3_12 or main
git pull origin <branch>
git tag -s vX.Y.Z -m "vX.Y.Z"
git push origin vX.Y.Z
```

3. **Publish the GitHub Release from the existing tag** (web UI): Repository → **Releases** → **Draft a new release** → choose tag **`vX.Y.Z`** → paste release notes → **Publish release**.  
   This triggers `.github/workflows/publish-to-ter.yml` (`release: published`).

4. **Optional — polish notes after publish:** editing description only is allowed, e.g.  
   `gh release edit vX.Y.Z --notes-file release-notes.md`  
   (Do **not** delete or recreate the tag or re-run a full `gh release create` for the same version.)

5. **TER re-publish only if needed:** use **Actions** → *Publish new extension version to TER* → *workflow_dispatch* and the version string. The workflow accepts **`X.Y.Z`** or **`vX.Y.Z`**. Normal releases should not need this.

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

After the GitHub Release is **published**, verify availability:

- **Packagist**: https://packagist.org/packages/netresearch/rte-ckeditor-image (auto-syncs via webhook)
- **TER**: https://extensions.typo3.org/extension/rte_ckeditor_image/ (auto-syncs from GitHub tag)

Both should pick up the new version within minutes.

## CI/CD Workflows

| Workflow | File | Triggers | Purpose |
|----------|------|----------|---------|
| **CI** | `.github/workflows/ci.yml` | Push + PR to `TYPO3_12` | Build matrix: lint, CGL, PHPStan, Rector, unit tests, functional tests, coverage |
| **PR Quality Gates** | `.github/workflows/pr-quality.yml` | PR to `TYPO3_12` | Auto-approve for solo maintainer |
| **Publish to TER** | `.github/workflows/publish-to-ter.yml` | GitHub release published | Uploads extension to TER via API |
| **CodeQL** | `.github/workflows/codeql-analysis.yml` | Push + PR + weekly schedule | Security analysis |
| **Add to Project** | `.github/workflows/add-to-project.yml` | Issue opened | Adds issues to project board |

The required status check for branch protection is **"Build ✓"** (the `build-summary` job in `ci.yml`).

## Versioning Scheme

- **TYPO3_12 branch**: `12.0.X` (patch only, maintenance releases)
- **main branch**: `13.X.Y` (minor for features, patch for fixes)

## Checklist

- [ ] All CI checks pass on the version-bump PR
- [ ] `ext_emconf.php` version bumped via PR
- [ ] PR merged to target branch
- [ ] Signed tag `vX.Y.Z` pushed; tag matches `ext_emconf.php`
- [ ] GitHub **Release** published for **existing** tag `vX.Y.Z` (not `gh release create` for a new tag)
- [ ] Release notes include bug reporters and contributors
- [ ] Packagist shows new version
- [ ] TER shows new version
