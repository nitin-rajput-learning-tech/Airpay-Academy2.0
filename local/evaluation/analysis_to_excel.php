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
require_once("$CFG->libdir/excellib.class.php");

$id = required_param('id', PARAM_INT); // Evaluation id.
$courseid = optional_param('courseid', '0', PARAM_INT);

$url = new moodle_url('/local/evaluation/analysis_to_excel.php', array('id' => $id));

$PAGE->set_url($url);

require_login();
$context = (new \local_evaluation\lib\accesslib())::get_module_context($id);
require_capability('local/evaluation:viewreports', $context);

$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));

// Buffering any output. This prevents some output before the excel-header will be send.
ob_start();
ob_end_clean();

// Get the questions (item-names).
$evaluationstructure = new local_evaluation_structure($evaluation);
if (!$items = $evaluationstructure->get_items(true)) {
    print_error('no_items_available_yet', 'local_evaluation', $url);
}

$mygroupid =0;

// Creating a workbook.
$filename = "evaluation_" . clean_filename($evaluation->name) . ".xls";
$workbook = new MoodleExcelWorkbook($filename);

// Creating the worksheet.
error_reporting(0);
$worksheet1 = $workbook->add_worksheet();
error_reporting($CFG->debug);
$worksheet1->hide_gridlines();
$worksheet1->set_column(0, 0, 10);
$worksheet1->set_column(1, 1, 30);
$worksheet1->set_column(2, 20, 15);

// Creating the needed formats.
$xlsformats = new stdClass();
$xlsformats->head1 = $workbook->add_format(['bold' => 1, 'size' => 12]);
$xlsformats->head2 = $workbook->add_format(['align' => 'left', 'bold' => 1, 'bottum' => 2]);
$xlsformats->default = $workbook->add_format(['align' => 'left', 'v_align' => 'top']);
$xlsformats->value_bold = $workbook->add_format(['align' => 'left', 'bold' => 1, 'v_align' => 'top']);
$xlsformats->procent = $workbook->add_format(['align' => 'left', 'bold' => 1, 'v_align' => 'top', 'num_format' => '#,##0.00%']);

// Writing the table header.
$rowoffset1 = 0;
$worksheet1->write_string($rowoffset1, 0, userdate(time()), $xlsformats->head1);

// Get the completeds.
$completedscount = evaluation_get_completeds_group_count($evaluation, $mygroupid, false);
if ($completedscount > 0) {
    // Write the count of completeds.
    $rowoffset1++;
    $worksheet1->write_string($rowoffset1,
        0,
        $evaluation->name.': '.strval($completedscount),
        $xlsformats->head1);
}

$rowoffset1++;
$worksheet1->write_string($rowoffset1,
    0,
    get_string('questions', 'local_evaluation').': '. strval(count($items)),
    $xlsformats->head1);

$rowoffset1 += 2;
$worksheet1->write_string($rowoffset1, 0, get_string('item_label', 'local_evaluation'), $xlsformats->head1);
$worksheet1->write_string($rowoffset1, 1, get_string('question', 'local_evaluation'), $xlsformats->head1);
$worksheet1->write_string($rowoffset1, 2, get_string('responses', 'local_evaluation'), $xlsformats->head1);
$rowoffset1++;

foreach ($items as $item) {
    // Get the class of item-typ.
    $itemobj = evaluation_get_item_class($item->typ);
    $rowoffset1 = $itemobj->excelprint_item($worksheet1,
        $rowoffset1,
        $xlsformats,
        $item,
        $mygroupid,
        $courseid);
}

$workbook->close();
