
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
 * TODO describe file view_transactions
 *
 * @package    local_biz_cart
 * @copyright  2024 Moodle India <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();
$courseid = optional_param('courseid', '', PARAM_INT);
$PAGE->requires->css('/local/notifications/css/jquery.dataTables.min.css', true);
$PAGE->requires->js_call_amd('local_costcenter/newcostcenter', 'downloadtrigger', array());
$PAGE->requires->js(new moodle_url('/local/notifications/js/jquery.dataTables.min.js'),true);
$PAGE->set_context($categorycontext);
$PAGE->set_heading(get_string('view_payment_log', 'local_biz_cart'));
$url = new moodle_url('/local/biz_cart/log_report.php', []);
$PAGE->navbar->add(get_string('manage_courses','local_courses'), new moodle_url("/local/courses/courses.php"));
$PAGE->navbar->add(get_string('view_payment_log','local_biz_cart'));
$PAGE->set_url($url);
echo $OUTPUT->header();
$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
if(!has_capability('local/courses:view', $categorycontext) && !has_capability('local/courses:manage', $categorycontext) ){
    throw new moodle_exception("You don't have permissions to view this page.");
}
$formattype = 'list';
$renderer = $PAGE->get_renderer('local_biz_cart');
$filterparams = $renderer->view_course_transaction_log(true,$formattype);
// for filtering users we are providing form
$formdata = new stdClass();
$formdata->courses = $courseid;
$mform = logs_filters_form($filterparams, (array)$formdata);
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/courses/courses.php');
} else{
    $filterdata =  $mform->get_data();
    if($filterdata){
        $collapse = false;
    } else{
        $collapse = true;
    }
}
if(empty($filterdata) && !empty($jsonparam)){
    $filterdata = (array)json_decode($jsonparam);
    foreach($thisfilters AS $filter){
        if(empty($filterdata->$filter)){
            unset($filterdata->$filter);
        }
    }
    $mform->set_data($filterdata);
}
if(!empty($courseid)){
        $mform->set_data($formdata);
}
if($filterdata){
    $collapse = false;
    $show = 'show';
} else{
    $collapse = true;
    $show = '';
}
echo '<a class="btn-link btn-sm" data-toggle="collapse" data-target="#local_courses-filter_collapse" aria-expanded="false" aria-controls="local_courses-filter_collapse">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
echo  '<div class="collapse '.$show.'" id="local_courses-filter_collapse">
            <div id="filters_form" class="card card-body p-2">';
                $mform->display();
echo        '</div>
        </div>';
$filterparams['submitid'] = 'form#filteringform';
$filterparams['filterdata'] = json_encode($formdata);
echo $OUTPUT->render_from_template('local_costcenter/global_filter', $filterparams);
$renderer = $PAGE->get_renderer('local_biz_cart');
echo $renderer->view_course_transaction_log(false, $formattype);
echo $OUTPUT->footer();