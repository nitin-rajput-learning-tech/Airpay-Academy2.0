<?php
namespace local_skillrepository\event;
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
 * @subpackage local_skillrepository
 */

use context_system;
require_once($CFG->dirroot.'/local/costcenter/lib.php');
class insertrepository{

    function skillrepository_opertaions($table, $operation, $object, $column, $value) {
        global $DB, $CFG, $OUTPUT, $USER,$PAGE;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        switch($operation){
            case 'insert':
                $object->usercreated = $USER->id;
                $object->timecreated = time();
                $process = $DB->insert_record($table, $object);
            break;
            case 'update':
                $object->usermodified = $USER->id;
                $object->timemodified = time();
                $process = $DB->update_record($table, $object);
            break;
            case 'delete':
                $process = $DB->delete_records($table, array($column=>$value));
            break;
            case 'fetch-single':
                $process = $DB->get_record($table, array($column=>$value));
            break;
            case 'fetch-multiple':
                if($column == null)
                    $process = $DB->get_records($table);
                else
                    $process = $DB->get_records($table, array($column=>$value));
            break;
            case 'exist':
                $process = $DB->record_exists($table, array($column=>$value));
            break;
            case 'error-operation':
                $process = print_error(get_string('error_operation', 'local_skillrepository'));
            break;
        }
        return $process;
    }
}
