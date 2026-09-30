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
 * RETIRED: the BizLMS to Sentientia copy script. It refuses to run.
 *
 * ADR-032 (BizLMS data import, Phase 0 source freezing) replaced it with one shared
 * import framework. This script cannot be reused because it
 *  - skips any target that already holds rows, so a partial earlier copy made a later
 *    run a silent no-op;
 *  - drops every column it cannot map without saying so, because import_record()
 *    skips unknown fields;
 *  - loads the whole source table into memory; and
 *  - calls reset_sequence inside the open transaction, which on MySQL is DDL and
 *    commits implicitly, so the rollback could not undo the rows already written.
 *
 * Use instead (see that script's header for the options and the guards):
 *
 *   php local/sentientia_platform/cli/import_bizlms.php --status
 *   php local/sentientia_platform/cli/import_bizlms.php --all                      (dry run, writes nothing)
 *   php local/sentientia_platform/cli/import_bizlms.php --all --apply --confirm=<fingerprint> --decisions=FILE
 *
 * The capability migration that used to live here is not part of the import; it was tied
 * to the retired local_airpay_ capability names.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Never define CLI_SCRIPT or load Moodle for a web request.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

fwrite(STDERR, "REFUSED: migrate_all.php is retired (ADR-032).\n"
    . "Use: php local/sentientia_platform/cli/import_bizlms.php --help\n");
exit(3);
