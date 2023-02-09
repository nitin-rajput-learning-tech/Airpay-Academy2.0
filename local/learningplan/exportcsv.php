<?php
 /**
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
 
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');

global $CFG, $USER,$PAGE,$OUTPUT,$DB;
require_once($CFG->dirroot . '/local/learningplan/lib.php');
$filtervalues = json_decode($_REQUEST['formdata']);
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context();
require_login(); 
$table = new html_table();
$table->id = "learningplan";
$table->head[] = get_string('planname','local_learningplan');
$table->head[] = get_string('plancode','local_learningplan');
$table->head[] = get_string('coursescount','local_learningplan');
$learningplan = new \local_learningplan\learningplan();
$plandata = $learningplan->get_learningplans($filtervalues, 0, 0);
$data = [];
$planlib = new \local_learningplan\lib\lib();
foreach($plandata AS $plan){
    $courses = $planlib->get_learningplan_assigned_courses($plan->id);
    $data[] = [$plan->name, $plan->shortname, count($courses)];
}

$table->id = "users";
$table->data = $data;
 require_once($CFG->libdir . '/csvlib.class.php');
    $matrix = array();
    $filename = 'learningplans';
    if (!empty($table->head)) {
        $countcols = count($table->head);
        $keys = array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {
            $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
        }
    }
    if (!empty($table->data)) {
        foreach ($table->data as $rkey => $row) {
            foreach ($row as $key => $item) {
                $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
            }
        }
    }
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
    foreach ($matrix as $ri => $col) {
        $csvexport->add_data($col);
    }
    $csvexport->download_file();
    exit;

 
