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
 * READ-ONLY snapshots for the 5.2 -> 5.3 upgrade rehearsal (tools/uat/rehearse_52_to_53.sh), taken before and after
 * the upgrade and compared by rehearse_helper.py. Works on any Moodle database; writes nothing.
 *
 *   php rehearse_counts.php --config=/absolute/path/to/config.php --what=tables   > counts.tsv
 *       one line per table (prefix removed):  table <TAB> row count
 *   php rehearse_counts.php --config=/absolute/path/to/config.php --what=config   > config.tsv
 *       one line per core / plugin setting:   plugin <TAB> name <TAB> value
 *       values are cut at 200 characters; a setting whose name looks secret (pass, secret, salt, key, token, cert) is
 *       printed as the first 8 hex digits of its sha1 so a change shows but the value never reaches a log.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$config = null;
$what = 'tables';
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $config = substr($arg, strlen('--config='));
    }
    if (strpos($arg, '--what=') === 0) {
        $what = substr($arg, strlen('--what='));
    }
}
if ($config === null || !is_readable($config) || !in_array($what, ['tables', 'config'], true)) {
    fwrite(STDERR, "Usage: php rehearse_counts.php --config=/absolute/path/to/config.php --what=tables|config\n");
    exit(2);
}
require($config);

if ($what === 'tables') {
    $tables = $DB->get_tables(false);
    sort($tables);
    foreach ($tables as $table) {
        printf("%s\t%d\n", $table, $DB->count_records($table));
    }
    fwrite(STDERR, count($tables) . " tables\n");
    exit(0);
}

$secret = '/pass|secret|salt|key|token|cert|credential/i';
$rows = 0;
$show = function (string $plugin, string $name, $value) use ($secret, &$rows): void {
    $value = (string) $value;
    if (preg_match($secret, $name)) {
        $value = 'sha1:' . substr(sha1($value), 0, 8);
    } else {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        if (strlen($value) > 200) {
            $value = substr($value, 0, 200) . '...';
        }
    }
    $rows++;
    printf("%s\t%s\t%s\n", $plugin, $name, $value);
};
$core = $DB->get_records('config', null, 'name ASC', 'id, name, value');
foreach ($core as $r) {
    $show('core', $r->name, $r->value);
}
$plugins = $DB->get_records('config_plugins', null, 'plugin ASC, name ASC', 'id, plugin, name, value');
foreach ($plugins as $r) {
    $show($r->plugin, $r->name, $r->value);
}
fwrite(STDERR, "$rows settings\n");
exit(0);
