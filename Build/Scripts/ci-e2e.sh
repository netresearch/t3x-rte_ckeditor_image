#!/usr/bin/env bash
#
# CI wrapper for e2e.sh -s e2e.
#
# The reusable workflow netresearch/typo3-ci-workflows/.github/workflows/e2e.yml
# (with `setup-script: Build/Scripts/ci-e2e.sh`) invokes this script with no
# arguments and exposes matrix env vars: E2E_TYPO3_VERSION (e.g. "^14.3") and
# E2E_VARIANT (e.g. "core-only"). This wrapper translates those into the
# CLI flags that e2e.sh expects, so e2e.sh stays a CLI tool and
# doesn't need to know about the workflow's env-var contract.
#
# Local invocation should call e2e.sh directly with -t/-X/-s/-p flags.
# (The PHP suites live in runTests.sh, which is the shared runner stub.)

set -euo pipefail

# Map E2E_TYPO3_VERSION constraint to the major number e2e.sh expects via
# `-t`. We only support v13 LTS and v14 LTS in CI; reject anything else loudly
# rather than silently falling back to a default.
case "${E2E_TYPO3_VERSION:-}" in
    "^13"*|"13"*) TYPO3_MAJOR=13 ;;
    "^14"*|"14"*) TYPO3_MAJOR=14 ;;
    "")
        echo "::error::ci-e2e.sh: E2E_TYPO3_VERSION env var is not set." >&2
        echo "  This script is a CI wrapper. Run e2e.sh directly for local E2E." >&2
        exit 1
        ;;
    *)
        echo "::error::ci-e2e.sh: Unsupported E2E_TYPO3_VERSION: ${E2E_TYPO3_VERSION}" >&2
        echo "  Expected ^13.x or ^14.x. Update the CI matrix or this wrapper." >&2
        exit 1
        ;;
esac

VARIANT="${E2E_VARIANT:-fsc}"

# PHP 8.5 is the canonical E2E runtime per Tests/AGENTS.md ("E2E runs on
# PHP 8.5"). Pin it here so matrix entries don't accidentally resolve to a
# different version when scaling out.
PHP_VERSION=8.5

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "ci-e2e.sh: TYPO3 ${E2E_TYPO3_VERSION} (major ${TYPO3_MAJOR}), PHP ${PHP_VERSION}, variant=${VARIANT}"
# runTests.sh, not e2e.sh: the environment is built by the shared runner's
# e2e-provision.sh from the hooks in Build/Scripts/runTests.conf.
#
# E2E_TYPO3_VERSION is exported rather than passed as -t. The runner's -t is not
# suite-gated — it also rewrites this extension's composer.json and runs a full
# `composer require typo3/cms-core:<constraint>` before the suite, which does
# nothing for an instance installed separately from scratch and kills the job if
# that resolve fails. The provisioner reads E2E_TYPO3_VERSION directly.
export E2E_TYPO3_VERSION E2E_VARIANT="${VARIANT}"
exec "${SCRIPT_DIR}/runTests.sh" \
    -s e2e \
    -p "${PHP_VERSION}"
