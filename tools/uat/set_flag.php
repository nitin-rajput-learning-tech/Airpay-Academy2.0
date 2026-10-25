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
 * Set ONE feature flag site-wide (tenant 0, customer 0), recorded as done by the site admin with a reason.
 *
 *   sudo -u www-data php /tmp/set_flag.php --config=/var/www/html/moodle5.2/config.php \
 *       --flag=sentientia.dashboard.blocksdrawer.enabled --value=on --reason="Nitin 2026-10-24: dashboard blocks drawer"
 *
 * --value is on, off or unset (unset removes the site-wide override, so the registered default applies again).
 * Only run it on Nitin's call: flags are never flipped on UAT without it. Prints the resolved value before and after.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$opts = ['config' => null, 'flag' => null, 'value' => null, 'reason' => ''];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(config|flag|value|reason)=(.*)$/s', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    }
}
if (!$opts['config'] || !is_readable($opts['config']) || !$opts['flag']
        || !in_array($opts['value'], ['on', 'off', 'unset'], true) || trim($opts['reason']) === '') {
    fwrite(STDERR, "Usage: php set_flag.php --config=/abs/config.php --flag=<key> --value=on|off|unset --reason=\"...\"\n");
    exit(2);
}
require($opts['config']);

\core\session\manager::set_user(get_admin());
$flags = '\\local_sentientia_platform\\feature_flags';
$before = $flags::is_enabled_for($opts['flag'], 0, 0);
$value = $opts['value'] === 'unset' ? null : ($opts['value'] === 'on');
$flags::set($opts['flag'], 0, $value, null, $opts['reason']);
$after = $flags::is_enabled_for($opts['flag'], 0, 0);
printf("%s: %s -> %s (site-wide override %s)\n", $opts['flag'], var_export($before, true), var_export($after, true),
    $value === null ? 'removed' : var_export($value, true));
