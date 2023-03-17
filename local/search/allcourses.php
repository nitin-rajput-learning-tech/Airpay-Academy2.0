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
 * @subpackage local_search
 */

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $USER, $CFG, $PAGE, $OUTPUT;
require_once($CFG->dirroot . '/local/search/renderer.php');
$systemcontext = \local_costcenter\lib\accesslib::get_module_context();
$PAGE->set_context($systemcontext);
require_login();
if(!has_capability('local/search:viewcatalog', $systemcontext)){
    print_error('No permissions');
}

$PAGE->set_url('/local/search/allcourses.php');
$PAGE->set_title(get_string('e_learning_courses','local_search'));
$PAGE->set_heading(get_string('leftmenu_search', 'local_search'));
$PAGE->set_pagelayout('context_image');
$PAGE->navbar->add(get_string('e_learning_courses','local_search'));

$category = optional_param('category', -1, PARAM_INT);
$type = optional_param('type', 0, PARAM_INT);
$global_search = optional_param('g_search', 0, PARAM_RAW);

$PAGE->requires->jquery();

$PAGE->requires->js('/local/search/js/angular.min.js');
$PAGE->requires->js('/local/search/js/custom.js');
$PAGE->requires->js('/local/search/js/dirPagination.js');
$PAGE->requires->js_call_amd('local_search/courseinfo', 'load', array());
$renderer = $PAGE->get_renderer('local_search');

local_search_include_search_js();
$plugininfo = local_search_get_enabled_searchplugin_info();


use local_search\output\searchlib;

//new one
define('ELE',1);
define('ICOURSE',2);
define('LP',-2);
define('ILT',-1);
define('MOOC',4);
define('LEARNINGPATH',-2);
define('PERPAGE',6);
define('LPCOURSE',8);
define('BLENDED',9);
echo $OUTPUT->header();
$return = array();
$return["loader"] = $CFG->wwwroot.'/local/ajax-loader.gif';

$renderer = $PAGE->get_renderer('local_search');
$content = "<div ng-app = 'catalog' class='' id='allcourses_section'>
    <div ng-controller = 'courseController' class='row'>".
        $OUTPUT->render_from_template('local_search/filters', $return).
   "<div class='col-md-9 col-lg-9 col-xl-10 col-sm-12 content_section'>
            <div id='' class='box jplist'>
                <div class='' ng-init='init(6)'>
".$OUTPUT->render_from_template('local_search/catalog_selectbox', $return).
                    "<div class='w-100 pull-left course_view_list_container'>
                        <div class='col-12 pull-left pl-15'>
                            <div ng-show='showLoader' class='loader_container'>
                                <img src= ".$return['loader']." />
                            </div>

                            <div ng-if=\"numberofrecords > 0\" class=' clearfix row'>
                                <div dir-paginate='record in courseinfo | filter:q | itemsPerPage: 15' total-items=numberofrecords class=' col-12 col-md-4  catcourses_list active'>
                                        <div ng-if=\"record.id >=1\" class='card coursecard'>
                                            <div ng-if=\" tab == 6\">";
                                        foreach($plugininfo AS $plugindata){
                                            $content .= "<div ng-if='record.type == ".$plugindata['type']."'>
                                                    <div class=\"w-full pull-left cr_courses\">".$OUTPUT->render_from_template($plugindata['templatename'], $return)."
                                                    </div>
                                                </div>";
                                        }

                                        $content .= "</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>".
                            $OUTPUT->render_from_template('local_search/recordnotfound', $return)
                    ."</div>
                </div>
                <div ng-if=\"numberofrecords > 0\">
                    <div class='col-12 pull-left'>
                        <dir-pagination-controls boundary-links='true' on-page-change='pageChangeHandler(newPageNumber, tab)' template-url='dirPagination.tpl.html'>
                        </dir-pagination-controls>
                    </div>
                </div>
            </div>

    </div>
</div>";
echo $content;

// <div ng-if='record.type == 1'>".$OUTPUT->render_from_template('local_search/elearning', $return)."
//                                                 </div>
//                                                 <div ng-if='record.type == 2'>
//                                                     <div class=\"w-full pull-left cr_courses\">".$OUTPUT->render_from_template('local_search/classroom', $return)."
//                                                     </div>
//                                                 </div>
//                                                 <div ng-if='record.type == 3'>
//                                                     <div class=\"w-full pull-left cr_courses\">".$OUTPUT->render_from_template('local_search/learningplan', $return)."
//                                                     </div>
//                                                 </div>

echo $OUTPUT->footer();
