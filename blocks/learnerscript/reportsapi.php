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

/** Learner Script
 * A Moodle block for creating LearnerScript Reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */
require_once("../../config.php");
// use \block_learnerscript\local\ls as ls;

require_login();
echo $costcenter = optional_param('costcenterid', 0, PARAM_INT);
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_title('Api');
$PAGE->set_pagelayout('report');
$PAGE->set_url('/blocks/learnerscript/reportsapi.php');

$reportid = 137;
$reporttype = 'table';
$basicparams = json_encode(
    array(
    	"reportid" => "$reportid",
    	"id" => "$reportid",
        "filter_organization" => "$costcenter",
    )
);
$instanceid = $reportid;

$PAGE->requires->js_call_amd('block_learnerscript/reportsapi', 'init',array($reportid, $reporttype, $basicparams, $instanceid));
echo $OUTPUT->header();

echo "<p class='apicontent'>Hello World</p>";

echo $OUTPUT->footer();



