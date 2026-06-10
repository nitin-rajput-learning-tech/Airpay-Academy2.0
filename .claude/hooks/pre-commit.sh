#!/bin/bash
# pre-commit.sh — Airpay Academy Pre-Commit Hook
# Enforces CLAUDE.md Absolute Rules at git commit time.
# Install: Copy to .git/hooks/pre-commit — git will auto-run it.
#         (or run tools/install-hooks.ps1 from the repo root).
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
#  11. Stray git conflict markers in staged source files
#      (P0 cleanup A, 2026-05-24 — CI #397/#403 root cause).
#  12. State-card freshness — every staged plugin change should also
#      stage its corresponding state-cards/<plugin>-state.md (Bucket
#      E4, 2026-05-28 — F-099 Stabilization Audit).

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
# Conflict-marker scan targets — every text format that has ever broken
# CI on a stray marker. .mustache uses {{<partial}} for parent-template
# inheritance, which is why we anchor on ^<<<<<<< (line start) instead
# of bare <<<<<<<.
STAGED_CONFLICT=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null \
    | grep -E '\.(php|mustache|scss|js|json|xml|md|yml)$' || true)

# ============================================================
# CHECK 1: PHP SYNTAX
# ============================================================
echo ""
echo "→ [1/15] PHP syntax check..."
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
echo "→ [2/15] MOODLE_INTERNAL guard..."
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
echo "→ [3/15] Superglobal access (\$_GET/\$_POST)..."
SUPER_ISSUES=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    # Skip test + CLI code: PHPUnit tests set $_POST['sesskey'] to exercise
    # external_api functions, and CLI harnesses simulate requests — both are
    # legitimate, established Moodle patterns (core does the same), not
    # web-request security risks.
    case "$file" in */tests/*|*/cli/*) continue ;; esac
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
echo "→ [4/15] Credential leak detection..."
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
echo "→ [5/15] .env file protection..."
if echo "$STAGED_ALL" | grep -qE '^\.env$|/\.env$'; then
    err ".env file staged — NEVER commit credentials"
    ERRORS=$((ERRORS+1))
else
    ok ".env not staged"
fi

# ============================================================
# CHECK 6: MOODLE CORE FILE PROTECTION
# ============================================================
echo "→ [6/15] Moodle core file protection..."
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
echo "→ [7/15] SOP file protection..."
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
echo "→ [8/15] SCORM ZIP structure..."
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
echo "→ [9/15] version.php format..."
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
echo "→ [10/15] Uncommitted CONFIRM placeholders..."
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
# CHECK 11: GIT CONFLICT MARKERS (P0 cleanup A, 2026-05-24)
# ============================================================
# CI runs #397 + #403 failed because mid-merge commits had stray
# <<<<<<<, =======, >>>>>>> markers in PHP + lang files. Markers
# yield a PHP parse error which only surfaces when CI runs PHP -l.
# We catch them at commit time instead.
#
# Regex matches git's exact conflict-marker format:
#   ^<<<<<<<( |$)   — '<<<<<<< HEAD' or bare '<<<<<<<'
#   ^=======$       — bare '=======' alone (never '========' setext
#                     banners or 32-wide CLI dividers)
#   ^>>>>>>>( |$)   — '>>>>>>> branch-name' or bare '>>>>>>>'
# This is strict enough to skip:
#   - {{<base/columns}}  Mustache parent-template inheritance
#   - `// =====`         SCSS section comment dividers
#   - `================`  setext-style heredoc CLI help banners
echo "→ [11/15] Git conflict-marker scan..."
CONFLICT_ISSUES=0
if [ -n "$STAGED_CONFLICT" ]; then
    while IFS= read -r file; do
        [ -f "$file" ] || continue
        markers=$(grep -nE '^<<<<<<<( |$)|^=======$|^>>>>>>>( |$)' "$file" 2>/dev/null || true)
        if [ -n "$markers" ]; then
            err "Git conflict marker in $file"
            echo "$markers" | head -6 | sed 's/^/       /'
            CONFLICT_ISSUES=$((CONFLICT_ISSUES + 1))
        fi
    done <<< "$STAGED_CONFLICT"
    [ "$CONFLICT_ISSUES" -eq 0 ] && ok "No conflict markers in staged source files"
else
    ok "No scan-eligible files staged"
fi

# ============================================================
# CHECK 12: STATE-CARD FRESHNESS (Bucket E4, 2026-05-28)
# ============================================================
# F-099 Stabilization Audit found 8 state cards in state-cards/ that
# had drifted into fiction — plugins had been actively modified but
# the state-cards/<plugin>-state.md was untouched for >90 days.
#
# This check runs in --mode=staged: looks at staged plugin changes
# and warns if the matching state card is NOT also staged. Soft
# warning (does not block — author may be splitting commits).
echo "→ [12/15] State-card freshness check..."
FRESHNESS_SCRIPT="tools/check_state_card_freshness.sh"
if [ -f "$FRESHNESS_SCRIPT" ]; then
    # NOTE: Use grep -c + sed instead of `... | while; warn`. A pipe
    # forks a subshell and any `WARNINGS=$((...))` inside is lost.
    FRESHNESS_OUT=$(bash "$FRESHNESS_SCRIPT" --mode=staged 2>&1 || true)
    F_WARN_COUNT=$(echo "$FRESHNESS_OUT" | grep -c '^⚠' || true)
    if [ "$F_WARN_COUNT" -gt 0 ]; then
        # Surface up to 20 lines of context so authors can act.
        warn "Plugin staged without matching state-card update"
        echo "$FRESHNESS_OUT" | sed 's/^/         /' | head -20
        WARNINGS=$((WARNINGS + F_WARN_COUNT - 1))  # already +1'd by warn
    else
        ok "State cards staged for all touched plugins"
    fi
else
    ok "Freshness gate not installed — skip"
fi

# ============================================================
# CHECK 13: MUSTACHE COMMENT LEAK (2026-06-09)
# ============================================================
# A {{! ... }} comment closes at the FIRST }} after {{!. If the body
# embeds a {{ }} / {{{ }}} / {{> }} example, Mustache leaks everything
# after that point onto the page as literal text. Bit us live on
# course/view.php (course_full_header.mustache) + 13 sibling templates
# on 2026-06-09. scan_mustache_comment_leaks.php is the single source
# of truth for the detection (CI runs the same script over whole trees).
echo "→ [13/15] Mustache comment-leak scan..."
STAGED_MUSTACHE=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null | grep '\.mustache$' || true)
LEAK_SCANNER="moodle-enhancement/tools/scan_mustache_comment_leaks.php"
if [ -z "$STAGED_MUSTACHE" ]; then
    ok "No .mustache files staged"
elif [ ! -f "$LEAK_SCANNER" ]; then
    ok "Leak scanner not present — skip"
else
    LEAK_OUT=$(echo "$STAGED_MUSTACHE" | xargs php "$LEAK_SCANNER" 2>/dev/null || true)
    if [ -n "$LEAK_OUT" ]; then
        err "Mustache comment leak — comment body embeds {{ or }} and will render onto the page"
        echo "$LEAK_OUT" | sed 's/^/       /' | head -20
        echo "       Fix: reword the comment so it contains no {{ or }} (describe braces in words)."
    else
        ok "No Mustache comment leaks in staged templates"
    fi
fi

# ============================================================
# CHECK 14: STALE theme_airpayux REFERENCES (2026-06-09)
# ============================================================
# After the theme_airpayux -> theme_sentientia de-brand, a QUOTED AMD module
# name or component string still pointing at theme_airpayux/* silently NO-OPs
# (window.require still exists, so the Gate-1 render-smoke cannot see it).
# scan_stale_theme_refs.php flags only quoted refs (real deps), excluding the
# legacy theme dir + tooling/docs. CI runs the same script over whole trees.
echo "→ [14/15] Stale theme_airpayux reference scan..."
STAGED_REFS=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null | grep -E '\.(php|js|mustache|scss|json)$' || true)
REF_SCANNER="moodle-enhancement/tools/scan_stale_theme_refs.php"
if [ -z "$STAGED_REFS" ]; then
    ok "No scan-eligible files staged"
elif [ ! -f "$REF_SCANNER" ]; then
    ok "Stale-theme-ref scanner not present — skip"
else
    REF_OUT=$(echo "$STAGED_REFS" | xargs php "$REF_SCANNER" 2>/dev/null || true)
    if [ -n "$REF_OUT" ]; then
        err "Stale theme_airpayux reference — quoted AMD/component ref silently no-ops post-rename"
        echo "$REF_OUT" | sed 's/^/       /' | head -20
        echo "       Fix: rename to theme_sentientia (or add 'stale-theme-ref-allow' on the line if intentional)."
    else
        ok "No stale theme_airpayux references in staged files"
    fi
fi

# CHECK 15: MISSING standard_end_of_body_html (ADR-027 Gate 0, 2026-06-10)
# ============================================================
# A full-page (</body>) layout template that forgets to flush
# standard_end_of_body_html ships ZERO working JS — RequireJS/AMD never boots, so
# charts/drawers/cart are inert (the dashboard regression, task #382). Gate-1
# render-smoke catches it at runtime; this is the cheaper static net. The detector
# strips {{! }} comments and resolves footer/shell partials, and honours an
# 'end-of-body-allow' marker for deliberate non-JS docs (e.g. the email wrapper).
echo "→ [15/15] Missing standard_end_of_body_html scan..."
STAGED_MUSTACHE=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null | grep -E '\.mustache$' || true)
EOB_SCANNER="moodle-enhancement/tools/scan_missing_end_of_body.php"
if [ -z "$STAGED_MUSTACHE" ]; then
    ok "No .mustache files staged"
elif [ ! -f "$EOB_SCANNER" ]; then
    ok "End-of-body scanner not present — skip"
else
    EOB_OUT=$(echo "$STAGED_MUSTACHE" | xargs php "$EOB_SCANNER" 2>/dev/null || true)
    if [ -n "$EOB_OUT" ]; then
        err "Full-page template missing standard_end_of_body_html — AMD will not boot"
        echo "$EOB_OUT" | sed 's/^/       /' | head -20
        echo "       Fix: emit {{{ output.standard_end_of_body_html }}} (or include the footer/shell partial)."
        echo "       Deliberate non-JS doc (e.g. email body)? add 'end-of-body-allow' to the file."
    else
        ok "All full-page templates flush standard_end_of_body_html"
    fi
fi

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
