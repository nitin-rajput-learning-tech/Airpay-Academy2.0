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
 * RETIRED: the local_costcenter to local_sentientia_org copy script. It refuses to run.
 *
 * ADR-032 (BizLMS data import, Phase 0 source freezing) replaced it with one shared
 * import framework. This script cannot be reused because it
 *  - skips the copy when the target already has any row, so a partial earlier copy
 *    made a later run a silent no-op;
 *  - reads theme_scheme, a column local_costcenter does not have;
 *  - copies a vancode string into an integer sort order;
 *  - overwrites the source timemodified with the time of the copy; and
 *  - resets the sequence before the commit, which on MySQL is DDL and commits
 *    implicitly.
 *
 * Use instead:
 *
 *   php local/sentientia_platform/cli/import_bizlms.php --status
 *   php local/sentientia_platform/cli/import_bizlms.php --feature=org                (dry run, writes nothing)
 *   php local/sentientia_platform/cli/import_bizlms.php --feature=org --apply --confirm=<fingerprint> --decisions=FILE
 *
 * This file sits in the plugin root, which a web server can reach, so it must never
 * define CLI_SCRIPT or load Moodle for a web request. It did both until 2026-09-30.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

fwrite(STDERR, "REFUSED: data_migration.php is retired (ADR-032).\n"
    . "Use: php local/sentientia_platform/cli/import_bizlms.php --feature=org --help\n");
exit(3);
