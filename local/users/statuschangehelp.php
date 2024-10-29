<?php
/*
 * This file is part of eAbyas
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_login();
global $CFG, $DB;
$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$PAGE->set_url('/local/users/statuschangehelp.php');
$PAGE->set_pagelayout('standard');
$strheading = get_string('pluginname', 'local_users') . ' : ' . get_string('manual', 'local_users');
$PAGE->set_title($strheading);
if (!has_capability('local/users:bulkstatuschange', $categorycontext)) {
    throw new moodle_exception('nopermissions');
}

$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add(get_string('pluginname', 'local_users'), new moodle_url('/local/users/index.php'));
$PAGE->navbar->add(get_string('changebulkuserstatus', 'local_users'));
echo $OUTPUT->header();
if (isset($CFG->allowframembedding) && ! $CFG->allowframembedding) {
    echo $OUTPUT->box(get_string('helpmanual', 'local_users'));
    echo '<div style="float:right;" class="mb-2"><a href="sync/changestatus.php"><button>' .
    get_string('back_upload_userstatus', 'local_users') . '</button></a></div>';
}
echo get_string('statuschangehelp', 'local_costcenter');
echo $OUTPUT->footer();
