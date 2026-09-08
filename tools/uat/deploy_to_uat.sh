#!/usr/bin/env bash
# deploy_to_uat.sh — surgical, checksum-verified deploy of specific files to the UAT box.
#
# Encodes the method proven by hand on 2026-09-04 and 2026-09-07:
#   stage → tar → scp → BACKUP current → sudo extract → chown www-data → sha256 -c → upgrade → purge
# It deploys ONLY the files you name (or a commit's deployable files) — never a whole-tree rsync,
# so unrelated working-tree drift never rides along.
#
# Prereq: corporate VPN up + `ssh uat-tunnel` established (TOTP; LocalForward 2222), so `ssh uat-lms` works.
#         The SSH user (nitin.rajput) must have passwordless sudo on the box (it does).
#
# Usage:
#   tools/uat/deploy_to_uat.sh [--yes] [--no-upgrade] [--commit <sha>|--range <a>..<b>] [PATH ...]
#     PATH          repo-relative file(s) to deploy. Each must map under the docroot
#                   (theme/…, local/…, blocks/…, mod/…, admin/…, lib/…). A leading
#                   `moodle-enhancement/` is stripped, so both trees map to public/<rel>.
#     --commit SHA  derive the deployable files from that commit (git show --name-only).
#     --range A..B  derive the deployable files from a commit range.
#     --yes         actually perform the deploy. DEFAULT IS A DRY RUN that only prints the plan.
#     --no-upgrade  skip admin/cli/upgrade.php (use only when no version.php changed).
#
# Examples:
#   tools/uat/deploy_to_uat.sh --commit 1f8dc0eaf              # dry-run: show what F-12 would deploy
#   tools/uat/deploy_to_uat.sh --yes --commit 1f8dc0eaf        # deploy F-12
#   tools/uat/deploy_to_uat.sh --yes theme/sentientia/templates/head.mustache
set -euo pipefail

SSH_HOST=uat-lms
DIRROOT=/var/www/html/moodle5.2
DOCROOT="$DIRROOT/public"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DEPLOYABLE_RE='^(theme|local|blocks|mod|admin|lib)/'

YES=0
DO_UPGRADE=1
declare -a INPUTS=()

# ── parse args ───────────────────────────────────────────────────────────────
while [ $# -gt 0 ]; do
    case "$1" in
        --yes) YES=1; shift ;;
        --no-upgrade) DO_UPGRADE=0; shift ;;
        --commit) shift; mapfile -t _c < <(git -C "$REPO_ROOT" show --name-only --pretty=format: "$1"); INPUTS+=("${_c[@]}"); shift ;;
        --range)  shift; mapfile -t _c < <(git -C "$REPO_ROOT" diff --name-only "$1"); INPUTS+=("${_c[@]}"); shift ;;
        -h|--help) sed -n '2,40p' "$0"; exit 0 ;;
        --*) echo "unknown flag: $1" >&2; exit 2 ;;
        *) INPUTS+=("$1"); shift ;;
    esac
done

[ "${#INPUTS[@]}" -gt 0 ] || { echo "no paths given. See --help." >&2; exit 2; }

# ── normalise: repo path → docroot-relative (rel), dedupe by rel ─────────────
declare -A SEEN=()
declare -a RELS=() SRCS=()
for p in "${INPUTS[@]}"; do
    [ -n "$p" ] || continue
    rel="${p#moodle-enhancement/}"                      # both trees map to public/<rel>
    [[ "$rel" =~ $DEPLOYABLE_RE ]] || { echo "skip (not under docroot): $p"; continue; }
    src="$REPO_ROOT/$p"
    [ -f "$src" ] || { echo "skip (not a file in repo): $p"; continue; }
    if [ -n "${SEEN[$rel]:-}" ]; then
        # same target from both trees — warn if the two sources differ
        if ! cmp -s "$src" "$REPO_ROOT/${SEEN[$rel]}"; then
            echo "WARN: $rel differs between $p and ${SEEN[$rel]} — using the first (${SEEN[$rel]})" >&2
        fi
        continue
    fi
    SEEN[$rel]="$p"; RELS+=("$rel"); SRCS+=("$src")
done

[ "${#RELS[@]}" -gt 0 ] || { echo "nothing deployable after filtering." >&2; exit 2; }

echo "── deploy plan → ${SSH_HOST}:${DOCROOT} ──"
for i in "${!RELS[@]}"; do echo "  ${RELS[$i]}"; done
echo "  upgrade: $([ "$DO_UPGRADE" = 1 ] && echo yes || echo no) · purge: yes · files: ${#RELS[@]}"

if [ "$YES" != 1 ]; then
    echo ""
    echo "DRY RUN — nothing sent. Re-run with --yes to deploy."
    exit 0
fi

# ── stage with docroot-relative structure, tar, manifest ─────────────────────
STAGE="$(mktemp -d)"; trap 'rm -rf "$STAGE"' EXIT
for i in "${!RELS[@]}"; do
    mkdir -p "$STAGE/$(dirname "${RELS[$i]}")"
    cp -p "${SRCS[$i]}" "$STAGE/${RELS[$i]}"
done
TGZ="$STAGE/.deploy.tgz"
tar -czf "$TGZ" -C "$STAGE" $(printf '%s ' "${RELS[@]}")
( cd "$STAGE" && sha256sum "${RELS[@]}" > .manifest )

TS="$(date +%Y%m%d-%H%M%S)"
# ONE source per scp call. A multi-source scp treats the destination as a DIRECTORY and
# would create /tmp/uat-deploy-$TS.tgz/ as a folder — the 2026-09-08 first-run failure
# ("tar: Cannot read: Is a directory"). The remote block's set -e stopped before any file
# was touched, but the deploy did not happen.
scp -o BatchMode=yes "$TGZ" "$SSH_HOST:/tmp/uat-deploy-$TS.tgz" >/dev/null
scp -o BatchMode=yes "$STAGE/.manifest" "$SSH_HOST:/tmp/uat-deploy-$TS.manifest" >/dev/null

# ── remote: backup existing (that exist) → extract → chown → verify ──────────
REL_LIST="$(printf '%s ' "${RELS[@]}")"
ssh -o BatchMode=yes "$SSH_HOST" "set -e
  cd '$DOCROOT'
  EXIST=''; for f in $REL_LIST; do [ -e \"\$f\" ] && EXIST=\"\$EXIST \$f\"; done
  if [ -n \"\$EXIST\" ]; then sudo tar -czf /tmp/uat-predeploy-backup-$TS.tgz \$EXIST && echo \"backup: /tmp/uat-predeploy-backup-$TS.tgz\"; fi
  sudo tar --no-same-owner -xzf /tmp/uat-deploy-$TS.tgz -C '$DOCROOT'
  sudo chown www-data:www-data $REL_LIST
  sudo chmod 644 $REL_LIST
  echo '── checksum verify ──'
  sha256sum -c /tmp/uat-deploy-$TS.manifest"

# ── remote: upgrade (version bumps) + purge ──────────────────────────────────
if [ "$DO_UPGRADE" = 1 ]; then
    ssh -o BatchMode=yes "$SSH_HOST" "sudo -u www-data php '$DIRROOT/admin/cli/upgrade.php' --non-interactive 2>&1 | tail -6"
fi
ssh -o BatchMode=yes "$SSH_HOST" "sudo -u www-data php '$DIRROOT/admin/cli/purge_caches.php'"

echo ""
echo "✅ deployed ${#RELS[@]} file(s). Backup on box: /tmp/uat-predeploy-backup-$TS.tgz"
echo "   Re-verify the change on https://academy2.airpay.ninja before calling it done."
