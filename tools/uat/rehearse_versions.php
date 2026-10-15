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
 * READ-ONLY plugin version table for the 5.2 -> 5.3 upgrade rehearsal (tools/uat/rehearse_52_to_53.sh) and for the
 * same check on UAT after the real upgrade. One tab-separated line per plugin:
 *
 *   component  versiondb  versiondisk  release  status  origin
 *
 * status is Moodle's own plugin status (uptodate, upgrade, new, downgrade, missing, delete); origin is "std" for a
 * plugin Moodle ships and "add" for an additional one (every Sentientia plugin). The first lines are "#core ..." rows
 * (version, release, branch). Exit 0 when every plugin on disk is installed and up to date and nothing is missing from
 * disk, 1 otherwise. Writes nothing.
 *
 *   php rehearse_versions.php --config=/absolute/path/to/config.php > versions.tsv
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
    fwrite(STDERR, "Usage: php rehearse_versions.php --config=/absolute/path/to/config.php\n");
    exit(2);
}
require($config);
require_once($CFG->libdir . '/adminlib.php');

echo "#core\tversion\t" . get_config('', 'version') . "\n";
echo "#core\trelease\t" . get_config('', 'release') . "\n";
echo "#core\tbranch\t" . get_config('', 'branch') . "\n";

$pm = core_plugin_manager::instance();
$pm->reset_caches();
$bad = 0;
$count = 0;
foreach ($pm->get_plugins() as $type => $plugins) {
    foreach ($plugins as $name => $info) {
        $count++;
        $status = $info->get_status();
        $db = $info->versiondb ?? '';
        $disk = $info->versiondisk ?? '';
        $release = $info->release ?? '';
        if (!in_array($status, [core_plugin_manager::PLUGIN_STATUS_UPTODATE, core_plugin_manager::PLUGIN_STATUS_NODB], true)) {
            $bad++;
        }
        printf("%s\t%s\t%s\t%s\t%s\t%s\n", $info->component, $db, $disk, $release, $status,
            $info->is_standard() ? 'std' : 'add');
    }
}
fwrite(STDERR, "$count plugins, $bad not up to date or missing\n");
exit($bad ? 1 : 0);
