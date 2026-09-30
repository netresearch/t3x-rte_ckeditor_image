#!/usr/bin/env bash

# Copyright (c) 2025-2026 Netresearch DTT GmbH
# SPDX-License-Identifier: AGPL-3.0-or-later

# Validates that ext_emconf.php version matches any semver tag pointing at HEAD.
# Used as a pre-push hook to prevent pushing mismatched versions.
set -euo pipefail

# Find semver tags (with or without v prefix) pointing at HEAD, normalize to bare version
TAGS=$(git tag --points-at HEAD | sed -nE 's/^v?([0-9]+\.[0-9]+\.[0-9]+)$/\1/p' || true)

if [[ -z "${TAGS}" ]]; then
    # No semver tag at HEAD — nothing to validate
    exit 0
fi

# Extract version from ext_emconf.php (portable sed instead of grep -P)
EMCONF_VERSION=$(sed -nE "s/.*'version'[[:space:]]*=>[[:space:]]*'([^']+)'.*/\1/p" ext_emconf.php)

if [[ -z "${EMCONF_VERSION}" ]]; then
    echo "ERROR: Could not extract version from ext_emconf.php" >&2
    exit 1
fi

# Check if ext_emconf.php version matches any of the tags at HEAD
if ! echo "${TAGS}" | grep -qFx -e "${EMCONF_VERSION}"; then
    echo "ERROR: ext_emconf.php version (${EMCONF_VERSION}) does not match any semver tag at HEAD." >&2
    echo "Tags found at HEAD:" >&2
    echo "${TAGS}" >&2
    echo "Update ext_emconf.php version to match the tag and amend your commit before pushing." >&2
    exit 1
fi

# package.json is private and not published, but it declares the same
# version; keep it in step with ext_emconf.php.
if [[ -f package.json ]]; then
    PACKAGE_VERSION=$(sed -nE 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"([^"]+)".*/\1/p' package.json | head -n1)
    if [[ -n "${PACKAGE_VERSION}" && "${PACKAGE_VERSION}" != "${EMCONF_VERSION}" ]]; then
        echo "ERROR: package.json version (${PACKAGE_VERSION}) does not match ext_emconf.php (${EMCONF_VERSION})." >&2
        echo "Update the version in package.json and amend your commit before pushing." >&2
        exit 1
    fi
fi

echo "Version check passed: ext_emconf.php (${EMCONF_VERSION}) matches tag(s)"
