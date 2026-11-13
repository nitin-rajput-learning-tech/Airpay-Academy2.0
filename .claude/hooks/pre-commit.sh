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
#  13. Mustache comment leak
#  14. Stale theme_airpayux references
#  15. Missing standard_end_of_body_html
#  16. AMD src/build parity
#  17. en/hi lang-pack parity
#  18. Tenant path boundary
#  19. Cross-tree plugin twins
#  20. SITEID strict comparisons

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

# Every staged-file list below uses --no-renames, so a moved or renamed file is
# listed as an add (A) at its new path. Without it git lists the file as R, which
# --diff-filter=ACM drops, and until 2026-11-13 a file that was renamed and
# edited in the same commit skipped every check (syntax, credentials, conflict
# markers, version.php ...).
STAGED_PHP=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep '\.php$' || true)
STAGED_ALL=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null || true)
STAGED_ZIP=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep '\.zip$' || true)
# Conflict-marker scan targets — every text format that has ever broken
# CI on a stray marker. .mustache uses {{<partial}} for parent-template
# inheritance, which is why we anchor on ^<<<<<<< (line start) instead
# of bare <<<<<<<.
STAGED_CONFLICT=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null \
    | grep -E '\.(php|mustache|scss|js|json|xml|md|yml)$' || true)

# ============================================================
# CHECK 1: PHP SYNTAX
# ============================================================
echo ""
echo "→ [1/20] PHP syntax check..."
# php -l exits non-zero on a parse error. Under set -euo pipefail that failed
# the assignment, and until 2026-11-13 the hook stopped here with no message
# (exit 255), so CHECKs 2-20 never ran. '|| true' keeps it going; the output is
# what is checked.
if [ -n "$STAGED_PHP" ] && ! command -v php >/dev/null 2>&1; then
    warn "php is not on PATH - PHP syntax unchecked"
elif [ -n "$STAGED_PHP" ]; then
    PHP_ERRORS=0
    while IFS= read -r file; do
        [ -f "$file" ] || continue
        result=$(php -l "$file" 2>&1 || true)
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
echo "→ [2/20] MOODLE_INTERNAL guard..."
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
echo "→ [3/20] Superglobal access (\$_GET/\$_POST)..."
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
echo "→ [4/20] Credential leak detection..."
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
echo "→ [5/20] .env file protection..."
if echo "$STAGED_ALL" | grep -qE '^\.env$|/\.env$'; then
    err ".env file staged — NEVER commit credentials"
    ERRORS=$((ERRORS+1))
else
    ok ".env not staged"
fi

# ============================================================
# CHECK 6: MOODLE CORE FILE PROTECTION
# ============================================================
# The repo root is a Moodle tree (4.5 layout: lib/, admin/, course/ at the
# root; a 5.1+ tree keeps the same dirs under public/, so that prefix is
# accepted too). Core edits are allowed only with a record in
# moodle-enhancement/docs/core-mods/ and a SENTIENTIA-CORE-MOD marker at the
# change site (root CLAUDE.md section 3). A staged file in a guarded core dir
# is an ERROR unless (1) a record in the index (committed or staged) names its
# exact path, and (2) the staged file carries the marker. (2) applies to every
# text file except JSON, source maps and CSV, which cannot hold a comment;
# those, binary files and deletions need only (1). Renames are listed as a
# delete plus an add (--no-renames), so moving a core file out of a guarded
# dir still needs a record.
#   Revert: a file whose HEAD version has a marker and whose staged version
#            has none is an undone core mod (dropped after an upstream fix,
#            or rolled back). It needs only (1); the record stays, with a
#            'Reverted' note. It is reported as a warning.
#   Upstream sync: a record line 'Core-Mods-Upstream-Sync: <guarded dir
#            prefix>/ <upstream version>' (line start, a record, not
#            README.md) that is staged new with this commit (in the index's
#            records, not in HEAD's) marks the files under the prefix as an
#            upstream Moodle or vendor import: they need neither (1) nor (2).
#            Upstream files carry no marker and a release changes hundreds of
#            them. Commit the line with the import; a line already in HEAD
#            exempts nothing.
# Known gaps: a record that names a path only in
# passing (e.g. as a caller) counts as (1); a marker anywhere in the staged
# file counts as (2), so a later edit to a file that already has one passes
# without a new marker; a record path written with a leading '/' is read as a
# URL (/my/index.php) and does not count; a marked patch replaced by a
# different unmarked edit passes as a revert; a sync line cannot prove the
# files under its prefix are upstream content, so a local edit committed with
# the sync passes (review the sync commit).
#   Guarded: lib/ admin/ course/ my/ mod/assign/ mod/quiz/ (the core dirs that
#            .claude/settings.json denies copying into, plus my/).
#   Ours, never flagged: admin/tool/sentientia_*/, mod/quiz/accessrule/
#            sentientia_*/, the my/ overlays (dashboard.php, switchrole.php,
#            templates/dropdown.mustache). local/, blocks/, enrol/,
#            payment/gateway/, theme/ and moodle-enhancement/ are outside the
#            guarded dirs.
#   Not guarded: other core dirs (auth/, login/, user/, question/ ...) and
#            root files such as version.php.
# Until 2026-11-13 the patterns were moodle/lib/, htdocs/moodle/... (no repo
# path has those prefixes), so only lib/outputrenderers*, lib/navigationlib*
# and admin/index.php were caught, and a documented core mod was blocked too.
# CI twin: tools/ci/check-core-mods.sh (.github/workflows/core-mods.yml)
# applies these rules to a commit range; change both together.
echo "→ [6/20] Moodle core file protection..."
CORE_ISSUES=0
CORE_STAGED=$(git diff --cached --name-only --no-renames --diff-filter=ACDMT 2>/dev/null \
    | grep -E '^(public/)?(lib|admin|course|my|mod/assign|mod/quiz)/' \
    | grep -vE '^(public/)?(admin/tool/sentientia_[^/]+|mod/quiz/accessrule/sentientia_[^/]+)/' \
    | grep -vE '^(public/)?my/(dashboard\.php|switchrole\.php|templates/dropdown\.mustache)$' || true)
