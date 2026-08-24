#!/usr/bin/env bash

#
# Bootstrap for the shared TYPO3 extension test runner.
#
# Copy this file to Build/Scripts/runTests.sh in an extension. It is NOT the
# runner: the runner is versioned once in netresearch/typo3-ci-workflows and
# composer links it to .Build/bin/runTests.sh. This stub exists only because
# the runner provisions the very environment it lives in, so it cannot be
# behind a completed `composer install` — the first run on a fresh clone has
# to create it.
#
# Per-extension settings belong in Build/Scripts/runTests.conf, never here.
# Anything this stub grows beyond handing over is drift; fix the shared runner
# instead.
#

set -euo pipefail

cd "$(dirname "$0")/../.."

# composer.json says where composer puts binaries. Hardcoding .Build/bin was
# wrong for nine extensions in this fleet: seven use lowercase .build/bin and
# two use vendor/bin, and there this stub would not have found the runner at
# all. The runner detects the same thing for itself; the stub has to do it
# before the runner exists.
bin_dir() {
    local dir=""
    if type jq >/dev/null 2>&1; then
        dir="$(jq -r '.config["bin-dir"] // ((.config["vendor-dir"] // "vendor") + "/bin") // empty' composer.json 2>/dev/null)"
    else
        dir="$(sed -n 's/.*"bin-dir"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' composer.json | head -n1)"
        [[ -n "${dir}" ]] || dir="$(sed -n 's/.*"vendor-dir"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1\/bin/p' composer.json | head -n1)"
    fi
    printf '%s' "${dir:-vendor/bin}"
}

RUNNER="$(bin_dir)/runTests.sh"

if [[ ! -x "${RUNNER}" ]]; then
    echo "runTests.sh: ${RUNNER} not found — installing dependencies first." >&2
    if ! type composer >/dev/null 2>&1; then
        echo "runTests.sh: composer is not on PATH, cannot bootstrap ${RUNNER}." >&2
        exit 1
    fi
    composer install --no-interaction --no-progress
fi

if [[ ! -x "${RUNNER}" ]]; then
    echo "runTests.sh: ${RUNNER} is still missing after composer install." >&2
    echo "             Is netresearch/typo3-ci-workflows in require-dev?" >&2
    exit 1
fi

exec "${RUNNER}" "$@"
