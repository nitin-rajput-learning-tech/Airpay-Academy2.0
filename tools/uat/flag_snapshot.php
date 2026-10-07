<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * READ-ONLY feature-flag snapshot: one line per registered flag (key, default, resolved site-wide, which
 * overrides exist). Run it before and after a deploy and diff the two files: a deploy must add flags only
 * as default OFF / resolved OFF and must not change any existing flag's resolved value.
 *
 *   sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php > /tmp/flags-before.txt
 *
 * Writes nothing. Safe on any box (UAT or local); it never sets a flag.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$config = null;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $config = substr($arg, strlen('--config='));
    }
}
if ($config === null || !is_readable($config)) {
    fwrite(STDERR, "Usage: php flag_snapshot.php --config=/absolute/path/to/config.php\n");
    exit(2);
}
require($config);

$flags = \local_sentientia_platform\feature_flags::all();
foreach ($flags as $key => $f) {
    $overrides = [];
    foreach (['global', 'customer', 'tenant', 'legacy_tenant'] as $kind) {
        if (!empty($f['has_' . $kind . '_override'])) {
            $overrides[] = $kind;
        }
    }
    printf("%s default=%s resolved=%s overrides=%s\n", $key, $f['default'] ? 'ON' : 'off',
        $f['resolved'] ? 'ON' : 'off', $overrides ? implode(',', $overrides) : '-');
}
fwrite(STDERR, count($flags) . " flags\n");
