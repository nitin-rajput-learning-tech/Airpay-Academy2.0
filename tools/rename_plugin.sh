#!/usr/bin/env bash
# rename_plugin.sh <shortname> -- full de-brand rename local_airpay_<X> -> local_sentientia_<X>
#
# Dev-time helper (ADR-025): renames the DEPLOYED plugin dir + code refs, then
# relabels the DB footprint in place via local_sentientia_core/cli/relabel_plugin.php
# (which itself is the artifact run on production during the maintenance-window deploy).
#
# Auto-detects the plugin's tables (from db/install.xml) and whether it declares
# capabilities (db/access.php -> passes --migrate-caps). Refuses if the target
# component already exists (name collision, e.g. airpay_core -> sentientia_core).
#
# Run on the local clone, then: admin/cli/upgrade.php + purge_caches.php + verify.
# Usage:  bash tools/rename_plugin.sh gamification
set -uo pipefail

X="${1:?usage: rename_plugin.sh <shortname>}"
PUB="C:/xampp/htdocs/moodle5/public"; L="$PUB/local"; T="$PUB/theme/airpayux"
PHP="/c/xampp/php/php.exe"
OLD="$L/airpay_$X"; NEW="$L/sentientia_$X"

[ -d "$OLD" ] || { echo "ERR: $OLD not found"; exit 1; }
[ -d "$NEW" ] && { echo "ERR: $NEW already exists - NAME COLLISION, resolve manually"; exit 2; }

# Tables from install.xml (captured BEFORE the move).
tables=$(grep -ohE 'TABLE NAME="[^"]+"' "$OLD/db/install.xml" 2>/dev/null | sed 's/TABLE NAME=//;s/"//g')
# Capabilities?
caps=""
if [ -f "$OLD/db/access.php" ] && grep -q "'local/" "$OLD/db/access.php" 2>/dev/null; then caps="--migrate-caps"; fi

echo "== rename airpay_$X -> sentientia_$X  (caps:${caps:-none}) =="
echo "   tables: ${tables:-none}"

# 1. Move dir + per-locale lang files.
mv "$OLD" "$NEW"
for d in "$NEW/lang"/*/; do
    [ -f "${d}local_airpay_$X.php" ] && mv "${d}local_airpay_$X.php" "${d}local_sentientia_$X.php"
done

# 2. Build sed args. baresed: for local/+theme/ where 'airpay_X' is unambiguous.
#    qualsed: for blocks/mod/payment where a bare 'airpay_X' could hit a SIBLING
#    component (e.g. quizaccess_airpay_proctoring) - rewrite only QUALIFIED refs there.
baresed=(-e "s/airpay_$X/sentientia_$X/g")
qualsed=(-e "s/local_airpay_$X/local_sentientia_$X/g" -e "s#local/airpay_$X#local/sentientia_$X#g")
map=""
for t in $tables; do
    nt="${t/local_airpay_/local_sentientia_}"
    [ "$nt" = "$t" ] && continue   # brand-neutral table (e.g. local_privacy_*): keep name, no rename
    baresed+=(-e "s/${t}/${nt}/g")
    qualsed+=(-e "s/${t}/${nt}/g")
    map="${map},${t}:${nt}"
done
map="${map#,}"

# 3a. Rewrite refs in local/+theme/ (bare name safe here).
pat="airpay_$X"
for t in $tables; do pat="${pat}|${t}"; done
grep -rlE "$pat" "$L" "$T" 2>/dev/null | grep -vE '\.min\.|\.map' | while read -r f; do
    [ -n "$f" ] && sed -i "${baresed[@]}" "$f"
done
# 3b. Rewrite QUALIFIED refs in blocks/mod/payment (don't clobber sibling components).
qpat="local_airpay_$X|local/airpay_$X"
for t in $tables; do qpat="${qpat}|${t}"; done
grep -rlE "$qpat" "$PUB/blocks" "$PUB/mod/quiz/accessrule" "$PUB/payment/gateway" 2>/dev/null | grep -vE '\.min\.|\.map' | while read -r f; do
    [ -n "$f" ] && sed -i "${qualsed[@]}" "$f"
done

# 4. Relabel the DB footprint in place.
"$PHP" "$L/sentientia_core/cli/relabel_plugin.php" \
    --from="local_airpay_$X" --to="local_sentientia_$X" ${map:+--tables="$map"} $caps --run

# 5. Residual check (local/theme bare + blocks/mod/payment qualified).
resid=$(grep -rl "airpay_$X" "$L" "$T" 2>/dev/null | grep -vE '\.min\.|\.map' | wc -l)
qresid=$(grep -rlE "local_airpay_$X([^a-z]|\$)|local/airpay_$X" "$PUB/blocks" "$PUB/mod/quiz/accessrule" "$PUB/payment/gateway" 2>/dev/null | grep -vE '\.min\.|\.map' | wc -l)
echo "== done airpay_$X: residual local/theme=$resid qualified-elsewhere=$qresid (expect 0 0) =="
