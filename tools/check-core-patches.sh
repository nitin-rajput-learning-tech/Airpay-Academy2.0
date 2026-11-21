#!/usr/bin/env bash
# check-core-patches.sh - versioned Moodle core patches (ADR-044): check them, apply them to a tree, verify a tree.
#
#   tools/check-core-patches.sh check [--ref <git-ref>]
#       Every file in moodle-enhancement/core-patches/MANIFEST is the pinned upstream file plus marked hunks:
#       at least one hunk, every "SENTIENTIA-CORE-MOD:" line closed by an "END SENTIENTIA-CORE-MOD" line, and the
#       file with its hunks removed hashes to the pinned SHA-256. Reads the working tree, or the committed files of
#       <git-ref> (CI passes HEAD).
#
#   tools/check-core-patches.sh apply --target <5.1|5.2|5.3> --tree <dir> [--ref <git-ref>]
#       For each manifest line of that Moodle version: if <dir>/<tree path> is the pinned upstream file, replace it
#       with the patched file (written with LF line ends); if it already is the patched file, leave it. Anything else
#       is refused: that tree does not hold the release the patch was made for. <dir> is the directory that holds
#       public/ (a staged package tree, or C:/xampp/htdocs/moodle5 for the local 5.1 site). Ends with verify-tree.
#
#   tools/check-core-patches.sh verify-tree --target <5.1|5.2|5.3> --tree <dir> [--ref <git-ref>]
#       Each file of that version in <dir> equals the patched file. Changes nothing.
#
# Hashes are taken with CR removed (the working tree may hold CRLF; Moodle ships LF). Exit 0 = pass, 1 = a check
# failed (nothing is written for that file), 2 = usage error. Needs bash, git, awk, tr, sha256sum (Git Bash, ubuntu).
# Record: moodle-enhancement/docs/core-mods/2026-11-20-form-js-dependency-guard.md. Rules: ADR-044.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST_REL="moodle-enhancement/core-patches/MANIFEST"

MODE="${1:-}"; [ $# -gt 0 ] && shift
TARGET=""; TREE=""; REF=""
while [ $# -gt 0 ]; do
    case "$1" in
        --target) shift; TARGET="${1:-}" ;;
        --tree)   shift; TREE="${1:-}" ;;
        --ref)    shift; REF="${1:-}" ;;
        -h|--help) sed -n '2,24p' "$0"; exit 0 ;;
        *) echo "unknown arg: $1 (see --help)" >&2; exit 2 ;;
    esac
    shift
done
case "$MODE" in
    check) ;;
    apply|verify-tree)
        [ -n "$TARGET" ] && [ -n "$TREE" ] || { echo "$MODE needs --target and --tree (see --help)" >&2; exit 2; }
        [ -d "$TREE/public" ] || { echo "--tree $TREE holds no public/ directory (pass the dir that holds public/)" >&2; exit 2; } ;;
    *) echo "usage: $0 check|apply|verify-tree [options] (see --help)" >&2; exit 2 ;;
esac

FAILS=0
fail() { echo "FAIL: $1" >&2; FAILS=$((FAILS + 1)); }
ok()   { echo "  ok   $1"; }
sha()  { sha256sum | cut -d' ' -f1; }

# Content of a repository file, CR removed: the committed blob of --ref, else the working-tree file.
repo_file() {
    if [ -n "$REF" ]; then
        git -C "$REPO" show "$REF:$1" | tr -d '\r'
    else
        tr -d '\r' < "$REPO/$1"
    fi
}
# Hunks removed: from a line with "SENTIENTIA-CORE-MOD:" to the next line with "END SENTIENTIA-CORE-MOD", inclusive.
# Exit 3 when the markers do not pair up.
strip_hunks() {
    awk '
        /END SENTIENTIA-CORE-MOD/ { if (!inhunk) bad = 1; inhunk = 0; next }
        /SENTIENTIA-CORE-MOD:/    { if (inhunk) bad = 1; inhunk = 1; next }
        !inhunk { print }
        END { if (inhunk || bad) exit 3 }'
}

