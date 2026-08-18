#!/usr/bin/env bash
# verify-harness.sh — Portable harness consistency checker
# Checks AGENTS.md and related files for agent harness maturity.
# Dependencies: coreutils + git (jq optional, graceful fallback)
set -euo pipefail

# ---------------------------------------------------------------------------
# Globals
# ---------------------------------------------------------------------------
ERRORS=0
WARNINGS=0
FORMAT=""
MAX_LEVEL=3
SINGLE_CHECK=""
STATUS_ONLY=false
PLATFORM="${PLATFORM:-}"

# Collected output lines (for final rendering)
declare -a OUTPUT_LINES=()
declare -a GITHUB_LINES=()
declare -a GITLAB_LINES=()

# Per-level pass/total counters
declare -A LEVEL_PASS=( [1]=0 [2]=0 [3]=0 )
declare -A LEVEL_TOTAL=( [1]=0 [2]=0 [3]=0 )

# Track the first failing level-1 suggestion for --status
NEXT_STEP=""

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
usage() {
    cat <<'USAGE'
Usage: verify-harness.sh [OPTIONS]

Verify agent harness consistency in the current repository.
Must be run from the repo root.

Options:
  --format=text     Plain text output (default for terminals)
  --format=github   GitHub Actions annotations (auto-detected in CI)
  --format=gitlab   GitLab CI section annotations (auto-detected in CI)
  --platform=P      Target platform: github, gitlab or forgejo (auto-detected
                    from CI environment or git remote URL if not specified)
  --level=N         Only check up to level N (1, 2, or 3; default: all)
  --check=NAME      Run single check category: refs, commands, drift, structure
  --status          Show current maturity level summary only
  --help            Show this help message

Exit codes:
  0  All checks pass
  1  Errors found (Level 1/2 failures)
  2  Only warnings (Level 3 suggestions)
USAGE
    exit 0
}

# Detect output format: github if running in CI, otherwise text
detect_format() {
    if [[ -n "$FORMAT" ]]; then
        return
    fi
    if [[ "${GITHUB_ACTIONS:-}" == "true" ]]; then
        FORMAT="github"
    elif [[ "${GITLAB_CI:-}" == "true" ]]; then
        FORMAT="gitlab"
    else
        FORMAT="text"
    fi
}

# Detect hosting platform from CI env, git remote, or flag
detect_platform() {
    if [[ -n "$PLATFORM" ]]; then
        if [[ "$PLATFORM" != "github" && "$PLATFORM" != "gitlab" && "$PLATFORM" != "forgejo" ]]; then
            echo "Error: Unsupported PLATFORM '${PLATFORM}'. Expected 'github', 'gitlab' or 'forgejo'." >&2
            exit 1
        fi
        return
    fi
    if [[ "${GITHUB_ACTIONS:-}" == "true" ]]; then
        # Forgejo/Gitea Actions also export GITHUB_ACTIONS=true; tell them apart
        # by the server URL — github.com (or a *.github.* Enterprise host) is real
        # GitHub, anything else is a self-hosted Forgejo/Gitea instance.
        if [[ -n "${GITHUB_SERVER_URL:-}" && "${GITHUB_SERVER_URL}" != *"github."* ]]; then
            PLATFORM="forgejo"
        else
            PLATFORM="github"
        fi
        return
    fi
    if [[ "${GITLAB_CI:-}" == "true" ]]; then
        PLATFORM="gitlab"
        return
    fi
    local remote_url=""
    remote_url=$(git remote get-url origin 2>/dev/null || true)
    if [[ "$remote_url" == *"forgejo"* || "$remote_url" == *"gitea"* ]]; then
        PLATFORM="forgejo"
    elif [[ "$remote_url" == *"github"* ]]; then
        PLATFORM="github"
    elif [[ "$remote_url" == *"gitlab"* ]]; then
        PLATFORM="gitlab"
    elif [[ -f ".gitlab-ci.yml" ]]; then
        # Infer GitLab from CI config presence (e.g. self-hosted GitLab)
        PLATFORM="gitlab"
    elif [[ -d ".forgejo" || -d ".gitea" ]]; then
        # Infer Forgejo/Gitea from config presence (self-hosted)
        PLATFORM="forgejo"
    elif [[ -d ".github" ]]; then
        PLATFORM="github"
    else
        PLATFORM="github"
    fi
}

