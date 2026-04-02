<?php
//
// This file is part of eAbyas
//
// Copyright eAbyas Info Solutons Pvt Ltd, India
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local
 * @subpackage  users
 * @author eabyas  <info@eabyas.in>
 **/

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/../../../config.php');
require_login();
global $CFG, $OUTPUT;
require_once($CFG->libdir . '/csvlib.class.php');

// Instantiate a DateTime with microseconds.
echo $OUTPUT->header();
$d = new DateTime('NOW');
$filedate = $d->format('Ymd');

$content = file_get_contents($CFG->dirroot.'/local/users/sync/csv/uploaduser_'.$filedate.'.csv');
if (!empty($content)) {
    $STD_FIELDS = array('organization', 'username', 'employee_id', 'employee_name', 'first_name', 'middle_name', 'last_name',
     'department', 'sub_department', 'address', 'zone_region', 'area', 'city', 'role_designation', 'group', 'level', 'team',
      'client', 'grade', 'gender', 'mobileno', 'email', 'marital_status', 'dob', 'doj', 'state_name', 'employee_status',
       'reportingmanager_code', 'reportingmanager_name', 'reportingmanager_email', 'dol', 'dor', 'country', 'officialmail');

    $PRF_FIELDS = array();
    $returnurl = new moodle_url('/local/users/index.php');

    $formdata = new stdClass();

    $formdata->option = 3;
    $formdata->enrollmentmethod = 1;
    $formdata->encoding = "UTF-8";
    $formdata->delimiter_name = "comma";
    $iid = csv_import_reader::get_new_iid('bulkuploadfile');
    $cir = new csv_import_reader($iid, 'bulkuploadfile');

    $readcount = $cir->load_csv_content($content, 'UTF-8', 'comma');
    $cir->init();
    $linenum = 1;
    $progresslibfunctions = new local_users\cron\progresslibfunctions();
    $filecolumns = $progresslibfunctions->uu_validate_user_upload_columns($cir, $STD_FIELDS, $PRF_FIELDS, $returnurl);

    $hrms = new local_users\cron\cronfunctionality();

    $hrms->main_hrms_frontendform_method($cir, $filecolumns, $formdata);

} else {
    echo get_string('file_notfound_msg', 'local_users');
}

echo $OUTPUT->footer();
