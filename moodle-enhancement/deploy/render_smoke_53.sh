#!/usr/bin/env bash
# Render smoke for a Moodle 5.3 instance under its production SAPI (php-cgi / php-fpm).
#
# This is a GATE CHECK (ADR-033 Decision item 8b, compatibility report finding F3).
# The 5.3 recipe carries NO ini_get_bool() polyfill and NO edit to lib/setuplib.php. Static
# reading says that is safe; only a render proves it. The four URL classes below are the ones
# that start as minimal ABORT_AFTER_CONFIG bootstraps and, on a cache miss, re-enter full setup:
#
#     lib/javascript.php    theme/styles.php    r.php (the ESM / React loader)    theme/font.php
#
# If any of them returns 500 on 5.3, re-apply BOTH halves of the 5.2 recipe (polyfill in
# config.php + the function_exists guard in lib/setuplib.php, hunk unchanged at line 530) and
# record it in docs/core-mods/2026-06-11-setuplib-ini-get-bool-guard.md. Never apply one half.
#
# How it works: fetch the login page, take one real URL of each class from it (the head carries
# the javascript.php and styles.php URLs and the import map; the compiled CSS carries the
# font.php URL), request each and require HTTP 200 with a non-empty body. It runs TWO rounds by
# default, because the first request of each class after a cache purge is the cache-miss path
# that re-enters full setup and the second is the cached path.
#
# Usage:
#   bash moodle-enhancement/deploy/render_smoke_53.sh http://localhost:8082
#   bash moodle-enhancement/deploy/render_smoke_53.sh https://uat53.example.invalid --insecure --rounds 3
#
# Run it right after `admin/cli/purge_caches.php` so round 1 really is a cache miss.
#
# Exit codes: 0 every class returned 200 in every round; 1 at least one class failed or could
# not be found in the page; 2 bad arguments or the login page is unreachable.

set -u

BASE=""
ROUNDS=2
CURL_EXTRA=()

while [ $# -gt 0 ]; do
    case "$1" in
        --insecure) CURL_EXTRA+=("-k"); shift ;;
        --rounds)   ROUNDS="${2:-2}"; shift 2 ;;
        --rounds=*) ROUNDS="${1#--rounds=}"; shift ;;
        -h|--help)  sed -n '2,29p' "$0"; exit 0 ;;
        -*)         echo "Unknown option: $1" >&2; exit 2 ;;
        *)          if [ -z "$BASE" ]; then BASE="${1%/}"; else echo "Unexpected argument: $1" >&2; exit 2; fi; shift ;;
    esac
done

if [ -z "$BASE" ]; then
    echo "Usage: $0 <base-url> [--insecure] [--rounds N]" >&2
    exit 2
fi
case "$ROUNDS" in ''|*[!0-9]*) echo "--rounds must be a number" >&2; exit 2 ;; esac

TMP="$(mktemp -d 2>/dev/null || echo "${TMPDIR:-/tmp}/render_smoke_53.$$")"
mkdir -p "$TMP"
trap 'rm -rf "$TMP"' EXIT

ORIGIN="$(printf '%s' "$BASE" | sed -E 's#^(https?://[^/]+).*#\1#')"

fetch() { # fetch <url> <outfile> -> prints "<http_code> <size>" ("000 0" when curl itself failed)
    local out
    out="$(curl -sS -L ${CURL_EXTRA[@]+"${CURL_EXTRA[@]}"} --max-time 180 -o "$2" -w '%{http_code} %{size_download}' "$1" 2>/dev/null)" || true
    case "$out" in
        [0-9][0-9][0-9]\ [0-9]*) printf '%s' "$out" ;;
        *)                       printf '000 0' ;;
    esac
}

abs() { # make a possibly relative / entity-encoded / json-escaped URL absolute against $BASE
    local u
    u="$(printf '%s' "$1" | sed 's/&amp;/\&/g; s#\\/#/#g')"
    case "$u" in
        http://*|https://*) printf '%s' "$u" ;;
        //*)                printf '%s' "${BASE%%:*}:$u" ;;
        /*)                 printf '%s%s' "$ORIGIN" "$u" ;;
        *)                  printf '%s/%s' "$BASE" "$u" ;;
    esac
}

echo "Render smoke: $BASE ($ROUNDS rounds)"
read -r CODE SIZE < <(fetch "$BASE/login/index.php" "$TMP/login.html")
if [ "$CODE" != "200" ] || [ "${SIZE:-0}" -le 0 ]; then
    echo "FAIL  login page: HTTP $CODE (size $SIZE) - the instance is not serving; nothing else can be checked." >&2
    exit 2
fi

pick() { # pick <ere> <file> -> first match, entity/escape normalised
    grep -oE "$1" "$2" | head -n 1 | sed 's/&amp;/\&/g; s#\\/#/#g'
}

JS_URL="$(pick "[^\"' <>]*javascript\.php[/?][^\"' <>]+" "$TMP/login.html")"
CSS_URL="$(pick "[^\"' <>]*styles\.php[/?][^\"' <>]+" "$TMP/login.html")"
# The import map carries absolute URLs of the ESM loader (r.php, or its rewritten route when the router is on).
sed -n '/type="importmap"/,/<\/script>/p' "$TMP/login.html" | tr -d '\n' > "$TMP/importmap.txt"
ESM_URL="$(pick '"https?:[^"]+"' "$TMP/importmap.txt" | tr -d '"')"

FONT_URL=""
if [ -n "$CSS_URL" ]; then
    CSS_ABS="$(abs "$CSS_URL")"
    fetch "$CSS_ABS" "$TMP/styles.css" >/dev/null
    FONT_URL="$(pick "[^\"'() <>]*font\.php[/?][^\"'() <>]+" "$TMP/styles.css")"
fi

FAILED=0
declare -a NAMES=("lib/javascript.php" "theme/styles.php" "r.php (ESM)" "theme/font.php")
declare -a URLS=("$JS_URL" "$CSS_URL" "$ESM_URL" "$FONT_URL")

round=1
while [ "$round" -le "$ROUNDS" ]; do
    echo "--- round $round"
    for i in 0 1 2 3; do
        name="${NAMES[$i]}"
        url="${URLS[$i]}"
        if [ -z "$url" ]; then
            echo "FAIL  $name: no URL of this class found in the login page / compiled CSS"
            FAILED=$((FAILED + 1))
            continue
        fi
        full="$(abs "$url")"
        read -r CODE SIZE < <(fetch "$full" "$TMP/body.$i")
        if [ "$CODE" = "200" ] && [ "${SIZE:-0}" -gt 0 ]; then
            echo "PASS  $name: HTTP 200, $SIZE bytes"
        else
            echo "FAIL  $name: HTTP $CODE, $SIZE bytes   $full"
            FAILED=$((FAILED + 1))
        fi
    done
    round=$((round + 1))
done

if [ "$FAILED" -ne 0 ]; then
    echo
    echo "RESULT: $FAILED failure(s). A 500 on any of these four classes means the php-cgi bootstrap still"
    echo "needs the 5.2 ini_get_bool recipe (both halves); see apache-sentientia53-vhost.conf.template."
    exit 1
fi
echo
echo "RESULT: all four classes returned 200 in every round."
exit 0
