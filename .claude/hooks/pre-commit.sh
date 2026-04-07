#!/bin/bash
# pre-commit.sh — Airpay Academy Pre-Commit Hook
# Enforces CLAUDE.md Absolute Rules at git commit time.
# Install: Copy to .git/hooks/pre-commit — git will auto-run it.
#
# Checks:
#   1. PHP syntax — all staged .php files
#   2. Moodle MOODLE_INTERNAL guard — class/lib files
#   3. Raw superglobal access — $_GET/$_POST/$_REQUEST
#   4. Credential patterns — hardcoded tokens/keys
#   5. .env file staged detection
#   6. Moodle core file modification
#   7. content/sops/ protection
#   8. SCORM ZIP root validation
#   9. version.php format check
#  10. Uncommitted CONFIRM-tagged placeholders

set -euo pipefail

RED='\033[0;31m'
YEL='\033[1;33m'
GRN='\033[0;32m'
NC='\033[0m'

ERRORS=0
WARNINGS=0

err()  { echo -e "${RED}  ✗ ERROR:${NC} $1"; ERRORS=$((ERRORS+1)); }
warn() { echo -e "${YEL}  ⚠ WARN:${NC} $1"; WARNINGS=$((WARNINGS+1)); }
ok()   { echo -e "${GRN}  ✓${NC} $1"; }

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║  Airpay Academy — Pre-Commit Security Check      ║"
echo "╚══════════════════════════════════════════════════╝"

STAGED_PHP=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null | grep '\.php$' || true)
STAGED_ALL=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null || true)
STAGED_ZIP=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null | grep '\.zip$' || true)

# ============================================================
# CHECK 1: PHP SYNTAX
# ============================================================
echo ""
echo "→ [1/10] PHP syntax check..."
if [ -n "$STAGED_PHP" ]; then
    PHP_ERRORS=0
    while IFS= read -r file; do
        [ -f "$file" ] || continue
        result=$(php -l "$file" 2>&1)
        if echo "$result" | grep -qE "Parse error|Fatal error"; then
            err "PHP syntax: $file"
            echo "       $result" | head -3
            PHP_ERRORS=$((PHP_ERRORS+1))
        fi
    done <<< "$STAGED_PHP"
    [ "$PHP_ERRORS" -eq 0 ] && ok "PHP syntax OK ($(echo "$STAGED_PHP" | wc -l | tr -d ' ') files)"
else
    ok "No PHP files staged"
fi

# ============================================================
# CHECK 2: MOODLE_INTERNAL GUARD
# ============================================================
echo "→ [2/10] MOODLE_INTERNAL guard..."
GUARD_ISSUES=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    # Only check internal files (not web entrypoints that load config.php)
    if [[ "$file" =~ classes/|/lib\.php$|settings\.php$|locallib\.php$ ]]; then
        if ! grep -q "defined.*MOODLE_INTERNAL\|require.*config" "$file" 2>/dev/null; then
            err "Missing MOODLE_INTERNAL guard: $file"
            GUARD_ISSUES=$((GUARD_ISSUES+1))
        fi
    fi
done <<< "$STAGED_PHP"
[ "$GUARD_ISSUES" -eq 0 ] && ok "MOODLE_INTERNAL guards present"

# ============================================================
# CHECK 3: RAW SUPERGLOBAL ACCESS
# ============================================================
echo "→ [3/10] Superglobal access (\$_GET/\$_POST)..."
SUPER_ISSUES=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    if grep -qE '\$_GET\[|\$_POST\[|\$_REQUEST\[|\$_SERVER\[.HTTP' "$file" 2>/dev/null; then
        # Allow $_SERVER['DOCUMENT_ROOT'] type accesses (not user input)
        user_input=$(grep -nE '\$_GET\[|\$_POST\[|\$_REQUEST\[' "$file" 2>/dev/null || true)
        if [ -n "$user_input" ]; then
            err "Raw superglobal in $file — use required_param() / optional_param()"
            echo "$user_input" | head -3 | sed 's/^/       /'
            SUPER_ISSUES=$((SUPER_ISSUES+1))
        fi
    fi
