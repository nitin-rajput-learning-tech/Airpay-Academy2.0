#!/usr/bin/env bash
# resolve-base.sh - the commit a CI gate compares HEAD with.
#
#   tools/ci/resolve-base.sh commit <sha>     that commit (a PR's base.sha, a pinned baseline)
#   tools/ci/resolve-base.sh push <before>    merge-base of <before> (github.event.before) and HEAD
#
# Prints the full sha on stdout; progress and errors go to stderr. Exit 0 =
# resolved, 1 = cannot be resolved (one ::error:: line), 2 = usage.
#
# A commit missing from the clone is fetched by sha first. After a force-push
# no ref holds the old tip, so fetch-depth 0 does not bring it, but GitHub
# serves it by sha until it is garbage-collected (actions/checkout leaves the
# token in the local git config, so the fetch is authenticated).
#
# It never falls back to the merge-base with origin/production. On a push to
# production that ref is the commit just pushed, so the base came out as HEAD
# and a gate compared HEAD with itself and passed without checking anything
# (review 2026-11-13: a force-push took exactly that path, because 'before'
# was no longer in the clone).
#
# push mode returns merge-base(<before>, HEAD), not <before>. On a fast-forward
# push the two are the same commit. After a force-push or a rebase, <before> is
# not an ancestor of HEAD: the merge-base covers every commit the push added
# (also an edit that the old and the new history both contain, which a
# two-point diff from <before> leaves out) and none of the commits it dropped.
# An empty or all-zero <before> (this push created the branch) has no range.
#
# Used by .github/workflows/ci.yml (upgrade-step-versions) and
# .github/workflows/core-mods.yml. Needs bash and git.

set -euo pipefail

usage() { echo "usage: $0 commit <sha> | push <before-sha>" >&2; exit 2; }
fail()  { echo "::error::resolve-base: $1" >&2; exit 1; }

[ "$#" -eq 2 ] || usage
MODE=$1
REF=$2

have() { git cat-file -e "$1^{commit}" 2>/dev/null; }

# Make sure <sha> is in the clone, fetching it by sha when it is not.
ensure() {
    have "$1" && return 0
    if [[ "$1" =~ ^[0-9a-f]{40}([0-9a-f]{24})?$ ]]; then
        echo "resolve-base: $1 is not in the clone; fetching it by sha" >&2
        git fetch -q --no-tags origin "$1" >&2 || true
    fi
    have "$1"
}

case "$MODE" in
    commit)
        [ -n "$REF" ] || fail "no base commit given (empty sha)"
        ensure "$REF" || fail "base commit $REF is not in the clone and could not be fetched"
        git rev-parse --verify "$REF^{commit}"
        ;;
    push)
        if [ -z "${REF//0/}" ]; then
            fail "the push has no 'before' commit ('${REF}': this push created the branch), so the pushed range is unknown. Run the gate by hand against a known-good base."
        fi
        ensure "$REF" || fail "the 'before' commit $REF is not in the clone and could not be fetched, so the pushed range is unknown. Run the gate by hand against a known-good base."
        BASE=$(git merge-base "$REF" HEAD) \
            || fail "the 'before' commit $REF and HEAD share no history, so the pushed range is unknown. Run the gate by hand against a known-good base."
        if [ "$BASE" != "$(git rev-parse --verify "$REF^{commit}")" ]; then
            echo "resolve-base: 'before' $REF is not an ancestor of HEAD (force-push or rebase); base = their merge-base $BASE" >&2
        fi
        echo "$BASE"
        ;;
    *)
        usage
        ;;
esac
