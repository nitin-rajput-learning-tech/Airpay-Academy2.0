<?php

use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;

class report_myclassrooms extends reportbase
{
  public function __construct($report, $reportproperties)
  {
    global $USER;
    parent::__construct($report);
    $this->components = array('columns', 'permissions', 'filters');
    $this->parent = true;
    $this->columns = ['classroomfield' => ['classroomfield'], 'myclassrooms' => ['classroomname', 'usercompletionstatus', 'usercompletiondate']];
    $this->filters = array('myclassroomscolumns', 'completionstatus');
    $this->orderable = array('classroomname');
    $this->defaultcolumn = 'cu.id';
  }
  function init()
  {
    parent::init();
  }
  function count()
  {
    $this->sql = "SELECT count(cu.id)";
  }
  function select()
  {
    $this->sql = "SELECT cu.id,lc.id as classroomid,lc.name as classroomname,
                     cu.completion_status as usercompletionstatus,cu.completiondate as usercompletiondate ";
    parent::select();
  }
  function from()
  {
    $this->sql .= " FROM {local_classroom} as lc";
  }
  function joins()
  {
    $this->sql .= " JOIN {local_classroom_users} as cu ON cu.classroomid = lc.id ";
    parent::joins();
  }
  function where()
  {
    global $USER, $DB;
    $this->sql .=  " WHERE cu.userid = $USER->id AND lc.status IN(1,3,4)
                          AND lc.visible = 1 ";
    // getscheduled report
    if (!is_siteadmin()) {
      $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid' => $this->reportid, 'sendinguserid' => $USER->id], IGNORE_MULTIPLE);
      if (!empty($scheduledreport)) {
        $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
        $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid' => $scheduledreport->roleid, 'capability' => 'local/costcenter:manage_ownorganization']);
        $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid' => $scheduledreport->roleid, 'capability' => 'local/costcenter:manage_owndepartments']);
      } else {
        $ohs = $dhs = 1;
      }
    }

    parent::where();
  }
  function search()
  {
    if (isset($this->search) && $this->search) {
      $fields = array("lc.name");
      $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
      $fields .= " LIKE '%" . $this->search . "%' ";
      $this->sql .= " AND ($fields) ";
    }
  }
  function filters()
  {
    if (!empty($this->params['filter_myclassroomscolumns'])) {
      $this->sql .= " AND lc.id = :classroomid ";
      $this->params['classroomid'] = $this->params['filter_myclassroomscolumns'];
    } 

    if ($this->params['filter_completionstatus'] > -1){
      $this->sql .= " AND cu.completion_status = :status ";
      $this->params['status'] = $this->params['filter_completionstatus'];
    }
    if ($this->ls_startdate > 0 && $this->ls_enddate > 0) {
      $this->sql .= " AND cu.completiondate > :report_startdate ";
      $this->params['report_startdate'] = $this->ls_startdate;

      $this->sql .= " AND cu.completiondate < :report_enddate ";
      $this->params['report_enddate'] = $this->ls_enddate;
    }
  }
  public function get_rows($myclassroomcolumns)
  {
    return $myclassroomcolumns;
  }
}
