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
 * @subpackage local_learningplan
 */

require_once(dirname(__FILE__) . '/../../config.php');

global $DB, $USER, $CFG,$PAGE,$OUTPUT;
require_once($CFG->dirroot . '/local/learningplan/lib.php');
$planid = optional_param('id', 0, PARAM_INT);
$users = optional_param('user', 'courses', PARAM_TEXT);
global $DB, $USER, $CFG,$PAGE,$OUTPUT;

$learningplan = new local_learningplan\lib\lib();
$sql="SELECT llu.id,llu.userid,llu.planid FROM {local_learningplan_user} as llu ";

$allusers=$DB->get_records_sql($sql);
foreach($allusers as $all){
   
//$completed=$learningplan->complete_the_lep(170,139);
$completed=$learningplan->complete_the_lep($all->planid,$all->userid);

}