if [ -n "$REF" ]; then
    git -C "$REPO" rev-parse --verify --quiet "$REF^{commit}" >/dev/null || { echo "--ref $REF is not a commit" >&2; exit 2; }
fi
MANIFEST="$(repo_file "$MANIFEST_REL")" || { echo "cannot read $MANIFEST_REL${REF:+ at $REF}" >&2; exit 2; }

MATCHED=0
while read -r targets repopath treepath pinned record _rest; do
    case "$targets" in ''|'#'*) continue ;; esac
    if [ -z "$record" ] || ! [[ "$pinned" =~ ^[0-9a-f]{64}$ ]]; then
        fail "malformed manifest line: $targets $repopath $treepath $pinned $record"
        continue
    fi
    if [ "$MODE" != check ]; then
        case ",$targets," in *",$TARGET,"*) ;; *) continue ;; esac
    fi
    MATCHED=$((MATCHED + 1))
    tmp="$(mktemp)"
    if ! repo_file "$repopath" > "$tmp" 2>/dev/null || [ ! -s "$tmp" ]; then
        fail "$repopath: cannot read it${REF:+ at $REF}"
        rm -f "$tmp"; continue
    fi
    patched="$(sha < "$tmp")"
    hunks="$(grep -c 'SENTIENTIA-CORE-MOD:' "$tmp" || true)"
    rc=0; stripped="$(strip_hunks < "$tmp" | sha)" || rc=$?
    # Under pipefail the pipeline reports awk's exit 3 (unpaired markers).
    if [ "$hunks" -lt 1 ]; then
        fail "$repopath: no SENTIENTIA-CORE-MOD hunk (the manifest lists a patched file)"
    elif [ "$rc" != 0 ]; then
        fail "$repopath: a SENTIENTIA-CORE-MOD line is not closed by END SENTIENTIA-CORE-MOD (or an END has no opening line)"
    elif [ "$stripped" != "$pinned" ]; then
        fail "$repopath: with its $hunks hunk(s) removed it is not the pinned upstream file (sha256 $stripped, pinned $pinned). Hunks may only add lines, and nothing outside them may differ from upstream."
    elif [ "$MODE" = check ]; then
        ok "$repopath = upstream ${pinned:0:12} + $hunks hunk(s) [$targets] ($record)"
    else
        dest="$TREE/$treepath"
        if [ ! -f "$dest" ]; then
            fail "$dest: missing (not a Moodle $TARGET tree?)"
        else
            current="$(tr -d '\r' < "$dest" | sha)"
            if [ "$MODE" = apply ] && [ "$current" = "$pinned" ]; then
                cp "$tmp" "$dest"
                current="$(tr -d '\r' < "$dest" | sha)"
                [ "$current" = "$patched" ] && ok "applied: $treepath (upstream ${pinned:0:12} -> patched ${patched:0:12}, $hunks hunk(s); $record)" \
                                            || fail "$dest: written, but it does not read back as the patched file"
            elif [ "$current" = "$patched" ]; then
                ok "$treepath carries the patch (${patched:0:12}, $hunks hunk(s); $record)"
            elif [ "$current" = "$pinned" ]; then
                fail "$dest: still the upstream file, the patch is NOT applied (run: $0 apply --target $TARGET --tree <dir>)"
            else
                fail "$dest: neither the pinned upstream file (${pinned:0:12}) nor the patched file (${patched:0:12}); it is ${current:0:12}. A different Moodle release or a local edit: compare it with upstream, re-apply the hunk to the new upstream file and update $MANIFEST_REL. Nothing was written."
            fi
        fi
    fi
    rm -f "$tmp"
done <<< "$MANIFEST"

if [ "$MODE" != check ] && [ "$MATCHED" = 0 ]; then
    echo "  ok   no core patch is listed for Moodle $TARGET"
fi
if [ "$FAILS" != 0 ]; then
    echo "core patches: $FAILS failure(s)" >&2
    exit 1
fi
echo "core patches: $MODE passed ($MATCHED file(s))"
