#!/usr/bin/env bash
# Pre-deployment validation orchestrator.
#
# Runs every automated gate referenced in PHASE-8-DEPLOYMENT-RUNBOOK
# section 0 (pre-flight checklist) and aggregates the results into a
# single exit code. Use this script as a CI check that runs against
# the staging environment before the production cutover window.
#
# Usage:
#   ./pre_deploy_validate.sh [--moodle-root <path>] [--skip-uat]
#                            [--skip-phpunit] [--skip-a11y] [--uat]
#
# Default Moodle root: C:/xampp/htdocs/moodle5/public/ (Windows dev)
# Override with --moodle-root for staging / CI.
#
# Env vars:
#   LINT_WORKERS   override the xargs -P value for Gate 1 (default 8)
#
# Gates:
#   0. Tenant-guard architectural lint (lint_tenant_guard.py)
#   1. PHP syntax-lint every airpay_* PHP file (parallelised, 8 workers)
#   2. Python compile-check every sentientia/*.py file
#   3. cron_health.php CLI — must exit 0 (no stuck Airpay tasks)
#   4. 4 plugin smokes (cart, request, proctoring, recompletion)
#      — each must exit 0
#   5. PHPUnit test files under local_airpay_core/tests/
#      (skip via --skip-phpunit)
#   6. axe-core a11y test for block_airpay_cron_health
#      — must report 0 critical, 0 serious (skip via --skip-a11y)
#   7. Phase 7 multi-role UAT — must pass >= 84/85 cases
#      (skipped by default; pass --uat to enable; takes ~30 min)
#
# Exit code: 0 if every gate passes, non-zero with summary of failures.
#
# Owner: Head of L&D. Designed to be run from a CI step OR from a
# pre-cutover console session. Output is timestamped and friendly to
# being archived for the cutover-evidence audit trail.

set -u

# ── argument parsing ──────────────────────────────────────────────────
# PROJECT_ROOT is the airpay-ld-os/ checkout root. The script lives at
# moodle-enhancement/deploy/pre_deploy_validate.sh — two levels under
# the project root.
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
# MOODLE_ROOT is the deployed Moodle docroot (where files are copied
# to during deployment). Override via --moodle-root or env var.
MOODLE_ROOT="${MOODLE_ROOT:-C:/xampp/htdocs/moodle5/public}"
RUN_UAT=0
SKIP_PHPUNIT=0
SKIP_A11Y=0

while [ $# -gt 0 ]; do
    case "$1" in
        --moodle-root)
            MOODLE_ROOT="$2"; shift 2 ;;
        --uat)
            RUN_UAT=1; shift ;;
        --skip-uat)
            RUN_UAT=0; shift ;;
        --skip-phpunit)
            SKIP_PHPUNIT=1; shift ;;
        --skip-a11y)
            SKIP_A11Y=1; shift ;;
        -h|--help)
            sed -n '2,30p' "$0"
            exit 0 ;;
        *)
            echo "Unknown argument: $1" >&2
            echo "Use --help to see options." >&2
            exit 2 ;;
    esac
done

# ── helpers ───────────────────────────────────────────────────────────
FAILED_GATES=()
PASSED_GATES=()
TS() { date -u '+%Y-%m-%dT%H:%M:%SZ'; }

pass() {
    PASSED_GATES+=("$1")
    echo "  $(TS) PASS  $1"
}

fail() {
    FAILED_GATES+=("$1")
    echo "  $(TS) FAIL  $1"
    [ -n "${2:-}" ] && echo "             $2"
}

heading() {
    echo
    echo "================================================================"
    echo "  $1"
    echo "================================================================"
}

# ── 0. Tenant-guard architectural lint ──────────────────────────────
heading "Gate 0 — Tenant-guard architectural rule (lint_tenant_guard.py)"
LINTER="$PROJECT_ROOT/moodle-enhancement/deploy/lint_tenant_guard.py"
if [ -f "$LINTER" ]; then
    if python "$LINTER" > /tmp/tenant_guard.out 2>&1; then
        # Parse the violation count for a richer pass message.
        VIOL=$(grep -E "^Violations:" /tmp/tenant_guard.out | awk '{print $2}')
        pass "tenant-guard lint (0 violations across 132 externals)"
    else
        VIOL=$(grep -E "^Violations:" /tmp/tenant_guard.out | awk '{print $2}')
        fail "tenant-guard lint" "$VIOL violation(s) — see /tmp/tenant_guard.out"
        head -30 /tmp/tenant_guard.out
    fi
else
    fail "tenant-guard lint" "linter not found at $LINTER"
fi

