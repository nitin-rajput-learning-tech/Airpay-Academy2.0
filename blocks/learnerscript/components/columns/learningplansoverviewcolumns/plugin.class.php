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
 * @package block_learnerscript
 */
use block_learnerscript\local\pluginbase;

class plugin_learningplansoverviewcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('learningplansoverviewcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('learningplansoverview');
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    // Data -> Plugin configuration data.
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG,$OUTPUT;
            switch ($data->column) {
                case 'optionalcourses':
                    $sql = "SELECT c.id, c.fullname
                            FROM {local_learningplan_courses} AS llc 
                            JOIN {course} c ON c.id = llc.courseid 
                            WHERE llc.planid = :planid AND nextsetoperator = :mandval";

                    $params = array('planid'=>$row->learningpathid, 'mandval'=>'or');
                    $optinalcourses = $DB->get_records_sql_menu($sql, $params);
                    if($optinalcourses){
                        $row->{$data->column} = implode(', ', $optinalcourses);
                    }else{
                        $row->{$data->column} = 'NA';
                    }
                break;
                case 'mandatorycourses':
                    $sql = "SELECT c.id, c.fullname
                            FROM {local_learningplan_courses} AS llc 
                            JOIN {course} c ON c.id = llc.courseid 
                            WHERE llc.planid = :planid AND nextsetoperator = :optval";

                    $params = array('planid'=>$row->learningpathid,'optval'=>'and');
                    $mandatorycourses = $DB->get_records_sql_menu($sql, $params);
                    if($mandatorycourses){
                        $row->{$data->column} = implode(', ', $mandatorycourses);
                    }else{
                        $row->{$data->column} = 'NA';
                    }
                break;                
            }
            
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}
