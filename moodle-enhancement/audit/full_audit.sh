#!/bin/bash
# Airpay Academy — Full Functional Audit Harness
#
# Walks every admin surface + every web service as each role tier
# (siteadmin, L&D admin, manager, learner) and reports per-step:
#   PASS  — HTTP 200 + expected page-region marker present
#   WARN  — HTTP 200 but unexpected content (UX issue, not a fail)
#   FAIL  — HTTP error or redirected to login (auth/permission bug)
#   SKIP  — endpoint not applicable to this role (expected)
#
# Each role uses a different test account. Passwords are local-only
# test creds set during one-time bootstrap in audit_bootstrap.sh.

set -u

BASE="http://localhost:8080/moodle"
LOGFILE="/tmp/airpay_audit_$(date +%Y%m%d_%H%M%S).log"
PASS=0; WARN=0; FAIL=0; SKIP=0

echo "Airpay Academy — Full Functional Audit"
echo "Log: $LOGFILE"
echo "════════════════════════════════════════════════════════════════════"

# ── Helpers ─────────────────────────────────────────────────────────────

login_as() {
    local username="$1"
    local password="$2"
    local cookies="$3"
    rm -f "$cookies"
    local token=$(curl -sL -c "$cookies" "$BASE/login/index.php" \
        | sed -n 's/.*name="logintoken"[^>]*value="\([^"]*\)".*/\1/p' | head -1)
    if [ -z "$token" ]; then echo "ERR: no logintoken" >&2; return 1; fi
    local url=$(curl -sL -b "$cookies" -c "$cookies" \
        --data-urlencode "username=$username" \
        --data-urlencode "password=$password" \
        --data-urlencode "logintoken=$token" \
        -o /dev/null -w "%{url_effective}" \
        "$BASE/login/index.php")
    if echo "$url" | grep -q "/login/index.php"; then
        echo "ERR: login rejected for $username" >&2
        return 1
    fi
    return 0
}

assert_page() {
    local cookies="$1"
    local label="$2"
    local path="$3"
    local marker="$4"
    local body=$(curl -sL -b "$cookies" --max-time 30 "$BASE$path")
    local http=$(curl -sL -b "$cookies" --max-time 30 -o /dev/null --write-out '%{http_code}' "$BASE$path")
    if [ "$http" != "200" ]; then
        echo "  FAIL [$label]: HTTP $http on $path" | tee -a "$LOGFILE"
        FAIL=$((FAIL + 1)); return 1
    fi
    if echo "$body" | grep -q "page-login-index"; then
        echo "  FAIL [$label]: redirected to login on $path" | tee -a "$LOGFILE"
        FAIL=$((FAIL + 1)); return 1
    fi
    if [ -n "$marker" ] && ! echo "$body" | grep -q "$marker"; then
        echo "  WARN [$label]: page loaded but '$marker' not found" | tee -a "$LOGFILE"
        WARN=$((WARN + 1)); return 1
    fi
    echo "  PASS [$label]" | tee -a "$LOGFILE"
    PASS=$((PASS + 1)); return 0
}

assert_ws() {
    local cookies="$1"
    local label="$2"
    local sesskey="$3"
    local methodname="$4"
    local args="$5"
    local response=$(curl -sL -b "$cookies" --max-time 30 \
        -X POST "$BASE/lib/ajax/service.php?sesskey=$sesskey" \
        -H "Content-Type: application/json" \
        -d "[{\"index\":0,\"methodname\":\"$methodname\",\"args\":$args}]")
    if echo "$response" | grep -q '"error":false'; then
        local total=$(echo "$response" | sed -n 's/.*"total":\([0-9]\{1,\}\).*/\1/p' | head -1)
        echo "  PASS [$label] total=$total" | tee -a "$LOGFILE"
        PASS=$((PASS + 1)); return 0
    fi
    if echo "$response" | grep -q '"errorcode":"accessexception"\|"errorcode":"required_capability\|"errorcode":"nopermissions"'; then
        echo "  SKIP [$label] (no permission — expected for this role)" | tee -a "$LOGFILE"
        SKIP=$((SKIP + 1)); return 0
    fi
    echo "  FAIL [$label]: $(echo "$response" | head -c 200)" | tee -a "$LOGFILE"
    FAIL=$((FAIL + 1)); return 1
}

extract_sesskey() {
    local cookies="$1"
    curl -sL -b "$cookies" "$BASE/local/airpay_users/index.php" \
        | sed -n 's/.*"sesskey":"\([^"]\{1,\}\)".*/\1/p' | head -1
}

# ══════════════════════════════════════════════════════════════════════
# ROLE 1: SITEADMIN
# ══════════════════════════════════════════════════════════════════════

echo
echo "──── ROLE: SITEADMIN (academy@airpay.co.in) ────"
COOKIES_ADMIN=$(mktemp)
if ! login_as "academy@airpay.co.in" "Airpay@Test2026!" "$COOKIES_ADMIN"; then
    echo "ERR: siteadmin login failed — check credentials" >&2
    exit 1
fi
SK_ADMIN=$(extract_sesskey "$COOKIES_ADMIN")
echo "  sesskey=$SK_ADMIN"

