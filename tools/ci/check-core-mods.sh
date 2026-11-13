#!/usr/bin/env bash
# check-core-mods.sh - Moodle core file protection for a commit range (CI).
#
#   tools/ci/check-core-mods.sh <base> <head>
#
# MIRRORS .claude/hooks/pre-commit.sh CHECK 6. The two must change together:
# same guarded dirs, same exclusions, same record tokens, same marker rule,
# same JSON/map/CSV, binary, deletion, revert and upstream-sync handling.
#
# The hook can be bypassed (--no-verify, a GUI client, a clone without the
# hook installed). This script runs in .github/workflows/core-mods.yml, which
# a commit cannot skip, but which blocks only on a pull request. A push to
# production has landed before CI starts, so there CI reports after the fact
# and cannot block. The workflow therefore checks a push cumulatively, from a
# pinned baseline to HEAD: a violation keeps production red until it is fixed,
# and the next push does not turn it green.
#
# The rule (root CLAUDE.md section 3): a changed file under
# (public/)?(lib|admin|course|my|mod/assign|mod/quiz)/ -- except
# admin/tool/sentientia_*/, mod/quiz/accessrule/sentientia_*/ and the my/
# overlays dashboard.php, switchrole.php, templates/dropdown.mustache -- is a
# violation unless (1) a record in moodle-enhancement/docs/core-mods/*.md or
# docs/core-mods/*.md (not README.md) names its exact path, and (2) the file
# carries a SENTIENTIA-CORE-MOD marker. JSON, source maps, CSV, binary files
# and deletions need only (1).
#
# Revert: a file whose <base> version has a marker and whose <head> version
# has none is an undone core mod (dropped after an upstream fix, or rolled
# back). It needs only (1); its record stays, with a 'Reverted' note.
#
# Upstream sync: a record line (at the start of a line, in a record, not in
# README.md)
#     Core-Mods-Upstream-Sync: <guarded dir prefix>/ <upstream version>
# for example 'Core-Mods-Upstream-Sync: admin/tool/certificate/ v5.0.3
# (2025041403)', says that the files under the prefix are upstream Moodle or
# vendor content, plus the recorded and marked patches, as of the commit that
# added the line (S). Upstream files carry no marker and a release changes
# hundreds of them, so a changed file under the prefix that is unchanged since
# S needs neither (1) nor (2). The line takes effect only when S is not in the
# history of <base>, i.e. when the sync is part of the range: an older sync
# exempts nothing. A file changed after S follows the normal rules, and does
# not count as a revert. Commit the line with the import or after it, not
# before (S must hold the imported files). In the hook, S is the commit being
# made: a sync line staged with the commit exempts the files under its prefix.
#
# What the hook reads from the index, this script reads from git objects:
#   changed files  git diff --name-only --no-renames --diff-filter=ACDMT <base> <head>
#   records, syncs git grep over the record files in the tree of <head>
#   marker, exists the file in the tree of <head>
#   binary         git diff --numstat <base> <head> (numstat prints '-')
#   revert         the file in the tree of <base>
# So a record or marker added by a later commit in the range covers an earlier
# commit's edit; the working tree is never read.
#
# Known gaps (the hook has the same ones): a record that names a path only in
# passing counts as (1); a marker anywhere in the file counts as (2); a record
# path with a leading '/' reads as a URL and does not count; git quotes a path
# with non-ASCII characters, so such a file in a guarded dir is not matched; a
# git grep setting such as grep.lineNumber would break the token parsing (none
# on CI). A marked patch replaced by a different unmarked edit passes as a
# revert. A sync line cannot prove that the files under its prefix are
# upstream content: a local edit committed with the sync, or earlier in the
# same range, passes. Review the sync commit.
#
# Exit 0 = pass, 1 = at least one violation (one ::error:: annotation per
# file), 2 = usage or git error. Unlike the hook, which reads a failed
# git diff as "no core changes", a git failure here fails the job.
#
# Needs bash, git, grep, sed, sort, cut, awk, tr, wc (ubuntu-latest and Git Bash).

set -euo pipefail

if [ "$#" -ne 2 ]; then
    echo "usage: $0 <base> <head>" >&2
    exit 2
fi