done <<< "$STAGED_PHP"
[ "$SUPER_ISSUES" -eq 0 ] && ok "No raw superglobal access"

# ============================================================
# CHECK 4: CREDENTIAL PATTERNS
# ============================================================
echo "→ [4/10] Credential leak detection..."
CRED_ISSUES=0
CRED_PATTERNS=(
    "(api_key|apikey|api_secret|secret_key)\s*=\s*['\"][a-zA-Z0-9_\-]{10,}"
    "(password|passwd|pwd)\s*=\s*['\"][^'\"]{8,}"
    "wstoken=[a-zA-Z0-9]{20,}"
    "MOODLE_TOKEN\s*=\s*['\"][a-zA-Z0-9]{10,}"
    "xi-api-key.*[a-zA-Z0-9]{20,}"
)
while IFS= read -r file; do
    [ -f "$file" ] || continue
    # Skip .env.example and documentation
    [[ "$file" =~ \.env\.example|\.md$|CLAUDE\.md ]] && continue
    for pattern in "${CRED_PATTERNS[@]}"; do
        if grep -qiE "$pattern" "$file" 2>/dev/null; then
            err "Possible credential in $file (pattern: $pattern)"
            grep -niE "$pattern" "$file" | head -2 | sed 's/^/       /'
            CRED_ISSUES=$((CRED_ISSUES+1))
        fi
    done
done <<< "$STAGED_ALL"
[ "$CRED_ISSUES" -eq 0 ] && ok "No credential patterns detected"

# ============================================================
# CHECK 5: .env FILE PROTECTION
# ============================================================
echo "→ [5/10] .env file protection..."
if echo "$STAGED_ALL" | grep -qE '^\.env$|/\.env$'; then
    err ".env file staged — NEVER commit credentials"
    ERRORS=$((ERRORS+1))
else
    ok ".env not staged"
fi

# ============================================================
# CHECK 6: MOODLE CORE FILE PROTECTION
# ============================================================
echo "→ [6/10] Moodle core file protection..."
CORE_ISSUES=0
CORE_PATTERNS=(
    "moodle/lib/"
    "moodle/admin/"
    "moodle/course/"
    "htdocs/moodle/lib/"
    "htdocs/moodle/admin/"
    "^lib/outputrenderers"
    "^lib/navigationlib"
    "^admin/index.php"
)
for pattern in "${CORE_PATTERNS[@]}"; do
    if echo "$STAGED_ALL" | grep -qE "$pattern"; then
        err "Moodle core file modification: $(echo "$STAGED_ALL" | grep -E "$pattern" | head -1)"
        CORE_ISSUES=$((CORE_ISSUES+1))
    fi
done
[ "$CORE_ISSUES" -eq 0 ] && ok "No core file modifications"

# ============================================================
# CHECK 7: CONTENT/SOPS PROTECTION
# ============================================================
echo "→ [7/10] SOP file protection..."
if git diff --cached --name-only --diff-filter=D 2>/dev/null | grep -q 'content/sops/'; then
    err "content/sops/ file DELETED — NEVER delete SOP source files"
elif git diff --cached --name-only --diff-filter=M 2>/dev/null | grep -q 'content/sops/'; then
    warn "content/sops/ file modified — verify this is intentional"
else
    ok "SOP files protected"
fi

