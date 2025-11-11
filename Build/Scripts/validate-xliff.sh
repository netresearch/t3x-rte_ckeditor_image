#!/bin/bash
set -e

echo "=== XLIFF Translation File Validation ==="
echo

LANG_DIR="Resources/Private/Language"
ERRORS=0

# Check if xmllint is available
if ! command -v xmllint &> /dev/null; then
    echo "⚠️  xmllint not found, skipping XML syntax validation"
    XMLLINT_AVAILABLE=false
else
    XMLLINT_AVAILABLE=true
fi

# 1. Validate XML syntax
if [ "$XMLLINT_AVAILABLE" = true ]; then
    echo "=== 1. XML Syntax Validation ==="
    for file in "$LANG_DIR"/*.xlf; do
        if xmllint --noout "$file" 2>&1; then
            echo "✅ $file - Valid XML"
        else
            echo "❌ $file - Invalid XML syntax"
            ERRORS=$((ERRORS + 1))
        fi
    done
    echo
fi

# 2. Check XLIFF version consistency
echo "=== 2. XLIFF Version Consistency ==="
VERSION_CHECK=$(grep -h 'xliff version=' "$LANG_DIR"/*.xlf | sort -u)
VERSION_COUNT=$(echo "$VERSION_CHECK" | wc -l)

if [ "$VERSION_COUNT" -eq 1 ]; then
    if echo "$VERSION_CHECK" | grep -q 'version="1.2"'; then
        echo "✅ All files use XLIFF 1.2"
    else
        echo "❌ Files not using XLIFF 1.2:"
        echo "$VERSION_CHECK"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "❌ Mixed XLIFF versions detected:"
    echo "$VERSION_CHECK"
    ERRORS=$((ERRORS + 1))
fi
echo

# 3. Check namespace declarations
echo "=== 3. XLIFF 1.2 Namespace Validation ==="
MISSING_NS=$(grep -L 'xmlns="urn:oasis:names:tc:xliff:document:1.2"' "$LANG_DIR"/*.xlf || true)
if [ -z "$MISSING_NS" ]; then
    echo "✅ All files have proper XLIFF 1.2 namespace"
else
    echo "❌ Files missing XLIFF 1.2 namespace:"
    echo "$MISSING_NS"
    ERRORS=$((ERRORS + 1))
fi
echo

# 4. Check for UTF-8 encoding (ASCII is valid UTF-8)
echo "=== 4. UTF-8 Encoding Validation ==="
NON_UTF8=$(file "$LANG_DIR"/*.xlf | grep -vE "(UTF-8|ASCII)" || true)
if [ -z "$NON_UTF8" ]; then
    echo "✅ All files are UTF-8 compatible"
else
    echo "❌ Files not UTF-8 compatible:"
    echo "$NON_UTF8"
    ERRORS=$((ERRORS + 1))
fi
echo

# 5. Warn about potential untranslated strings (source == target)
echo "=== 5. Translation Completeness Check ==="
echo "(Warning only - some matches are legitimate multilingual terms)"
echo

python3 << 'PYEOF'
import re
from pathlib import Path

lang_dir = Path('Resources/Private/Language')

# Known multilingual terms that are OK to match
MULTILINGUAL_TERMS = {
    'Retina', 'Retina (2.0x)',
    'Ultra', 'Ultra (3.0x)',
    'Standard', 'Standard (1.0x)',
    'DPI'
}

warnings = 0
for filepath in sorted(lang_dir.glob('??.locallang_be.xlf')):
    content = filepath.read_text(encoding='utf-8')

    units = re.findall(
        r'<trans-unit id="([^"]+)"[^>]*>.*?<source>([^<]+)</source>\s*<target[^>]*>([^<]+)</target>.*?</trans-unit>',
        content,
        re.DOTALL
    )

    untranslated = []
    for unit_id, source, target in units:
        if source.strip() == target.strip() and source.strip() not in MULTILINGUAL_TERMS:
            untranslated.append((unit_id, source.strip()))

    if untranslated:
        warnings += 1
        print(f"⚠️  {filepath.name}: {len(untranslated)} potential untranslated strings")
        for unit_id, text in untranslated[:3]:
            print(f"    - {unit_id}: {text}")
        if len(untranslated) > 3:
            print(f"    ... and {len(untranslated)-3} more")

if warnings == 0:
    print("✅ No unexpected untranslated strings found")
else:
    print(f"\n⚠️  Found {warnings} files with potential untranslated strings")
    print("(This is a warning only - review manually)")
PYEOF

echo
echo "=== Validation Summary ==="
if [ $ERRORS -eq 0 ]; then
    echo "✅ All validation checks passed!"
    exit 0
else
    echo "❌ Found $ERRORS validation error(s)"
    exit 1
fi
