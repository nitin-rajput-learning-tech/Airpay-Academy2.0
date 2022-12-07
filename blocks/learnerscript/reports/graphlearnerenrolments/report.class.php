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
/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
 * @author: Revanth Kumar
 * @date: 2021
  */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_graphlearnerenrolments extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'permissions','orderable','plot');
        $this->columns = ['graphlearnerenrolmentscolumns'=> ['month', 'enrolments']];    
        $this->parent = true;
        $this->orderable = array('month', 'enrolments');
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->defaultcolumn = 'm.month';
    }
    function init() {
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }        
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(DISTINCT m.month)";
    }
    function select() {
        $this->sql  = "SELECT m.month "; 
        parent::select();                
    }
    function from() {
        $this->sql .= "FROM (
                        SELECT 'January' AS
                        MONTH
                        UNION SELECT 'February' AS
                        MONTH
                        UNION SELECT 'March' AS
                        MONTH
                        UNION SELECT 'April' AS
                        MONTH
                        UNION SELECT 'May' AS
                        MONTH
                        UNION SELECT 'June' AS
                        MONTH
                        UNION SELECT 'July' AS
                        MONTH
                        UNION SELECT 'August' AS
                        MONTH
                        UNION SELECT 'September' AS
                        MONTH
                        UNION SELECT 'October' AS
                        MONTH
                        UNION SELECT 'November' AS
                        MONTH
                        UNION SELECT 'December' AS
                        MONTH
                        ) AS m ";
    }
    function joins() {
          parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
         parent::where();
    }   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('m.month');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){
        global $DB, $USER;
    }
    public function get_rows($learningpaths) {
        return $learningpaths;
    }
}
