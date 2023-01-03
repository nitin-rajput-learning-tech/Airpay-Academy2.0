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
 * @subpackage local_evaluation
 */


require_once("../../config.php");
require_once("lib.php");

$current_tab = 'analysis';

$id = required_param('id', PARAM_INT);  
global $USER;
$url = new moodle_url('/local/evaluation/analysis.php', array('id'=>$id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
require_login();
redirect(new moodle_url('/local/evaluation/index.php'));
$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));

$evaluationstructure = new local_evaluation_structure($evaluation);

$context = (new \local_evaluation\lib\accesslib())::get_module_context($id);
$PAGE->set_context($context);
if (!$evaluationstructure->can_view_analysis()) {
    print_error(get_string('cannotaccess', 'local_evaluation'));
}

/// Print the page header

$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
$PAGE->navbar->add(get_string("manageevaluation", 'local_evaluation'), new moodle_url('index.php'));
$PAGE->navbar->add($evaluation->name);
echo $OUTPUT->header();
if(!(is_siteadmin())){
    if(explode('/',$evaluation->open_path)[1] !=explode('/',$USER->open_path)[1]){
        print_error(get_string('cannotaccess', 'local_evaluation'));
    }
}

echo html_writer::start_tag('div', array('class'=>'tab_responses'));
echo html_writer::tag('h4', get_string('analysis_header', 'local_evaluation'), array('class'=>''));
echo html_writer::end_tag('div');

$content =  html_writer::start_tag('div', array('class'=>'w-full pull-left p-15'));
    $summary = new local_evaluation\output\summary($evaluationstructure, 0);
    $content .=  html_writer::start_tag('div', array('class'=>'col-md-8 col-sm-12 col-12 pull-left summary_details'));
        $content .= $OUTPUT->render_from_template('local_evaluation/summary', $summary->export_for_template($OUTPUT));
    $content .= html_writer::end_tag('div');
    // Button "Export to excel".
    if (has_capability('local/evaluation:viewreports', $context) && $evaluationstructure->get_items()) {
        $content .=  html_writer::start_tag('div', array('class'=>'col-md-4 col-sm-12 col-12 pull-left'));
            $aurl = new moodle_url('/local/evaluation/analysis_to_excel.php', ['sesskey' => sesskey(), 'id' => $id]);
            $content .= html_writer::start_tag('span', array('class'=>'pull-right text-xs-center resp_export btn'));
            $content .= html_writer::tag('span', '<i class="fa fa-file-text-o" aria-hidden="true"></i>', array('class'=>'resp_export_icon pr-2'));
            $content .= html_writer::link($aurl, get_string('export_to_excel', 'local_evaluation'), array('class'=>'pull-right'));
            $content .= html_writer::end_tag('span');
        $content .= html_writer::end_tag('div');
    }
$content .= html_writer::end_tag('div');
echo $content;
$items = $evaluationstructure->get_items(true);

$check_anonymously = true;

echo '<div class="pull-left w-100">';
if ($check_anonymously) {
    // Print the items in an analysed form.
    foreach ($items as $item) {
        $itemobj = evaluation_get_item_class($item->typ);
        $printnr = ($evaluation->autonumbering && $item->itemnr) ? ($item->itemnr . '.') : '';
        echo $itemobj->print_analysed($item, $printnr, 0);
        
    }
    $backurl = new moodle_url('/local/evaluation/eval_view.php', array('id'=>$id));
    echo $OUTPUT->continue_button($backurl);
} else {
    echo $OUTPUT->heading_with_help(get_string('insufficient_responses_for_this_group', 'local_evaluation'),
                                    'insufficient_responses',
                                    'local_evaluation', '', '', 3);
}
echo '</div>';

echo $OUTPUT->footer();