if [ -z "$CORE_STAGED" ]; then
    ok "No core file modifications"
else
    # Every path-like token in the records, read from the index so the record
    # has to be committed or staged. README.md is the ledger's guide; its
    # example paths are not records. URL tokens (leading /) are dropped;
    # public/ and trailing dots are stripped so 'public/lib/x.php' and
    # 'lib/x.php.' both match lib/x.php.
    CORE_RECORDED=$(git grep --cached -h -o -E '[A-Za-z0-9_./-]+' \
        -- 'moodle-enhancement/docs/core-mods/*.md' 'docs/core-mods/*.md' ':(exclude)*README.md' 2>/dev/null \
        | grep -v '^/' | sed -e 's#^public/##' -e 's#[.]*$##' | sort -u || true)
    # Upstream sync lines staged new with this commit (in the index's records,
    # not in HEAD's). Group 2 of SYNC_RE is the prefix without public/; the
    # same pattern is in tools/ci/check-core-mods.sh.
    SYNC_RE='^Core-Mods-Upstream-Sync:[[:space:]]+(public/)?((lib|admin|course|my|mod/assign|mod/quiz)/([A-Za-z0-9_.-]+/)*)[[:space:]]+[^[:space:]]'
    SYNC_OLD=$(git grep -h -E '^Core-Mods-Upstream-Sync:' HEAD \
        -- 'moodle-enhancement/docs/core-mods/*.md' 'docs/core-mods/*.md' ':(exclude)*README.md' 2>/dev/null \
        | tr -d '\r' | LC_ALL=C sort -u || true)
    SYNC_IDX=$(git grep --cached -h -E '^Core-Mods-Upstream-Sync:' \
        -- 'moodle-enhancement/docs/core-mods/*.md' 'docs/core-mods/*.md' ':(exclude)*README.md' 2>/dev/null \
        | tr -d '\r' | LC_ALL=C sort -u || true)
    SYNC_PREFIXES=""
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        grep -qxF -- "$line" <<< "$SYNC_OLD" && continue
        if [[ "$line" =~ $SYNC_RE ]]; then
            SYNC_PREFIXES+="${BASH_REMATCH[2]}"$'\n'
        else
            warn "Ignored a malformed sync line (want 'Core-Mods-Upstream-Sync: <guarded dir prefix>/ <upstream version>'): $line"
        fi
    done <<< "$SYNC_IDX"
    CORE_SYNCED=0
    CORE_REVERTS=0
    while IFS= read -r file; do
        [ -n "$file" ] || continue
        SYNCED_BY=""
        while IFS= read -r prefix; do
            [ -n "$prefix" ] || continue
            case "${file#public/}" in "$prefix"*) SYNCED_BY=$prefix; break ;; esac
        done <<< "$SYNC_PREFIXES"
        if [ -n "$SYNCED_BY" ]; then
            CORE_SYNCED=$((CORE_SYNCED+1))
            continue
        fi
        # Here-strings, not pipes into grep -q: under pipefail an early grep
        # exit can SIGPIPE the writer and turn a match into a failure.
        if ! grep -qxF "${file#public/}" <<< "$CORE_RECORDED"; then
            err "Moodle core file modification without a core-mods record: $file"
            CORE_ISSUES=$((CORE_ISSUES+1))
        elif ! [[ "$file" =~ \.(json|map|csv)$ ]] && git cat-file -e ":$file" 2>/dev/null \
             && [ "$(git --literal-pathspecs diff --cached --numstat --no-renames -- "$file" 2>/dev/null | cut -f1)" != "-" ]; then
            # Staged, not deleted, and not binary (numstat prints '-' for binary).
            MARKERS=$(git show ":$file" 2>/dev/null | grep -c 'SENTIENTIA-CORE-MOD' || true)
            if [ "${MARKERS:-0}" -eq 0 ]; then
                # A revert when HEAD's version carries a marker: (1) is enough.
                BASE_MARKERS=$(git show "HEAD:$file" 2>/dev/null | grep -c 'SENTIENTIA-CORE-MOD' || true)
                if [ "${BASE_MARKERS:-0}" -gt 0 ]; then
                    warn "Revert of a recorded core mod (HEAD has a SENTIENTIA-CORE-MOD marker, the staged file has none): $file. Keep its record, with a 'Reverted' note."
                    CORE_REVERTS=$((CORE_REVERTS+1))
                else
                    err "Moodle core file modification without a SENTIENTIA-CORE-MOD marker: $file"
                    CORE_ISSUES=$((CORE_ISSUES+1))
                fi
            fi
        fi
    done <<< "$CORE_STAGED"
    CORE_DETAIL="$(echo "$CORE_STAGED" | wc -l | tr -d ' ') file(s)"
    [ "$CORE_REVERTS" -eq 0 ] || CORE_DETAIL+=", $CORE_REVERTS revert(s)"
    [ "$CORE_SYNCED" -eq 0 ] || CORE_DETAIL+=", $CORE_SYNCED under an upstream sync staged with this commit"
    if [ "$CORE_ISSUES" -gt 0 ]; then
        echo "       Each core edit needs a record in moodle-enhancement/docs/core-mods/YYYY-MM-DD-<slug>.md"
        echo "       that names the file path (committed or staged with this commit) and a"
        echo "       '// SENTIENTIA-CORE-MOD: <reason>' marker at the change site (root CLAUDE.md section 3;"
        echo "       in XML/HTML/SVG use <!-- SENTIENTIA-CORE-MOD: <reason> -->, in YAML/.feature use #)."
        echo "       An upstream Moodle or vendor import stages a 'Core-Mods-Upstream-Sync: <prefix>/ <version>'"
        echo "       line in its record instead (moodle-enhancement/docs/core-mods/README.md)."
    else
        ok "Core file modifications pass the core-mods record and marker rules ($CORE_DETAIL)"
    fi
