#!/usr/bin/env bash
# build-5.2-standalone.sh - DEPRECATED thin wrapper (2026-10-08, ADR-033).
#
# The Moodle 5.2 package is now built by the parameterised recipe, from a git export of the repository
# instead of from the local 5.1 XAMPP webroot:
#
#   tools/packaging/build-standalone.sh --target 5.2 [--date YYYY-MM-DD] ...
#
# This wrapper keeps the old command line working while the 5.2 UAT instance is still in service. The two old
# stage switches no longer exist (nothing is synced into a webroot any more), so they are accepted and ignored.
# Read build-standalone.sh for the recipe, the verification gates and the options; the 5.2 base tree defaults
# to C:\xampp\htdocs\moodle5.2 (override with --base). That tree is an already-overlaid staging copy, not a
# vanilla extract: the build copies it, re-overlays from git and fails if a forbidden file is in it.
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
args=()
for a in "$@"; do
    case "$a" in
        --skip-sync|--skip-overlay) echo "build-5.2-standalone.sh: $a is obsolete and ignored (the build now exports from git)" >&2 ;;
        *) args+=("$a") ;;
    esac
done
exec bash "$here/build-standalone.sh" --target 5.2 ${args[@]+"${args[@]}"}
