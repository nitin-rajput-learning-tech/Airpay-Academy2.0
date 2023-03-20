<?php
defined('MOODLE_INTERNAL') || die;
  //////For display on index page//////////
  function myskill_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $context = context_system::instance();
    $PAGE->set_context($context);

    $countsql = "SELECT count(c.id) 
                  FROM {course_completions} cc
                  JOIN {course} c ON cc.course = c.id
                  JOIN {local_skill} ls ON c.open_skill = ls.id
                  JOIN {local_course_levels} ll ON c.open_level = ll.id
                  WHERE cc.userid = {$USER->id} 
                  AND cc.timecompleted IS NOT NULL 
                 ";

    $selectsql = "SELECT c.id, c.fullname, c.open_points, ls.name AS skillname, ll.name as levelname,
                  ls.name, cc.timecompleted  
                  FROM {course_completions} cc
                  JOIN {course} c ON cc.course = c.id
                  JOIN {local_skill} ls ON c.open_skill = ls.id
                  JOIN {local_course_levels} ll ON c.open_level = ll.id
                  WHERE cc.userid = {$USER->id} AND cc.timecompleted IS NOT NULL 
                ";
    $queryparam = array();

    $count = $DB->count_records_sql($countsql);
    $concatsql.=" order by c.id desc";
    $skillsacquired = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

    $list=array();
    if ($skillsacquired) {
      $data = array();
      foreach ($skillsacquired as $skill) {
        $list['skill_name'] = $skill->skillname;
        $list['levelname'] = $skill->levelname;
        $list['course_name'] = $skill->fullname;
        $list['achieved_on'] = date('d/M/Y', $skill->timecompleted);
        $data[] = $list;
      }
    }
    return array('count' => $count, 'data' => $data); 
  }
?>