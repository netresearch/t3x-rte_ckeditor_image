#!/usr/bin/env bash
# =============================================================================
# TYPO3 Documentation Linting Script
# Based on: https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/
# =============================================================================
#
# Checks:
# - Required files (Index.rst, guides.xml, Includes.rst.txt)
# - Line length (warn on >80 chars)
# - Trailing whitespace
# - Tabs (should use 4 spaces)
# - Include directive at top of RST files
# - Alt text on figures
# - American English spelling (common issues)
#
# Usage: ./Build/Scripts/validate-docs.sh [--fix] [--verbose]
#
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DOC_DIR="${PROJECT_ROOT}/Documentation"

# Colors for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
ERRORS=0
WARNINGS=0

# Parse arguments
FIX_MODE=false
VERBOSE=false
for arg in "$@"; do
    case $arg in
        --fix)
            FIX_MODE=true
            ;;
        --verbose|-v)
            VERBOSE=true
            ;;
    esac
done

log_error() {
    echo -e "${RED}ERROR:${NC} $1"
    ((ERRORS++)) || true
}

log_warning() {
    echo -e "${YELLOW}WARNING:${NC} $1"
    ((WARNINGS++)) || true
}

log_info() {
    echo -e "${BLUE}INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}OK:${NC} $1"
}

# =============================================================================
# Check 1: Required files
# =============================================================================
check_required_files() {
    echo ""
    echo "=== Checking required files ==="

    for file in "Index.rst" "guides.xml" "Includes.rst.txt"; do
        if [[ -f "${DOC_DIR}/${file}" ]]; then
            log_success "${file} exists"
        else
            log_error "${file} is missing"
        fi
    done
}

# =============================================================================
# Check 2: Index.rst in each subdirectory
# =============================================================================
check_index_files() {
    echo ""
    echo "=== Checking Index.rst in subdirectories ==="

    for dir in "${DOC_DIR}"/*/; do
        dirname=$(basename "$dir")
        # Skip non-documentation directories
        case "$dirname" in
            Images|Configuration|_*) continue ;;
        esac
        if [[ -f "${dir}Index.rst" ]]; then
            [[ "$VERBOSE" == "true" ]] && log_success "${dirname}/Index.rst exists"
        else
            log_error "${dirname}/ missing Index.rst"
        fi
    done
    log_success "Subdirectory Index.rst check complete"
}

# =============================================================================
# Check 3: Trailing whitespace
# =============================================================================
check_trailing_whitespace() {
    echo ""
    echo "=== Checking trailing whitespace ==="

    local files_with_trailing
    files_with_trailing=$(grep -rlE '[[:space:]]$' "${DOC_DIR}" --include="*.rst" --include="*.rst.txt" 2>/dev/null || true)

    if [[ -n "$files_with_trailing" ]]; then
        while IFS= read -r file; do
            local relative_path="${file#$DOC_DIR/}"
            if [[ "$FIX_MODE" == "true" ]]; then
                sed -i 's/[[:space:]]*$//' "$file"
                log_info "Fixed trailing whitespace in ${relative_path}"
            else
                log_error "${relative_path} has trailing whitespace"
            fi
        done <<< "$files_with_trailing"
    else
        log_success "No trailing whitespace found"
    fi
}

# =============================================================================
# Check 4: Tabs instead of spaces
# =============================================================================
check_tabs() {
    echo ""
    echo "=== Checking for tabs (should use 4 spaces) ==="

    local files_with_tabs
    files_with_tabs=$(grep -rlP '\t' "${DOC_DIR}" --include="*.rst" --include="*.rst.txt" 2>/dev/null || true)

    if [[ -n "$files_with_tabs" ]]; then
        while IFS= read -r file; do
            local relative_path="${file#$DOC_DIR/}"
            if [[ "$FIX_MODE" == "true" ]]; then
                sed -i 's/\t/    /g' "$file"
                log_info "Fixed tabs in ${relative_path}"
            else
                log_error "${relative_path} contains tabs (use 4 spaces)"
            fi
        done <<< "$files_with_tabs"
    else
        log_success "No tabs found"
    fi
}

# =============================================================================
# Check 5: Include directive in RST files
# =============================================================================
check_includes() {
    echo ""
    echo "=== Checking for include directive ==="

    local missing=0
    for file in "${DOC_DIR}"/*.rst "${DOC_DIR}"/**/*.rst; do
        [[ -f "$file" ]] || continue
        [[ "$file" == *"Includes.rst.txt" ]] && continue

        if ! head -5 "$file" | grep -q '^\.\. include:: /Includes.rst.txt'; then
            ((missing++)) || true
            local relative_path="${file#$DOC_DIR/}"
            [[ "$VERBOSE" == "true" ]] && log_warning "${relative_path} missing include directive"
        fi
    done

    if [[ $missing -eq 0 ]]; then
        log_success "All RST files include /Includes.rst.txt"
    else
        log_warning "${missing} files missing include directive"
    fi
}

