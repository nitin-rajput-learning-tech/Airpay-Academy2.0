#!/bin/bash
# Manager + Learner audit — runs after audit_bootstrap.php has set
# the test passwords on representative users.
#
# Manager (kunal@airpay.co.in) — 14 direct reports, path /1/2/235/236
# Learner (rasika.thakare@airpay.co.in) — under a manager, path /1/183/184/185

set -u
BASE="http://localhost:8080/moodle"
LOGFILE="/tmp/airpay_audit_mgr_lrn_$(date +%Y%m%d_%H%M%S).log"
PASS=0; WARN=0; FAIL=0; SKIP=0; DENIED=0

login_as() {
    local username="$1" password="$2" cookies="$3"
    rm -f "$cookies"
    local token=$(curl -sL -c "$cookies" "$BASE/login/index.php" \
        | sed -n 's/.*name="logintoken"[^>]*value="\([^"]*\)".*/\1/p' | head -1)
    [ -z "$token" ] && return 1
    local url=$(curl -sL -b "$cookies" -c "$cookies" \
        --data-urlencode "username=$username" \
        --data-urlencode "password=$password" \
        --data-urlencode "logintoken=$token" \
        -o /dev/null -w "%{url_effective}" \
        "$BASE/login/index.php")
    echo "$url" | grep -q "/login/index.php" && return 1
    return 0
}

# assert_page <cookies> <label> <path> <expect>
# expect = "ok"      → must be HTTP 200, not redirected to login
#          "denied"  → must be either HTTP 403 OR redirected to login (= correct deny)
#          "marker:X" → must contain X
assert_page() {
    local cookies="$1" label="$2" path="$3" expect="$4"
    local body=$(curl -sL -b "$cookies" --max-time 30 "$BASE$path")
    local http=$(curl -sL -b "$cookies" --max-time 30 -o /dev/null --write-out '%{http_code}' "$BASE$path")
    local at_login=$(echo "$body" | grep -q "page-login-index" && echo yes || echo no)

    case "$expect" in
        ok)
            if [ "$http" = "200" ] && [ "$at_login" = "no" ]; then
                echo "  PASS [$label]" | tee -a "$LOGFILE"
                PASS=$((PASS+1))
            else
                echo "  FAIL [$label]: HTTP $http, login=$at_login" | tee -a "$LOGFILE"
                FAIL=$((FAIL+1))
            fi ;;
        denied)
            # Moodle deliberately returns 404 (not 403) for "you can't access
            # this" to avoid leaking page existence. Accept any of:
            #   - HTTP 403   — explicit forbid
            #   - HTTP 404   — Moodle's "no access" rendering (most common)
            #   - At login   — redirected to /login/index.php
            #   - HTTP 200 with errorbox / "no permission" markup
            if [ "$http" = "403" ] || [ "$http" = "404" ] || [ "$at_login" = "yes" ] || \
               echo "$body" | grep -qE "nopermission|no permission|access denied|required_capability|errorbox"; then
                echo "  PASS [$label] (correctly denied, http=$http)" | tee -a "$LOGFILE"
                DENIED=$((DENIED+1))
            else
                echo "  FAIL [$label]: expected denial but got HTTP $http with no error marker" | tee -a "$LOGFILE"
                FAIL=$((FAIL+1))
            fi ;;
        marker:*)
            local marker=${expect#marker:}
            if [ "$http" = "200" ] && echo "$body" | grep -q "$marker"; then
                echo "  PASS [$label]" | tee -a "$LOGFILE"
                PASS=$((PASS+1))
            else
                echo "  WARN [$label]: marker '$marker' missing" | tee -a "$LOGFILE"
                WARN=$((WARN+1))
            fi ;;
    esac
}

# ══════════════════════════════════════════════════════════════════════
# ROLE: MANAGER (kunal@airpay.co.in)
# ══════════════════════════════════════════════════════════════════════
echo "──── ROLE: MANAGER (kunal@airpay.co.in, 14 direct reports) ────" | tee -a "$LOGFILE"
COOKIES_MGR=$(mktemp)
if ! login_as "kunal@airpay.co.in" "Airpay@Test2026!" "$COOKIES_MGR"; then
    echo "  ERR: manager login failed" | tee -a "$LOGFILE"
    exit 1
fi

