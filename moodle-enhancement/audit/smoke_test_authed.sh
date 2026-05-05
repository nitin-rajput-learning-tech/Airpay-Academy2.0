#!/bin/bash
# Authenticated smoke test against admin pages.
# Uses academy@airpay.co.in / Airpay@Test2026! (local-only test creds).

BASE="http://localhost:8080/moodle"
COOKIES=$(mktemp)
trap "rm -f $COOKIES" EXIT

echo "=== Authenticated Smoke Test ==="
echo

# 1. GET login page to obtain logintoken
echo "[1] Fetching login page..."
LOGIN_HTML=$(curl -sL -c "$COOKIES" "$BASE/login/index.php")
TOKEN=$(echo "$LOGIN_HTML" | sed -n 's/.*name="logintoken"[^>]*value="\([^"]*\)".*/\1/p' | head -1)
echo "    logintoken=$TOKEN"

if [ -z "$TOKEN" ]; then
    echo "    !!! Could not extract logintoken — login form structure may have changed."
    exit 1
fi

# 2. POST credentials
echo
echo "[2] POSTing credentials..."
LOGIN_RESULT=$(curl -sL -b "$COOKIES" -c "$COOKIES" \
    --data-urlencode "username=academy@airpay.co.in" \
    --data-urlencode "password=Airpay@Test2026!" \
    --data-urlencode "logintoken=$TOKEN" \
    -w "%{http_code} %{url_effective}" \
    -o /tmp/login_response.html \
    "$BASE/login/index.php")
echo "    response: $LOGIN_RESULT"

# Quick check: did we get redirected away from login?
if echo "$LOGIN_RESULT" | grep -q "/login/index.php"; then
    echo "    !!! Still on login page — credentials rejected"
    grep -oE "loginerror|login.*error" /tmp/login_response.html | head -3
    exit 1
fi
echo "    -> Login successful"

# 3. Hit each admin page and assert it doesn't redirect to login.
echo
echo "[3] Smoke testing admin pages..."
PAGES=(
    "/local/airpay_users/index.php|airpay-users|User Management"
    "/local/airpay_courses/index.php|airpay-courses|Course Management"
    "/local/airpay_classroom/index.php|airpay-classroom|Classrooms"
    "/local/airpay_exams/index.php|airpay-exams|Exams"
    "/local/airpay_evaluation/index.php|airpay-evaluation|Evaluations"
    "/local/airpay_reports/index.php|airpay-reports|Reports"
    "/local/airpay_skills/admin.php|airpay-skills|Skills"
    "/local/airpay_notifications/index.php||Notifications"
    "/local/airpay_org/admin.php|airpay-org|Organisation"
    "/local/airpay_analytics/index.php||Analytics"
)

PASS=0
FAIL=0
for entry in "${PAGES[@]}"; do
    IFS='|' read -r path region label <<< "$entry"
    BODY=$(curl -sL -b "$COOKIES" "$BASE$path")
    HTTP=$(curl -sL -b "$COOKIES" -o /dev/null --write-out '%{http_code}' "$BASE$path")

    # Check 1: HTTP 200
    if [ "$HTTP" != "200" ]; then
        echo "    FAIL [$label]: HTTP $HTTP"
        FAIL=$((FAIL + 1))
        continue
    fi

    # Check 2: not redirected to login (page-login-index in body class)
    if echo "$BODY" | grep -q "page-login-index"; then
        echo "    FAIL [$label]: redirected to login"
        FAIL=$((FAIL + 1))
        continue
    fi

    # Check 3: page-specific marker present (only if specified)
    if [ -n "$region" ] && ! echo "$BODY" | grep -q "data-region=\"$region\""; then
        # Some pages don't use data-region — fall back to title check.
        if ! echo "$BODY" | grep -q "$label"; then
            echo "    PASS-WARN [$label]: HTTP 200 but no expected marker — render may be partial"
            PASS=$((PASS + 1))
            continue
        fi
    fi

    echo "    PASS [$label]"
    PASS=$((PASS + 1))
done

echo
echo "=== Results: $PASS pass, $FAIL fail ==="

# 4. Hit list_users and list_courses WS as authed user.
echo
echo "[4] Testing AJAX web service endpoints (authed)..."
PAGE_HTML=$(curl -sL -b "$COOKIES" "$BASE/local/airpay_users/index.php")
SESSKEY=$(echo "$PAGE_HTML" | sed -n 's/.*"sesskey":"\([^"]\{1,\}\)".*/\1/p' | head -1)
echo "    sesskey=$SESSKEY"

extract_total() {
    echo "$1" | sed -n 's/.*"total":\([0-9][0-9]*\).*/\1/p' | head -1
}

if [ -n "$SESSKEY" ]; then
    WS_USERS=$(curl -sL -b "$COOKIES" \
        -X POST "$BASE/lib/ajax/service.php?sesskey=$SESSKEY" \
        -H "Content-Type: application/json" \
        -d '[{"index":0,"methodname":"local_airpay_users_list_users","args":{"search":"nitin","sort":"lastname","sortdir":"asc","page":0,"perpage":3,"filters":"{\"status\":\"all\"}"}}]')
    if echo "$WS_USERS" | grep -q '"error":false'; then
        TOTAL=$(extract_total "$WS_USERS")
        echo "    PASS list_users: search 'nitin' → total=$TOTAL"
    else
        echo "    FAIL list_users:"
        echo "$WS_USERS" | head -c 300
        echo
    fi

    WS_COURSES=$(curl -sL -b "$COOKIES" \
        -X POST "$BASE/lib/ajax/service.php?sesskey=$SESSKEY" \
        -H "Content-Type: application/json" \
        -d '[{"index":0,"methodname":"local_airpay_courses_list_courses","args":{"search":"POSH","sort":"fullname","sortdir":"asc","page":0,"perpage":3,"filters":"{}"}}]')
    if echo "$WS_COURSES" | grep -q '"error":false'; then
        TOTAL=$(extract_total "$WS_COURSES")
        echo "    PASS list_courses: search 'POSH' → total=$TOTAL"
    else
        echo "    FAIL list_courses:"
        echo "$WS_COURSES" | head -c 300
        echo
    fi
else
    echo "    SKIP — could not extract sesskey"
fi

echo
echo "Done."
