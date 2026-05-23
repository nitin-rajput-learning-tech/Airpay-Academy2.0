#!/usr/bin/env bash
# php-docker.sh - run PHP 8.4 inside the moodle-5.2-cli container against
# our local XAMPP Moodle tree.
#
# Why: WDAC blocks native PHP 8.4 binaries on this Windows host.
# See: docs/5.2-merge/PHP-8.4-INSTALL-WDAC-PIVOT.md
#
# Usage (from any cwd):
#   tools/php-docker.sh -v
#   tools/php-docker.sh -l local/airpay_core/classes/cm_navigation.php
#   tools/php-docker.sh ../admin/cli/upgrade.php --non-interactive
#
# The script transparently mounts:
#   C:\xampp\htdocs\moodle5\public   -> /var/www/html      (read-write)
#   C:\xampp\htdocs\moodle5          -> /var/www/moodle    (read-write)
#   C:\xampp\moodledata              -> /var/moodledata    (read-write)
#
# Network: --add-host=host.docker.internal:host-gateway gives the
# container access to MariaDB running on the Windows host at port 3306.

set -euo pipefail

IMAGE='moodle-5.2-cli'
MOODLE_ROOT='C:\xampp\htdocs\moodle5'
PUBLIC_DIR="${MOODLE_ROOT}\\public"
MOODLEDATA_DIR='C:\xampp\moodledata'

# Make sure the image exists.
if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    echo "Image $IMAGE not built yet. Building from Dockerfile.moodle-5.2..." >&2
    SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
    docker build -t "$IMAGE" -f "$SCRIPT_DIR/Dockerfile.moodle-5.2" "$SCRIPT_DIR" >&2
fi

# Run.
docker run --rm \
    -v "${PUBLIC_DIR}:/var/www/html" \
    -v "${MOODLE_ROOT}:/var/www/moodle" \
    -v "${MOODLEDATA_DIR}:/var/moodledata" \
    --add-host=host.docker.internal:host-gateway \
    --network=bridge \
    "$IMAGE" "$@"
