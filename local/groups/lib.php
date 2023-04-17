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
 * @subpackage local_groups
 */

defined('MOODLE_INTERNAL') || die();

define('LOCAL_COHORT_ALL', 0);
define('LOCAL_COHORT_COUNT_MEMBERS', 1);
define('LOCAL_COHORT_COUNT_ENROLLED_MEMBERS', 3);
define('LOCAL_COHORT_WITH_MEMBERS_ONLY', 5);
define('LOCAL_COHORT_WITH_ENROLLED_MEMBERS_ONLY', 17);
define('LOCAL_COHORT_WITH_NOTENROLLED_MEMBERS_ONLY', 23);


class local_groups implements renderable {
     public function __construct($page, $perpage, $searchquery,$showall) {
        $context =  (new \local_groups\lib\accesslib())::get_module_context();
        if ($showall || is_siteadmin()) {
            $cohorts = local_groups_get_all_groups($context->id,$page, $perpage, $searchquery);
        } else {
            $cohorts = local_groups_get_groups($context->id, $page, $perpage, $searchquery);
        }
        $this->context = $context;
        $this->groups = $cohorts;
        $this->showall = $showall;
        $this->page = $page;
        $this->searchquery = $searchquery;
     }
}
function manage_groups_count($stable,$filterdata){
    global $DB,$USER;
     $countsql = "SELECT  count(c.id) ";
     $fields = "SELECT c.*";
     $countfields = "SELECT COUNT(1)";
     $sql = " FROM {cohort} c, {local_groups} g
              WHERE g.cohortid = c.id";
     $context =  (new \local_groups\lib\accesslib())::get_module_context();
     $costcenterpathconcatsql = (new \local_groups\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $sql .=" $costcenterpathconcatsql";
    $params = array();

    $order = " ORDER BY  g.id DESC";
    if(isset($filterdata->search_query) && trim($filterdata->search_query) != ''){
        $formsql .= " AND (c.name LIKE :search1 OR c.idnumber LIKE :search2 OR c.description LIKE :search3 )";
        $params['search1'] = '%'.trim($filterdata->search_query).'%';
        $params['search2'] = '%'.trim($filterdata->search_query).'%';
        $params['search3'] = '%'.trim($filterdata->search_query).'%';
    }

    $groups = $DB->get_records_sql($fields . $sql . $order, $params, $stable->start, $stable->length);
    return array('totalgroups' => $totalgroups, 'groups' => $groups, 'allgroups' => $allgroups);

}
 function manage_groups_content($stable,$filterdata){
        global $DB, $OUTPUT, $PAGE, $CFG, $USER;
        $output = '';
        $data = array();
        $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
        $editcolumnisempty = true;
        $groups = manage_groups_count($stable,$filterdata);
        $groupslist = $groups['groups'];
           $row = [];
        foreach($groupslist as $cohort) {
                $line = array();
                $groupname = $cohort->name;
                if(strlen($groupname) >15){
                     $groupname = substr($groupname, 0,15).'...';
                }

                $groupid  =$cohort->idnumber;
                if(strlen($groupid) >8){
                     $groupid = substr($groupid, 0,8).'...';
                }
        $cohortcontext =  (new \local_groups\lib\accesslib())::get_module_context();

               // $cohortcontext = context::instance_by_id($cohort->contextid);
                $urlparams = array('id' => $cohort->id, 'returnurl' => $baseurl->out_as_local_url());
                $group_members_count = $DB->count_records('cohort_members', array('cohortid'=>$cohort->id));
                $line['groupname'] = $groupname;
                $line['groupid'] = $groupid;
                $buttons = array();






                if (empty($cohort->component)) {
                    $cohortmanager = has_capability('moodle/cohort:manage', $cohortcontext);
                    $cohortcanassign = has_capability('moodle/cohort:assign', $cohortcontext);
            
                    
                    $showhideurl = new moodle_url('/local/groups/edit.php', $urlparams + array('sesskey' => sesskey()));
                    if ($cohortmanager) {
                        $buttons[] = html_writer::start_tag('li'); 
                        $buttons[] = html_writer::link('javascript:void(0)',
                            $OUTPUT->pix_icon('t/edit', get_string('edit')),
                            array('title' => get_string('edit'), 'onclick' => '(function(e){ require("local_groups/newgroup").init({contextid:'.$systemcontext->id.', groupsid:'.$cohort->id.'}) })(event)'));
                        $buttons[] = html_writer::end_tag('li');

                        $editcolumnisempty = false;
                        if ($cohort->visible) {
                            $showhideurl->param('hide', 1);
                            $visibleimg = $OUTPUT->pix_icon('t/hide', get_string('hidecohort','local_groups'));

                            $buttons[] = html_writer::start_tag('li'); 
                                $buttons[]= html_writer::link(
                                    "javascript:void(0)",
                                    $OUTPUT->pix_icon('t/hide', get_string('hidecohort','local_groups'), 'moodle', array('title' => '')),
                                    array('id' => 'confirmhide' . $cohort->id . '', 'onclick' => '(
                                          function(e){
                                    require("local_groups/renderselections").hideshow_cohort(' . $cohort->id . ', "' . $cohort->name . '")
                                    })(event)'));
                                $buttons[] = html_writer::end_tag('li');
                        } else {
                            $showhideurl->param('show', 1);
                            $visibleimg = $OUTPUT->pix_icon('t/show', get_string('showcohort','local_groups'));
                            $buttons[] = html_writer::start_tag('li'); 
                                $buttons[] = html_writer::link(
                                    "javascript:void(0)",
                                    $OUTPUT->pix_icon('t/show', get_string('showcohort','local_groups'), 'moodle', array('title' => '')),
                                    array('id' => 'confirmshow' . $cohort->id . '', 'onclick' => '(
                                          function(e){
                                    require("local_groups/renderselections").showhide_cohort(' . $cohort->id . ', "' . $cohort->name . '")
                                    })(event)'));
                                $buttons[] = html_writer::end_tag('li');
                        }
                    }

                    if ($cohortcanassign) {
                        $buttons[] = html_writer::start_tag('li'); 
                           $buttons[] = html_writer::link(new moodle_url('/local/groups/assign.php', $urlparams),
                            $OUTPUT->pix_icon('i/enrolusers', get_string('assign', 'core_cohort')),
                            array('title' => get_string('assign', 'core_cohort')));
                        $editcolumnisempty = false;
                          $buttons[] =  html_writer::end_tag('li');
                        $buttons[] = html_writer::start_tag('li'); 
                           $buttons[] =           html_writer::link(new moodle_url('/local/groups/mass_enroll.php', $urlparams),
                            $OUTPUT->pix_icon('i/users', get_string('bulk_enroll', 'local_groups')),
                            array('title' => get_string('bulk_enroll', 'local_groups')));
                    }       $buttons[] = html_writer::end_tag('li');
                    if ($cohortmanager)
                    $buttons[] = html_writer::start_tag('li'); 
                    $buttons[] =  html_writer::link(
                                    "javascript:void(0)",
                                    $OUTPUT->pix_icon('i/delete', get_string('delete'), 'moodle', array('title' => '')),
                                    array('id' => 'deleteconfirm' . $cohort->id . '', 'onclick' => '(
                                          function(e){
                            require("local_groups/renderselections").deletecohort(' . $cohort->id . ', "' . $cohort->name . '")
                            })(event)'));
                     
                }
                    $buttons[] = html_writer::end_tag('li'); 
                


                $line['actions'] = implode(' ', $buttons);

                if (!$cohort->visible) {
                    $row->attributes['class'] = 'dimmed_text';
                }
                $imagehtml = '';
                
                $group_images= $DB->get_records('cohort_members', array('cohortid'=>$cohort->id),$sort='id desc', $fields='*', $limitfrom=0, $limitnum=4);
         
           foreach($group_images as $group_image){
                $user_record = $DB->get_record('user',array('id'=>$group_image->userid));
                $user_picture = new user_picture($user_record, array('size' => 80, 'class' => 'userpic', 'link'=>false));
                $user_picture = $user_picture->get_url($PAGE);
                $userpic = $user_picture->out();
                $userimage = html_writer::start_tag('li'); 
                $userimage .= html_writer::tag('img', '', array('src' => $userpic));
                $userimage .= html_writer::end_tag('li'); 
                $imagehtml .= $userimage;
           }
           $line['userimages'] = $imagehtml;
           $cohort_users = $DB->count_records('cohort_members', array('cohortid'=>$cohort->id));
           $line['groupcount'] = $cohort_users;
           $line['userid']=$cohort->id;
           $line['location_url'] = $CFG->wwwroot . '/local/groups/assign.php?id='.$cohort->id;
        if (has_capability('local/groups:manage',  (new \local_groups\lib\accesslib())::get_module_context()) || is_siteadmin())
            $row[] = $line;
        }
        return $row;
    }
/**
 * Add new groups.
 *
 * @param  stdClass $groups
 * @return int new groups id
 */
function local_groups_add_groups($groups) {
    global $DB, $USER;
    if (!isset($groups->name)) {
        throw new coding_exception('Missing groups name in groups_add_groups().');
    }
    if (!isset($groups->idnumber)) {
        $groups->idnumber = NULL;
    }

    if(!empty($groups->description_editor['text'])){
        $groups->description = $groups->description_editor['text'];
    }else{
        $groups->description = '';
    }

    if (!empty($groups->description_editor['format'])) {
        $groups->descriptionformat = $groups->description_editor['format'];
    }else{
        $groups->descriptionformat = FORMAT_HTML;
    }

    if (!isset($groups->visible)) {
        $groups->visible = 1;
    }
    if (empty($groups->component)) {
        $groups->component = '';
    }
    if (!isset($groups->timecreated)) {
        $groups->timecreated = time();
    }
    if (!isset($groups->timemodified)) {
        $groups->timemodified = $groups->timecreated;
    }
    $groups->id = $DB->insert_record('cohort', $groups);
    
    // create relation between cohort and creator of this cohort
    $new_group = new stdClass();
    $new_group->cohortid = $groups->id;
    $new_group->usermodified = $USER->id;
    $new_group->timemodified = time();
    $new_group->open_path = $groups->open_path;
    $new_group->departmentid = is_array($groups->departmentid) ? implode(',', $groups->departmentid) : $groups->departmentid;
    $DB->insert_record('local_groups', $new_group);

    $event = \core\event\cohort_created::create(array(
        'context' =>(new \local_groups\lib\accesslib())::get_module_context(),
       'objectid' => $groups->id,
    ));
    $event->add_record_snapshot('groups', $groups);
    $event->trigger();

    return $groups->id;
}

/**
 * Update existing groups.
 * @param  stdClass $groups
 * @return void
 */
function local_groups_update_groups($groups) {
    global $DB;
    if (property_exists($groups, 'component') and empty($groups->component)) {
    // prevent NULLs
    $groups->component = '';
    }

    if(!empty($groups->description_editor['text'])){
        $groups->description = $groups->description_editor['text'];
    }else{
        $groups->description = '';
    }

    if (!empty($groups->description_editor['format'])) {
        $groups->descriptionformat = $groups->description_editor['format'];
    }else{
        $groups->descriptionformat = FORMAT_HTML;
    }

    $groups->timemodified = time();
    $DB->update_record('cohort', $groups);
    $cohort_group = $DB->get_record('local_groups', array('cohortid'=>$groups->id));
    $DB->update_record('local_groups', $groups);
    $event = \core\event\cohort_updated::create(array(
        'context' =>(new \local_groups\lib\accesslib())::get_module_context(),
       'objectid' => $groups->id,
    ));
    $event->trigger();
}

/**
 * Delete groups.
 * @param  stdClass $groups
 * @return void
 */
function local_groups_delete_groups($groups) {
    global $DB;

    if ($groups->component) {
        // TODO: add component delete callback
    }

    $DB->delete_records('cohort_members', array('cohortid'=>$groups->id));
    $DB->delete_records('cohort', array('id'=>$groups->id));
    $DB->delete_records('local_groups', array('cohortid'=>$groups->id));

    // Notify the competency subsystem.
    \core_competency\api::hook_cohort_deleted($groups);

    $event = \core\event\cohort_deleted::create(array(
       'context' => context::instance_by_id($groups->contextid),
       'objectid' => $groups->id,
    ));
    $event->add_record_snapshot('groups', $groups);
    $event->trigger();
}

/**
 * Somehow deal with groups when deleting course category,
 * we can not just delete them because they might be used in enrol
 * plugins or referenced in external systems.
 * @param  stdClass|coursecat $category
 * @return void
 */
function local_groups_delete_category($category) {
    global $DB;
    // TODO: make sure that groups are really, really not used anywhere and delete, for now just move to parent or system context

    $oldcontext = context_coursecat::instance($category->id);

    if ($category->parent and $parent = $DB->get_record('course_categories', array('id'=>$category->parent))) {
        $parentcontext = context_coursecat::instance($parent->id);
        $sql = "UPDATE {cohort} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$parentcontext->id);
    } else {
        $syscontext =  (new \local_groups\lib\accesslib())::get_module_context();
        $sql = "UPDATE {cohort} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$syscontext->id);
    }

    $DB->execute($sql, $params);
}

/**
 * Add groups member
 * @param  int $groupsid
 * @param  int $userid
 * @return void
 */
function local_groups_add_member($groupsid, $userid) {
    global $DB;
    if ($DB->record_exists('cohort_members', array('cohortid'=>$groupsid, 'userid'=>$userid))) {
        // No duplicates!
        return;
    }
    $record = new stdClass();
    $record->cohortid  = $groupsid;
    $record->userid    = $userid;
    $record->timeadded = time();
    $DB->insert_record('cohort_members', $record);

    $groups = $DB->get_record('cohort', array('id' => $groupsid), '*', MUST_EXIST);

    $event = \core\event\cohort_member_added::create(array(
       'context' => context::instance_by_id($groups->contextid),
       'objectid' => $groupsid,
       'relateduserid' => $userid,
    ));
    $event->add_record_snapshot('groups', $groups);
    $event->trigger();
}

/**
 * Remove groups member
 * @param  int $groupsid
 * @param  int $userid
 * @return void
 */
function local_groups_remove_member($groupsid, $userid) {
    global $DB;
    $DB->delete_records('cohort_members', array('cohortid'=>$groupsid, 'userid'=>$userid));

    $groups = $DB->get_record('cohort', array('id' => $groupsid), '*', MUST_EXIST);

    $event = \core\event\cohort_member_removed::create(array(
       'context' => context::instance_by_id($groups->contextid),
       'objectid' => $groupsid,
       'relateduserid' => $userid,
    ));
    $event->add_record_snapshot('groups', $groups);
    $event->trigger();
}

/**
 * Is this user a groups member?
 * @param int $groupsid
 * @param int $userid
 * @return bool
 */
function local_groups_is_member($groupsid, $userid) {
    global $DB;

    return $DB->record_exists('groups_members', array('groupsid'=>$groupsid, 'userid'=>$userid));
}

/**
 * Returns the list of groups visible to the current user in the given course.
 *
 * The following fields are returned in each record: id, name, contextid, idnumber, visible
 * Fields memberscnt and enrolledcnt will be also returned if requested
 *
 * @param context $currentcontext
 * @param int $withmembers one of the COHORT_XXX constants that allows to return non empty groups only
 *      or groups with enroled/not enroled users, or just return members count
 * @param int $offset
 * @param int $limit
 * @param string $search
 * @return array
 */
function local_groups_get_available_groups($currentcontext, $withmembers = 0, $offset = 0, $limit = 10, $search = '') {
    global $DB;
    $params = array();

    // Build context subquery. Find the list of parent context where user is able to see any or visible-only groups.
    // Since this method is normally called for the current course all parent contexts are already preloaded.
    $contextsany = array_filter($currentcontext->get_parent_context_ids(),
        function($a) {
            return has_capability("moodle/cohort:view", context::instance_by_id($a));
        });
    $contextsvisible = array_diff($currentcontext->get_parent_context_ids(), $contextsany);
    if (empty($contextsany) && empty($contextsvisible)) {
        // User does not have any permissions to view groups.
        return array();
    }
    $subqueries = array();
    if (!empty($contextsany)) {
        list($parentsql, $params1) = $DB->get_in_or_equal($contextsany, SQL_PARAMS_NAMED, 'ctxa');
        $subqueries[] = 'c.contextid ' . $parentsql;
        $params = array_merge($params, $params1);
    }
    if (!empty($contextsvisible)) {
        list($parentsql, $params1) = $DB->get_in_or_equal($contextsvisible, SQL_PARAMS_NAMED, 'ctxv');
        $subqueries[] = '(c.visible = 1 AND c.contextid ' . $parentsql. ')';
        $params = array_merge($params, $params1);
    }
    $wheresql = '(' . implode(' OR ', $subqueries) . ')';

    // Build the rest of the query.
    $fromsql = "";
    $fieldssql = 'c.id, c.name, c.contextid, c.idnumber, c.visible';
    $groupbysql = '';
    $havingsql = '';
    if ($withmembers) {
        $fieldssql .= ', s.memberscnt';
        $subfields = "c.id, COUNT(DISTINCT cm.userid) AS memberscnt";
        $groupbysql = " GROUP BY c.id";
        $fromsql = " LEFT JOIN {cohort_members} cm ON cm.groupsid = c.id ";
        if (in_array($withmembers,
                array(LOCAL_COHORT_COUNT_ENROLLED_MEMBERS, LOCAL_COHORT_WITH_ENROLLED_MEMBERS_ONLY, LOCAL_COHORT_WITH_NOTENROLLED_MEMBERS_ONLY))) {
            list($esql, $params2) = get_enrolled_sql($currentcontext);
            $fromsql .= " LEFT JOIN ($esql) u ON u.id = cm.userid ";
            $params = array_merge($params2, $params);
            $fieldssql .= ', s.enrolledcnt';
            $subfields .= ', COUNT(DISTINCT u.id) AS enrolledcnt';
        }
        if ($withmembers == LOCAL_COHORT_WITH_MEMBERS_ONLY) {
            $havingsql = " HAVING COUNT(DISTINCT cm.userid) > 0";
        } else if ($withmembers == LOCAL_COHORT_WITH_ENROLLED_MEMBERS_ONLY) {
            $havingsql = " HAVING COUNT(DISTINCT u.id) > 0";
        } else if ($withmembers == LOCAL_COHORT_WITH_NOTENROLLED_MEMBERS_ONLY) {
            $havingsql = " HAVING COUNT(DISTINCT cm.userid) > COUNT(DISTINCT u.id)";
        }
    }
    if ($search) {
        list($searchsql, $searchparams) = groups_get_search_query($search);
        $wheresql .= ' AND ' . $searchsql;
        $params = array_merge($params, $searchparams);
    }

    if ($withmembers) {
        $sql = "SELECT " . str_replace('c.', 'groups.', $fieldssql) . "
                  FROM {cohort} groups
                  JOIN (SELECT $subfields
                          FROM {cohort} c $fromsql
                         WHERE $wheresql $groupbysql $havingsql
                        ) s ON groups.id = s.id
              ORDER BY groups.name, groups.idnumber";
    } else {
        $sql = "SELECT $fieldssql
                  FROM {cohort} c $fromsql
                 WHERE $wheresql
              ORDER BY c.name, c.idnumber";
    }

    return $DB->get_records_sql($sql, $params, $offset, $limit);
}

/**
 * Check if groups exists and user is allowed to access it from the given context.
 *
 * @param stdClass|int $groupsorid groups object or id
 * @param context $currentcontext current context (course) where visibility is checked
 * @return boolean
 */
function local_groups_can_view_groups($groupsorid, $currentcontext) {
    global $DB;
    if (is_numeric($groupsorid)) {
        $groups = $DB->get_record('cohort', array('id' => $groupsorid), 'id, contextid, visible');
    } else {
        $groups = $groupsorid;
    }

    if ($groups && in_array($groups->contextid, $currentcontext->get_parent_context_ids())) {
        if ($groups->visible) {
            return true;
        }
        $groupscontext = context::instance_by_id($groups->contextid);
        if (has_capability('moodle/cohort:view', $groupscontext)) {
            return true;
        }
    }
    return false;
}

/**
 * Get a groups by id. Also does a visibility check and returns false if the user cannot see this groups.
 *
 * @param stdClass|int $groupsorid groups object or id
 * @param context $currentcontext current context (course) where visibility is checked
 * @return stdClass|boolean
 */
function local_groups_get_group($groupsorid, $currentcontext) {
    global $DB;
    if (is_numeric($groupsorid)) {
        $groups = $DB->get_record('cohort', array('id' => $groupsorid), 'id, contextid, visible');
    } else {
        $groups = $groupsorid;
    }

    if ($groups && in_array($groups->contextid, $currentcontext->get_parent_context_ids())) {
        if ($groups->visible) {
            return $groups;
        }
        $groupscontext = context::instance_by_id($groups->contextid);
        if (has_capability('moodle/cohort:view', $groupscontext)) {
            return $groups;
        }
    }
    return false;
}

/**
 * Produces a part of SQL query to filter groups by the search string
 *
 * Called from {@link groups_get_groups()}, {@link groups_get_all_groups()} and {@link groups_get_available_groups()}
 *
 * @access private
 *
 * @param string $search search string
 * @param string $tablealias alias of groups table in the SQL query (highly recommended if other tables are used in query)
 * @return array of two elements - SQL condition and array of named parameters
 */
function local_groups_get_search_query($search, $tablealias = '') {
    global $DB;
    $params = array();
    if (empty($search)) {
        // This function should not be called if there is no search string, just in case return dummy query.
        return array('1=1', $params);
    }
    if ($tablealias && substr($tablealias, -1) !== '.') {
        $tablealias .= '.';
    }
    $searchparam = '%' . $DB->sql_like_escape($search->search_query) . '%';
    $conditions = array();
    $fields = array('name', 'idnumber', 'description');
    $cnt = 0;
    foreach ($fields as $field) {
        $conditions[] = $DB->sql_like($tablealias . $field, ':csearch' . $cnt, false);
        $params['csearch' . $cnt] = $searchparam;
        $cnt++;
    }
    $sql = '(' . implode(' OR ', $conditions) . ')';
    return array($sql, $params);
}

/**
 * Get all the groups defined in given context.
 *
 * The function does not check user capability to view/manage groups in the given context
 * assuming that it has been already verified.
 *
 * @param int $contextid
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalgroups => int, groups => array, allgroups => int)
 */
function local_groups_get_groups($contextid, $page = 0, $perpage = 10, $search = '') {
     global $DB,$USER;
     $fields = "SELECT c.*";
     $countfields = "SELECT COUNT(1)";
     $sql = " FROM {cohort} c, {local_groups} g
              WHERE g.cohortid = c.id";
     $context = (new \local_groups\lib\accesslib())::get_module_context();
     $costcenterpathconcatsql = (new \local_groups\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $sql .=" $costcenterpathconcatsql";
    $params = array('contextid' => $contextid);
    $order = " ORDER BY  g.id DESC";
    if(isset($search) && isset($search->search_query)){
       $sql .= " AND c.name LIKE '%".trim($search->search_query)."%'";
    } 
    $allgroups = '';
    //$totalgroups = $allgroups = $DB->count_records('cohort', array('contextid' => $contextid));
   // if (!empty($search)) {
        $totalgroups = $DB->count_records_sql($countfields . $sql, $params);
    //}
        $groups = $DB->get_records_sql($fields . $sql . $order, $params, $page, $perpage);
    return array('totalgroups' => $totalgroups, 'groups' => $groups, 'allgroups' => $allgroups);
}

/**
 * Get all the groups defined anywhere in system.
 *
 * The function assumes that user capability to view/manage groups on system level
 * has already been verified. This function only checks if such capabilities have been
 * revoked in child (categories) contexts.
 *
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalgroups => int, groups => array, allgroups => int)
 */
function local_groups_get_all_groups($context,$page = 0, $perpage = 10, $search = '') {
    global $DB,$USER;
    $fields = "SELECT c.*, ".context_helper::get_preload_record_columns_sql('ctx');
    $countfields = "SELECT COUNT(*)";
    $sql = " FROM {cohort} c
             JOIN {context} ctx ON ctx.id = c.contextid
             JOIN {local_groups} g ON g.cohortid = c.id ";
     $context = (new \local_groups\lib\accesslib())::get_module_context();
     $costcenterpathconcatsql = (new \local_groups\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $sql .="$costcenterpathconcatsql";
    $params = array();
    $wheresql = '';

    if ($excludedcontexts = local_groups_get_invisible_contexts()) {
        list($excludedsql, $excludedparams) = $DB->get_in_or_equal($excludedcontexts, SQL_PARAMS_NAMED, 'excl', false);
        $wheresql = ' WHERE c.contextid '.$excludedsql;
        $params = array_merge($params, $excludedparams);
    }

    $totalgroups = $allgroups = $DB->count_records_sql($countfields . $sql . $wheresql, $params);

    if (!empty($search)) {
        list($searchcondition, $searchparams) = local_groups_get_search_query($search, 'c');
        $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . $searchcondition;
        $params = array_merge($params, $searchparams);
        $totalgroups = $DB->count_records_sql($countfields . $sql . $wheresql, $params);
    }

    $order = " ORDER BY g.id DESC";
    $groups = $DB->get_records_sql($fields . $sql . $wheresql . $order, $params, $page, $perpage);
    // Preload used contexts, they will be used to check view/manage/assign capabilities and display categories names.
    foreach (array_keys($groups) as $key) {
        context_helper::preload_from_record($groups[$key]);
    }
    return array('totalgroups' => $totalgroups, 'groups' => $groups/*, 'allgroups' => $allgroups*/);
}

/**
 * Returns list of contexts where groups are present but current user does not have capability to view/manage them.
 *
 * This function is called from {@link groups_get_all_groups()} to ensure correct pagination in rare cases when user
 * is revoked capability in child contexts. It assumes that user's capability to view/manage groups on system
 * level has already been verified.
 *
 * @access private
 *
 * @return array array of context ids
 */
function local_groups_get_invisible_contexts() {
    global $DB;
    if (is_siteadmin()) {
        // Shortcut, admin can do anything and can not be prohibited from any context.
        return array();
    }
    $records = $DB->get_recordset_sql("SELECT DISTINCT ctx.id, ".context_helper::get_preload_record_columns_sql('ctx')." ".
        "FROM {context} ctx JOIN {cohort} c ON ctx.id = c.contextid ");
    $excludedcontexts = array();
    foreach ($records as $ctx) {
        context_helper::preload_from_record($ctx);
        if (!has_any_capability(array('moodle/cohort:manage', 'moodle/cohort:view'), context::instance_by_id($ctx->id))) {
            $excludedcontexts[] = $ctx->id;
        }
    }
    return $excludedcontexts;
}

/**
 * Returns navigation controls (tabtree) to be displayed on groups management pages
 *
 * @param context $context system or category context where groups controls are about to be displayed
 * @param moodle_url $currenturl
 * @return null|`able
 */
function local_groups_edit_controls(context $context, moodle_url $currenturl) {
    $tabs = array();
    $currenttab = 'view';
    $viewurl = new moodle_url('/local/groups/index.php', array('contextid' => $context->id));
    if (($searchquery = $currenturl->get_param('search'))) {
        $viewurl->param('search', $searchquery);
    }
  
    if ($context->contextlevel ==(new \local_groups\lib\accesslib())::get_module_context()->contextlevel) {
        $tabs[] = new tabobject('view', new moodle_url($viewurl, array('showall' => 0)), get_string('cohorts', 'local_groups'));
    } else {
        $tabs[] = new tabobject('view', $viewurl, get_string('cohort', 'local_groups'));
    }
    if (has_capability('moodle/cohort:manage', $context)) {
        $addurl = new moodle_url('/local/groups/edit.php', array('contextid' => $context->id));
        $tabs[] = new tabobject('addgroups', $addurl, get_string('addcohort', 'local_groups'));
        if ($currenturl->get_path() === $addurl->get_path() && !$currenturl->param('id')) {
            $currenttab = 'addgroups';
        }
    }
    if (count($tabs) > 1) {
        return new tabtree($tabs, $currenttab);
    }
    return null;
}


/**
* [available_enrolled_users description]
* @param  string  $type       [description]
* @param  integer $groupid [description]
* @param  [type]  $params     [description]
* @param  integer $total      [description]
* @param  integer $offset1    [description]
* @param  integer $perpage    [description]
* @param  integer $lastitem   [description]
* @return [type]              [description]
*/
function local_group_users($type = null, $groupid = 0, $params = array(), $total=0, $offset1=-1, $perpage=-1, $lastitem=0){
    global $DB, $USER;
    $context =  (new \local_groups\lib\accesslib())::get_module_context();
    $group = $DB->get_record('cohort', array('id' => $groupid));
    $cohort_group  = $DB->get_record('local_groups', array('cohortid' => $groupid));
    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
    $params['suspended'] = 0;
    $params['deleted'] = 0;
  
    if($total==0){
        $sql = "SELECT u.id,concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname";
    }else{
        $sql = "SELECT count(u.id) as total";
    }
    $sql.=" FROM {user} AS u 
            WHERE  u.id > 2 AND u.suspended = :suspended AND u.deleted = :deleted $costcenterpathconcatsql";
    if($lastitem!=0){
        $sql.= " AND u.id > $lastitem";
    }
    if (!empty($params['email'])) {
        $sql.=" AND u.id IN ({$params['email']})";
    }
    if (!empty($params['uname'])) {
        $sql .=" AND u.id IN ({$params['uname']})";
    }
    if (!empty($params['idnumber'])) {
        $sql .=" AND u.id IN ({$params['idnumber']})";
    }
    if (!empty($params['organization'])) {
        $organizations = explode(',', $params['organization']);
        $orgsql = [];
        foreach($organizations AS $organisation){
            $orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/'.$organisation.'/%';
        }
        if(!empty($orgsql)){
            $sql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
        }
    }
    if (!empty($params['department'])) {
        $departments = explode(',', $params['department']);
        $deptsql = [];
        foreach($departments AS $department){
            $deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/'.$department.'/%';
        }
        if(!empty($deptsql)){
            $sql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
        }
    }
    if (!empty($params['subdepartment'])) {
        $subdepartments = explode(',', $params['subdepartment']);
        $subdeptsql = [];
        foreach($subdepartments AS $subdepartment){
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/'.$subdepartment.'/%';
        }
        if(!empty($subdeptsql)){
            $sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }
    if (!empty($params['department4level'])) {
        $depart4level = explode(',',$params['department4level']);
        $department4levelsql = [];
        foreach($depart4level AS $department4level){
            $department4levelsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/'.$department4level.'/%';
        }
        if(!empty($department4levelsql)){
            $sql .= " AND ( ".implode(' OR ', $department4levelsql)." ) ";
        }
    }
    if (!empty($params['department5level'])) {
        $depart5level = explode(',', $params['department5level']);
        $department5levelsql = [];
        foreach($depart5level AS $department5level){
            $department5levelsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
            $params["department5levelparam_{$department5level}"] = '%/'.$department5level.'/%';
        }
        if(!empty($department5levelsql)){
            $sql .= " AND ( ".implode(' OR ', $department5levelsql)." ) ";
        }
    }
    if (!empty($params['location'])) {
        $locations = explode(',', $params['location']);
        list($locationsql, $locationparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
        $params = array_merge($params, $locationparams);
        $sql .= " AND u.open_location {$locationsql} ";
    }

    if (!empty($params['hrmsrole'])) {
        $hrmsroles = explode(',', $params['hrmsrole']);
        list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
        $params = array_merge($params, $hrmsroleparams);
        $sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
    }
    if (!empty($params['village'])) {
        $villages = explode(',', $params['village']);
        list($villagesql, $villageparam) = $DB->get_in_or_equal($villages, SQL_PARAMS_NAMED, 'village');
        $params = array_merge($params, $villageparam);
        $sql .= " AND u.open_village {$villagesql} ";
    }
    if (!empty($params['subdistrict'])) {
        $subdistricts = explode(',', $params['subdistrict']);
        list($subdistrictsql, $subdistrictparam) = $DB->get_in_or_equal($subdistricts, SQL_PARAMS_NAMED, 'subdistrict');
        $params = array_merge($params, $subdistrictparam);
        $sql .= " AND u.open_subdistrict {$subdistrictsql} ";
    }
    if (!empty($params['district'])) {
        $districts = explode(',', $params['district']);
        list($districtsql, $districtparam) = $DB->get_in_or_equal($districts, SQL_PARAMS_NAMED, 'district');
        $params = array_merge($params, $districtparam);
        $sql .= " AND u.open_district {$districtsql} ";
    }
    if (!empty($params['states'])) {
        $state = explode(',', $params['states']);
        list($statessql, $statesparam) = $DB->get_in_or_equal($state, SQL_PARAMS_NAMED, 'states');
        $params = array_merge($params, $statesparam);
        $sql .= " AND u.open_states {$statessql} ";
    }
    if (!empty($params['groups'])) {
        $query = "SELECT u.id 
                FROM {user} AS u
                JOIN {cohort_members} AS cm ON cm.userid=u.id 
                WHERE cm.cohortid IN ({$params['groups']})";
        $groupusers = $DB->get_records_sql_menu($query);
       if($userslist){
            $userslist = implode(',', $groupusers);
            $sql .=" AND u.id IN ($userslist) ";
       }else{
        $sql .=" AND u.id IN (NULL) ";
       }
    }
    if ($type=='add') {
        $sql .= " AND u.id NOT IN (SELECT userid FROM {cohort_members} WHERE cohortid = $groupid) ";
    } elseif ($type=='remove') {
        $sql .= " AND u.id IN (SELECT userid FROM {cohort_members} WHERE cohortid = $groupid) ";
    } 
    $order = ' ORDER BY u.id ASC ';
    if($total==0){
        $availableusers = $DB->get_records_sql_menu($sql .$order,$params);
    }else{
        $availableusers = $DB->count_records_sql($sql,$params);
    }

    return $availableusers;
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function local_core_groups_inplace_editable($itemtype, $itemid, $newvalue) {
    if ($itemtype === 'groupsname') {
        return \core_groups\output\groupsname::update($itemid, $newvalue);
    } else if ($itemtype === 'groupsidnumber') {
        return \core_groups\output\groupsidnumber::update($itemid, $newvalue);
    }
}


/**
 * [groups_filter form element function]
 * @param  [form] $mform [filter form]
 * @return 
 */
function groups_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB, $USER;
    $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
    $groupslist=array();
    $data=data_submitted();
    $costcenterpathconcatsql = (new \local_groups\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $groupslist_sql="SELECT c.id, c.name as fullname FROM {local_groups} g, {cohort} c  WHERE c.visible = 1 AND c.id = g.cohortid $costcenterpathconcatsql";
    if(!empty($query)){ 
        if ($searchanywhere) {
            $groupslist_sql.=" AND c.name LIKE '%$query%' ";
        } else {
            $groupslist_sql.=" AND c.name LIKE '$query%' ";
        }
    }
    if(isset($data->groups)&&!empty(($data->groups))){
        list($groups_sql, $groupsparam) = $DB->get_in_or_equal($data->groups, SQL_PARAMS_NAMED);
        $groupslist_sql.=" AND c.id $groups_sql ";
    }
    if(!empty($query)||empty($mform)){ 
        $groupslist = $DB->get_records_sql($groupslist_sql, $groupsparam, $page, $perpage);
        return $groupslist;
    }
    if((isset($data->groups)&&!empty($data->groups))){ 
        $groupslist = $DB->get_records_sql_menu($groupslist_sql, $groupsparam, $page, $perpage);
    }
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'groups',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('cohort', 'local_groups')
     );
    $select = $mform->addElement('autocomplete', 'groups', '', $groupslist,$options);
    $mform->setType('groups', PARAM_RAW);
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_groups_leftmenunode(){
    $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
    $groupnode = '';
    if(has_capability('moodle/cohort:manage',$systemcontext) || is_siteadmin()) {
        $groupnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_groups', 'class'=>'pull-left user_nav_div users'));
            $users_url = new moodle_url('/local/groups/index.php');
            $users = html_writer::link($users_url, '<i class="fa fa-users" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('leftmenu_groups','local_groups').'</span>',array('class'=>'user_navigation_link'));
            $groupnode .= $users;
        $groupnode .= html_writer::end_tag('li');
    }

    return array('5' => $groupnode);
}
/**
 * process the groups_mass_enroll
 * @param csv_import_reader $cir  an import reader created by caller
 * @param Object $groups  a groups record from table mdl_local_groups
 * @param Object $context  course context instance
 * @param Object $data    data from a moodleform
 * @return string  log of operations
 */
function groups_mass_enroll($cir, $groups, $context, $data) {
    global $CFG,$DB, $USER;
    require_once ($CFG->dirroot . '/group/lib.php');
    // init csv import helper
    $useridfield = $data->firstcolumn;
    $cir->init();
    $enrollablecount = 0;
    while ($fields = $cir->next()) {
        $a = new stdClass();
        if (empty ($fields))
            continue;
        $fields[0]= str_replace('"', '', trim($fields[0]));
        /*First Condition To validate users*/
        $costcenterpathconcatsql = (new \local_groups\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $sql="SELECT u.* from {user} u where u.deleted=0 and u.suspended=0 and u.$useridfield='$fields[0]' $costcenterpathconcatsql";
        $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
        if (!$user = $DB->get_record_sql($sql)) {
            $result .= '<div class="alert alert-error">'.get_string('im:user_unknown', 'local_courses', $fields[0] ). '</div>';
            continue;
        } else {
            if ($DB->record_exists('cohort_members', array('cohortid'=>$groups->id, 'userid'=>$user->id))) {               //
                $result .= '<div class="alert alert-error">'.get_string('user_exist', 'local_groups', $fields[0] ). '</div>';
                continue;
            } else {
                $record = new stdClass();
                $record->cohortid  = $groups->id;
                $record->userid    = $user->id;
                $record->timeadded = time();
                $DB->insert_record('cohort_members', $record);
                $result .= '<div class="alert alert-success">'.get_string('im:stats_i', 'local_groups', fullname($user)).'</div>';

                $enrollablecount ++;
           }
        }
    }
    $result .= '<br/>';
    $result .= get_string('im:stats_i', 'local_groups', $enrollablecount) . "";
    return $result;
}
function local_groups_output_fragment_new_groupsform($args){
    global $CFG,$DB;
    $args = (object) $args;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
    $serialiseddata = json_decode($args->jsonformdata);
    if(is_object($serialiseddata)){
        $serialiseddata = serialize($serialiseddata);
    }
    parse_str($serialiseddata, $formdata);
        
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => true,
        'subdirs' => false
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);
    if ($args->groupsid > 0) {
        $heading = get_string('libupdategroup','local_groups');
        $collapse = false;
        $data = $DB->get_record('cohort', array('id'=>$args->groupsid));
        
        $data->description_editor['text'] = $data->description;
        $data->description_editor['format'] = FORMAT_HTML;

        $groups_data = $DB->get_record('local_groups', array('cohortid'=>$data->id));
        $customdata = array('id' => $groups_data->id,
        'open_path' => $groups_data->open_path,
        'editoroptions' => $editoroptions,
        'data' => $data
    );
    local_costcenter_set_costcenter_path($customdata);
    local_users_set_userprofile_datafields($customdata,$groups_data);
        $mform = new local_groups\form\edit_form(null, $customdata, 'post', '', null, true, $formdata);
        $mform->set_data($data);
    }
    else{
        $customdata = array(
        'editoroptions' => $editoroptions,
    );
    local_costcenter_set_costcenter_path($customdata);
    local_users_set_userprofile_datafields($customdata,$formdata);
        $mform = new local_groups\form\edit_form(null,$customdata, 'post', '', null, true, $formdata);
    }
    if (!empty($formdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
 
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

   