fi

# ============================================================
# CHECK 7: CONTENT/SOPS PROTECTION
# ============================================================
# Renames are listed as a delete plus an add (--no-renames, as in CHECK 6).
# Until 2026-11-13 git listed a move out of content/sops/ as a rename (R),
# which neither filter saw, so the move passed as "SOP files protected". A
# rename inside content/sops/ removes the old name too, so it is blocked as
# well. The lists are captured first instead of piped into grep -q (see the
# SIGPIPE note in CHECK 6). core.quotePath=false prints a non-ASCII path as it
# is, so the listed path can be pasted into the restore command.
echo "→ [7/20] SOP file protection..."
SOP_DELETED=$(git -c core.quotePath=false diff --cached --name-only --no-renames --diff-filter=D 2>/dev/null | grep 'content/sops/' || true)
SOP_MODIFIED=$(git -c core.quotePath=false diff --cached --name-only --no-renames --diff-filter=MT 2>/dev/null | grep 'content/sops/' || true)
if [ -n "$SOP_DELETED" ]; then
    err "content/sops/ file DELETED — NEVER delete SOP source files"
    sed -e 's/^/       /' -e '20q' <<< "$SOP_DELETED"
    SOP_DELETED_COUNT=$(wc -l <<< "$SOP_DELETED")
    [ "$SOP_DELETED_COUNT" -gt 20 ] && echo "       ... $SOP_DELETED_COUNT files in all"
    echo "       A move or rename counts as a delete: copy the file instead and keep the original."
    echo "       To unstage the delete: git restore --staged -- <path>"
    echo "       If the file is also gone from disk, then: git restore -- <path>"
