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
 * @subpackage local_request
 */
require_once("../../../config.php");
global $CFG, $DB;
$formPath = "$CFG->libdir/formslib.php";
require_once($formPath);
require_login();

require_once('../lib/displayLists.php');

$context = context_system::instance();
if (has_capability('block/request:viewrecord',$context)) {
} else {
  print_error(get_string('cannotviewrecord', 'block_request'));
}

$mid = required_param('id', PARAM_INT);

$rec = $DB->get_recordset_select('block_request_records', 'id = ' . $mid);
$displayModHTML = block_request_display_admin_list($rec, false, false, false, '');
echo '<div style="font-family: Arial,Verdana,Helvetica,sans-serif">';
echo $displayModHTML;
echo '</div>';
