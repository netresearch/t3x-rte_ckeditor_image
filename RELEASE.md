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

**Never let `gh release create` mint the tag for you** (i.e. running it before the tag exists). That pattern is easy to get wrong (wrong target commit, duplicate tags, or conflicting release metadata). This repository uses a **tag-first** flow: tag the correct commit locally, push the tag, *then* attach the GitHub Release to that existing tag — either via CLI (`gh release create vX.Y.Z` reuses the existing tag) or via the web UI.

1. **Update local clone** to the merged bump commit on the **same branch you released from** (`TYPO3_12` or `main`; version in `ext_emconf.php` must match the tag).

2. **Create a signed annotated tag** on that commit:

```bash
git checkout <branch>          # e.g. TYPO3_12 or main
git pull origin <branch>
git tag -s vX.Y.Z -m "vX.Y.Z"
git push origin vX.Y.Z
```

3. **Publish the GitHub Release from the existing tag.** Two options — **CLI is the default for `TYPO3_12` (and any non-default branch)** because it forces the correct "Latest" flag.

   **a) CLI (recommended for `TYPO3_12`):** the existing tag is reused; no new tag is created.

   ```bash
   gh release create vX.Y.Z --latest=false --title "vX.Y.Z" --notes-file release-notes.md
   ```

   > ⚠️ **`--latest=false` is mandatory for `TYPO3_12` (and any non-default branch).** GitHub assigns the "Latest" badge by **release creation timestamp**, not semver. A v12.0.X release published *after* a newer `main` release (e.g. v13.Y.Z) will steal the "Latest" badge unless `--latest=false` is passed. See [v12.0.13](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.13) for the canonical example.

   For `main` releases the latest tag is also the latest by semver, so omit the flag (default `--latest=true` is correct).

   **b) Web UI (alternative):** Repository → **Releases** → **Draft a new release** → choose tag **`vX.Y.Z`** → paste release notes → **Publish release**.

   > ⚠️ When publishing via Web UI on `TYPO3_12` (or any non-default branch), **uncheck "Set as the latest release"**. The default is checked, which on a maintenance-branch release would steal the "Latest" badge from a newer `main` release.

   Either path triggers `.github/workflows/publish-to-ter.yml` (`release: published`).

4. **Optional — polish notes after publish:** editing description only is allowed, e.g.  
   `gh release edit vX.Y.Z --notes-file release-notes.md`  
   (Do **not** delete or recreate the tag. If the "Latest" badge was incorrectly assigned, correct it via the Web UI on the appropriate release, or `gh release edit vX.Y.Z --latest=<true|false>` — this only changes release metadata, not the tag or assets.)

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

- **Packagist**: https://packagist.org/packages/netresearch/rte-ckeditor-image (syncs from new **tags** via webhook; allow a few minutes)
- **TER**: https://extensions.typo3.org/extension/rte_ckeditor_image/ — the **Publish new extension version to TER** workflow uploads the version (runs on **`release: published`** for that tag, or via **`workflow_dispatch`**). It is not a separate “TER polls GitHub tags” integration.

Allow a few minutes after the workflow succeeds before expecting the TER page to list the version.

## CI/CD Workflows

| Workflow | File | Triggers | Purpose |
|----------|------|----------|---------|
| **CI** | `.github/workflows/ci.yml` | Push + PR to `TYPO3_12` | Build matrix: lint, CGL, PHPStan, Rector, unit tests, functional tests, coverage |
| **PR Quality Gates** | `.github/workflows/pr-quality.yml` | PR to `TYPO3_12` | Auto-approve for solo maintainer |
| **Publish to TER** | `.github/workflows/publish-to-ter.yml` | `release: published` (+ `workflow_dispatch`) | Uploads extension to TER via Tailor (`ter:publish`) |
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
- [ ] GitHub **Release** published for **existing** tag `vX.Y.Z` with the "Latest" badge correctly set (use `--latest=false` / uncheck "Set as the latest release" on maintenance branches like `TYPO3_12` to preserve `main`'s "Latest" badge)
- [ ] Release notes include bug reporters and contributors
- [ ] Packagist shows new version
- [ ] TER shows new version
