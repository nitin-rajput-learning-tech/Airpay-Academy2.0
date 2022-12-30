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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;

class report_questionattempthours extends reportbase implements report {
  /**
   * [__construct description]
   * @param [type] $report           [description]
   * @param [type] $reportproperties [description]
   */
  public function __construct($report, $reportproperties) {
    parent::__construct($report);
    $this->components = array('columns', 'ordering', 'filters', 'permissions', 'plot');
    $columns = array('qcount','day','month','year','hour','ymdh');
    $columnsarray = array('questionattempthours' => $columns);
    $this->columns = $columnsarray;
    $this->parent = true;
    // $this->basicparams = array(['name' => 'course']);
    $this->filters = array('organization','departments','quiz');
    $this->defaultcolumn = 'qa.id';
    // $this->groupcolumn = ' YEAR(FROM_UNIXTIME(qa.timemodified)), MONTH(FROM_UNIXTIME(qa.timemodified)), DAY(FROM_UNIXTIME(qa.timemodified)), HOUR(FROM_UNIXTIME(qa.timemodified))';
    $this->groupcolumn = ' HOUR(FROM_UNIXTIME(qa.timemodified))';
    $this->orderable = array('qcount','day','month','year','hour','ymdh');
  }

  function init() {
    parent::init();
  }

  function count() {
    $this->sql = "SELECT COUNT( distinct FROM_UNIXTIME(qa.timemodified, '%h %p')) ";
  }

  function select() {
    $this->sql = "SELECT from_unixtime( qa.timemodified,  '%Y-%m-%d %H' ) as  ymdh, COUNT(qa.id) as qcount, YEAR(FROM_UNIXTIME(qa.timemodified)) as year, MONTH(FROM_UNIXTIME(qa.timemodified)) as month, DAY(FROM_UNIXTIME(qa.timemodified)) as day, FROM_UNIXTIME(qa.timemodified, '%h %p') as hour, from_unixtime( qa.timemodified,  '%Y-%m-%d %H' ) as  ymdh " ;
    parent::select();
  }

  function from() {
    $this->sql .= " FROM {question_attempts} AS qa ";
  }

  function joins() {
    $this->sql .=" JOIN {question} qst ON qst.id = qa.questionid 
    JOIN {question_usages} mqu ON qa.questionusageid = mqu.id
    JOIN {quiz_attempts} mquiza  ON mqu.id = mquiza.uniqueid
    JOIN {quiz} mq ON mq.id = mquiza.quiz ";

    parent::joins();
  }

  function where() {
    global $USER, $DB;
    // $courseid = $this->params['filter_course'];
    $this->sql .= " WHERE 1=1 AND  qa.timemodified >= 0 ";
    parent::where();
  }

  function search() {
    // if (isset($this->search) && $this->search) {
    //   $fields = array('c.fullname',"CONCAT(u.firstname,' ', u.lastname)",'u.email', 'u.open_employeeid');
    //   $fields = implode(" LIKE '%$this->search%' OR ", $fields);
    //   $fields .= " LIKE '%$this->search%' ";
    //   $this->sql .= " AND ($fields) ";
    // }
  }

  function filters() {
    if(isset($this->params['filter_quiz']) && $this->params['filter_quiz'] > 0) {
        $this->sql .= " AND mq.id = :quizid ";
        $this->params['quizid'] = $this->params['filter_quiz'];
    }    
  }

  public function get_rows($questions) {
    return $questions;
  }
}