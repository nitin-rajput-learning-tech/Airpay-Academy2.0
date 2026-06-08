#!/usr/bin/env bash
# Workspace ↔ deployed drift gate.
#
# Born from F-095 (Stabilization Audit Phase 1, 2026-05-28). Two
# plugins (airpay_pages, airpay_lifecycle) had files in deployed
# xampp that were missing from the git workspace — making the
# DEPLOYMENT-RUNBOOK reproduce a broken plugin if followed.
#
# This script compares the git workspace to the deployed copy and
# reports any deployed-only files. Designed to:
#   1. Run locally pre-commit as a soft warning
#   2. Run in CI as a hard gate (--strict)
#   3. Run on-demand by anyone before a release deploy
#
# Usage:
#   tools/check_workspace_sync.sh                 # report
#   tools/check_workspace_sync.sh --strict        # exit 1 on drift
#   tools/check_workspace_sync.sh --component theme/sentientia  # limit scope
#
# Configuration via env (or override on cmdline):
#   WORKSPACE_ROOT   default: parent of this script's dir
#   DEPLOYED_ROOT    default: /c/xampp/htdocs/moodle5/public  (mingw path)
#                    or:      C:\xampp\htdocs\moodle5\public  (Windows)

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKSPACE_ROOT="${WORKSPACE_ROOT:-$(cd "$SCRIPT_DIR/.." && pwd)/moodle-enhancement}"
DEPLOYED_ROOT="${DEPLOYED_ROOT:-/c/xampp/htdocs/moodle5/public}"

STRICT=0
COMPONENT=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --strict) STRICT=1 ;;
        --component) COMPONENT="$2"; shift ;;
        --help|-h)
            sed -n '1,30p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) echo "Unknown arg: $1" >&2; exit 2 ;;
    esac
    shift
done

if [[ ! -d "$WORKSPACE_ROOT" ]]; then
    echo "✗ WORKSPACE_ROOT not found: $WORKSPACE_ROOT" >&2
    exit 2
fi
if [[ ! -d "$DEPLOYED_ROOT" ]]; then
    echo "⚠  DEPLOYED_ROOT not found: $DEPLOYED_ROOT" >&2
    echo "   (CI will skip this check — no local xampp available)"
    exit 0
fi

# Which components do we track? Anything under moodle-enhancement/ that
# has a corresponding folder in deployed. We don't enumerate every
# Moodle core file (~50k files) — only files we own.
if [[ -n "$COMPONENT" ]]; then
    COMPONENTS=("$COMPONENT")
else
    COMPONENTS=(
        "theme/sentientia"
    )
    # Note: blocks/learnerscript is vendored 3rd-party — we override ONE
    # file (classes/observer.php for the CLI guard, see F-077). The other
    # 883 files are upstream. Excluded from this check; if we ever add
    # more overrides, list them explicitly via the targeted-files approach.
    # Auto-discover local/sentientia_* + local/sentientia_* dirs.
    for d in "$WORKSPACE_ROOT/local"/{airpay,sentientia}_*; do
        [[ -d "$d" ]] || continue
        rel="${d#$WORKSPACE_ROOT/}"
        COMPONENTS+=("$rel")
    done
    # Auto-discover block_* and other workspace dirs.
    for d in "$WORKSPACE_ROOT/blocks"/airpay_* "$WORKSPACE_ROOT/blocks"/sentientia_*; do
        [[ -d "$d" ]] || continue
        rel="${d#$WORKSPACE_ROOT/}"
        COMPONENTS+=("$rel")
    done
fi

drift_count=0
drift_components=()

echo "=== Workspace ↔ deployed drift check ==="
echo "    workspace:  $WORKSPACE_ROOT"
echo "    deployed:   $DEPLOYED_ROOT"
echo "    components: ${#COMPONENTS[@]}"
echo ""

for comp in "${COMPONENTS[@]}"; do
    ws_dir="$WORKSPACE_ROOT/$comp"
    dp_dir="$DEPLOYED_ROOT/$comp"
    if [[ ! -d "$ws_dir" ]]; then
        continue  # No workspace mirror — skip (e.g. theme/epsilon)
    fi
    if [[ ! -d "$dp_dir" ]]; then
        echo "  ⚠  $comp: in workspace but not deployed"
        continue
    fi

    # Find files in deployed but not in workspace.
    #
    # Skip patterns:
    #   - *_BACKUP*, *MONOLITH* — local-only backup files
    #   - .git/, Claude/, .vscode/ — tool / IDE artifacts
    #   - node_modules/, vendor/ — dependency directories
    #   - *.min.js.map — auto-built sourcemaps (sometimes git-ignored)
    #   - *.log, *.tmp — transient files
    deployed_only=()
    while IFS= read -r f; do
        rel="${f#$dp_dir/}"
        # Skip noise patterns
        case "$rel" in
            *_BACKUP*|*MONOLITH*|*Claude*|*.vscode*|*node_modules*|*vendor/*|*.log|*.tmp)
                continue ;;
            # Botched-deploy garbage: blocks/airpay_trainer has a stray
            # nested airpay_trainer/ subfolder on xampp. Moodle's plugin
            # loader doesn't recurse, so the nested files are unloadable
            # dead weight. Cleanup is a one-off rm -rf on xampp (gated
            # by user [CONFIRM]); the gate skips them so it doesn't
            # blow up CI in the meantime.
            airpay_trainer/*) continue ;;
        esac
        if [[ ! -f "$ws_dir/$rel" ]]; then
            deployed_only+=("$rel")
        fi
    done < <(find "$dp_dir" -type f -not -path "*/.git/*" -not -path "*/Claude/*" 2>/dev/null)

    if [[ ${#deployed_only[@]} -gt 0 ]]; then
        drift_count=$((drift_count + ${#deployed_only[@]}))
        drift_components+=("$comp")
        echo "  ✗ $comp: ${#deployed_only[@]} deployed-only files"
        # Show first 5
        for f in "${deployed_only[@]:0:5}"; do
            echo "      DEPLOYED_ONLY: $f"
        done
        if [[ ${#deployed_only[@]} -gt 5 ]]; then
            echo "      ... (${#deployed_only[@]} total — back-port with cp -r)"
        fi
    fi
done

echo ""
if [[ $drift_count -eq 0 ]]; then
    echo "✅ No drift detected."
    exit 0
fi

echo "✗ Drift: $drift_count file(s) across ${#drift_components[@]} component(s)."
echo ""
echo "Remediation: back-port deployed → workspace, e.g."
echo "    cp -r \$DEPLOYED_ROOT/<component>/<path> \$WORKSPACE_ROOT/<component>/<path>"
echo "    git add moodle-enhancement/<component>/<path>"
echo ""
echo "Why this matters: DEPLOYMENT-RUNBOOK §1-§4 instructs IT to deploy"
echo "from the workspace ('Source: theme/sentientia/' etc.). Drift means"
echo "a fresh deploy ships a broken plugin (missing version.php / lang /"
echo "runtime classes). See audit doc F-091, F-092 for the case study."

if [[ $STRICT -eq 1 ]]; then
    exit 1
fi
exit 0