# Record a passing check
pass() {
    local level="$1"
    local msg="$2"
    (( LEVEL_PASS[$level]++ )) || true
    (( LEVEL_TOTAL[$level]++ )) || true
    OUTPUT_LINES+=("  PASS|${level}|${msg}")
}

# Record a failing check (error)
fail() {
    local level="$1"
    local msg="$2"
    local file="${3-AGENTS.md}"
    (( ERRORS++ )) || true
    (( LEVEL_TOTAL[$level]++ )) || true
    OUTPUT_LINES+=("  FAIL|${level}|${msg}")
    GITHUB_LINES+=("::error file=${file}::${msg} -- required for Level ${level} harness maturity")
    GITLAB_LINES+=("ERROR: [Level ${level}] ${msg}${file:+ (${file})}")
    # Capture first actionable suggestion for --status
    if [[ -z "$NEXT_STEP" ]]; then
        NEXT_STEP="$msg"
    fi
}

# Record a warning
warn() {
    local level="$1"
    local msg="$2"
    local file="${3-AGENTS.md}"
    (( WARNINGS++ )) || true
    (( LEVEL_TOTAL[$level]++ )) || true
    OUTPUT_LINES+=("  WARN|${level}|${msg}")
    GITHUB_LINES+=("::warning file=${file}::${msg}")
    GITLAB_LINES+=("WARNING: [Level ${level}] ${msg}${file:+ (${file})}")
    if [[ -z "$NEXT_STEP" ]]; then
        NEXT_STEP="$msg"
    fi
}

# ---------------------------------------------------------------------------
# Level 1 checks — Basic
# ---------------------------------------------------------------------------
check_agents_md_exists() {
    if [[ -f "AGENTS.md" ]]; then
        pass 1 "AGENTS.md exists"
    else
        fail 1 "AGENTS.md missing at repo root"
    fi
}

check_agents_md_length() {
    if [[ ! -f "AGENTS.md" ]]; then
        fail 1 "AGENTS.md length check skipped (file missing)"
        return
    fi
    local lines
    lines=$(wc -l < "AGENTS.md")
    if (( lines < 150 )); then
        pass 1 "AGENTS.md is index-format (${lines} lines)"
    else
        fail 1 "AGENTS.md is ${lines} lines (should be under 150)"
    fi
}

check_agents_md_commands() {
    if [[ ! -f "AGENTS.md" ]]; then
        fail 1 "Commands section check skipped (AGENTS.md missing)"
        return
    fi
    if grep -qi '^## *\(available \)\?commands' "AGENTS.md"; then
        pass 1 "Commands section found"
    else
        fail 1 "AGENTS.md missing ## Commands section"
    fi
}

check_docs_exists() {
    if [[ -d "docs" ]]; then
        pass 1 "docs/ directory exists"
    else
        fail 1 "docs/ directory missing" ""
    fi
}

run_level1() {
    check_agents_md_exists
    check_agents_md_length
    check_agents_md_commands
    check_docs_exists
}

# ---------------------------------------------------------------------------
# Level 2 checks — Verified
# ---------------------------------------------------------------------------

