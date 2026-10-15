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
 * The Sentientia fresh-install posture step of tools/uat/stage-a-install.sh (step 5c), as a file so the upgrade
 * rehearsal can replay it on its throw-away database. It does exactly what UAT's Stage A did on 2026-09-03:
 * theme sentientia, forcelogin off, site home on, empty front page, the two footer links off. The hi language pack is
 * NOT replayed (it needs the network). Writes only config values in the database named by --config. Never run it on
 * UAT: UAT already carries this posture and a re-run would also reset an admin's later changes.
 *
 *   php rehearse_posture.php --config=/absolute/path/to/config.php --i-am-the-rehearsal
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$config = null;
$confirmed = false;
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $config = substr($arg, strlen('--config='));
    }
    if ($arg === '--i-am-the-rehearsal') {
        $confirmed = true;
    }
}
if ($config === null || !is_readable($config) || !$confirmed) {
    fwrite(STDERR, "Usage: php rehearse_posture.php --config=/absolute/path/to/config.php --i-am-the-rehearsal\n");
    exit(2);
}
require($config);
if ($CFG->dbname !== 'uatreh52') {
    fwrite(STDERR, "refused: database is {$CFG->dbname}, this step is for the rehearsal database uatreh52 only\n");
    exit(2);
}
set_config('theme', 'sentientia');
set_config('forcelogin', 0);
set_config('enablemyhome', 1);
set_config('frontpage', '');
set_config('frontpageloggedin', '');
set_config('setuplink', '', 'tool_mobile');
set_config('showdataretentionsummary', 0, 'tool_dataprivacy');
echo 'posture: theme=sentientia forcelogin=0 enablemyhome=1 frontpage="" footer-links=off', PHP_EOL;
