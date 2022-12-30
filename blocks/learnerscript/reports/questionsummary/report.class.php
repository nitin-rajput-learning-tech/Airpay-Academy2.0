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

class report_questionsummary extends reportbase implements report {
  /**
   * [__construct description]
   * @param [type] $report           [description]
   * @param [type] $reportproperties [description]
   */
  public function __construct($report, $reportproperties) {
    parent::__construct($report);
    $this->components = array('columns', 'ordering', 'filters', 'permissions', 'plot');
    $columns = array('questionname','average','correct','incorrect','total');
    $this->orderable = array('questionname','average','correct','incorrect','total');
    $columnsarray = array('questionsummary' => $columns);
    $this->columns = $columnsarray;
    $this->parent = true;
    // $this->basicparams = array(['name' => 'course']);
    $this->filters = array('organization','departments','quiz');
    $this->defaultcolumn = 'qst.id';
    $this->groupcolumn = 'qst.id';
  }

  function init() {
      parent::init();
  }

  function count() {
      $this->sql = "SELECT COUNT(distinct qst.id) ";
  }

  function select() {
    $this->sql = "SELECT qst.id, qst.questiontext AS 'question',

( select count(qa.id) 
 
FROM {quiz_attempts} quiza
JOIN {quiz} q ON q.id=quiza.quiz
JOIN {question_usages} qu ON qu.id = quiza.uniqueid
JOIN {question_attempts} qa ON qa.questionusageid = qu.id
JOIN {question} que ON que.id = qa.questionid


 
WHERE que.id = qst.id AND qa.rightanswer = qa.responsesummary AND q.id = mq.id ) as correctanswers,

( select count(qa.id) 
 
FROM {quiz_attempts} quiza
JOIN {quiz} q ON q.id=quiza.quiz
JOIN {question_usages} qu ON qu.id = quiza.uniqueid
JOIN {question_attempts} qa ON qa.questionusageid = qu.id
JOIN {question} que ON que.id = qa.questionid


 
WHERE que.id = qst.id AND qa.rightanswer != qa.responsesummary AND q.id = mq.id ) as wrongansers,
( select count(qa.id) 
 
FROM {quiz_attempts} quiza
JOIN {quiz} q ON q.id=quiza.quiz
JOIN {question_usages} qu ON qu.id = quiza.uniqueid
JOIN {question_attempts} qa ON qa.questionusageid = qu.id
JOIN {question} que ON que.id = qa.questionid


 
WHERE que.id = qst.id AND q.id = mq.id ) as totalresponses
  " ;
    parent::select();
  }

  function from() {
    $this->sql .= " FROM {question} qst ";
  }

  function joins() {
    $this->sql .=" 
    JOIN {question_attempts} mqa ON qst.id = mqa.questionid 
    JOIN {question_usages} mqu ON mqa.questionusageid = mqu.id
    JOIN {quiz_attempts} mquiza  ON mqu.id = mquiza.uniqueid
    JOIN {quiz} mq ON mq.id = mquiza.quiz
    ";

    parent::joins();
  }

  function where() {
    global $USER, $DB;
    // $courseid = $this->params['filter_course'];
    $this->sql .= " where 1=1 ";
    parent::where();
  }

  function search() {
    if (isset($this->search) && $this->search) {
      $fields = array('qst.name');
      $fields = implode(" LIKE '%$this->search%' OR ", $fields);
      $fields .= " LIKE '%$this->search%' ";
      $this->sql .= " AND ($fields) ";
    }
  }

  function filters() {
    if(isset($this->params['filter_quiz']) && $this->params['filter_quiz'] > 0) {
        $this->sql .= " AND mq.id = :quizid ";
        $this->params['quizid'] = $this->params['filter_quiz'];
    } else {
      $this->sql .= " AND mq.id = 0 ";
    }
  }

  public function get_rows($questions) {
    global $DB;
    $finalelements = array();
    if($questions){
      $data = array();
      // $columns = array('questionname','average','correct','incorrect','total');
      foreach($questions as $question){
        $question->questionname = $question->question;
        $question->correct = $question->correctanswers;
        $question->incorrect = $question->wrongansers;
        $question->total = $question->totalresponses;
        if (!empty($question->correctanswers) && !empty($question->totalresponses))
        $question->average = round((($question->correctanswers / $question->totalresponses) * 100), 2).'%';
        else
        $question->average ='0 %';
        
        $data[] = $question;
      }
      return $data;
    }
    return $finalelements;
  }
}