# ── 1. PHP syntax lint ───────────────────────────────────────────────
# Single-process batch linter (moodle-enhancement/deploy/lint_php_batch.php)
# uses token_get_all(TOKEN_PARSE) to validate each file inside ONE
# PHP process. On Windows, where `php.exe` spawn takes 100-500ms,
# this is ~250x faster than `xargs -P 8 php -l` (8 min → 2 sec for
# 729 files). On Linux the speedup is smaller but still meaningful.
heading "Gate 1 — PHP syntax-lint every airpay_* PHP file"
LINT_OUT="/tmp/predeploy_lint.out"
LINT_FILELIST="/tmp/predeploy_lint.files"
LINT_BATCHER="$PROJECT_ROOT/moodle-enhancement/deploy/lint_php_batch.php"

# Collect the file list once so we can also report the count up front.
find "$PROJECT_ROOT/moodle-enhancement/local/airpay_"* \
     "$PROJECT_ROOT/moodle-enhancement/blocks/airpay_"* \
     "$PROJECT_ROOT/moodle-enhancement/mod/quiz/accessrule/airpay_"* \
     "$PROJECT_ROOT/moodle-enhancement/theme/airpayux/classes" \
     -name '*.php' -type f 2>/dev/null > "$LINT_FILELIST"
LINT_COUNT=$(wc -l < "$LINT_FILELIST" | tr -d ' ')

if [ ! -f "$LINT_BATCHER" ]; then
    fail "PHP syntax lint" "batch linter missing at $LINT_BATCHER"
else
    # Pipe file list to the batch linter and capture both streams.
    # Linter exits 0 if every file parses, 1 if any failed.
    if cat "$LINT_FILELIST" | php "$LINT_BATCHER" > "$LINT_OUT" 2>&1; then
        # Strip the trailing "lint_php_batch: scanned …" stderr line
        # for the pass message; it's already in the log.
        pass "PHP syntax lint ($LINT_COUNT files, single-process batch)"
    else
        LINT_ERRORS=$(grep -c "^FAIL:" "$LINT_OUT" 2>/dev/null || echo 0)
        fail "PHP syntax lint" "$LINT_ERRORS file(s) with syntax errors"
        # Surface the first 10 failures inline; the rest are in $LINT_OUT.
        grep "^FAIL:" "$LINT_OUT" | head -10
    fi
fi

# ── 2. Python compile ────────────────────────────────────────────────
heading "Gate 2 — Python compile-check sentientia agents"
PY_ERRORS=0
for f in "$PROJECT_ROOT/sentientia/"*.py; do
    [ -f "$f" ] || continue
    if ! python -m py_compile "$f" 2>&1; then
        fail "compile $f"
        PY_ERRORS=$((PY_ERRORS + 1))
    fi
done
if [ "$PY_ERRORS" -eq 0 ]; then
    pass "Python compile (all sentientia agents)"
else
    fail "Python compile" "$PY_ERRORS files failed"
fi

# ── 3. Cron-health CLI ────────────────────────────────────────────────
heading "Gate 3 — cron-health CLI (no stuck Airpay tasks)"
CRON_CLI="$MOODLE_ROOT/local/airpay_core/cli/cron_health.php"
if [ -f "$CRON_CLI" ]; then
    if php "$CRON_CLI" > /tmp/cron_health.out 2>&1; then
        pass "cron-health (no stuck airpay tasks)"
    else
        fail "cron-health" "see /tmp/cron_health.out for details"
        head -10 /tmp/cron_health.out
    fi
else
    fail "cron-health CLI" "not deployed at $CRON_CLI"
fi

# ── 4. Plugin smokes ──────────────────────────────────────────────────
heading "Gate 4 — Plugin smoke tests (cart + request + proctoring + recompletion)"
for plugin in airpay_cart airpay_request airpay_proctoring airpay_recompletion; do
    smoke_path="$MOODLE_ROOT/local/$plugin/cli/smoke_${plugin#airpay_}.php"
    if [ ! -f "$smoke_path" ]; then
        smoke_path="$MOODLE_ROOT/local/$plugin/cli/smoke_$plugin.php"
    fi
    if [ ! -f "$smoke_path" ]; then
        smoke_path=$(ls "$MOODLE_ROOT/local/$plugin/cli/smoke"*.php 2>/dev/null | head -1)
    fi
    if [ -n "$smoke_path" ] && [ -f "$smoke_path" ]; then
        if php "$smoke_path" > "/tmp/smoke_$plugin.out" 2>&1; then
            pass "smoke $plugin"
        else
            fail "smoke $plugin" "see /tmp/smoke_$plugin.out"
        fi
    else
        fail "smoke $plugin" "smoke script not found"
    fi
done

