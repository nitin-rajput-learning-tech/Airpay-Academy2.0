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
 * List the tool provided 
 *
 * @package   local
 * @subpackage  courses
 * @copyright  2015 Rajut 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $DB,$OUTPUT,$USER,$CFG,$PAGE;
require('../../config.php');
require_login();
$systemcontext = context_system::instance();
$id        = optional_param('id', 0, PARAM_INT);
$PAGE->set_context($systemcontext);
$pageurl = new moodle_url('/local/courses/info.php');
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('iltfullpage');

echo $OUTPUT->header();
echo '<style>
        #error {
            margin-top: 20px;
            background-color: #03a9f4;
            border-radius: 10px !important;
            padding-top: 30px;
            padding-bottom: 30px;
            box-shadow: 4px 4px 5px #dedcdc;
        }
        #error .image_container {
            margin: auto;
            border: 1px solid #ccc;
            float: none;
            width: 52%;
            border-radius: 10px;
            background-color: #F6F8F7;
            box-shadow: 4px 4px 5px #909090;
        }
        #error .image_container > img {
            width: 100%;
            border-radius: 10px 10px 0px 0px;
        }
        #error .desc{
            padding: 15px 0px 100px;
            border-radius: 0px 0px 10px 10px;
            text-align: center;
            background-color: #F6F8F7;
        }
        @media(max-width:991px) {
            #error,
            body#page-local-error.pagelayout-iltfullpage #page_container_wrapper div#page_content_wrapper section#region-main{
                width:100%;
            }
        }
        @media (max-width:425px) {
            #error .image_container {
                width: 80%;
            }
            #error .desc {
                padding: 15px 5px 50px;
            }
        }
      </style>';
	  switch($id) {
	    case 1:
		  $errormsg= "Sorry... you dont have access to this page";
		  break;
		case 2:
          $errormsg= "Sorry... this course is not part of your department..";
		  break;
	}

echo '  <div class="col-md-2"></div>
        <div id="error" class="col-md-8" align="center">
                <div class="image_container">
                    <img src="' . $CFG->wwwroot .'/local/errornew.jpg">
                    <div class="desc">'.$errormsg.'</div>
                </div>    
        </div>
        <div class="col-md-2"></div>';

echo $OUTPUT->footer();