# Workflow-command escaping: % CR LF in data; property values also : and ,
esc_data() { local s=${1//%/%25}; s=${s//$'\r'/%0D}; s=${s//$'\n'/%0A}; printf '%s' "$s"; }
esc_prop() { local s; s=$(esc_data "$1"); s=${s//:/%3A}; s=${s//,/%2C}; printf '%s' "$s"; }
fail2() { echo "::error::check-core-mods: $(esc_data "$1")"; exit 2; }

TOP=$(git rev-parse --show-toplevel 2>/dev/null) || fail2 "not inside a git work tree"
# git grep pathspecs are relative to the current directory; the hook runs at the top.
cd "$TOP"

BASE=$(git rev-parse --verify --quiet "$1^{commit}") || fail2 "base '$1' is not a commit here (fetch-depth 0?)"
HEAD=$(git rev-parse --verify --quiet "$2^{commit}") || fail2 "head '$2' is not a commit here"
echo "core-mods check: $BASE..$HEAD"

CHANGED=$(git diff --name-only --no-renames --diff-filter=ACDMT "$BASE" "$HEAD") \
    || fail2 "git diff --name-only $BASE $HEAD failed"

# Same three filters as CHECK 6, in the same order.
CORE_CHANGED=$(printf '%s\n' "$CHANGED" \
    | grep -E '^(public/)?(lib|admin|course|my|mod/assign|mod/quiz)/' \
    | grep -vE '^(public/)?(admin/tool/sentientia_[^/]+|mod/quiz/accessrule/sentientia_[^/]+)/' \
    | grep -vE '^(public/)?my/(dashboard\.php|switchrole\.php|templates/dropdown\.mustache)$' || true)
if [ -z "$CORE_CHANGED" ]; then
    echo "ok: no core file modifications"
    exit 0
fi
CORE_TOTAL=$(wc -l <<< "$CORE_CHANGED" | tr -d ' ')

RECSPEC=('moodle-enhancement/docs/core-mods/*.md' 'docs/core-mods/*.md' ':(exclude)*README.md')

# Every path-like token in the records at <head>. git grep exits 1 when
# nothing matches (no records), which is not an error. README.md is the
# ledger's guide; its example paths are not records. URL tokens (leading /)
# are dropped; public/ and trailing dots are stripped, as in the hook.
RC=0
RECORD_TOKENS=$(git grep -h -o -E '[A-Za-z0-9_./-]+' "$HEAD" -- "${RECSPEC[@]}") || RC=$?
[ "$RC" -le 1 ] || fail2 "git grep over the core-mods records at $HEAD failed (exit $RC)"
CORE_RECORDED=$(printf '%s\n' "$RECORD_TOKENS" \
    | grep -v '^/' | sed -e 's#^public/##' -e 's#[.]*$##' | sort -u || true)

# Upstream syncs that are part of the range (see the header). Same line
# pattern as the hook; group 2 is the prefix without public/.
SYNC_RE='^Core-Mods-Upstream-Sync:[[:space:]]+(public/)?((lib|admin|course|my|mod/assign|mod/quiz)/([A-Za-z0-9_.-]+/)*)[[:space:]]+[^[:space:]]'
RC=0
SYNC_LINES=$(git grep -h -E '^Core-Mods-Upstream-Sync:' "$HEAD" -- "${RECSPEC[@]}") || RC=$?
[ "$RC" -le 1 ] || fail2 "git grep for Core-Mods-Upstream-Sync lines at $HEAD failed (exit $RC)"
SYNC_LINES=$(printf '%s\n' "$SYNC_LINES" | tr -d '\r' | LC_ALL=C sort -u)
SYNC_COVERED=""   # changed core files under the prefix of a sync in the range
SYNC_EXEMPT=""    # ... that are also unchanged since that sync's commit
SYNC_COUNT=0
while IFS= read -r line; do
    [ -n "$line" ] || continue
    if ! [[ "$line" =~ $SYNC_RE ]]; then
        echo "::warning::$(esc_data "core-mods: ignored a malformed sync line (want 'Core-Mods-Upstream-Sync: <guarded dir prefix>/ <upstream version>'): $line")"
        continue
    fi
    prefix=${BASH_REMATCH[2]}
    # The commit that added the line: the newest one that changed its count.
    sync_commit=$(git log -1 --format=%H -S"$line" "$HEAD" -- "${RECSPEC[@]}") \
        || fail2 "git log -S for the sync line '$line' failed"
    if [ -z "$sync_commit" ]; then
        echo "::warning::$(esc_data "core-mods: no commit found that added the sync line '$line'; it exempts nothing")"
        continue
    fi
    RC=0
    git merge-base --is-ancestor "$sync_commit" "$BASE" || RC=$?
    case "$RC" in
        0) continue ;;  # the sync is older than the range
        1) ;;
        *) fail2 "git merge-base --is-ancestor $sync_commit $BASE failed (exit $RC)" ;;
    esac
    under=$(printf '%s\n' "$CORE_CHANGED" | awk -v p="$prefix" 'index($0, p) == 1 || index($0, "public/" p) == 1')
    [ -n "$under" ] || continue
    since=$(git diff --name-only --no-renames "$sync_commit" "$HEAD" -- "$prefix" "public/$prefix") \
        || fail2 "git diff --name-only $sync_commit $HEAD failed"
    if [ -z "$since" ]; then
        exempt=$under
    else
        exempt=$(grep -vxF -f <(printf '%s\n' "$since") <<< "$under" || true)
    fi
    SYNC_COVERED+=$under$'\n'
    if [ -n "$exempt" ]; then
        SYNC_EXEMPT+=$exempt$'\n'
        n=$(wc -l <<< "$exempt" | tr -d ' ')
        echo "upstream sync in ${sync_commit:0:12} ('$line'): $n file(s) under $prefix unchanged since that commit need no record path or marker"
    fi
done <<< "$SYNC_LINES"
if [ -n "$SYNC_EXEMPT" ]; then
    CORE_CHANGED=$(grep -vxF -f <(printf '%s' "$SYNC_EXEMPT") <<< "$CORE_CHANGED" || true)
    SYNC_COUNT=$(( CORE_TOTAL - $(printf '%s' "$CORE_CHANGED" | grep -c '' || true) ))
fi

CORE_ISSUES=0
CORE_COUNT=0
CORE_REVERTS=0
while IFS= read -r file; do
    [ -n "$file" ] || continue
    CORE_COUNT=$((CORE_COUNT+1))
    # Here-strings, not pipes into grep -q: under pipefail an early grep exit
    # can SIGPIPE the writer and turn a match into a failure (CHECK 6 note).
    if ! grep -qxF "${file#public/}" <<< "$CORE_RECORDED"; then
        echo "::error file=$(esc_prop "$file")::$(esc_data "Moodle core file modification without a core-mods record: $file")"
        CORE_ISSUES=$((CORE_ISSUES+1))
    elif ! [[ "$file" =~ \.(json|map|csv)$ ]] && git cat-file -e "$HEAD:$file" 2>/dev/null \
         && [ "$(git --literal-pathspecs diff --numstat --no-renames "$BASE" "$HEAD" -- "$file" 2>/dev/null | cut -f1)" != "-" ]; then
        # Present at <head>, not deleted, and not binary (numstat prints '-' for binary).
        MARKERS=$(git show "$HEAD:$file" 2>/dev/null | grep -c 'SENTIENTIA-CORE-MOD' || true)
        if [ "${MARKERS:-0}" -eq 0 ]; then
            BASE_MARKERS=$(git show "$BASE:$file" 2>/dev/null | grep -c 'SENTIENTIA-CORE-MOD' || true)
            if [ "${BASE_MARKERS:-0}" -gt 0 ] && ! grep -qxF "$file" <<< "$SYNC_COVERED"; then
                echo "::notice file=$(esc_prop "$file")::$(esc_data "Revert of a recorded core mod (the base version has a SENTIENTIA-CORE-MOD marker, this one has none): $file. Keep its record, with a 'Reverted' note.")"
                CORE_REVERTS=$((CORE_REVERTS+1))
            else
                echo "::error file=$(esc_prop "$file")::$(esc_data "Moodle core file modification without a SENTIENTIA-CORE-MOD marker: $file")"
                CORE_ISSUES=$((CORE_ISSUES+1))
            fi
        fi
    fi
done <<< "$CORE_CHANGED"

DETAIL="$CORE_COUNT file(s) checked"
[ "$CORE_REVERTS" -eq 0 ] || DETAIL+=", $CORE_REVERTS revert(s)"
[ "$SYNC_COUNT" -eq 0 ] || DETAIL+=", $SYNC_COUNT exempt by an upstream sync"
if [ "$CORE_ISSUES" -gt 0 ]; then
    echo "$CORE_ISSUES of $CORE_TOTAL core file(s) fail the core-mods rules ($DETAIL)."
    echo "Each core edit needs a record in moodle-enhancement/docs/core-mods/YYYY-MM-DD-<slug>.md"
    echo "that names the file path and a '// SENTIENTIA-CORE-MOD: <reason>' marker at the change"
    echo "site (root CLAUDE.md section 3; in XML/HTML/SVG use <!-- SENTIENTIA-CORE-MOD: <reason> -->,"
    echo "in YAML/.feature use #). An upstream Moodle or vendor import adds a"
    echo "'Core-Mods-Upstream-Sync: <prefix>/ <version>' line to its record instead (core-mods README)."
    exit 1
fi
echo "ok: core file modifications pass the core-mods record and marker rules ($DETAIL)"
