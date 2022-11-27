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
 * @package Bizlms 
 * @subpackage local_classroom
 */
namespace local_classroom\event;
use stdClass;
defined('MOODLE_INTERNAL') or die;
class category {
    /**
     * @param [object] $data
     * @return institute updated
     */
    public function category_update_instance($data) {
        global $DB;
        $DB->update_record('local_location_institutes', $data);
        return '';
    }
    public function institute_insert_instance($data) {
        global $DB, $CFG, $USER;
        $record            = new stdClass();
        $record->fullname  = $data->fullname;
        $record->shortname = $data->fullname;
        try {
            $categories = $DB->insert_record('local_classroom_categories', $record);
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return $categories;
    }
}
