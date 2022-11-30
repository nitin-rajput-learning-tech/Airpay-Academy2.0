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
$context = (new \local_evaluation\lib\accesslib())::get_module_context();
$PAGE->set_context($context);
require_login();
global $DB;
$path = 'index.php';
$out = "<ul>
<li><a href='eval_view.php'>".get_string('edit_items', 'local_evaluation')."</a></li>
<li><a href='eval_view.php'>".get_string('templates', 'local_evaluation')."</a></li>
<li><a href='$path'>".get_string('cancel')."</a></li>
</ul>";

$itemarray = $_GET['item'];
$item = (object) $itemarray;

$evaluation = $DB->get_record('local_evaluations', array('id'=>$item->evaluation));
$itemobj = evaluation_get_item_class($item->typ);
$printnr = ($evaluation->autonumbering && $item->itemnr) ? ($item->itemnr . '.') : '';
$output = $itemobj->custom_print_analysed($item, $printnr, 0);

echo $output;
die;