elif [ -n "$SOP_MODIFIED" ]; then
    warn "content/sops/ file modified — verify this is intentional"
else
    ok "SOP files protected"
fi

# ============================================================
# CHECK 8: SCORM ZIP VALIDATION
# ============================================================
echo "→ [8/20] SCORM ZIP structure..."
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
echo "→ [9/20] version.php format..."
VERSION_ISSUES=0
while IFS= read -r file; do
    [ -f "$file" ] || continue
    [[ "$(basename "$file")" != "version.php" ]] && continue
    # Not plugin version files: Moodle core's own version.php declares $version,
    # and version.php files under tests/fixtures/ are test inputs, some broken on
    # purpose (lib/tests/fixtures/update_validator/). Moodle installs neither.
    case "$file" in version.php|public/version.php|*/tests/fixtures/*) continue ;; esac

    # The greps end in '|| true': under set -euo pipefail a grep with no match
    # failed the assignment, and until 2026-11-13 the hook exited 1 here with no
    # message. A missing line is reported as an error instead, because Moodle's
    # upgrade stops on it (plugin_defective_exception in lib/upgradelib.php).

    # Check version is 10-digit YYYYMMDDNN
    version_line=$(grep -oE '\$plugin->version\s*=\s*[0-9]+' "$file" 2>/dev/null | head -1 || true)
    if [ -n "$version_line" ]; then
        version_num=$(echo "$version_line" | grep -oE '[0-9]+' || true)
        if [ ${#version_num} -ne 10 ]; then
            err "version.php: \$plugin->version must be 10 digits (YYYYMMDDNN), got: $version_num in $file"
            VERSION_ISSUES=$((VERSION_ISSUES+1))
        fi
    else
        err "version.php: no numeric \$plugin->version line in $file (Moodle's upgrade stops on this)"
        VERSION_ISSUES=$((VERSION_ISSUES+1))
    fi

    # Check component matches directory ('single' or "double" quotes)
    component=$(grep -oE "\\\$plugin->component\s*=\s*['\"][^'\"]+['\"]" "$file" 2>/dev/null \
        | grep -oE "['\"][^'\"]+['\"]" || true)
    if [ -z "$component" ]; then
        err "version.php: no \$plugin->component line in $file (Moodle's upgrade stops on this)"
        VERSION_ISSUES=$((VERSION_ISSUES+1))
    else
        # Not 'dirname | xargs basename': xargs splits on spaces and stops on a
        # quote, which under set -e ended the hook with no summary.
        dir=$(basename "$(dirname "$file")")
        component_clean=$(echo "$component" | tr -d "'\"")
        # The directory must equal the component minus its type prefix
        # (local_airhub → airhub, local_sentientia_courses → sentientia_courses).
        # Fixed 2026-09-08: the old `##*_` stripped up to the LAST underscore, so
        # every multi-word plugin (sentientia_*) warned on each commit.
        plugin_dir="${component_clean#*_}"
        if [ "$plugin_dir" != "$dir" ] && [ "$component_clean" != "$dir" ]; then
            warn "version.php component '$component_clean' may not match directory '$dir'"
        fi
    fi
done <<< "$STAGED_PHP"
[ "$VERSION_ISSUES" -eq 0 ] && ok "version.php formats valid"

# ============================================================
# CHECK 10: UNCOMMITTED [CONFIRM] PLACEHOLDERS
# ============================================================
echo "→ [10/20] Uncommitted CONFIRM placeholders..."
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
echo "→ [11/20] Git conflict-marker scan..."
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
echo "→ [12/20] State-card freshness check..."
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
echo "→ [13/20] Mustache comment-leak scan..."
STAGED_MUSTACHE=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep '\.mustache$' || true)
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
echo "→ [14/20] Stale theme_airpayux reference scan..."
STAGED_REFS=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep -E '\.(php|js|mustache|scss|json)$' || true)
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
echo "→ [15/20] Missing standard_end_of_body_html scan..."
STAGED_MUSTACHE=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep -E '\.mustache$' || true)
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
# CHECK 16: AMD src/build PARITY (2026-06-19, Moodle 5.2 audit)
# ============================================================
# Moodle serves the MINIFIED amd/build/<name>.min.js in PRODUCTION mode (the
# default on a real install). Staging an amd/src/*.js without its built
# amd/build/*.min.js ships a module that 404s at runtime — whatever loads it
# via AMD dies silently. That is exactly how local_sentientia_courses/
# enrolledusers shipped a dead enrol-user modal (surfaced by the 2026-06-19
# Moodle 5.2 compat audit as a RECURRING gap). scan_amd_build_parity.php is the
# single source of truth (CI's amd-build-parity job runs the same script over
# whole trees); pre-existing gaps are grandfathered in
# tools/amd-build-parity-allowlist.txt, opt-out marker `amd-build-parity-allow`.
echo "→ [16/20] AMD src/build parity..."
STAGED_AMD_SRC=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep -E '/amd/src/.*\.js$' || true)
AMD_SCANNER="moodle-enhancement/tools/scan_amd_build_parity.php"
if [ -z "$STAGED_AMD_SRC" ]; then
    ok "No amd/src JS staged"
elif [ ! -f "$AMD_SCANNER" ]; then
    ok "AMD parity scanner not present — skip"
else
    AMD_OUT=$(echo "$STAGED_AMD_SRC" | xargs php "$AMD_SCANNER" 2>/dev/null || true)
    if [ -n "$AMD_OUT" ]; then
        err "amd/src module staged without a built amd/build/*.min.js — AMD 404s in PRODUCTION mode"
        echo "$AMD_OUT" | sed 's/^/       /' | head -20
        echo "       Fix: cd <moodle-dirroot> && npx grunt amd --root=public/<plugin> (or --files=<one src>),"
        echo "            then stage the produced amd/build/*.min.js (+ .map)."
    else
        ok "All staged amd/src modules have a built amd/build/*.min.js"
        # Built file exists on disk but isn't staged? The commit would omit it
        # and CI (amd-build-parity, which sees only the committed tree) would
        # then fail — warn so it gets staged in the same commit.
        while IFS= read -r src; do
            [ -n "$src" ] || continue
            build="${src/\/amd\/src\//\/amd\/build\/}"; build="${build%.js}.min.js"
            # $STAGED_ALL is the same ACM list; a here-string, not a pipe into
            # grep -q (see the SIGPIPE note in CHECK 6).
            if [ -f "$build" ] && ! grep -qxF "$build" <<< "$STAGED_ALL"; then
                warn "Built $build exists but is NOT staged — 'git add' it (CI checks the committed tree)"
            fi
        done <<< "$STAGED_AMD_SRC"
    fi
fi

# ============================================================
# CHECK 17: EN/HI LANG-PACK PARITY (ADR-028 Phase 1.5, 2026-08-04)
# ============================================================
# CLAUDE.md mandates 100% Hindi parity; the UI-NAV shell chrome proved
# the convention regresses silently. Runs the repo-wide gate ONLY when
# a lang file is staged (fast path otherwise). FAILs on en<->hi key
# drift where a hi pack exists; en-only components are warnings (known
# backlog). CI twin: .github/workflows/ci.yml::lang-parity-check.
echo "-> [17/20] en/hi lang-pack parity..."
STAGED_LANG=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep -E '/lang/(en|hi)/.*[.]php$' || true)
if [ -z "$STAGED_LANG" ]; then
    ok "No lang files staged"
elif [ -f "tools/check-lang-parity.php" ]; then
    PARITY_OUT=$(php tools/check-lang-parity.php --quiet 2>&1 || true)
    PARITY_FAILS=$(echo "$PARITY_OUT" | grep -c '^FAIL' || true)
    if [ "$PARITY_FAILS" -gt 0 ]; then
        err "en/hi lang-pack parity broken ($PARITY_FAILS component file(s))"
        echo "$PARITY_OUT" | grep -A6 '^FAIL' | sed 's/^/       /' | head -30
        echo "       Fix: add the missing keys to the other pack (en<->hi 1:1)."
    else
        ok "en/hi parity clean (where hi packs exist)"
    fi
else
    warn "tools/check-lang-parity.php missing - parity unchecked"
fi

echo "-> [18/20] Tenant path-boundary scan..."
STAGED_PHP=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep -E '[.]php$' || true)
if [ -z "$STAGED_PHP" ]; then
    ok "No PHP files staged"
elif [ -f "tools/check-path-boundary.php" ]; then
    PB_OUT=$(echo "$STAGED_PHP" | php tools/check-path-boundary.php --stdin 2>&1 || true)
    PB_FAILS=$(echo "$PB_OUT" | grep -c '^FAIL' || true)
    if [ "$PB_FAILS" -gt 0 ]; then
        err "Unbounded tenant/org path prefix ($PB_FAILS site(s))"
        echo "$PB_OUT" | sed 's/^/       /' | head -30
        echo "       A path prefix must be '/'-terminated AND match the node itself."
        echo "       Use \local_sentientia_platform\tenant::path_descendant_filter()."
        echo "       This defect shipped 4 times and is silent - only the numbers go wrong."
    else
        ok "No unbounded path prefixes in staged PHP"
    fi
else
    warn "tools/check-path-boundary.php missing - path boundaries unchecked"
fi

echo "-> [19/20] Cross-tree plugin twin check..."
# Every local plugin exists twice, in local/ and moodle-enhancement/local/,
# and BOTH are deployed from (deploy_to_uat.sh takes --prefer-top/--prefer-me;
# UAT serves org, analytics, learningpath, compliance_report and courses from
# the ME tree). A change staged in one tree and not the other is invisible
# until the wrong copy is served - structured_logger.php carried a retired
# component prefix in one tree for months exactly that way.
#
# The CI job walks both trees in full. This only checks what is staged, so it
# is fast and catches the mistake while you can still fix it in one commit.
TWIN_STAGED=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null \
    | grep -E '^(local|moodle-enhancement/local)/' || true)
if [ -z "$TWIN_STAGED" ]; then
    ok "No dual-tree plugin files staged"
else
    TWIN_MISMATCH=0
    TWIN_REPORT=""
    for f in $TWIN_STAGED; do
        case "$f" in
            moodle-enhancement/local/*) twin="local/${f#moodle-enhancement/local/}" ;;
            local/*)                    twin="moodle-enhancement/local/${f#local/}" ;;
            *) continue ;;
        esac
        # A file with no twin at all is reported by the CI job against the
        # baseline, not here: creating a genuinely single-tree file is a
        # legitimate thing to do in one commit.
        [ -f "$twin" ] || continue
        # Normalise line endings: the trees genuinely differ in CRLF/LF and
        # that is not drift.
        if ! diff -q <(tr -d '\r' < "$f") <(tr -d '\r' < "$twin") >/dev/null 2>&1; then
            TWIN_MISMATCH=$((TWIN_MISMATCH + 1))
            TWIN_REPORT="${TWIN_REPORT}       $f\n         differs from  $twin\n"
        fi
    done
    if [ "$TWIN_MISMATCH" -gt 0 ]; then
        # Warning, not error: 99 files already differ (see
        # tools/tree-drift-baseline.txt) and some of those divergences are
        # deliberate. The CI gate is what blocks NEW drift.
        warn "Staged file differs from its twin in the other tree ($TWIN_MISMATCH)"
        printf "$TWIN_REPORT" | head -20
        echo "       Apply the change to both trees, or record the divergence:"
        echo "         php tools/check-tree-drift.php --update-baseline"
    else
        ok "Staged plugin files match their twins"
    fi
fi

echo "-> [20/20] SITEID strict-comparison scan..."
# SITEID is defined from $SITE->id, which MySQL/MariaDB return as the STRING '1'.
# '(int) $id !== SITEID' is therefore always true and '$id === SITEID' always
# false: the site-course guard silently does nothing. It shipped five times
# before 0eb31f32f. CI twin: .github/workflows/ci.yml::siteid-compare-check.
STAGED_PHP=$(git diff --cached --name-only --no-renames --diff-filter=ACM 2>/dev/null | grep -E '[.]php$' || true)
if [ -z "$STAGED_PHP" ]; then
    ok "No PHP files staged"
elif [ -f "tools/check-siteid-compare.php" ]; then
    SID_OUT=$(echo "$STAGED_PHP" | php tools/check-siteid-compare.php --stdin 2>&1 || true)
    SID_FAILS=$(echo "$SID_OUT" | grep -c '^FAIL' || true)
    if [ "$SID_FAILS" -gt 0 ]; then
        err "Strict comparison against an un-cast SITEID ($SID_FAILS site(s))"
        echo "$SID_OUT" | sed 's/^/       /' | head -30
        echo "       SITEID is a database string ('1'), so === / !== against an int never matches."
        echo "       Write (int) SITEID, e.g.  (int) \$course->id !== (int) SITEID."
        echo "       This defect shipped 5 times and is silent - the guard just does nothing."
    else
        ok "No strict comparison against an un-cast SITEID in staged PHP"
    fi
else
    warn "tools/check-siteid-compare.php missing - SITEID comparisons unchecked"
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
