#!/usr/bin/env bash
# Post-deploy verification — runs after the cutover window closes.
#
# Counterpart to pre_deploy_validate.sh. Where pre-deploy answers
# "is this codebase safe to deploy?", post-deploy answers "is the
# deployed instance functioning?". Aggregates every Airpay-owned
# diagnostic CLI into one pass/fail report.
#
# Day-2 (2026-05-14) — wires up:
#   diagnose_admin_ux.php       (Sprint A — Learning Path admin UX)
#   cert_emails_report.php      (Sprint B — recent cert deliveries)
#   manage_shares.php --list    (Sprint C — active share table state)
#   cron_health.php             (cron daemon liveness)
#   Block presence: cron_health + cert_health on /my/ system page
#
# Usage:
#   bash moodle-enhancement/deploy/post_deploy_verify.sh
#
#   --user=<email>     Diagnose the specified admin account too
#   --moodle-root=PATH Override deployed Moodle docroot
#   --json             Machine-readable output for CI integration
#
# Exit codes:
#   0  every check green
#   1  one or more checks red
#   2  invalid arguments
#
# Owner: IT / Head of L&D.

set -u

# ── argument parsing ──────────────────────────────────────────────────
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MOODLE_ROOT="${MOODLE_ROOT:-C:/xampp/htdocs/moodle5/public}"
TARGET_USER=""
EMIT_JSON=0

while [ $# -gt 0 ]; do
    case "$1" in
        --user=*)
            TARGET_USER="${1#--user=}"; shift ;;
        --user)
            TARGET_USER="$2"; shift 2 ;;
        --moodle-root=*)
            MOODLE_ROOT="${1#--moodle-root=}"; shift ;;
        --moodle-root)
            MOODLE_ROOT="$2"; shift 2 ;;
        --json)
            EMIT_JSON=1; shift ;;
        -h|--help)
            sed -n '2,30p' "$0"; exit 0 ;;
        *)
            echo "Unknown argument: $1" >&2
            echo "Use --help to see options." >&2
            exit 2 ;;
    esac
done

# ── helpers ───────────────────────────────────────────────────────────
PASSED=()
FAILED=()
WARNED=()
TS() { date -u '+%Y-%m-%dT%H:%M:%SZ'; }

pass() { PASSED+=("$1"); [ "$EMIT_JSON" -eq 0 ] && echo "  $(TS) PASS  $1"; }
fail() {
    FAILED+=("$1")
    [ "$EMIT_JSON" -eq 0 ] && {
        echo "  $(TS) FAIL  $1"
        [ -n "${2:-}" ] && echo "             $2"
    }
}
warn() {
    WARNED+=("$1")
    [ "$EMIT_JSON" -eq 0 ] && {
        echo "  $(TS) WARN  $1"
        [ -n "${2:-}" ] && echo "             $2"
    }
}
heading() {
    [ "$EMIT_JSON" -eq 1 ] && return 0
    echo
    echo "================================================================"
    echo "  $1"
    echo "================================================================"
}

# ── Gate 1: Sprint A — Learning Path admin UX ─────────────────────────
heading "Gate 1 — Learning Path admin UX (Sprint A diagnostic)"
LP_CLI="$MOODLE_ROOT/local/airpay_learningpath/cli/diagnose_admin_ux.php"
if [ -f "$LP_CLI" ]; then
    if php "$LP_CLI" > /tmp/postdeploy_lp.out 2>&1; then
        pass "Learning Path admin UX (7 checks)"
    else
        fail "Learning Path admin UX" "one or more checks failed — see /tmp/postdeploy_lp.out"
        [ "$EMIT_JSON" -eq 0 ] && grep -E "FAIL|FIX" /tmp/postdeploy_lp.out | head -10
    fi
else
    fail "Learning Path admin UX" "diagnostic CLI not deployed at $LP_CLI"
fi

# Optional: diagnose the named user.
if [ -n "$TARGET_USER" ] && [ -f "$LP_CLI" ]; then
    if php "$LP_CLI" --user="$TARGET_USER" > /tmp/postdeploy_lp_user.out 2>&1; then
        pass "Learning Path admin UX for $TARGET_USER"
    else
        fail "Learning Path admin UX for $TARGET_USER" \
             "see /tmp/postdeploy_lp_user.out"
    fi
fi

# ── Gate 2: Sprint B — certificate-email pipeline alive ──────────────
heading "Gate 2 — Cert email delivery (Sprint B audit CLI)"
CERT_CLI="$MOODLE_ROOT/local/airpay_emails/cli/cert_emails_report.php"
if [ -f "$CERT_CLI" ]; then
    # The CLI exits 0 even when no cert emails have been sent — that's
    # the "fresh deploy" state. We don't fail the gate on zero rows.
    # We DO fail if the CLI itself errors.
    if php "$CERT_CLI" --since=2026-01-01 > /tmp/postdeploy_cert.out 2>&1; then
        # Count failed sends in the trailing summary.
        FAILED_COUNT=$(grep -oE 'Failed: [0-9]+' /tmp/postdeploy_cert.out \
            | head -1 | grep -oE '[0-9]+' || echo 0)
        if [ "${FAILED_COUNT:-0}" -gt 0 ]; then
            warn "Cert email pipeline" "$FAILED_COUNT failed send(s) in audit log"
        else
            pass "Cert email pipeline (no failures in audit log)"
        fi
    else
        fail "Cert email pipeline" "CLI errored — see /tmp/postdeploy_cert.out"
    fi
