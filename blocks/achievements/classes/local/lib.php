<?php
defined('MOODLE_INTERNAL') || die;
function point_details($tablelimits, $filtervalues){
    global $DB, $CFG, $USER,$PAGE;
    $countsql = "SELECT count(distinct c.id)
        FROM {user_enrolments} AS m
        INNER JOIN {enrol} AS e ON  m.enrolid=e.id
        INNER JOIN {course} AS c ON e.courseid=c.id
        INNER JOIN {course_completions} AS cc ON  c.id=cc.course
        WHERE cc.userid=:cuserid AND m.userid=:muserid
        AND cc.timecompleted IS NOT NULL ";

    $selectsql = "SELECT distinct(c.id),c.fullname,
        c.open_points FROM {user_enrolments} AS m
        INNER JOIN {enrol} AS e ON  m.enrolid=e.id
        INNER JOIN {course} AS c ON e.courseid=c.id
        INNER JOIN {course_completions} AS cc ON  c.id=cc.course
        WHERE cc.userid=:cuserid AND m.userid=:muserid
        AND cc.timecompleted IS NOT NULL ";

    $queryparam = array();
    $queryparam['cuserid'] = $USER->id;
    $queryparam['muserid'] = $USER->id;
    $count = $DB->count_records_sql($countsql,$queryparam);
    $pointsrecived = $DB->get_records_sql($selectsql, $queryparam, $tablelimits->start, $tablelimits->length);

    $list=array();
    if ($pointsrecived) {
        $data = array();
        foreach ($pointsrecived as $points) {
            $list['points_title'] = $points->fullname;
            $list['points_credit']=$points->open_points;
            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data);
}

function badges_details($tablelimits, $filtervalues){
    global $DB, $CFG, $USER,$PAGE;
    $countsql = "SELECT count(bi.uniquehash) FROM  {badge} b, {badge_issued} bi, {user} u, {course} c
        WHERE bi.userid = {$USER->id}
        AND b.id = bi.badgeid
        AND u.id = bi.userid
        AND c.id = b.courseid ";

    $selectsql = "SELECT bi.uniquehash,
        bi.dateissued,
        bi.dateexpire,
        bi.id as issuedid,
        bi.visible,
        u.email,
        b.*
        FROM  {badge} b, {badge_issued} bi, {user} u, {course} c
        WHERE bi.userid = {$USER->id}
        AND b.id = bi.badgeid
        AND u.id = bi.userid
        AND c.id = b.courseid ";
    $queryparam = array();
    
    $count = $DB->count_records_sql($countsql);
    $concatsql.=" ORDER BY bi.dateissued DESC";
    $badgesrecived = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);
    // $coursecontext = (new \local_courses\lib\accesslib())::get_module_context($badge->courseid);

    $list=array();
    if ($badgesrecived) {
        $data = array();
        foreach ($badgesrecived as $badge) {
            $coursecontext = \context_course::instance($badge->courseid);
            $context = ($badge->type == 1) ? $coursecontext : $coursecontext;
            $badgeurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
            $list['imageurl'] = $badgeurl->out();
            $list['badge_name'] = strlen($badge->name) > 14 ? substr($badge->name, 0, 14).'...' : $badge->name;
            $list['badge_name_str'] = $badge->name;
            $list['uniquehash'] = $badge->uniquehash;
            $list['badge_id'] = $badge->id;
            $issued_on=\local_costcenter\lib::get_userdate('d-M-Y H:i', $badge->dateissued);
            $list['issued_on']=$issued_on;
            $list['issued_by']=$badge->issuername;

            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data);
}

function certification_details($tablelimits, $filtervalues){
    global $DB, $CFG, $USER,$PAGE;
    $queryparam = array('userid' => $USER->id);
    $countsql = "SELECT count(lci.id) FROM {tool_certificate_issues} AS lci
        JOIN {tool_certificate_templates} AS lc ON lc.id = lci.templateid 
        WHERE lci.userid = :userid ";
    $selectsql = "SELECT lci.id, lc.id as moduleid ,lc.name ,lci.code, lci.moduletype,
    (SELECT 
        CASE 
        WHEN lci.moduletype LIKE 'course'
            THEN (SELECT module.fullname FROM {course} AS module WHERE module.id = lci.moduleid )
        WHEN lci.moduletype LIKE 'classroom'
            THEN (SELECT module.name FROM {local_classroom} AS module WHERE module.id = lci.moduleid) 
        WHEN lci.moduletype LIKE 'learningplan'
            THEN (SELECT module.name FROM {local_learningplan} AS module WHERE module.id = lci.moduleid)
        ELSE '' END) AS modulename 
        FROM {tool_certificate_issues} AS lci
        JOIN {tool_certificate_templates} AS lc ON lc.id = lci.templateid 
        WHERE lci.userid = :userid ";
    $count = $DB->count_records_sql($countsql,$queryparam);

    $certirecived = $DB->get_records_sql($selectsql, $queryparam, $tablelimits->start, $tablelimits->length);
    $list=array();
    $data = array();
    if ($certirecived) {
        foreach ($certirecived as $certificate) {
            $list['module_id']=$certificate->moduleid;
            $list['module_type']=$certificate->moduletype;
            $list['certificate_code']=$certificate->code;
          //  $list['certificate_name']= "{$certificate->name}(".ucfirst($certificate->moduletype)." - {$certificate->modulename})";
            $list['certificate_name']=ucfirst($certificate->moduletype)." ({$certificate->modulename})";
            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data);       
}