echo "  ─ Should-have-access pages ─" | tee -a "$LOGFILE"
assert_page "$COOKIES_MGR" "Dashboard"                  "/my/dashboard.php"                          ok
assert_page "$COOKIES_MGR" "My Team"                    "/local/airpay_manager/index.php"            marker:airpay
# Drill-down on an actual direct report (id=344, Rahul Jain).
# Trying to drill into a non-report should be DENIED — covered next.
assert_page "$COOKIES_MGR" "My Team — own report drill" "/local/airpay_manager/member.php?id=344"    ok
assert_page "$COOKIES_MGR" "My Team — denied drill"     "/local/airpay_manager/member.php?id=3113"   denied
assert_page "$COOKIES_MGR" "Course Catalog"             "/local/airpay_catalog/index.php"            ok
assert_page "$COOKIES_MGR" "My Courses"                 "/local/airpay_catalog/mycourses.php"        ok

echo "  ─ Should-be-DENIED pages (admin-only) ─" | tee -a "$LOGFILE"
assert_page "$COOKIES_MGR" "Manage Users"            "/local/airpay_users/index.php"              denied
assert_page "$COOKIES_MGR" "Manage Courses"          "/local/airpay_courses/index.php"            denied
assert_page "$COOKIES_MGR" "Online Exams"            "/local/airpay_exams/index.php"              denied
assert_page "$COOKIES_MGR" "Classrooms"              "/local/airpay_classroom/index.php"          denied
assert_page "$COOKIES_MGR" "Reports"                 "/local/airpay_reports/index.php"            denied
assert_page "$COOKIES_MGR" "Skills Admin"            "/local/airpay_skills/admin.php"             denied
assert_page "$COOKIES_MGR" "Notifications"           "/local/airpay_notifications/index.php"      denied
assert_page "$COOKIES_MGR" "Evaluations"             "/local/airpay_evaluation/index.php"         denied
assert_page "$COOKIES_MGR" "Organisation"            "/local/airpay_org/admin.php"                denied
assert_page "$COOKIES_MGR" "Site Admin"              "/admin/search.php"                          denied

# ══════════════════════════════════════════════════════════════════════
# ROLE: LEARNER (rasika.thakare@airpay.co.in)
# ══════════════════════════════════════════════════════════════════════
echo "" | tee -a "$LOGFILE"
echo "──── ROLE: LEARNER (rasika.thakare@airpay.co.in) ────" | tee -a "$LOGFILE"
COOKIES_LRN=$(mktemp)
if ! login_as "rasika.thakare@airpay.co.in" "Airpay@Test2026!" "$COOKIES_LRN"; then
    echo "  ERR: learner login failed" | tee -a "$LOGFILE"
    exit 1
fi

echo "  ─ Should-have-access pages ─" | tee -a "$LOGFILE"
assert_page "$COOKIES_LRN" "Dashboard"               "/my/dashboard.php"                          ok
assert_page "$COOKIES_LRN" "Course Catalog"          "/local/airpay_catalog/index.php"            ok
assert_page "$COOKIES_LRN" "My Courses"              "/local/airpay_catalog/mycourses.php"        ok
assert_page "$COOKIES_LRN" "User Self-profile"       "/user/profile.php"                          ok

echo "  ─ Should-be-DENIED pages (admin/manager only) ─" | tee -a "$LOGFILE"
assert_page "$COOKIES_LRN" "Manage Users"            "/local/airpay_users/index.php"              denied
assert_page "$COOKIES_LRN" "Manage Courses"          "/local/airpay_courses/index.php"            denied
assert_page "$COOKIES_LRN" "Reports"                 "/local/airpay_reports/index.php"            denied
assert_page "$COOKIES_LRN" "Skills Admin"            "/local/airpay_skills/admin.php"             denied
assert_page "$COOKIES_LRN" "Organisation"            "/local/airpay_org/admin.php"                denied
assert_page "$COOKIES_LRN" "My Team"                 "/local/airpay_manager/index.php"            denied
assert_page "$COOKIES_LRN" "Site Admin"              "/admin/search.php"                          denied

echo "" | tee -a "$LOGFILE"
echo "════════════════════════════════════════════════════════════════════" | tee -a "$LOGFILE"
echo "MGR + LEARNER RESULTS: $PASS access pass, $DENIED correctly denied, $WARN warn, $FAIL fail" | tee -a "$LOGFILE"
echo "Log: $LOGFILE" | tee -a "$LOGFILE"

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