# Check that all local file references in AGENTS.md resolve
check_refs() {
    if [[ ! -f "AGENTS.md" ]]; then
        fail 2 "Reference check skipped (AGENTS.md missing)"
        return
    fi
    local has_broken=false
    # Extract markdown links: [text](path) — skip http(s):// and #anchors
    while IFS= read -r ref; do
        # Strip anchor (#...) and query string (?...)
        local clean
        clean="${ref%%#*}"
        clean="${clean%%\?*}"
        # Skip empty after stripping
        [[ -z "$clean" ]] && continue
        # Skip URLs
        [[ "$clean" =~ ^https?:// ]] && continue
        # Check if file/dir exists
        if [[ ! -e "$clean" ]]; then
            warn 2 "Broken reference in AGENTS.md: ${ref} -> ${clean} not found"
            has_broken=true
        fi
    done < <(grep -oP '\]\(\K[^)]+' "AGENTS.md" 2>/dev/null || true)

    if [[ "$has_broken" == false ]]; then
        pass 2 "All references resolve"
    fi
}

# Check that documented commands have matching targets/scripts
check_commands() {
    if [[ ! -f "AGENTS.md" ]]; then
        fail 2 "Command check skipped (AGENTS.md missing)"
        return
    fi

    local found_any=false

    # -- Makefile targets --
    if [[ -f "Makefile" ]]; then
        found_any=true
        local has_make_issue=false
        while IFS= read -r target; do
            # Check if Makefile defines this target (pattern: "target:" at start of line)
            if ! grep -qE "^${target}[[:space:]]*:" "Makefile"; then
                warn 2 "make ${target}: no matching Makefile target (warning)"
                has_make_issue=true
            fi
        done < <(grep -oP '`make\s+\K[a-zA-Z0-9_-]+' "AGENTS.md" 2>/dev/null || true)
        if [[ "$has_make_issue" == false ]]; then
            local make_count
            make_count=$(grep -oP '`make\s+\K[a-zA-Z0-9_-]+' "AGENTS.md" 2>/dev/null | wc -l || true)
            if [[ "$make_count" -gt 0 ]]; then
                pass 2 "All make targets verified (${make_count} targets)"
            fi
        fi
    fi

    # -- composer.json scripts --
    if [[ -f "composer.json" ]]; then
        found_any=true
        local has_composer_issue=false
        # Built-in composer commands that are NOT user-defined scripts
        local composer_builtins="install|update|require|remove|dump-autoload|dumpautoload|clear-cache|clearcache|config|create-project|exec|global|init|outdated|prohibits|why|why-not|search|self-update|selfupdate|show|status|validate|archive|browse|check-platform-reqs|diagnose|fund|licenses|run-script|suggests|upgrade"
        while IFS= read -r script; do
            # Skip built-in composer commands
            if echo "$script" | grep -qE "^(${composer_builtins})$"; then
                continue
            fi
            # Look for the script name in composer.json's scripts section
            # Using grep since jq is optional
            if ! grep -qE "\"${script}\"" "composer.json"; then
                warn 2 "composer ${script}: no matching composer.json script (warning)"
                has_composer_issue=true
            fi
        done < <(grep -oP '`composer\s+\K[a-zA-Z0-9:_-]+' "AGENTS.md" 2>/dev/null || true)
        if [[ "$has_composer_issue" == false ]]; then
            local composer_count
            composer_count=$(grep -oP '`composer\s+\K[a-zA-Z0-9:_-]+' "AGENTS.md" 2>/dev/null | wc -l || true)
            if [[ "$composer_count" -gt 0 ]]; then
                pass 2 "All composer scripts verified (${composer_count} scripts)"
            fi
        fi
    fi

    # -- package.json scripts --
    if [[ -f "package.json" ]]; then
        found_any=true
        local has_npm_issue=false
        while IFS= read -r script; do
            if ! grep -qE "\"${script}\"" "package.json"; then
                warn 2 "npm run ${script}: no matching package.json script (warning)"
                has_npm_issue=true
            fi
        done < <(grep -oP '`npm run\s+\K[a-zA-Z0-9:_-]+' "AGENTS.md" 2>/dev/null || true)
        if [[ "$has_npm_issue" == false ]]; then
            local npm_count
            npm_count=$(grep -oP '`npm run\s+\K[a-zA-Z0-9:_-]+' "AGENTS.md" 2>/dev/null | wc -l || true)
            if [[ "$npm_count" -gt 0 ]]; then
                pass 2 "All npm scripts verified (${npm_count} scripts)"
            fi
        fi
    fi

    if [[ "$found_any" == false ]]; then
        pass 2 "No build system files found to check commands against"
    fi
}

check_architecture_doc() {
    if [[ -f "docs/ARCHITECTURE.md" ]]; then
        pass 2 "docs/ARCHITECTURE.md exists"
    else
        fail 2 "docs/ARCHITECTURE.md missing" ""
    fi
}

check_ci_workflow() {
    if [[ "$PLATFORM" == "gitlab" ]]; then
        if [[ -f ".gitlab-ci.yml" ]]; then
            # Search root file and all include:local files for harness job
            local found_harness=false
            if grep -qE "harness-verify|verify-harness" ".gitlab-ci.yml"; then
                found_harness=true
            else
                # Check files referenced via include:local (supports globs).
                # Portable parser using sed/awk — handles common GitLab CI forms:
                #   include: { local: 'path' }
                #   include:
                #     - local: 'path'
                #   include:
                #     - local:
                #         - path1
                #         - path2
                # Skips PCRE (-P) and lookaround for BSD/macOS grep portability.
                while IFS= read -r inc_pattern; do
                    [[ -z "$inc_pattern" ]] && continue
                    # shellcheck disable=SC2086
                    for inc_file in $inc_pattern; do
                        if [[ -f "$inc_file" ]] && grep -qE "harness-verify|verify-harness" "$inc_file"; then
                            found_harness=true
                            break 2
                        fi
                    done
                done < <(awk '
                    /^[[:space:]]*-?[[:space:]]*local:[[:space:]]*\[/ {
                        # inline list form: local: [a.yml, b.yml]
                        s=$0; sub(/.*local:[[:space:]]*\[/,"",s); sub(/\].*/,"",s);
                        n=split(s, a, /[[:space:]]*,[[:space:]]*/);
                        for (i=1;i<=n;i++) print a[i]; next
                    }
                    /^[[:space:]]*-?[[:space:]]*local:[[:space:]]*[^[:space:]\[]/ {
                        # scalar form: local: path or - local: path
                        s=$0; sub(/.*local:[[:space:]]*/,"",s); print s; next
                    }
                ' ".gitlab-ci.yml" 2>/dev/null | tr -d "'\"" || true)
            fi
            if [[ "$found_harness" == true ]]; then
                pass 2 "CI harness job found in GitLab CI config"
            else
                warn 2 "CI config exists (.gitlab-ci.yml) but no harness-verify job found"
            fi
        else
            fail 2 "CI harness workflow missing -- create .gitlab-ci.yml with a harness-verify job" ""
        fi
    elif [[ "$PLATFORM" == "forgejo" ]]; then
        if [[ -f ".forgejo/workflows/harness-verify.yml" || -f ".gitea/workflows/harness-verify.yml" ]]; then
            pass 2 "CI harness workflow exists"
        else
            fail 2 "CI harness workflow missing -- create .forgejo/workflows/harness-verify.yml" ""
        fi
    else
        if [[ -f ".github/workflows/harness-verify.yml" ]]; then
            pass 2 "CI harness workflow exists"
        else
            fail 2 "CI harness workflow missing -- create .github/workflows/harness-verify.yml" ""
        fi
    fi
}

run_level2() {
    check_refs
    check_commands
    check_architecture_doc
    check_ci_workflow
}

# ---------------------------------------------------------------------------
# Level 3 checks — Enforced
# ---------------------------------------------------------------------------

check_hooks_autosetup() {
    local found=false
    local via=""

    # Check .envrc for hooksPath
    if [[ -f ".envrc" ]] && grep -q "hooksPath" ".envrc"; then
        found=true
        via=".envrc"
    fi

    # Check for Husky
    if [[ -d ".husky" ]]; then
        found=true
        via=".husky"
    fi

    # Check composer.json for post-install-cmd with hooks
    if [[ -f "composer.json" ]] && grep -q "post-install-cmd" "composer.json"; then
        if grep -q "hook" "composer.json"; then
            found=true
            via="composer.json post-install-cmd"
        fi
    fi

    if [[ "$found" == true ]]; then
        pass 3 "Git hooks auto-setup via ${via}"
    else
        warn 3 "No git hooks auto-setup detected (.envrc hooksPath, .husky/, or composer.json post-install-cmd)"
    fi
}

check_pr_template() {
    if [[ "$PLATFORM" == "forgejo" ]]; then
        # Forgejo/Gitea honour PR templates under .forgejo/, .gitea/ or .github/
        local f
        for f in .forgejo/pull_request_template.md .gitea/pull_request_template.md \
                 .forgejo/PULL_REQUEST_TEMPLATE.md .gitea/PULL_REQUEST_TEMPLATE.md \
                 .github/pull_request_template.md; do
            if [[ -f "$f" ]]; then
                pass 3 "PR template exists ($f)"
                return
            fi
        done
        warn 3 "PR template missing (create .forgejo/pull_request_template.md)"
        return
    fi
    if [[ "$PLATFORM" == "gitlab" ]]; then
        if [[ -d ".gitlab/merge_request_templates" ]]; then
            local tmpl_count
            tmpl_count=$(find .gitlab/merge_request_templates -maxdepth 1 -type f -name '*.md' 2>/dev/null | wc -l)
            if (( tmpl_count > 0 )); then
                pass 3 "MR template exists (.gitlab/merge_request_templates/, ${tmpl_count} template(s))"
                return
            fi
        fi
        warn 3 "MR template missing (create .gitlab/merge_request_templates/Default.md)"
    else
        if [[ -f ".github/pull_request_template.md" ]]; then
            pass 3 "PR template exists (repo-level)"
            return
        fi
        if [[ -d ".github/PULL_REQUEST_TEMPLATE" ]]; then
            pass 3 "PR template exists (directory form)"
            return
        fi
        local org=""
        org=$(git remote get-url origin 2>/dev/null | sed -n 's|.*github\.com[:/]\([^/]*\)/.*|\1|p')
        if [[ -n "$org" ]]; then
            local api_result=""
            api_result=$(gh api "repos/${org}/.github/contents/pull_request_template.md" --jq '.name' 2>/dev/null || true)
            if [[ "$api_result" == "pull_request_template.md" ]]; then
                pass 3 "PR template exists (org-level via ${org}/.github)"
                return
            fi
        fi
        warn 3 "PR template missing (.github/pull_request_template.md or org-level)"
    fi
}

check_drift() {
    # Skip if git is not available
    if ! command -v git &>/dev/null; then
        pass 3 "Drift check skipped (git not available)"
        return
    fi

    # Skip if not in a git repo
    if ! git rev-parse --git-dir &>/dev/null 2>&1; then
        pass 3 "Drift check skipped (not a git repository)"
        return
    fi

    # Skip if no parent commit (initial commit)
    if ! git rev-parse HEAD~1 &>/dev/null 2>&1; then
        pass 3 "Drift check skipped (no parent commit)"
        return
    fi

    # Check if build/CI files changed in last commit
    local build_files_changed=false
    local agents_changed=false

    while IFS= read -r changed_file; do
        case "$changed_file" in
            Makefile|composer.json|package.json|.github/workflows/*|.gitlab-ci.yml|.forgejo/workflows/*|.gitea/workflows/*)
                build_files_changed=true
                ;;
            AGENTS.md)
                agents_changed=true
                ;;
        esac
    done < <(git diff --name-only HEAD~1 HEAD 2>/dev/null || true)

    if [[ "$build_files_changed" == true && "$agents_changed" == false ]]; then
        warn 3 "Potential drift: build/CI files changed in last commit but AGENTS.md was not updated"
    else
        pass 3 "No drift detected"
    fi
}

run_level3() {
    check_hooks_autosetup
    check_pr_template
    check_drift
}

# ---------------------------------------------------------------------------
# Output rendering
# ---------------------------------------------------------------------------

render_text() {
    echo "Agent Harness Verification"
    echo "=========================="
    echo ""

    local current_level=0
    local level_names=( [1]="Basic" [2]="Verified" [3]="Enforced" )

    for line in "${OUTPUT_LINES[@]}"; do
        local kind level msg
        kind="${line%%|*}"
        local rest="${line#*|}"
        level="${rest%%|*}"
        msg="${rest#*|}"
        kind="${kind#"${kind%%[![:space:]]*}"}" # trim leading whitespace

        # Print level header when level changes
        if (( level != current_level )); then
            if (( current_level != 0 )); then
                echo ""
            fi
            echo "Level ${level} -- ${level_names[$level]}"
            current_level=$level
        fi

        case "$kind" in
            PASS) echo "  ✓ ${msg}" ;;
            FAIL) echo "  ✗ ${msg}" ;;
            WARN) echo "  ! ${msg}" ;;
        esac
    done

    echo ""

    # Summary line
    local maturity_level=0
    for lvl in 1 2 3; do
        if (( ${LEVEL_TOTAL[$lvl]} > 0 && ${LEVEL_PASS[$lvl]} == ${LEVEL_TOTAL[$lvl]} )); then
            maturity_level=$lvl
        else
            break
        fi
    done

    local status="COMPLETE"
    if (( maturity_level == 0 )); then
        if (( LEVEL_TOTAL[1] > 0 && LEVEL_PASS[1] > 0 )); then
            status="PARTIAL"
        else
            status="NONE"
        fi
        maturity_level=1
    elif (( maturity_level < 3 )); then
        # Check if next level is partially done
        local next_lvl=$(( maturity_level + 1 ))
        if (( ${LEVEL_TOTAL[$next_lvl]} > 0 && ${LEVEL_PASS[$next_lvl]} < ${LEVEL_TOTAL[$next_lvl]} )); then
            status="PARTIAL"
        fi
    fi

    echo "Summary: Level ${maturity_level} ${status} | ${ERRORS} error(s), ${WARNINGS} warning(s)"
}

render_github() {
    if (( ${#GITHUB_LINES[@]} > 0 )); then
        for line in "${GITHUB_LINES[@]}"; do
            echo "$line"
        done
    fi
}

render_gitlab() {
    local ts
    ts=$(date +%s)
    echo -e "\e[0Ksection_start:${ts}:harness_verify[collapsed=false]\r\e[0KAgent Harness Verification"
    if (( ${#GITLAB_LINES[@]} > 0 )); then
        for line in "${GITLAB_LINES[@]}"; do
            echo "$line"
        done
    fi
    echo ""
    echo "Summary: ${ERRORS} error(s), ${WARNINGS} warning(s)"
    echo -e "\e[0Ksection_end:${ts}:harness_verify\r\e[0K"
}

render_status() {
    # Determine highest fully-passing level
    local maturity_level=0
    local level_names=( [1]="Basic" [2]="Verified" [3]="Enforced" )

    for lvl in 1 2 3; do
        if (( ${LEVEL_TOTAL[$lvl]} > 0 && ${LEVEL_PASS[$lvl]} == ${LEVEL_TOTAL[$lvl]} )); then
            maturity_level=$lvl
        else
            break
        fi
    done

    local status
    if (( maturity_level == 0 )); then
        if (( LEVEL_TOTAL[1] > 0 && LEVEL_PASS[1] > 0 )); then
            status="PARTIAL"
        else
            status="NONE"
        fi
        # Display as Level 1 when no level is fully complete
        local display_level=1
        echo "Harness Maturity: Level ${display_level} (${level_names[$display_level]}) -- ${status}"
    else
        echo "Harness Maturity: Level ${maturity_level} (${level_names[$maturity_level]}) -- COMPLETE"
    fi

    for lvl in 1 2 3; do
        if (( ${LEVEL_TOTAL[$lvl]} > 0 )); then
            echo "  Level ${lvl}: ${LEVEL_PASS[$lvl]}/${LEVEL_TOTAL[$lvl]} checks pass"
        fi
    done

    if [[ -n "$NEXT_STEP" ]]; then
        echo "Next step: ${NEXT_STEP}"
    fi
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --format=*)
                FORMAT="${1#--format=}"
                ;;
            --platform=*)
                PLATFORM="${1#--platform=}"
                if [[ ! "$PLATFORM" =~ ^(github|gitlab|forgejo)$ ]]; then
                    echo "Error: --platform must be 'github', 'gitlab' or 'forgejo'" >&2
                    exit 1
                fi
                ;;
            --level=*)
                MAX_LEVEL="${1#--level=}"
                if [[ ! "$MAX_LEVEL" =~ ^[123]$ ]]; then
                    echo "Error: --level must be 1, 2, or 3" >&2
                    exit 1
                fi
                ;;
            --check=*)
                SINGLE_CHECK="${1#--check=}"
                ;;
            --status)
                STATUS_ONLY=true
                ;;
            --help|-h)
                usage
                ;;
            *)
                echo "Unknown option: $1" >&2
                echo "Run with --help for usage info" >&2
                exit 1
                ;;
        esac
        shift
    done

    detect_format
    detect_platform

    # Run single check category if requested
    if [[ -n "$SINGLE_CHECK" ]]; then
        case "$SINGLE_CHECK" in
            refs)       check_refs ;;
            commands)   check_commands ;;
            drift)      check_drift ;;
            structure)
                check_agents_md_exists
                check_docs_exists
                check_architecture_doc
                check_ci_workflow
                check_pr_template
                ;;
            *)
                echo "Unknown check: ${SINGLE_CHECK}" >&2
                echo "Valid checks: refs, commands, drift, structure" >&2
                exit 1
                ;;
        esac
    else
        # Run all checks up to MAX_LEVEL
        if (( MAX_LEVEL >= 1 )); then
            run_level1
        fi
        if (( MAX_LEVEL >= 2 )); then
            run_level2
        fi
        if (( MAX_LEVEL >= 3 )); then
            run_level3
        fi
    fi

    # Render output
    if [[ "$STATUS_ONLY" == true ]]; then
        render_status
    elif [[ "$FORMAT" == "github" ]]; then
        render_github
    elif [[ "$FORMAT" == "gitlab" ]]; then
        render_gitlab
    else
        render_text
    fi

    # Exit code
    if (( ERRORS > 0 )); then
        exit 1
    elif (( WARNINGS > 0 )); then
        exit 2
    else
        exit 0
    fi
}

main "$@"
