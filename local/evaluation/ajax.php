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


if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require(__DIR__.'/../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sesskey = optional_param('sesskey', false, PARAM_TEXT);
$itemorder = optional_param('itemorder', false, PARAM_SEQUENCE);

$evaluation = $DB->get_record('local_evaluations', array('id'=>$id), '*', MUST_EXIST);

require_sesskey();

$context = (new \local_evaluation\lib\accesslib())::get_module_context();
require_login();
require_capability('local/evaluation:edititems', $context);

$return = false;

switch ($action) {
    case 'saveitemorder':
        $itemlist = explode(',', trim($itemorder, ','));
        if (count($itemlist) > 0) {
            $return = evaluation_ajax_saveitemorder($itemlist, $evaluation);
        }
        break;
}

echo json_encode($return);
die;
