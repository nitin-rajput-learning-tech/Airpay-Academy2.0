<?php
/*
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */

require_once(dirname(__FILE__) . '/../../config.php');

global $CFG, $USER, $DB, $PAGE;
$PAGE->requires->jquery();

$id = optional_param('id', $USER->id, PARAM_INT);

$PAGE->set_url('/local/users/profile.php', array('id' => $id));

$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$PAGE->requires->js_call_amd('local_users/newuser', 'load', array());
$PAGE->requires->js_call_amd('local_users/datatablesamd', 'load', array());
$PAGE->set_pagelayout('context_image');
$PAGE->set_pagelayout('standard');
require_login();

$strheading = get_string('viewprofile', 'local_users');
$PAGE->set_title(get_string('viewprofile', 'local_users'));
if (($id != $USER->id) && (!is_siteadmin())) {
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
        $selectsql = "SELECT  u.id  FROM {user} AS u
            WHERE u.id > 2 AND u.deleted = 0 AND u.id = {$id} ".$costcenterpathconcatsql;
        if(!$DB->record_exists_sql($selectsql)){
            throw new moodle_exception(get_string('nopermission', 'local_users'));
        }
}
echo $OUTPUT->header();
    $renderer   = $PAGE->get_renderer('local_users');
    echo $renderer->employees_profile_view($id);
echo $OUTPUT->footer();