# ============================================================
# CHECK 8: SCORM ZIP VALIDATION
# ============================================================
echo "→ [8/10] SCORM ZIP structure..."
if [ -n "$STAGED_ZIP" ]; then
    while IFS= read -r zipfile; do
        [ -f "$zipfile" ] || continue
        if command -v python3 &>/dev/null; then
            manifest_check=$(python3 -c "
import zipfile, sys
try:
    with zipfile.ZipFile('$zipfile', 'r') as z:
        names = z.namelist()
        if 'imsmanifest.xml' not in names:
            print('FAIL: imsmanifest.xml not at root')
            # Check if it's nested
            nested = [n for n in names if n.endswith('imsmanifest.xml') and n != 'imsmanifest.xml']
            if nested: print(f'Found nested: {nested[0]}')
            sys.exit(1)
        else:
            print('OK')
except Exception as e:
    print(f'FAIL: {e}')
    sys.exit(1)
" 2>&1)
            if echo "$manifest_check" | grep -q "FAIL"; then
                err "SCORM structure invalid: $zipfile"
                echo "       $manifest_check" | sed 's/^/       /'
            else
                ok "SCORM ZIP valid: $zipfile"
            fi
        fi
    done <<< "$STAGED_ZIP"
else
    ok "No SCORM ZIPs staged"
fi

# ============================================================
# CHECK 9: version.php FORMAT
# ============================================================
echo "→ [9/10] version.php format..."
VERSION_ISSUES=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    [[ "$(basename "$file")" != "version.php" ]] && continue

    # Check version is 10-digit YYYYMMDDNN
    version_line=$(grep -oE '\$plugin->version\s*=\s*[0-9]+' "$file" 2>/dev/null | head -1)
    if [ -n "$version_line" ]; then
        version_num=$(echo "$version_line" | grep -oE '[0-9]+')
        if [ ${#version_num} -ne 10 ]; then
            err "version.php: \$plugin->version must be 10 digits (YYYYMMDDNN), got: $version_num in $file"
            VERSION_ISSUES=$((VERSION_ISSUES+1))
        fi
    fi

    # Check component matches directory
    component=$(grep -oE "\\\$plugin->component\s*=\s*'[^']+'" "$file" | grep -oE "'[^']+'")
    if [ -n "$component" ]; then
        dir=$(dirname "$file" | xargs basename)
        component_clean=$(echo "$component" | tr -d "'")
        # component should end with the directory name: local_airhub → airhub
        plugin_dir="${component_clean##*_}"
        if [ "$plugin_dir" != "$dir" ] && [ "$component_clean" != "$dir" ]; then
            warn "version.php component '$component_clean' may not match directory '$dir'"
        fi
    fi
done <<< "$STAGED_PHP"
[ "$VERSION_ISSUES" -eq 0 ] && ok "version.php formats valid"

# ============================================================
# CHECK 10: UNCOMMITTED [CONFIRM] PLACEHOLDERS
# ============================================================
echo "→ [10/10] Uncommitted CONFIRM placeholders..."
CONFIRM_ISSUES=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    if grep -qE '# \[CONFIRM\]|// \[CONFIRM\]|\[CONFIRM_BEFORE_RUNNING\]' "$file" 2>/dev/null; then
        warn "[CONFIRM] placeholder left in: $file — verify this is intentional"
        CONFIRM_ISSUES=$((CONFIRM_ISSUES+1))
    fi
done <<< "$STAGED_ALL"
[ "$CONFIRM_ISSUES" -eq 0 ] && ok "No CONFIRM placeholders"

# ============================================================
# SUMMARY
# ============================================================
echo ""
echo "══════════════════════════════════════════════════════"
if [ "$ERRORS" -gt 0 ]; then
    echo -e "${RED}❌ BLOCKED: $ERRORS error(s) found. Fix before committing.${NC}"
    if [ "$WARNINGS" -gt 0 ]; then
        echo -e "${YEL}   Also: $WARNINGS warning(s) (non-blocking)${NC}"
    fi
    exit 1
elif [ "$WARNINGS" -gt 0 ]; then
    echo -e "${YEL}⚠  CAUTION: $WARNINGS warning(s) found (commit allowed).${NC}"
    echo -e "${GRN}✅ Committing...${NC}"
    exit 0
else
    echo -e "${GRN}✅ All checks passed. Committing...${NC}"
    exit 0
fi
