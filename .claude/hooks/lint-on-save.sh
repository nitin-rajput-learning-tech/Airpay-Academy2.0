#!/bin/bash
# lint-on-save.sh — Airpay Academy Lint-on-Save Hook
# Run via VS Code File Watcher, PhpStorm File Watcher, or Claude Code PostToolUse hook.
# Usage: bash lint-on-save.sh [filepath]
#
# Covers: PHP syntax + Moodle standards | SCSS tokens | Mustache XSS | XML validity
#         version.php format | SCORM manifest | Python API patterns

FILE="${1:-}"
if [ -z "$FILE" ]; then echo "Usage: lint-on-save.sh <filepath>"; exit 0; fi
[ -f "$FILE" ] || exit 0  # File doesn't exist (may be deleted), exit silently

EXT="${FILE##*.}"
BASE=$(basename "$FILE")

RED='\033[0;31m'
YEL='\033[1;33m'
GRN='\033[0;32m'
BLU='\033[0;34m'
NC='\033[0m'

ERRORS=0
WARNINGS=0

err()  { echo -e "${RED}  ✗ ${NC}$1"; ERRORS=$((ERRORS+1)); }
warn() { echo -e "${YEL}  ⚠ ${NC}$1"; WARNINGS=$((WARNINGS+1)); }
ok()   { echo -e "${GRN}  ✓ ${NC}$1"; }
info() { echo -e "${BLU}  ℹ ${NC}$1"; }

echo ""
echo "─── Airpay Lint: $BASE ──────────────────────────────"

