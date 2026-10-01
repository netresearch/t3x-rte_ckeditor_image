<!--
Copyright (c) 2025-2026 Netresearch DTT GmbH
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Welcome to our contributing guide

Thank you for investing your time in contributing to our project! It is much appreciated.

## Asking questions & report problems

If you'd like to ask a question or report a problem, please follow these steps:

1. Look through the [existing issues](https://github.com/netresearch/t3x-rte_ckeditor_image/issues?q=is%3Aissue). Maybe we are already working on it.
2. Have a look at the [README](README.md). It might answer your question.
3. Couldn't find anything? Then feel free to create an issue. If possible, use the issue templates so we get all the necessary information.

## How to contribute code

If you want to contribute code, please follow these steps:

1. Clone the repository and checkout a local working branch.
2. Make your changes and commit them with DCO sign-off:
   ```bash
   git commit -s -m "feat: your change description"
   ```
   The `-s` flag adds a `Signed-off-by` line certifying you have the right to submit the code under the project's license ([Developer Certificate of Origin](https://developercertificate.org/)).
3. Push your working branch to the GitHub repository.
4. Create a pull request (PR) for your branch on GitHub.
5. Create an issue and [link it to your pull request](https://docs.github.com/en/issues/tracking-your-work-with-issues/linking-a-pull-request-to-an-issue).

We look through the issues and pull requests regularly.

## Project Access & Roles

The following teams have access to sensitive project resources:

| Team | Access Level | Scope |
|------|-------------|-------|
| [@netresearch/typo3](https://github.com/orgs/netresearch/teams/typo3) | Write | Source code, CI/CD workflows, issue management |
| [@netresearch/sec](https://github.com/orgs/netresearch/teams/sec) | Security | Security advisories, vulnerability reports, SECURITY.md |
| Repository Admins | Admin | Branch protection, secrets, team membership, releases |

**Secrets** (managed via GitHub Encrypted Secrets, admin-only):
- `CODECOV_TOKEN` — Code coverage reporting
- `TYPO3_TER_ACCESS_TOKEN` — TYPO3 Extension Repository publishing

### Permission escalation

Before granting elevated permissions to a contributor:

1. The contributor must have a history of quality contributions (reviewed PRs, issue reports)
2. An existing team member must sponsor the request
3. At least one repository admin must approve the access change
4. The change is logged in GitHub's organization audit log

## Governance and policies

This extension follows the organisation-wide Netresearch policies:

- [Governance](https://github.com/netresearch/.github/blob/main/GOVERNANCE.md): ownership, roles, how decisions are made and how conflicts are resolved.
- [Roadmap](https://github.com/netresearch/.github/blob/main/ROADMAP.md): planned and excluded work for the next twelve months.
- [Handling of dependency and code analysis findings](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings): which vulnerability, licence and static-analysis findings must be fixed, by when, and how exceptions are recorded.
- [Secret management](https://github.com/netresearch/.github/blob/main/SECURITY.md#secret-management): where CI and release credentials are stored, who may use them, how committed secrets are detected, and when they are rotated.
- [Access roster](https://github.com/netresearch/.github/blob/main/docs/access-roster.md): the accounts with admin, maintain or write access to this repository.

Checks that run on pull requests in this repository:

- `.github/workflows/checks.yml`: Composer Audit (fails on any advisory for an installed package unless `config.audit.ignore` in `composer.json` lists it with a reason) and Opengrep SAST (which findings block is set organisation-wide, see [Static analysis (SAST)](https://github.com/netresearch/.github/blob/main/SECURITY.md#static-analysis-sast)), both through `typo3-ci-workflows`' `security.yml`; Dependency Review (fails on newly added dependencies with a known vulnerability of severity high or higher); License Check (fails on an SSPL or BSL licensed Composer dependency); CodeQL with language auto-detection (CodeQL has no PHP analysis; PHPStan and Opengrep cover the PHP code); Betterleaks secret scanning; zizmor for the workflow files (its findings go to code scanning and do not fail the check); the pull request quality check (`pr-quality`, on non-draft pull requests only: its Quality Gate job reports the size of the change and warns on large pull requests, its Auto-Approve job approves pull requests that maintainers open from this repository); and the aggregate gate `All security checks`, which fails when one of these jobs fails or is cancelled. The `fuzz` job finds no `Build/phpunit.xml` and is skipped; the php-fuzzer targets in `Tests/Fuzz` run locally with `composer ci:fuzz`. The OpenSSF Scorecard job runs only on pushes to `main` and on the weekly schedule.
- `.github/workflows/ci.yml`: PHP lint, code style (PHP-CS-Fixer dry run), PHPStan (level 10, `Build/phpstan.neon`) plus an advisory PHPStan pass against the PHPUnit the matrix resolves without the version cap (`PHPStan (unpinned PHPUnit)`), Rector dry run, unit tests and functional tests (SQLite) on PHP 8.2 to 8.5 with TYPO3 ^13.4.21 and ^14.3, the rendering of `Documentation/`, and the Playwright suite in `Tests/E2E` against TYPO3 ^13.4.21 and ^14.3 in the setup variants `fsc`, `core-only` and `bootstrap`, and the aggregate gate `All CI checks`. The Vitest suite in `Tests/JavaScript` is not part of CI; run it with `composer ci:test:js:unit`.
- `.github/workflows/codeql.yml`: CodeQL for the workflow files.
- `.github/workflows/harness-verify.yml`: `Build/Scripts/verify-harness.sh`.
- `.github/workflows/check-template-drift.yml`: drift of the managed files from the `typo3-extension` template in netresearch/.github (`Template drift`).
- `.github/workflows/labeler.yml`: labels the pull request by the paths it changes.
- `.github/workflows/community.yml`: greets a contributor on their first pull request; `.github/workflows/auto-merge-deps.yml`: approves and enables auto-merge for Dependabot and Renovate pull requests that carry neither the `deps-no-automerge` nor the `deps-major` label, and is skipped for all others.

## Help translate this extension

You can help translate this extension into your language through TYPO3's Crowdin platform:

**Translation Platform**: https://crowdin.com/project/typo3-extension-rte_ckeditor_image

**How to contribute translations**:

1. **Create a Crowdin account** (free for open source contributors)
2. **Join the TYPO3 translation team** for your language
3. **Translate strings** directly in the Crowdin web interface
4. **Review translations** from other contributors
5. **Suggest improvements** to existing translations

**Why translate?**

- Make TYPO3 more accessible to speakers of your language
- Help the global TYPO3 community
- No programming knowledge required
- Translations are automatically integrated via pull requests

**Translation notes**:

- Some terms like "Retina", "Ultra", "Standard" are multilingual - keep as-is or transliterate if more natural in your language
- Context notes are provided for technical terms to help with accurate translation
- Your contributions are reviewed by language coordinators before integration

**Need help?**

- Contact the TYPO3 localization team on [Slack](https://typo3.slack.com/) in `#typo3-localization-team`
- Check the [TYPO3 translation documentation](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Localization/Index.html)

Again, thank you very much for taking the time to help!
