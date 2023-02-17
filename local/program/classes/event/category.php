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
namespace local_program\event;
use stdClass;
defined('MOODLE_INTERNAL') or die;
class category {
    /**
     * @param [object] $data
     * @return institute updated
     */
    public function category_update_instance($data){
        global $DB;
        $DB->update_record('local_location_institutes', $data);
        return '';
    }
     public function institute_insert_instance($data){
        global $DB, $CFG, $USER;
          $record = new stdClass();
          // $record->costcenter = $data->costcenter;
          $record->fullname = $data->fullname;
          $record->shortname = $data->fullname;
          // $record->address = $data->address;
          // $record->visible = 1;
          // $record->institute_type = $data->institute_type;
          // $record->usercreated = $USER->id;
          // $record->timecreated = time();
          try{
        $categories = $DB->insert_record('local_program_categories', $record);
            }catch(dml_exception $ex) {
        print_error($ex);
        }
        return $categories;
    }
}