else
    fail "Cert email pipeline" "audit CLI not deployed at $CERT_CLI"
fi

# ── Gate 3: Sprint C — share table queryable ─────────────────────────
heading "Gate 3 — Course-share data layer (Sprint C ops CLI)"
SHARE_CLI="$MOODLE_ROOT/local/airpay_courses/cli/manage_shares.php"
if [ -f "$SHARE_CLI" ]; then
    if php "$SHARE_CLI" --list > /tmp/postdeploy_share.out 2>&1; then
        pass "Course-share data layer (table queryable)"
    else
        fail "Course-share data layer" \
             "manage_shares --list failed — see /tmp/postdeploy_share.out"
    fi
else
    fail "Course-share data layer" "ops CLI not deployed at $SHARE_CLI"
fi

# ── Gate 4: cron daemon liveness ──────────────────────────────────────
heading "Gate 4 — Moodle cron daemon liveness"
CRON_CLI="$MOODLE_ROOT/local/airpay_core/cli/cron_health.php"
if [ -f "$CRON_CLI" ]; then
    if php "$CRON_CLI" > /tmp/postdeploy_cron.out 2>&1; then
        pass "Cron daemon (no stuck Airpay tasks)"
    else
        # Cron-health returns non-zero when tasks are stuck. On a fresh
        # deploy that's almost guaranteed (cron hasn't run yet). Warn,
        # not fail.
        warn "Cron daemon" \
             "stuck tasks reported — expected on a brand-new deploy; "\
"re-run after the cron daemon has had one cycle"
    fi
else
    fail "Cron daemon CLI" "not deployed at $CRON_CLI"
fi

# ── Gate 5: Sprint B/C dashboard widgets installed ───────────────────
heading "Gate 5 — Dashboard widgets installed"
for block_dir in airpay_cron_health airpay_cert_health; do
    if [ -d "$MOODLE_ROOT/blocks/$block_dir" ] && \
       [ -f "$MOODLE_ROOT/blocks/$block_dir/block_$block_dir.php" ]; then
        pass "block_$block_dir on disk"
    else
        fail "block_$block_dir not deployed" \
             "expected $MOODLE_ROOT/blocks/$block_dir/"
    fi
done

# Each block is site-admin-only and silently hides for everyone else,
# so admin must manually drop them onto their /my/ — we don't auto-fail
# if instances are missing, just warn so the admin notices.
if command -v mysql > /dev/null 2>&1 && [ -n "${DB_HOST:-}" ]; then
    # Optional: detect if instances exist. Requires DB env vars,
    # skip silently when not present.
    INSTANCES=$(mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" \
        "${DB_NAME}" -sN -e \
        "SELECT COUNT(*) FROM mdl_block_instances WHERE blockname IN \
         ('airpay_cron_health','airpay_cert_health')" 2>/dev/null || echo "")
    if [ -n "$INSTANCES" ] && [ "$INSTANCES" -eq 0 ]; then
        warn "Dashboard block instances" \
             "no instances configured — add via /my/ → Customise this page"
    fi
fi

# ── Summary ──────────────────────────────────────────────────────────
if [ "$EMIT_JSON" -eq 1 ]; then
    # JSON output for CI integration.
    printf '{\n'
    printf '  "when": "%s",\n' "$(TS)"
    printf '  "passed": %d,\n' "${#PASSED[@]}"
    printf '  "warned": %d,\n' "${#WARNED[@]}"
    printf '  "failed": %d,\n' "${#FAILED[@]}"
    printf '  "checks": {\n'
    printf '    "pass": ['
    for i in "${!PASSED[@]}"; do
        [ "$i" -gt 0 ] && printf ', '
        printf '"%s"' "${PASSED[$i]}"
    done
    printf '],\n'
    printf '    "warn": ['
    for i in "${!WARNED[@]}"; do
        [ "$i" -gt 0 ] && printf ', '
        printf '"%s"' "${WARNED[$i]}"
    done
    printf '],\n'
    printf '    "fail": ['
    for i in "${!FAILED[@]}"; do
        [ "$i" -gt 0 ] && printf ', '
        printf '"%s"' "${FAILED[$i]}"
    done
    printf ']\n'
    printf '  }\n'
    printf '}\n'
else
    echo
    echo "================================================================"
    echo "  Post-deploy verification summary — $(TS)"
    echo "================================================================"
    echo "  Passed: ${#PASSED[@]}"
    echo "  Warned: ${#WARNED[@]}"
    echo "  Failed: ${#FAILED[@]}"
    echo
    if [ "${#FAILED[@]}" -eq 0 ]; then
        echo "  POST-DEPLOY VERIFICATION COMPLETE — instance is healthy."
        if [ "${#WARNED[@]}" -gt 0 ]; then
            echo "  ${#WARNED[@]} warning(s) — review but not blocking."
        fi
    else
        echo "  ${#FAILED[@]} check(s) failed — fix before signing off."
        echo
        echo "  Failed gates:"
        for g in "${FAILED[@]}"; do
            echo "    - $g"
        done
    fi
fi

exit $([ "${#FAILED[@]}" -eq 0 ] && echo 0 || echo 1)