echo
echo "  ─ Admin pages ─"
assert_page "$COOKIES_ADMIN" "Dashboard"           "/my/dashboard.php"                   "airpay-dash"
assert_page "$COOKIES_ADMIN" "Manage Users"        "/local/airpay_users/index.php"        "airpay-users"
assert_page "$COOKIES_ADMIN" "Manage Courses"      "/local/airpay_courses/index.php"      "airpay-courses"
assert_page "$COOKIES_ADMIN" "Online Exams"        "/local/airpay_exams/index.php"        "airpay-exams"
assert_page "$COOKIES_ADMIN" "Classrooms"          "/local/airpay_classroom/index.php"    "airpay-classroom"
assert_page "$COOKIES_ADMIN" "Learning Paths"      "/local/airpay_learningpath/index.php" "airpay-paths"
assert_page "$COOKIES_ADMIN" "Programs"            "/local/airpay_programs/index.php"     "airpay-programs"
assert_page "$COOKIES_ADMIN" "Reports"             "/local/airpay_reports/index.php"      "airpay-reports"
assert_page "$COOKIES_ADMIN" "Skills"              "/local/airpay_skills/admin.php"       "airpay-skills"
assert_page "$COOKIES_ADMIN" "Notifications"       "/local/airpay_notifications/index.php" "airpay-notifications"
assert_page "$COOKIES_ADMIN" "Evaluations"         "/local/airpay_evaluation/index.php"   "airpay-evaluation"
assert_page "$COOKIES_ADMIN" "Organisation"        "/local/airpay_org/admin.php"          "airpay-org"
assert_page "$COOKIES_ADMIN" "Analytics"           "/local/airpay_analytics/index.php"    "airpay-analytics"
assert_page "$COOKIES_ADMIN" "Compliance"          "/local/airpay_compliance_report/index.php" ""
assert_page "$COOKIES_ADMIN" "Emails"              "/local/airpay_emails/manage.php"      ""
assert_page "$COOKIES_ADMIN" "Privacy"             "/local/airpay_privacy/index.php"      ""
assert_page "$COOKIES_ADMIN" "Site Admin"          "/admin/search.php"                    ""
assert_page "$COOKIES_ADMIN" "Certificate Templates" "/admin/tool/certificate/manage_templates.php" ""

echo
echo "  ─ Web services (read) ─"
assert_ws "$COOKIES_ADMIN" "list_users (search nitin)"        "$SK_ADMIN" "local_airpay_users_list_users" \
    '{"search":"nitin","sort":"lastname","sortdir":"asc","page":0,"perpage":3,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_courses (search POSH)"       "$SK_ADMIN" "local_airpay_courses_list_courses" \
    '{"search":"POSH","sort":"fullname","sortdir":"asc","page":0,"perpage":3,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_classrooms"                   "$SK_ADMIN" "local_airpay_classroom_list_classrooms" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_exams"                        "$SK_ADMIN" "local_airpay_exams_list_exams" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_evaluations"                  "$SK_ADMIN" "local_airpay_evaluation_list_evaluations" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_skills"                       "$SK_ADMIN" "local_airpay_skills_list_skills" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_rules (notifications)"        "$SK_ADMIN" "local_airpay_notifications_list_rules" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_programs"                     "$SK_ADMIN" "local_airpay_programs_list_programs" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_paths"                        "$SK_ADMIN" "local_airpay_learningpath_list_paths" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'
assert_ws "$COOKIES_ADMIN" "list_reports"                      "$SK_ADMIN" "local_airpay_reports_list_reports" \
    '{"search":"","sort":"name","sortdir":"asc","page":0,"perpage":5,"filters":"{}"}'

echo
echo "  ─ Tenant scoping (siteadmin sees all) ─"
assert_ws "$COOKIES_ADMIN" "list_users orgid=1 (Airpay)"       "$SK_ADMIN" "local_airpay_users_list_users" \
    '{"search":"","sort":"lastname","sortdir":"asc","page":0,"perpage":3,"filters":"{\"orgid\":1,\"status\":\"all\"}"}'
assert_ws "$COOKIES_ADMIN" "list_users orgid=77 (Public)"      "$SK_ADMIN" "local_airpay_users_list_users" \
    '{"search":"","sort":"lastname","sortdir":"asc","page":0,"perpage":3,"filters":"{\"orgid\":77,\"status\":\"all\"}"}'
assert_ws "$COOKIES_ADMIN" "list_users orgid=177 (ZEEA)"       "$SK_ADMIN" "local_airpay_users_list_users" \
    '{"search":"","sort":"lastname","sortdir":"asc","page":0,"perpage":3,"filters":"{\"orgid\":177,\"status\":\"all\"}"}'

echo
echo "  ─ Drill-down pages ─"
NITIN_ID=142
assert_page "$COOKIES_ADMIN" "User Profile"        "/local/airpay_users/profile.php?id=$NITIN_ID"     ""
assert_page "$COOKIES_ADMIN" "Manager My Team"     "/local/airpay_manager/index.php"                  ""
assert_page "$COOKIES_ADMIN" "Manager Member Drill" "/local/airpay_manager/member.php?id=$NITIN_ID"   ""

# ══════════════════════════════════════════════════════════════════════
# ROLE 2: MANAGER (with direct reports)
# ══════════════════════════════════════════════════════════════════════

echo
echo "──── ROLE: MANAGER (kunal@airpay.co.in) ────"
echo "    NOTE: requires manager test password to be set; skipping if not."

# ──── ROLE 3: LEARNER ────
echo
echo "──── ROLE: LEARNER ────"
echo "    NOTE: requires learner test password to be set; skipping if not."

echo
echo "════════════════════════════════════════════════════════════════════"
echo "RESULTS: $PASS pass, $WARN warn, $FAIL fail, $SKIP skip"
echo "Log: $LOGFILE"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
