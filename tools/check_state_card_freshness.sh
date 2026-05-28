#!/usr/bin/env bash
# State-card freshness gate.
#
# Born from F-099 (Stabilization Audit Phase 3, 2026-05-28). The
# stabilization audit found that 8 of 32 plugin state cards under
# moodle-enhancement/state-cards/airpay_*-state.md and
# sentientia_*-state.md hadn't been touched in >90 days, while the
# plugin code had been actively modified. State cards drift into
# fiction when not maintained.
#
# This script runs in two modes:
#
#   --mode=staged   — pre-commit mode. Looks at git-staged plugin
#                     changes and warns if the corresponding state card
#                     is NOT also staged. Soft warning, doesn't block.
#
#   --mode=stale    — weekly cron mode. Looks at the timestamp of every
#                     state card vs the most recent file mtime under
#                     local/<plugin>/. If the plugin was touched in the
#                     last STALE_PLUGIN_DAYS days but the state card
#                     has not been touched in the last STALE_CARD_DAYS
#                     days, the card is stale.
#
# Defaults:
#   STALE_PLUGIN_DAYS = 30    (plugin actively modified)
#   STALE_CARD_DAYS   = 90    (state card untouched)
#
# Usage:
#   tools/check_state_card_freshness.sh                     # default: --mode=stale
#   tools/check_state_card_freshness.sh --mode=staged       # pre-commit
#   tools/check_state_card_freshness.sh --mode=stale --strict   # CI/cron: exit 1 if stale
#
# Configuration via env:
#   WORKSPACE_ROOT       default: parent of this script's dir + moodle-enhancement
#   STALE_PLUGIN_DAYS    default: 30
#   STALE_CARD_DAYS      default: 90

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKSPACE_ROOT="${WORKSPACE_ROOT:-$(cd "$SCRIPT_DIR/.." && pwd)/moodle-enhancement}"
STALE_PLUGIN_DAYS="${STALE_PLUGIN_DAYS:-30}"
STALE_CARD_DAYS="${STALE_CARD_DAYS:-90}"

MODE="stale"
STRICT=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --mode) MODE="$2"; shift ;;
        --mode=*) MODE="${1#--mode=}" ;;
        --strict) STRICT=1 ;;
        --help|-h)
            sed -n '1,40p' "$0" | sed 's/^# \{0,1\}//'
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

CARDS_DIR="$WORKSPACE_ROOT/state-cards"
if [[ ! -d "$CARDS_DIR" ]]; then
    echo "✗ state-cards/ not found at: $CARDS_DIR" >&2
    exit 2
fi

# ────────────────────────────────────────────────────────────────────
# Helper: convert a file mtime to "days since modified".
# ────────────────────────────────────────────────────────────────────
days_since_mtime() {
    local f="$1"
    local now
    local mtime
    now=$(date +%s)
    # GNU stat and BSD stat differ. Try GNU first (Linux + Git Bash).
    if mtime=$(stat -c %Y "$f" 2>/dev/null); then
        :
    elif mtime=$(stat -f %m "$f" 2>/dev/null); then
        :
    else
        echo "stat failed on $f" >&2
        return 1
    fi
    echo $(( (now - mtime) / 86400 ))
}

