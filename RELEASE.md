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

### 4. Tag; CI publishes the release

Pushing a tag `v*` starts `.github/workflows/release.yml`, which calls the shared `netresearch/typo3-ci-workflows` workflow `release-typo3-extension.yml`. It builds the archives with SBOMs, signatures and build-provenance attestations, creates the GitHub Release, uploads the version to TER, and checks Packagist and docs.typo3.org. Do not create the GitHub Release by hand.

1. **Update local clone** to the merged bump commit on the **same branch you released from** (`TYPO3_12` or `main`; version in `ext_emconf.php` must match the tag).

2. **Create and push a signed annotated tag** on that commit:

```bash
git checkout <branch>          # e.g. TYPO3_12 or main
git pull origin <branch>
git tag -s vX.Y.Z -m "vX.Y.Z"
git push origin vX.Y.Z
```

3. **Wait for the *Release* workflow run of the tag.** On `TYPO3_12` it calls the shared workflow with `make-latest: false`, so a 12.0.X release never takes the "Latest" badge from a `main` release.

4. **Optional — polish notes after publish:** editing description only is allowed, e.g.  
   `gh release edit vX.Y.Z --notes-file release-notes.md`  
   (Do **not** delete or recreate the tag.)

5. **Re-publish only if needed:** a failed target of the run is retried with **Re-run failed jobs** on that run. Later, use **Actions** → *Republish* (`.github/workflows/republish.yml` on `main`) → *workflow_dispatch* with the tag `vX.Y.Z`; it checks out that tag, so it covers `TYPO3_12` tags too. Normal releases should not need this.

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

The release body carries a "Publication status" section that the *Release* run fills in for each target. Verify availability:

- **Packagist**: https://packagist.org/packages/netresearch/rte-ckeditor-image (syncs from new **tags** via webhook; allow a few minutes)
- **TER**: https://extensions.typo3.org/extension/rte_ckeditor_image/ — the *Release* run uploads the version in its TER job. It is not a separate “TER polls GitHub tags” integration.

## CI/CD Workflows

| Workflow | File | Triggers | Purpose |
|----------|------|----------|---------|
| **CI** | `.github/workflows/ci.yml` | Push + PR to `TYPO3_12` | Build matrix: lint, CGL, PHPStan, Rector, unit tests, functional tests, coverage |
| **PR Quality Gates** | `.github/workflows/pr-quality.yml` | PR to `TYPO3_12` | Auto-approve for solo maintainer |
| **Release** | `.github/workflows/release.yml` | Tag push `v*` | Builds and signs the archives, creates the GitHub Release, publishes to TER, checks Packagist and docs.typo3.org (shared `release-typo3-extension.yml`) |
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
- [ ] *Release* workflow run of the tag succeeded; the GitHub Release exists and, for `TYPO3_12`, is not marked "Latest"
- [ ] Release notes include bug reporters and contributors
- [ ] Packagist shows new version
- [ ] TER shows new version
