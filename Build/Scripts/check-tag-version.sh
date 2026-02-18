#!/usr/bin/env bash
# Validates that ext_emconf.php version matches any semver tag pointing at HEAD.
# Used as a CaptainHook pre-push hook to prevent pushing mismatched versions.
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
    echo "ERROR: Could not extract version from ext_emconf.php"
    exit 1
fi

# Check if ext_emconf.php version matches any of the tags at HEAD
if ! echo "${TAGS}" | grep -qFx "${EMCONF_VERSION}"; then
    echo "ERROR: ext_emconf.php version (${EMCONF_VERSION}) does not match any semver tag at HEAD."
    echo "Tags found at HEAD:"
    echo "${TAGS}"
    echo "Update ext_emconf.php version to match the tag and amend your commit before pushing."
    exit 1
fi

echo "Version check passed: ext_emconf.php (${EMCONF_VERSION}) matches tag(s)"