# ────────────────────────────────────────────────────────────────────
# Mode A: --mode=staged (pre-commit)
# ────────────────────────────────────────────────────────────────────
mode_staged() {
    # Find staged plugin files: anything under
    #   moodle-enhancement/local/airpay_*/*
    #   moodle-enhancement/local/sentientia_*/*
    # for which the corresponding state card is NOT also staged.
    local staged_files
    staged_files=$(git diff --cached --name-only --diff-filter=ACMR 2>/dev/null || true)
    if [[ -z "$staged_files" ]]; then
        echo "ℹ  No staged files."
        exit 0
    fi

    local plugins_touched=()
    while IFS= read -r f; do
        # Strip moodle-enhancement/ prefix if present
        local rel="${f#moodle-enhancement/}"
        # Match local/{airpay,sentientia}_NAME/...
        if [[ "$rel" =~ ^local/((airpay|sentientia)_[a-z_]+)/ ]]; then
            local plugin="${BASH_REMATCH[1]}"
            # Skip the state card itself
            if [[ "$rel" == "state-cards/${plugin}-state.md" ]]; then
                continue
            fi
            # Dedup
            local found=0
            for p in "${plugins_touched[@]:-}"; do
                if [[ "$p" == "$plugin" ]]; then found=1; break; fi
            done
            if [[ $found -eq 0 ]]; then
                plugins_touched+=("$plugin")
            fi
        fi
    done <<< "$staged_files"

    if [[ ${#plugins_touched[@]} -eq 0 ]]; then
        echo "ℹ  No plugin files staged."
        exit 0
    fi

    local missing=()
    for plugin in "${plugins_touched[@]}"; do
        local card_rel="moodle-enhancement/state-cards/${plugin}-state.md"
        # Was the state card also staged?
        if ! echo "$staged_files" | grep -q "^${card_rel}\$"; then
            missing+=("$plugin")
        fi
    done

    if [[ ${#missing[@]} -eq 0 ]]; then
        echo "✅ State cards staged for all touched plugins."
        exit 0
    fi

    echo "⚠  Plugins modified WITHOUT updating their state cards:"
    for p in "${missing[@]}"; do
        echo "      - $p   (state-cards/${p}-state.md not staged)"
    done
    echo ""
    echo "Remediation: edit the state card to reflect what changed,"
    echo "then 'git add moodle-enhancement/state-cards/<plugin>-state.md'."
    echo "If the change genuinely doesn't affect state, add an empty line"
    echo "+ a 1-line 'No state change — see commit X' note + stage that."

    if [[ $STRICT -eq 1 ]]; then exit 1; fi
    exit 0
}

# ────────────────────────────────────────────────────────────────────
# Mode B: --mode=stale (weekly cron)
# ────────────────────────────────────────────────────────────────────
mode_stale() {
    local stale=()
    local checked=0

    for card in "$CARDS_DIR"/airpay_*-state.md "$CARDS_DIR"/sentientia_*-state.md; do
        [[ -f "$card" ]] || continue
        checked=$((checked + 1))

        # Derive plugin name: airpay_courses-state.md → airpay_courses
        local base
        base=$(basename "$card")
        local plugin="${base%-state.md}"
        local plugin_dir="$WORKSPACE_ROOT/local/$plugin"
        if [[ ! -d "$plugin_dir" ]]; then
            # State card with no matching plugin — probably a non-local
            # state card (theme, block) or archived plugin.
            continue
        fi

        # Most recent file mtime under the plugin dir.
        #
        # Perf note: per-file `stat` is ~30ms on Git Bash for Windows
        # (Windows-side fork is expensive). 30 plugins × ~50 files =
        # 1500 stat calls = 45s. We use GNU find's -printf '%T@' instead,
        # which prints all mtimes in a single subprocess. Falls back to
        # per-file stat (capped at 200 files) only on BSD find (macOS).
        #
        # Also note: `\( -name A -o -name B \) -not -path Z` grouping
        # is required — without escaped parens, `-not -path` only binds
        # to the rightmost `-name`, so vendor/ would be walked for
        # .php/.mustache/.scss/.js.
        local newest
        newest=$(find "$plugin_dir" -type f \
            \( -name "*.php" -o -name "*.mustache" -o -name "*.scss" \
               -o -name "*.js" -o -name "*.xml" \) \
            -not -path "*/vendor/*" \
            -not -path "*/node_modules/*" \
            -not -path "*/.git/*" \
            -printf '%T@\n' 2>/dev/null | sort -rn | head -1 | cut -d. -f1)
        if [[ -z "$newest" ]]; then
            # BSD find fallback (macOS) — caps at 200 files for sanity.
            newest=0
            while IFS= read -r f; do
                local f_mtime
                if f_mtime=$(stat -f %m "$f" 2>/dev/null); then
                    (( f_mtime > newest )) && newest=$f_mtime
                fi
            done < <(find "$plugin_dir" -type f \
                \( -name "*.php" -o -name "*.mustache" -o -name "*.scss" \
                   -o -name "*.js" -o -name "*.xml" \) \
                -not -path "*/vendor/*" \
                -not -path "*/node_modules/*" \
                -not -path "*/.git/*" 2>/dev/null | head -200)
        fi

        [[ -z "$newest" || "$newest" -eq 0 ]] && continue

        local now
        now=$(date +%s)
        local plugin_age_days=$(( (now - newest) / 86400 ))

        local card_age_days
        card_age_days=$(days_since_mtime "$card") || continue

        # Stale = plugin touched recently AND card untouched a long time
        if (( plugin_age_days <= STALE_PLUGIN_DAYS && card_age_days > STALE_CARD_DAYS )); then
            stale+=("$plugin|$plugin_age_days|$card_age_days")
        fi
    done

    echo "=== State-card freshness check ==="
    echo "    workspace:       $WORKSPACE_ROOT"
    echo "    plugin recency:  ≤ $STALE_PLUGIN_DAYS days (modified)"
    echo "    card threshold:  > $STALE_CARD_DAYS days (untouched)"
    echo "    cards checked:   $checked"
    echo ""

    if [[ ${#stale[@]} -eq 0 ]]; then
        echo "✅ All plugin state cards are fresh relative to their code."
        exit 0
    fi

    echo "⚠  ${#stale[@]} stale state card(s) — code is moving, doc is not:"
    printf "%-30s %15s %15s\n" "PLUGIN" "PLUGIN_AGE_D" "CARD_AGE_D"
    printf "%-30s %15s %15s\n" "------" "-------------" "-----------"
    for entry in "${stale[@]}"; do
        IFS='|' read -r plugin plugin_age card_age <<< "$entry"
        printf "%-30s %15d %15d\n" "$plugin" "$plugin_age" "$card_age"
    done
    echo ""
    echo "Remediation: open state-cards/<plugin>-state.md, refresh the"
    echo "'Current state' / 'Recent changes' sections, save."

    if [[ $STRICT -eq 1 ]]; then exit 1; fi
    exit 0
}

case "$MODE" in
    staged) mode_staged ;;
    stale)  mode_stale  ;;
    *)
        echo "Unknown --mode: $MODE (use 'staged' or 'stale')" >&2
        exit 2
        ;;
esac
