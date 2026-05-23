#!/usr/bin/env bash
# Generate the diff trio between our 5.1.3+ fork and Moodle 5.2 stable.
#
# Usage:
#   tools/generate-5.2-diffs.sh
#
# Pre-requisite:
#   - 5.2 source extracted at /d/Claude Local/moodle-5.2-source/moodle/public/
#   - Our 5.1.3+ source at /c/xampp/htdocs/moodle5/public/
#   - GNU diffutils on PATH (`which diff` should point at /usr/bin/diff
#     from MSYS Bash / Git for Windows)
#
# Why not PowerShell?
#   PowerShell 5.1 aliases `diff` to Compare-Object — which is totally
#   different. Using GNU diff via MSYS Bash is the path of least
#   resistance for cross-platform diffing on Windows.
#
# Output:
#   /d/Claude Local/moodle-5.2-diffs/*.diff + *.txt

set -uo pipefail

SRC="/c/xampp/htdocs/moodle5/public"
DST="/d/Claude Local/moodle-5.2-source/moodle/public"
OUT="/d/Claude Local/moodle-5.2-diffs"

[ -d "$SRC" ] || { echo "Source tree not found: $SRC" >&2; exit 1; }
[ -d "$DST" ] || { echo "5.2 tree not found: $DST" >&2; exit 1; }
mkdir -p "$OUT"

echo "Source (5.1 fork): $SRC"
echo "Target (5.2 ref):  $DST"
echo "Output:            $OUT"
echo ""

run_diff() {
  local label=$1
  local a=$2
  local b=$3
  local outfile=$4
  local start=$(date +%s)
  diff -r "$a" "$b" > "$OUT/$outfile" 2>&1
  local elapsed=$(($(date +%s) - start))
  local size_bytes=$(stat -c%s "$OUT/$outfile" 2>/dev/null || echo 0)
  local size_mb=$(awk "BEGIN {printf \"%.2f\", $size_bytes/1048576}")
  echo "[$label] -> $size_mb MB ($elapsed s)"
}

run_brief() {
  local label=$1
  local a=$2
  local b=$3
  local outfile=$4
  local start=$(date +%s)
  diff -r --brief "$a" "$b" > "$OUT/$outfile" 2>&1
  local elapsed=$(($(date +%s) - start))
  local lines=$(wc -l < "$OUT/$outfile")
  echo "[$label] BRIEF -> $lines lines ($elapsed s)"
}

run_brief "1/7" "$SRC"                        "$DST"                         "5.2-brief-summary.txt"
run_diff  "2/7" "$SRC/theme/boost"            "$DST/theme/boost"             "5.2-theme-boost-full.diff"
run_diff  "3/7" "$SRC/lib"                    "$DST/lib"                     "5.2-lib-full.diff"
run_diff  "4/7" "$SRC/blocks/myoverview"      "$DST/blocks/myoverview"       "5.2-block-myoverview.diff"
run_diff  "5/7" "$SRC/backup"                 "$DST/backup"                  "5.2-backup.diff"
run_diff  "6/7" "$SRC/course"                 "$DST/course"                  "5.2-course.diff"
run_diff  "7/7" "$SRC/admin"                  "$DST/admin"                   "5.2-admin.diff"

echo ""
echo "Diff trio generation complete."
echo ""
echo "=== Output files ==="
ls -lh "$OUT" 2>&1