# =============================================================================
# Check 6: Alt text on figures
# =============================================================================
check_alt_text() {
    echo ""
    echo "=== Checking alt text on figures ==="

    local missing=0
    for file in "${DOC_DIR}"/*.rst "${DOC_DIR}"/**/*.rst; do
        [[ -f "$file" ]] || continue

        # Find figures without :alt: on the next few lines
        local in_figure=false
        local lineno=0
        local figure_line=0

        while IFS= read -r line; do
            ((lineno++)) || true

            if [[ "$line" =~ ^\.\.[[:space:]]+(figure|image):: ]]; then
                if [[ "$in_figure" == "true" ]]; then
                    # Previous figure didn't have alt
                    ((missing++)) || true
                    local relative_path="${file#$DOC_DIR/}"
                    log_error "${relative_path}:${figure_line}: Figure missing :alt: text"
                fi
                in_figure=true
                figure_line=$lineno
            elif [[ "$in_figure" == "true" ]]; then
                if [[ "$line" =~ ^[[:space:]]+:alt: ]]; then
                    in_figure=false
                elif [[ ! "$line" =~ ^[[:space:]] ]] && [[ -n "$line" ]] && [[ ! "$line" =~ ^$ ]]; then
                    # Non-indented line = end of directive without alt
                    ((missing++)) || true
                    local relative_path="${file#$DOC_DIR/}"
                    log_error "${relative_path}:${figure_line}: Figure missing :alt: text"
                    in_figure=false
                fi
            fi
        done < "$file"
    done

    if [[ $missing -eq 0 ]]; then
        log_success "All figures have alt text"
    fi
}

# =============================================================================
# Check 7: Line length (informational only)
# =============================================================================
check_line_length() {
    echo ""
    echo "=== Checking line length (>80 chars) ==="

    local long_lines=0
    for file in "${DOC_DIR}"/*.rst "${DOC_DIR}"/**/*.rst; do
        [[ -f "$file" ]] || continue
        # Count lines over 80 chars (excluding URLs)
        local count
        count=$(grep -vE 'https?://' "$file" | awk 'length > 80' | wc -l || echo "0")
        if [[ $count -gt 0 ]]; then
            ((long_lines+=count)) || true
        fi
    done

    if [[ $long_lines -gt 0 ]]; then
        log_warning "${long_lines} lines exceed 80 characters (recommend wrapping)"
    else
        log_success "All lines within 80 char limit"
    fi
}

# =============================================================================
# Check 8: American English spelling
# =============================================================================
check_spelling() {
    echo ""
    echo "=== Checking spelling (American English) ==="

    # Common British spellings to check
    local british_words="behaviour|colour|favourite|honour|organise|organisation|realise|customise|initialise|optimise|recognise|licence|centre|fibre|grey|analyse|catalogue|dialogue|programme|unauthorised|synchronise"

    local issues=0
    for file in "${DOC_DIR}"/*.rst "${DOC_DIR}"/**/*.rst; do
        [[ -f "$file" ]] || continue

        if grep -qiE "\b(${british_words})\b" "$file" 2>/dev/null; then
            ((issues++)) || true
            local relative_path="${file#$DOC_DIR/}"
            if [[ "$VERBOSE" == "true" ]]; then
                log_warning "${relative_path}: Contains British spelling"
            fi
        fi
    done

    if [[ $issues -gt 0 ]]; then
        log_warning "${issues} files contain British spellings (use American English)"
    else
        log_success "No British spelling issues found"
    fi
}

# =============================================================================
# Check 9: Screenshot guidelines
# =============================================================================
check_images() {
    echo ""
    echo "=== Checking image files ==="

    local img_dir="${DOC_DIR}/Images"

    if [[ ! -d "$img_dir" ]]; then
        log_warning "No Images directory found"
        return
    fi

    # Count image types
    local png_count jpg_count svg_count gif_count
    png_count=$(find "$img_dir" -name "*.png" 2>/dev/null | wc -l)
    jpg_count=$(find "$img_dir" -name "*.jpg" -o -name "*.jpeg" 2>/dev/null | wc -l)
    svg_count=$(find "$img_dir" -name "*.svg" 2>/dev/null | wc -l)
    gif_count=$(find "$img_dir" -name "*.gif" 2>/dev/null | wc -l)

    echo "  PNG: ${png_count}, SVG: ${svg_count}, GIF: ${gif_count}, JPG: ${jpg_count}"

    if [[ $jpg_count -gt 0 ]]; then
        log_warning "Found ${jpg_count} JPG files - consider PNG for screenshots"
    fi

    log_success "Image check complete"
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo "========================================"
    echo "TYPO3 Documentation Linting"
    echo "========================================"
    echo "Documentation: ${DOC_DIR}"
    echo "Fix mode: ${FIX_MODE}"

    # Run all checks
    check_required_files
    check_index_files
    check_trailing_whitespace
    check_tabs
    check_includes
    check_alt_text
    check_line_length
    check_spelling
    check_images

    # Summary
    echo ""
    echo "========================================"
    echo "Summary"
    echo "========================================"
    echo -e "Errors:   ${RED}${ERRORS}${NC}"
    echo -e "Warnings: ${YELLOW}${WARNINGS}${NC}"

    if [[ $ERRORS -gt 0 ]]; then
        echo ""
        echo -e "${RED}Documentation validation failed with ${ERRORS} errors${NC}"
        echo "Run with --fix to auto-fix some issues"
        exit 1
    elif [[ $WARNINGS -gt 0 ]]; then
        echo ""
        echo -e "${YELLOW}Documentation validation passed with ${WARNINGS} warnings${NC}"
        exit 0
    else
        echo ""
        echo -e "${GREEN}Documentation validation passed!${NC}"
        exit 0
    fi
}

main "$@"