# ============================================================
# PHP FILES
# ============================================================
if [ "$EXT" = "php" ]; then

    # 1. Syntax check (immediate — block on failure)
    SYNTAX=$(php -l "$FILE" 2>&1)
    if echo "$SYNTAX" | grep -qE "Parse error|Fatal error"; then
        err "PHP SYNTAX ERROR:"
        echo "$SYNTAX" | grep -E "Parse error|Fatal error|on line" | sed 's/^/    /'
        exit 1
    fi
    ok "Syntax OK"

    # 2. MOODLE_INTERNAL guard (for internal files)
    if [[ "$FILE" =~ classes/|/lib\.php$|settings\.php$|locallib\.php$|/db/upgrade\.php$ ]]; then
        if ! grep -q "defined.*MOODLE_INTERNAL\|require.*config" "$FILE" 2>/dev/null; then
            err "Missing: defined('MOODLE_INTERNAL') || die(); at top of $BASE"
            info "Add after <?php: defined('MOODLE_INTERNAL') || die();"
        else
            ok "MOODLE_INTERNAL guard present"
        fi
    fi

    # 3. Raw superglobal detection
    if grep -qE '\$_GET\[|\$_POST\[|\$_REQUEST\[' "$FILE" 2>/dev/null; then
        lines=$(grep -nE '\$_GET\[|\$_POST\[|\$_REQUEST\[' "$FILE")
        err "Raw superglobal access — use required_param() / optional_param()"
        echo "$lines" | head -3 | sed 's/^/    /'
        info "Fix: \$id = required_param('id', PARAM_INT);"
    else
        ok "No raw superglobal access"
    fi

    # 4. Unescaped echo detection
    if grep -qE '^[[:space:]]*(echo|print)\s+\$[A-Za-z_]' "$FILE" 2>/dev/null; then
        lines=$(grep -nE '^[[:space:]]*(echo|print)\s+\$[A-Za-z_]' "$FILE" | grep -v '//\s*ok\|format_string\|s(\$')
        if [ -n "$lines" ]; then
            warn "Possible unescaped echo — verify escaping:"
            echo "$lines" | head -3 | sed 's/^/    /'
            info "Use: echo format_string(\$text); or echo s(\$value); or echo html_writer::..."
        fi
    fi

    # 5. Credential patterns
    if grep -qiE "(api_key|secret|token|password)\s*=\s*['\"][a-zA-Z0-9_\-]{10,}" "$FILE" 2>/dev/null; then
        err "Possible hardcoded credential in $BASE"
        info "Use: \$key = get_config('local_pluginname', 'apikey'); or getenv('KEY')"
    else
        ok "No credential patterns"
    fi

    # 6. Raw SQL detection
    if grep -qE "mysqli_query|mysql_query|->query\(|PDO::" "$FILE" 2>/dev/null; then
        err "Raw DB call detected — use Moodle \$DB API"
        info "Replace with: \$DB->get_records() / \$DB->get_records_sql() etc."
    fi

    # 7. version.php specific checks
    if [ "$BASE" = "version.php" ]; then
        info "version.php checks:"

        # Version format (10 digits YYYYMMDDNN)
        version=$(grep -oE '\$plugin->version\s*=\s*[0-9]+' "$FILE" | grep -oE '[0-9]+' | head -1)
        if [ -n "$version" ]; then
            if [ ${#version} -ne 10 ]; then
                err "version must be 10 digits YYYYMMDDNN, got: $version (${#version} digits)"
            else
                year=${version:0:4}
                if [ "$year" -lt 2024 ] || [ "$year" -gt 2030 ]; then
                    warn "version year $year seems unusual (expected 2024-2030)"
                else
                    ok "Version format: $version"
                fi
            fi
        else
            err "\$plugin->version not found in version.php"
        fi

        # requires check (must be ≥ 2024100700 for Moodle 4.5)
        requires=$(grep -oE '\$plugin->requires\s*=\s*[0-9]+' "$FILE" | grep -oE '[0-9]+' | head -1)
        if [ -n "$requires" ]; then
            if [ "$requires" -lt 2024100700 ]; then
                warn "\$plugin->requires = $requires is below Moodle 4.5 minimum (2024100700)"
                info "Update to: \$plugin->requires = 2024100700;"
            else
                ok "requires OK: $requires"
            fi
        fi

        # component check
        if ! grep -q '\$plugin->component' "$FILE"; then
            err "\$plugin->component not set in version.php"
        fi
        if ! grep -q '\$plugin->maturity' "$FILE"; then
            warn "\$plugin->maturity not set (recommend MATURITY_STABLE)"
        fi
    fi

    # 8. PAGE setup order check (for index/view entrypoints)
    if [[ "$BASE" =~ ^(index|view)\.php$ ]]; then
        if ! grep -q 'require_login' "$FILE"; then
            warn "No require_login() found in $BASE — is authentication intentionally skipped?"
        fi
        if ! grep -q '\$PAGE->set_url' "$FILE"; then
            warn "No \$PAGE->set_url() found in $BASE — set canonical URL for breadcrumbs"
        fi
        if ! grep -q '\$OUTPUT->header\(\)' "$FILE" && ! grep -q 'echo.*header' "$FILE"; then
            warn "No \$OUTPUT->header() call — is this page outputting content?"
        fi
    fi

fi

# ============================================================
# SCSS / CSS FILES
# ============================================================
if [ "$EXT" = "scss" ] || [ "$EXT" = "css" ]; then

    # Check for hardcoded design system values (should be variables)
    HEX_ISSUES=0

    # Airpay brand colours that must be variables
    declare -A COLOUR_MAP=(
        ["#0066A7"]='$ap-primary'
        ["#0f7a73"]='$ap-accent'
        ["#F2F4FB"]='$ap-bg'
        ["#1a1a2e"]='$ap-text-primary'
    )

    for hex in "${!COLOUR_MAP[@]}"; do
        var="${COLOUR_MAP[$hex]}"
        lower_hex=$(echo "$hex" | tr '[:upper:]' '[:lower:]')
        if grep -qiE "$hex|$lower_hex" "$FILE" 2>/dev/null; then
            # Allow in variable definition lines, not in rules
            rule_uses=$(grep -niE "$hex|$lower_hex" "$FILE" | grep -v '^\s*\$' | head -3)
            if [ -n "$rule_uses" ]; then
                warn "Hardcoded colour $hex in rule — use variable $var:"
                echo "$rule_uses" | sed 's/^/    /'
                HEX_ISSUES=$((HEX_ISSUES+1))
            fi
        fi
    done
    [ "$HEX_ISSUES" -eq 0 ] && ok "Colour variables used correctly"

    # Check !important overuse
    important_count=$(grep -c '!important' "$FILE" 2>/dev/null || echo 0)
    if [ "$important_count" -gt 8 ]; then
        warn "$important_count !important declarations — indicates specificity problems"
        info "Refactor selectors or use higher-specificity selectors instead"
    elif [ "$important_count" -gt 0 ]; then
        ok "$important_count !important (acceptable)"
    fi

    # Check for non-BEM class names (new classes should follow .airpay- prefix)
    new_classes=$(grep -oE '\.[a-zA-Z][a-zA-Z0-9_-]+\s*\{' "$FILE" | grep -v '^\.' | head -5)
    if [ -n "$new_classes" ]; then
        info "New CSS classes — ensure they follow .airpay-[block]__[element]--[modifier] naming"
    fi

    # SCSS variable existence check
    if [ "$EXT" = "scss" ]; then
        undefined_vars=$(grep -oE '\$[a-z][a-zA-Z0-9_-]+' "$FILE" | sort -u | while read var; do
            # Check if defined in this file or is an ap- token
            if ! grep -q "^$var\s*:" "$FILE" 2>/dev/null && [[ "$var" != *"ap-"* ]]; then
                echo "$var (may be from another file — verify)"
            fi
        done)
        if [ -n "$undefined_vars" ]; then
            info "Referenced SCSS variables (verify defined in custom_changes.scss):"
            echo "$undefined_vars" | head -5 | sed 's/^/    /'
        fi
    fi
fi

# ============================================================
# MUSTACHE TEMPLATES
# ============================================================
if [ "$EXT" = "mustache" ]; then

    # 1. Triple-brace unescaped output (potential XSS)
    if grep -q '{{{' "$FILE" 2>/dev/null; then
        triple_lines=$(grep -n '{{{' "$FILE")
        warn "Unescaped triple-brace {{{ }}} found — verify content is pre-sanitised:"
        echo "$triple_lines" | sed 's/^/    /'
        info "Use {{ }} for user-generated content (auto-escaped)"
    else
        ok "No unescaped triple-braces"
    fi

    # 2. Hardcoded English strings
    hardcoded=$(grep -nE '>[A-Z][a-z]+ [a-z]' "$FILE" | grep -v '{{' | grep -v '<!--' | head -5)
    if [ -n "$hardcoded" ]; then
        warn "Possible hardcoded English text (use {{# str }} helper):"
        echo "$hardcoded" | sed 's/^/    /'
        info "Replace with: {{# str }}stringkey, component{{/ str }}"
    else
        ok "No obvious hardcoded strings"
    fi

    # 3. Unmatched mustache tags
    open_tags=$(grep -oE '\{\{#[^}]+\}\}' "$FILE" | sed 's/{{#//' | sed 's/}}//' | tr -d ' ')
    close_tags=$(grep -oE '\{\{/[^}]+\}\}' "$FILE" | sed 's/{{\//' | sed 's/}}//' | tr -d ' ')
    open_count=$(echo "$open_tags" | grep -c . 2>/dev/null || echo 0)
    close_count=$(echo "$close_tags" | grep -c . 2>/dev/null || echo 0)
    if [ "$open_count" -ne "$close_count" ]; then
        warn "Mustache tag mismatch: $open_count opening, $close_count closing tags"
        info "Check that every {{# tag }} has a matching {{/ tag }}"
    else
        ok "Mustache tags balanced"
    fi

    # 4. Check for sesskey in forms
    if grep -qi 'method.*post\|type.*submit' "$FILE" 2>/dev/null; then
        if ! grep -q 'sesskey\|logintoken' "$FILE"; then
            warn "Form found without sesskey — CSRF protection needed"
            info "Add: <input type=\"hidden\" name=\"sesskey\" value=\"{{sesskey}}\"/>"
        fi
    fi
fi

# ============================================================
# XML FILES (db/install.xml, imsmanifest.xml)
# ============================================================
if [ "$EXT" = "xml" ]; then

    if command -v xmllint &>/dev/null; then
        XML_RESULT=$(xmllint --noout "$FILE" 2>&1)
        if [ -n "$XML_RESULT" ]; then
            err "XML syntax error in $BASE:"
            echo "$XML_RESULT" | head -5 | sed 's/^/    /'
        else
            ok "XML syntax valid"
        fi
    fi

    # SCORM manifest checks
    if [ "$BASE" = "imsmanifest.xml" ]; then
        info "SCORM manifest checks:"

        if ! grep -q 'masteryscore\|mastery_score' "$FILE"; then
            err "masteryscore not found — Airpay default is 70"
            info "Add: <imsss:masteryScore>70</imsss:masteryScore>"
        else
            score=$(grep -oE 'masteryscore[^>]*>[0-9]+' "$FILE" | grep -oE '[0-9]+')
            if [ "$score" != "70" ]; then
                warn "masteryscore=$score (Airpay default is 70 — verify intentional)"
            else
                ok "masteryscore=70 ✓"
            fi
        fi

        if ! grep -q 'default="ORG_01"' "$FILE"; then
            warn "organizations default attribute: expected ORG_01"
        else
            ok "organizations default=ORG_01 ✓"
        fi

        if ! grep -q 'index.html\|index.htm' "$FILE"; then
            warn "Launch file (index.html) not referenced in manifest"
        fi
    fi

    # XMLDB install.xml checks
    if [ "$BASE" = "install.xml" ]; then
        info "XMLDB checks:"

        if ! grep -q 'timecreated' "$FILE"; then
            warn "timecreated column not found — add to all tables"
        fi
        if ! grep -q 'timemodified' "$FILE"; then
            warn "timemodified column not found — add to all tables"
        fi
        if grep -q 'TYPE="int"' "$FILE" && ! grep -q 'INDEX\|KEY' "$FILE"; then
            warn "Table has integer fields but no indexes — add INDEX for foreign keys"
        fi
    fi
fi

# ============================================================
# PYTHON FILES (SENTIENTIA pipeline)
# ============================================================
if [ "$EXT" = "py" ]; then

    ok "Python file: $BASE"

    # Credential leak in Python
    if grep -qiE "(api_key|secret|token|password)\s*=\s*['\"][a-zA-Z0-9_\-]{10,}" "$FILE" 2>/dev/null; then
        err "Possible hardcoded credential in Python file"
        info "Use: os.getenv('KEY_NAME') with load_dotenv()"
    else
        ok "No hardcoded credentials"
    fi

    # Missing dotenv load
    if grep -q 'os.getenv\|os.environ' "$FILE" && ! grep -q 'load_dotenv\|dotenv' "$FILE"; then
        warn "os.getenv() used but load_dotenv() not found — env vars may not load from .env"
        info "Add: from dotenv import load_dotenv; load_dotenv()"
    fi

    # Missing timeout on requests
    if grep -q 'requests.post\|requests.get' "$FILE" && ! grep -q 'timeout=' "$FILE"; then
        warn "requests calls without timeout — add timeout=30 (or 120 for uploads)"
    fi

    # ElevenLabs/Gamma call without CONFIRM marker
    if grep -qE 'elevenlabs|api.gamma|ElevenLabs' "$FILE"; then
        if ! grep -qE 'CONFIRM|confirm' "$FILE"; then
            warn "External API call (ElevenLabs/Gamma) without [CONFIRM] marker"
            info "Add docstring: '\"\"\"[CONFIRM] required before calling — costs money.\"\"\"'"
        fi
    fi
fi

# ============================================================
# SUMMARY
# ============================================================
echo ""
if [ "$ERRORS" -gt 0 ]; then
    echo -e "${RED}❌ $ERRORS error(s) in $BASE${NC}"
    [ "$WARNINGS" -gt 0 ] && echo -e "${YEL}   + $WARNINGS warning(s)${NC}"
    exit 1
elif [ "$WARNINGS" -gt 0 ]; then
    echo -e "${YEL}⚠  $WARNINGS warning(s) in $BASE — review before committing${NC}"
    exit 0
else
    echo -e "${GRN}✅ $BASE — clean${NC}"
    exit 0
fi