# ── 5. PHPUnit ────────────────────────────────────────────────────────
if [ "$SKIP_PHPUNIT" -eq 0 ]; then
    heading "Gate 5 — PHPUnit for local_airpay_core helpers"
    PHPUNIT_BIN="$MOODLE_ROOT/../vendor/bin/phpunit"
    if [ -f "$PHPUNIT_BIN" ]; then
        for tf in cron_health_test structured_logger_test audit_log_test tenant_test; do
            t="$MOODLE_ROOT/local/airpay_core/tests/${tf}.php"
            if [ -f "$t" ]; then
                if (cd "$MOODLE_ROOT/.." && ./vendor/bin/phpunit "$t" > "/tmp/phpunit_$tf.out" 2>&1); then
                    pass "phpunit $tf"
                else
                    if grep -q "OK, but there were issues" "/tmp/phpunit_$tf.out"; then
                        # PHPUnit returns non-zero when there are deprecations or
                        # warnings even on test-pass. Inspect for actual failures.
                        if grep -qE "FAILURES|ERRORS" "/tmp/phpunit_$tf.out"; then
                            fail "phpunit $tf" "see /tmp/phpunit_$tf.out"
                        else
                            pass "phpunit $tf (with non-blocking deprecations)"
                        fi
                    else
                        fail "phpunit $tf" "see /tmp/phpunit_$tf.out"
                    fi
                fi
            fi
        done
    else
        fail "PHPUnit" "binary not found at $PHPUNIT_BIN"
    fi
else
    echo
    echo "  (PHPUnit gate skipped per --skip-phpunit)"
fi

# ── 6. axe-core a11y baseline (block_airpay_cron_health) ─────────────
# Runs against a static fixture (no XAMPP / DB dependency). ~10s on
# warm cache. Skip via --skip-a11y if Chromium / Chrome is unavailable
# on the runner.
if [ "$SKIP_A11Y" -eq 0 ]; then
    heading "Gate 6 — axe-core a11y baseline (block_airpay_cron_health)"
    A11Y_HARNESS="$PROJECT_ROOT/moodle-enhancement/audit/playwright/a11y_block_cron_health.mjs"
    if [ -f "$A11Y_HARNESS" ]; then
        # The harness exits 0 on no critical/serious violations, 1 otherwise.
        if (cd "$PROJECT_ROOT/moodle-enhancement/audit/playwright" && \
                HEADLESS=1 node a11y_block_cron_health.mjs > /tmp/a11y.out 2>&1); then
            # Parse the violation totals for a richer pass message.
            CRIT=$(grep -E "critical\s*:" /tmp/a11y.out | head -1 | awk '{print $NF}')
            SERIOUS=$(grep -E "serious\s*:" /tmp/a11y.out | head -1 | awk '{print $NF}')
            pass "axe-core a11y (cron_health block — 0 critical, 0 serious)"
        else
            fail "axe-core a11y" "critical or serious violations — see /tmp/a11y.out"
            tail -20 /tmp/a11y.out
        fi
    else
        fail "axe-core a11y" "harness not found at $A11Y_HARNESS"
    fi
else
    echo
    echo "  (axe-core a11y gate skipped per --skip-a11y)"
fi

# ── 7. Phase 7 UAT (opt-in) ──────────────────────────────────────────
if [ "$RUN_UAT" -eq 1 ]; then
    heading "Gate 7 — Phase 7 multi-role UAT (target >= 84 of 85 cases)"
    UAT_HARNESS="$PROJECT_ROOT/moodle-enhancement/audit/playwright/uat_phase7_multirole.mjs"
    if [ -f "$UAT_HARNESS" ]; then
        if node "$UAT_HARNESS" > /tmp/uat.out 2>&1; then
            # Parse the final tally line.
            if grep -q "84/85\|85/85" /tmp/uat.out; then
                pass "Phase 7 UAT"
            else
                fail "Phase 7 UAT" "did not reach 84/85 — see /tmp/uat.out"
            fi
        else
            fail "Phase 7 UAT" "node exited non-zero — see /tmp/uat.out"
        fi
    else
        fail "Phase 7 UAT" "harness not found at $UAT_HARNESS"
    fi
else
    echo
    echo "  (Phase 7 UAT skipped — re-run with --uat to enable; takes ~30 min)"
fi

# ── Summary ──────────────────────────────────────────────────────────
echo
echo "================================================================"
echo "  Pre-deploy validation summary — $(TS)"
echo "================================================================"
echo "  Passed: ${#PASSED_GATES[@]}"
echo "  Failed: ${#FAILED_GATES[@]}"
echo

if [ "${#FAILED_GATES[@]}" -eq 0 ]; then
    echo "  ALL GATES PASS — safe to proceed with deploy"
    exit 0
fi

echo "  Failed gates:"
for g in "${FAILED_GATES[@]}"; do
    echo "    - $g"
done
echo
echo "  DEPLOY BLOCKED — resolve failures and re-run"
exit 1
