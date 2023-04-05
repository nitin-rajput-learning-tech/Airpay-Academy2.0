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
 * @subpackage local_evaluation
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/user/editlib.php');
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot.'/local/evaluation/lib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');
/** Include eventslib.php */
//require_once($CFG->libdir.'/eventslib.php');
// Include forms lib.
define('EVALUATION_ANONYMOUS_YES', 1);
define('EVALUATION_ANONYMOUS_NO', 2);
define('EVALUATION_MIN_ANONYMOUS_COUNT_IN_GROUP', 2);
define('EVALUATION_DECIMAL', '.');
define('EVALUATION_THOUSAND', ',');
define('EVALUATION_RESETFORM_RESET', 'evaluation_reset_data_');
define('EVALUATION_RESETFORM_DROP', 'evaluation_drop_evaluation_');
define('EVALUATION_MAX_PIX_LENGTH', '400'); //max. Breite des grafischen Balkens in der Auswertung
define('EVALUATION_DEFAULT_PAGE_COUNT', 20);

// Event types.
define('EVALUATION_EVENT_TYPE_OPEN', 'open');
define('EVALUATION_EVENT_TYPE_CLOSE', 'close');

/**
 * Returns all other caps used in module.
 *
 * @return array
 */
function evaluation_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function evaluation_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Serve the new evalaution form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_evaluation_output_fragment_new_evaluation_form($args) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/local/evaluation/evaluation_form.php');
    $args = (object) $args;
    $id = $args->evalid;
    $instance = $args->instance;
    $plugin = $args->plugin;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        parse_str($args->jsonformdata, $formdata);
    }
    $data = new stdclass();
    if ($id > 0) {
		$data = $DB->get_record('local_evaluations', array('id'=>$id));
	}
    // Used to set the courseid.
    $customdata = array(
        'open_path' => $data->open_path,'id' => $id, 'instance'=>$instance, 'plugin'=>$plugin);
        local_costcenter_set_costcenter_path($customdata);
        local_users_set_userprofile_datafields($customdata,$data);
        $mform = new \evaluation_form(null, $customdata,'post', '', null, true, $formdata);
        if ($data->id > 0) {
		$data->introeditor['text'] = $data->intro;
        $data->departmentid = explode(',',$data->departmentid);
        // Populate tags.
       // $data->tags = local_tags_tag::get_item_tags_array('local_evaluation', 'evaluation', $id);
        $default_values = (array)$data;
		$mform->data_preprocessing($default_values);
	}
	$mform->set_data($default_values);

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
/**
 * creates a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $evaluation the object given by local_evaluation_local_form
 * @return int id of the new instance.
 */
function evaluation_add_instance($evaluation) {
    global $DB, $USER,$CFG;
    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    $evaluation->timemodified = time();
    $evaluation->usermodified = $USER->id;


    $introeditor = $evaluation->introeditor;
    unset($evaluation->introeditor);
    $evaluation->intro       = $introeditor['text'];
    $evaluation->introformat = $introeditor['format'];

    if (empty($evaluation->site_after_submit)) {
        $evaluation->site_after_submit = '';
    }
    $evaluation->intro = file_save_draft_area_files($introeditor['itemid'], $context->id, 'local_evaluation', 'intro', 0, array('subdirs'=>true), $evaluation->intro);

    if ($evaluation->instance>0) {
            require_once($CFG->dirroot . '/local/'.$evaluation->plugin.'/lib.php');
            $function = $evaluation->plugin.'_manage_evaluations';
            if(function_exists($function)) {
                $evaltypes = $function($evaluation,'add');
            }
    }else{
        $evaluationid = $DB->insert_record("local_evaluations", $evaluation);
        $evaluation->id = $evaluationid;
    }
    evaluation_set_events($evaluation); // no problem with local_instance
    $editoroptions = evaluation_get_editor_options();

    // process the custom wysiwyg editor in page_after_submit
    if ($draftitemid = $evaluation->page_after_submit_editor['itemid']) {
        $evaluation->page_after_submit = file_save_draft_area_files($draftitemid, $context->id,
                                                    'local_evaluation', 'page_after_submit',
                                                    0, $editoroptions,
                                                    $evaluation->page_after_submit_editor['text']);
        $evaluation->page_after_submitformat = $evaluation->page_after_submit_editor['format'];
    }
    $DB->update_record('local_evaluations', $evaluation);

    // Update evaluation tags.

    // Trigger evaluation created event.
    if ($evaluation->instance == 0) {
        $params = array(
            'context' => $context,
            'objectid' => $evaluation->id
        );

        $event = \local_evaluation\event\evaluation_created::create($params);
        $event->add_record_snapshot('local_evaluations', $evaluation);
        $event->trigger();
    }
    return $evaluation->id;
}

/**
 * updates a given instance
 *
 * @global object
 * @param object $evaluation the object given by local_evaluation_local_form
 * @return int evaluation id
 */
function evaluation_update_instance($evaluation) {
    global $DB, $USER,$CFG;
    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    $evaluation->timemodified = time();
    $evaluation->usermodified = $USER->id;
    $introeditor = $evaluation->introeditor;
    unset($evaluation->introeditor);
    $evaluation->intro       = $introeditor['text'];
    $evaluation->introformat = $introeditor['format'];
    if (empty($evaluation->site_after_submit)) {
        $evaluation->site_after_submit = '';
    }

    $evaluation->intro = file_save_draft_area_files($introeditor['itemid'], $context->id, 'local_evaluation', 'intro', 0, array('subdirs'=>true), $evaluation->intro);
    //save the evaluation into the db
    $DB->update_record("local_evaluations", $evaluation);

    //create or update the new events
    evaluation_set_events($evaluation);
     // update ILT if evaluation
    if ($evaluation->instance>0) {
            require_once($CFG->dirroot . '/local/'.$evaluation->plugin.'/lib.php');
            $function = $evaluation->plugin.'_manage_evaluations';
            if(function_exists($function)) {
                $evaltypes = $function($evaluation,'update');
            }
    }
    $editoroptions = evaluation_get_editor_options();
    // process the custom wysiwyg editor in page_after_submit
    if ($draftitemid = $evaluation->page_after_submit_editor['itemid']) {
        $evaluation->page_after_submit = file_save_draft_area_files($draftitemid, $context->id,
                                                    'local_evaluation', 'page_after_submit',
                                                    0, $editoroptions, $evaluation->page_after_submit_editor['text']);
        $evaluation->page_after_submitformat = $evaluation->page_after_submit_editor['format'];
    }
    $DB->update_record('local_evaluations', $evaluation);

    // Update evaluation tags.

    // Trigger evaluation created event.
    $params = array(
        'context' => $context,
        'objectid' => $evaluation->id
    );

    // Trigger evaluation created event.
    $params = array(
        'context' => $context,
        'objectid' => $evaluation->id
    );

    $event = \local_evaluation\event\evaluation_updated::create($params);
    $event->add_record_snapshot('local_evaluations', $evaluation);
    $event->trigger();
    return $evaluation->id;
}

/**
 * deletes a given instance

 * @param @param int $id the instanceid of evaluation
 * @return boolean
 */
function check_evaluationdeletion($evalautionid){
    global $DB;
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_classroom_trainers')) {
        if ($DB->record_exists('local_classroom_trainers', array('feedback_id'=>$evalautionid)) )
        return false;
    else
    return true;
    }
    else
    return true;
}

/**
 * this will return sql statement

 * @param $context int contexid of evaluation
 * @return string query based on the role
 */
function dep_sql($context) {
    global $DB, $USER;
    $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    if ( has_capability('local/costcenter:manage_multiorganizations', $context ) ) {
        $sql = "$costcenterpathconcatsql ";
    }
    return $sql;
}

/**
 * Add course module.
 *
 * The function does not check user capabilities.
 * The function creates course module, module instance, add the module to the correct section.
 * It also trigger common action that need to be done after adding/updating a module.
 *
 * @param object $moduleinfo the moudle data
 * @param object $course the course of the module
 * @param object $mform this is required by an existing hack to deal with files during MODULENAME_add_instance()
 * @return object the updated module info
 */
function add_evaluation($moduleinfo, $mform) {
    global $DB, $CFG;

    // Make database changes, so start transaction.
    $transaction = $DB->start_delegated_transaction();

    try {
        $returnfromfunc = evaluation_add_instance($moduleinfo, $mform);
    } catch (moodle_exception $e) {
        $returnfromfunc = $e;
    }

    $transaction->allow_commit();
    return $returnfromfunc;
}

/**
 * this will delete a given instance.
 * all referenced data also will be deleted
 *
 * @global object
 * @param int $id the instanceid of evaluation
 * @return boolean
 */
function evaluation_delete_instance($id) {
    global $DB,$CFG, $USER;

    //get all referenced items
    $evaluationitems = $DB->get_records('local_evaluation_item', array('evaluation'=>$id));

    //deleting all referenced items and values
    if (is_array($evaluationitems)) {
        foreach ($evaluationitems as $evaluationitem) {
            $DB->delete_records("local_evaluation_value", array("item"=>$evaluationitem->id));
            $DB->delete_records("local_eval_valuetmp", array("item"=>$evaluationitem->id));
        }
    }

    //deleting the completeds
    // $DB->delete_records("local_evaluation_completed", array("evaluation"=>$id));

    //deleting the unfinished completeds
    $DB->delete_records("local_eval_completedtmp", array("evaluation"=>$id));

    // delete events related to local evaluation
    $DB->delete_records('event', array('plugin_instance'=>$id, 'plugin'=>'local_evaluation'));

    $evaluation=$DB->get_record_sql("SELECT id,plugin,instance
                                            FROM {local_evaluations} WHERE id =$id");

    if ($evaluation->instance > 0) {

        require_once($CFG->dirroot . '/local/'.$evaluation->plugin.'/lib.php');

        $function = $evaluation->plugin.'_evaluation_completed';

        if(function_exists($function)) {

           $function($id,0,'update');
        }
     }


    $deletedobject = new stdClass();
    $deletedobject->id = $id;
    $deletedobject->timemodified = time();
    $deletedobject->usermodified = $USER->id;
    $deletedobject->deleted = 1;
    return $DB->update_record("local_evaluations",  $deletedobject);
}

/**
 * @return bool true
 */
function evaluation_cron () {
    return true;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function evaluation_get_view_actions() {
    return array('view', 'view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function evaluation_get_post_actions() {
    return array('submit');
}

/**
 * This gets an array with default options for the editor
 *
 * @return array the options
 */
function evaluation_get_editor_options() {
    return array('maxfiles' => EDITOR_UNLIMITED_FILES,
                'trusttext'=>true);
}

/**
 * This creates new events given as timeopen and closeopen by $evaluation.
 *
 * @global object
 * @param object $evaluation
 * @return void
 */
function evaluation_set_events($evaluation) {
    global $DB, $CFG, $USER;
    // Include calendar/lib.php.
    require_once($CFG->dirroot.'/calendar/lib.php');

    // evaluation start calendar events.
    $eventid = $DB->get_field('event', 'id',
            array('modulename' => '0', 'instance' => 0, 'plugin'=> 'local_evaluation', 'plugin_instance'=>$evaluation->id, 'eventtype' => EVALUATION_EVENT_TYPE_OPEN, 'local_eventtype' => EVALUATION_EVENT_TYPE_OPEN));

    if (isset($evaluation->timeopen) && $evaluation->timeopen > 0) {
        $event = new stdClass();
        $event->eventtype    = EVALUATION_EVENT_TYPE_OPEN;
        $event->type         = empty($evaluation->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->name         = get_string('calendarstart', 'local_evaluation', $evaluation->name);
        $event->description  = "<a href='$CFG->wwwroot/local/evaluation/eval_view.php?id=$evaluation->id>'>$evaluation->name</a>";
        $event->timestart    = $evaluation->timeopen;
        $event->timesort     = $evaluation->timeopen;
        $event->visible      = $evaluation->visible;
        $event->timeduration = 0;
        $event->plugin_instance = $evaluation->id;
        $event->plugin = 'local_evaluation';
        $event->local_eventtype    = EVALUATION_EVENT_TYPE_OPEN;
        $event->relateduserid    = $USER->id;
        if ($eventid) {
            // Calendar event exists so update it.
            $event->id = $eventid;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            // Event doesn't exist so create one.
            $event->courseid     = 0;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 0;
            $event->instance     = 0;
            $event->eventtype    = EVALUATION_EVENT_TYPE_OPEN;

            //print_object($event);
            calendar_event::create($event);
        }
    } else if ($eventid) {
        // Calendar event is on longer needed.
        $calendarevent = calendar_event::load($eventid);
        $calendarevent->delete();
    }

    // evaluation close calendar events.
    $eventid = $DB->get_field('event', 'id',
            array('modulename' => '0', 'instance' => 0, 'plugin'=> 'local_evaluation', 'plugin_instance'=>$evaluation->id, 'eventtype' => EVALUATION_EVENT_TYPE_CLOSE, 'local_eventtype' => EVALUATION_EVENT_TYPE_CLOSE));

    if (isset($evaluation->timeclose) && $evaluation->timeclose > 0) {
        $event = new stdClass();
        $event->type         = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype    = EVALUATION_EVENT_TYPE_CLOSE;
        $event->name         = get_string('calendarend', 'local_evaluation', $evaluation->name);
        $event->description  = "<a href='$CFG->wwwroot/local/evaluation/eval_view.php?id=$evaluation->id>'>$evaluation->name</a>";
        $event->timestart    = $evaluation->timeclose;
        $event->timesort     = $evaluation->timeclose;
        $event->visible      = $evaluation->visible;;
        $event->timeduration = 0;
        $event->plugin_instance = $evaluation->id;
        $event->plugin = 'local_evaluation';
        $event->local_eventtype    = EVALUATION_EVENT_TYPE_CLOSE;
        $event->relateduserid    = $USER->id;
        if ($eventid) {
            // Calendar event exists so update it.
            $event->id = $eventid;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            // Event doesn't exist so create one.
            $event->courseid     = 0;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 0;
            $event->instance     = 0;
            calendar_event::create($event);
        }
    } else if ($eventid) {
        // Calendar event is on longer needed.
        $calendarevent = calendar_event::load($eventid);
        $calendarevent->delete();
    }
}

////////////////////////////////////////////////
//functions to handle capabilities
////////////////////////////////////////////////

/**
 *  returns true if the current role is faked by switching role feature
 *
 * @global object
 * @return boolean
 */
function evaluation_check_is_switchrole() {
    global $USER;
    if (isset($USER->switchrole) AND
            is_array($USER->switchrole) AND
            count($USER->switchrole) > 0) {

        return true;
    }
    return false;
}

/**
 * count users which have not completed the evaluation
 *
 * @global object
 * @param evaluation $evaluation feedback object
 * @param int $group single groupid
 * @param string $sort
 * @param int $startpage
 * @param int $pagecount
 * @param bool $includestatus to return if the user started or not the evaluation among the complete user record
 * @return array array of user ids or user objects when $includestatus set to true
 */
function evaluation_get_incomplete_users($evaluation,
                                       $group = false,
                                       $sort = '',
                                       $startpage = false,
                                       $pagecount = false,
                                       $includestatus = false) {

    global $DB;

    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $userprofilesql = (new \local_users\lib\accesslib())::get_userprofilematch_concatsql($evaluation);
    //first get all user who can complete this evaluation
    $cap = 'local/evaluation:complete';

    $sql = "SELECT u.id, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
    u.firstname, u.lastname, u.picture, u.email, u.imagealt FROM {user} u
                WHERE u.id >1 AND u.deleted=0 AND u.suspended=0 $costcenterpathconcatsql $userprofilesql
                 ";
    if($evaluation->id != 0){
        $enrolled_users=$DB->get_fieldset_sql("SELECT userid FROM {local_evaluation_users} WHERE evaluationid = $evaluation->id");
    } else {
        $enrolled_users=$DB->get_fieldset_sql("SELECT userid FROM {local_evaluation_users}");
    }

    array_push($enrolled_users, 1);
    $enrolled_userslist = implode(',',$enrolled_users);
    if(!empty($enrolled_userslist)){
        $sql.=' AND u.id in(' . $enrolled_userslist . ')';
    }

    $existing_users = $DB->get_fieldset_sql("SELECT userid FROM {local_evaluation_completed} WHERE evaluation = $evaluation->id");
    array_push($existing_users, 1);
    $existing_userslist = implode(',',$existing_users);
    if(!empty($existing_userslist)){
        $sql.=' AND u.id not in(' . $existing_userslist . ')';
    }
    $allusers = $DB->get_records_sql($sql);

    //for paging I use array_slice()
    if ($startpage !== false AND $pagecount !== false) {
        $allusers = array_slice($allusers, $startpage, $pagecount);
    }
    return $allusers;
}

/**
 * count users which have not completed the evaluation
 *
 * @global object
 * @param object $evaluation
 * @param int $group single groupid
 * @return int count of userrecords
 */
function evaluation_count_incomplete_users($evaluation, $group = false) {
    if ($allusers = evaluation_get_incomplete_users($evaluation, $group)) {
        return count($allusers);
    }
    return 0;
}

////////////////////////////////////////////////
//functions to handle the templates
////////////////////////////////////////////////
////////////////////////////////////////////////

/**
 * creates a new template-record.
 *
 * @global object
 * @param int $courseid
 * @param string $name the name of template shown in the templatelist
 * @param int $ispublic 0:privat 1:public
 * @return int the new templateid
 */
function evaluation_create_template($courseid, $name, $ispublic = 0, $data) {
    global $DB, $USER;
    $templ = new stdClass();
    $templ->course   = 0;
    $templ->name     = $name;
    $templ->ispublic = $ispublic;

    $evaluation = $DB->get_record('local_evaluations', array('id'=>$data->id));
    if ($evaluation->instance == 0) {
        $templ->open_path = $evaluation->open_path;
    } else {
        $templ->open_path = (new \local_evaluation\lib\accesslib())::get_user_roleswitch_path();
    }
    $templid = $DB->insert_record('local_evaluation_template', $templ);
    return $DB->get_record('local_evaluation_template', array('id'=>$templid));
}

/**
 * creates new template items.
 * all items will be copied and the attribute evaluation will be set to 0
 * and the attribute template will be set to the new templateid
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CONTEXT_COURSE
 * @param object $evaluation
 * @param string $name the name of template shown in the templatelist
 * @param int $ispublic 0:privat 1:public
 * @return boolean
 */
function evaluation_save_as_template($evaluation, $name, $ispublic = 0, $data) {
    global $DB;
    $fs = get_file_storage();
    if (!$evaluationitems = $DB->get_records('local_evaluation_item', array('evaluation'=>$evaluation->id))) {
        return false;
    }
    if (!$newtempl = evaluation_create_template(0, $name, $ispublic, $data)) {
        return false;
    }

    //files in the template_item are in the context of the current course or
    //if the template is public the files are in the system context
    //files in the evaluation_item are in the evaluation_context of the evaluation

    $s_context = (new \local_evaluation\lib\accesslib())::get_module_context();

    //create items of this new template
    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();
    foreach ($evaluationitems as $item) {

        $t_item = clone($item);

        unset($t_item->id);
        $t_item->evaluation = 0;
        $t_item->template     = $newtempl->id;
        $t_item->id = $DB->insert_record('local_evaluation_item', $t_item);
        //copy all included files to the evaluation_template filearea
        $itemfiles = $fs->get_area_files($s_context->id,
                                    'local_evaluation',
                                    'item',
                                    $item->id,
                                    "id",
                                    false);
        if ($itemfiles) {
            foreach ($itemfiles as $ifile) {
                $file_record = new stdClass();
                $file_record->contextid = $s_context->id;
                $file_record->component = 'local_evaluation';
                $file_record->filearea = 'template';
                $file_record->itemid = $t_item->id;
                $fs->create_file_from_storedfile($file_record, $ifile);
            }
        }

        $itembackup[$item->id] = $t_item->id;
        if ($t_item->dependitem) {
            $dependitemsmap[$t_item->id] = $t_item->dependitem;
        }

    }

    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('local_evaluation_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('local_evaluation_item', $newitem);
    }

    return true;
}

/**
 * deletes all evaluation_items related to the given template id
 *
 * @global object
 * @uses CONTEXT_COURSE
 * @param object $template the template
 * @return void
 */
function evaluation_delete_template($template) {
    global $DB;

    //deleting the files from the item is done by evaluation_delete_item
    if ($t_items = $DB->get_records("local_evaluation_item", array("template"=>$template->id))) {
        foreach ($t_items as $t_item) {
            evaluation_delete_item($t_item->id, false, $template);
        }
    }
    $DB->delete_records("local_evaluation_template", array("id"=>$template->id));
}

/**
 * creates new evaluation_item-records from template.
 * if $deleteold is set true so the existing items of the given evaluation will be deleted
 * if $deleteold is set false so the new items will be appanded to the old items
 *
 * @global object
 * @uses CONTEXT_COURSE
 * @uses CONTEXT_MODULE
 * @param object $evaluation
 * @param int $templateid
 * @param boolean $deleteold
 */
function evaluation_items_from_template($evaluation, $templateid, $deleteold = false) {
    global $DB, $CFG;

    require_once($CFG->libdir.'/completionlib.php');

    $fs = get_file_storage();

    if (!$template = $DB->get_record('local_evaluation_template', array('id'=>$templateid))) {
        return false;
    }
    //get all templateitems
    if (!$templitems = $DB->get_records('local_evaluation_item', array('template'=>$templateid))) {
        return false;
    }

    $s_context = (new \local_evaluation\lib\accesslib())::get_module_context();

    //if deleteold then delete all old items before
    //get all items
    if ($deleteold) {
        if ($evaluationitems = $DB->get_records('local_evaluation_item', array('evaluation'=>$evaluation->id))) {
            //delete all items of this evaluation
            foreach ($evaluationitems as $item) {
                evaluation_delete_item($item->id, false);
            }

            $params = array('evaluation'=>$evaluation->id);
            if ($completeds = $DB->get_records('local_evaluation_completed', $params)) {
                foreach ($completeds as $completed) {
                    $DB->delete_records('local_evaluation_completed', array('id' => $completed->id));
                }
            }
            $DB->delete_records('local_eval_completedtmp', array('evaluation'=>$evaluation->id));
        }
        $positionoffset = 0;
    } else {
        //if the old items are kept the new items will be appended
        //therefor the new position has an offset
        $positionoffset = $DB->count_records('local_evaluation_item', array('evaluation'=>$evaluation->id));
    }

    //create items of this new template
    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();

    foreach ($templitems as $t_item) {
        $item = clone($t_item);
        unset($item->id);
        $item->evaluation = $evaluation->id;
        $item->template = 0;
        $item->position = $item->position + $positionoffset;

        $item->id = $DB->insert_record('local_evaluation_item', $item);

        //moving the files to the new item
        $templatefiles = $fs->get_area_files($s_context->id,
                                        'local_evaluation',
                                        'template',
                                        $t_item->id,
                                        "id",
                                        false);
        if ($templatefiles) {
            foreach ($templatefiles as $tfile) {
                $file_record = new stdClass();
                $file_record->contextid = $s_context->id;
                $file_record->component = 'local_evaluation';
                $file_record->filearea = 'item';
                $file_record->itemid = $item->id;
                $fs->create_file_from_storedfile($file_record, $tfile);
            }
        }

        $itembackup[$t_item->id] = $item->id;
        if ($item->dependitem) {
            $dependitemsmap[$item->id] = $item->dependitem;
        }
    }

    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('local_evaluation_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('local_evaluation_item', $newitem);
    }
}

/**
 * get the list of available templates.
 * if the $onlyown param is set true so only templates from own course will be served
 * this is important for droping templates
 *
 * @global object
 * @param object $course
 * @param string $onlyownorpublic
 * @return array the template recordsets
 */
function evaluation_get_template_list($onlyownorpublic = '') {
    global $DB, $CFG, $USER;

    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    switch($onlyownorpublic) {
        case '':
            $templates = $DB->get_records_select('local_evaluation_template', 'course = ? OR ispublic = 1', array('course'=>0), 'name');
            break;
        case 'own':
            $templates = $DB->get_records('local_evaluation_template', array('course'=>0), 'name');
            break;
        case 'public':
            $templates = $DB->get_records('local_evaluation_template', array('ispublic'=>1), 'name');
            break;
        case 'all':
            if (is_siteadmin()) {
                $sql ="select id, name from {local_evaluation_template} where id > 0 ";
            } else{
                $deptsql = dep_sql($context);
                $sql ="select id, name from {local_evaluation_template} where id > 0 $deptsql ";
            }
            $templates = $DB->get_records_sql($sql);
            break;
    }
    return $templates;
}

////////////////////////////////////////////////
//Handling der Items
////////////////////////////////////////////////
////////////////////////////////////////////////

/**
 * load the lib.php from item-plugin-dir and returns the instance of the itemclass
 *
 * @param string $typ
 * @return evaluation_item_base the instance of itemclass
 */
function evaluation_get_item_class($typ) {
    global $CFG;
    require_once($CFG->dirroot.'/local/evaluation/item/evaluation_item_class.php');
    //get the class of item-typ
    $itemclass = 'evaluation_item_'.$typ;
    //get the instance of item-class
    if (!class_exists($itemclass)) {
        require_once($CFG->dirroot.'/local/evaluation/item/'.$typ.'/lib.php');
    }
    return new $itemclass();
}

/**
 * load the available item plugins from given subdirectory of $CFG->dirroot
 * the default is "mod/evaluation/item"
 *
 * @global object
 * @param string $dir the subdir
 * @return array pluginnames as string
 */
function evaluation_load_evaluation_items($dir = 'local/evaluation/item', $type) {
    global $CFG;
    $names = get_list_of_plugins($dir);
    $ret_names = array();
    foreach ($names as $name) {
        require_once($CFG->dirroot.'/'.$dir.'/'.$name.'/lib.php');
        if ($name == 'captcha')
        continue;
        if ($type == 2) {
            if ($name == 'textarea' OR $name == 'textfield' OR $name == 'label' OR $name == 'info' )
            continue;
        }
        if (class_exists('evaluation_item_'.$name)) {
            $ret_names[] = $name;
        }
    }
    return $ret_names;
}

/**
 * load the available item plugins to use as dropdown-options
 *
 * @global object
 * @return array pluginnames as string
 */
function evaluation_load_evaluation_items_options($type) {
    global $CFG;

    $evaluation_options = array("pagebreak" => get_string('add_pagebreak', 'local_evaluation'));

    if (!$evaluation_names = evaluation_load_evaluation_items('local/evaluation/item', $type)) {
        return array();
    }

    foreach ($evaluation_names as $fn) {
        $evaluation_options[$fn] = get_string($fn, 'local_evaluation');
    }
    asort($evaluation_options);
    return $evaluation_options;
}

/**
 * load the available items for the depend item dropdown list shown in the edit_item form
 *
 * @global object
 * @param object $evaluation
 * @param object $item the item of the edit_item form
 * @return array all items except the item $item, labels and pagebreaks
 */
function evaluation_get_depend_candidates_for_item($evaluation, $item) {
    global $DB;
    //all items for dependitem
    $where = "evaluation = ? AND typ != 'pagebreak' AND hasvalue = 1";
    $params = array($evaluation->id);
    if (isset($item->id) AND $item->id) {
        $where .= ' AND id != ?';
        $params[] = $item->id;
    }
    $dependitems = array(0 => get_string('choose'));
    $evaluationitems = $DB->get_records_select_menu('local_evaluation_item',
                                                  $where,
                                                  $params,
                                                  'position',
                                                  'id, label');

    if (!$evaluationitems) {
        return $dependitems;
    }
    //adding the choose-option
    foreach ($evaluationitems as $key => $val) {
        if (trim(strval($val)) !== '') {
            $dependitems[$key] = format_string($val);
        }
    }
    return $dependitems;
}

/**
 * creates a new item-record
 *
 * @deprecated since 3.1
 * @param object $data the data from edit_item_form
 * @return int the new itemid
 */
function evaluation_create_item($data) {
    debugging('Function evaluation_create_item() is deprecated because it was not used.',
            DEBUG_DEVELOPER);
    global $DB;

    $item = new stdClass();
    $item->evaluation = $data->evaluationid;

    $item->template=0;
    if (isset($data->templateid)) {
            $item->template = intval($data->templateid);
    }

    $itemname = trim($data->itemname);
    $item->name = ($itemname ? $data->itemname : get_string('no_itemname', 'local_evaluation'));

    if (!empty($data->itemlabel)) {
        $item->label = trim($data->itemlabel);
    } else {
        $item->label = get_string('no_itemlabel', 'local_evaluation');
    }

    $itemobj = evaluation_get_item_class($data->typ);
    $item->presentation = ''; //the date comes from postupdate() of the itemobj

    $item->hasvalue = $itemobj->get_hasvalue();

    $item->typ = $data->typ;
    $item->position = $data->position;

    $item->required=0;
    if (!empty($data->required)) {
        $item->required = $data->required;
    }

    $item->id = $DB->insert_record('local_evaluation_item', $item);

    //move all itemdata to the data
    $data->id = $item->id;
    $data->evaluation = $item->evaluation;
    $data->name = $item->name;
    $data->label = $item->label;
    $data->required = $item->required;
    return $itemobj->postupdate($data);
}

/**
 * save the changes of a given item.
 *
 * @global object
 * @param object $item
 * @return boolean
 */
function evaluation_update_item($item) {
    global $DB;
    return $DB->update_record("local_evaluation_item", $item);
}

/**
 * deletes an item and also deletes all related values
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $itemid
 * @param boolean $renumber should the kept items renumbered Yes/No
 * @param object $template if the template is given so the items are bound to it
 * @return void
 */
function evaluation_delete_item($itemid, $renumber = true, $template = false) {
    global $DB;

    $item = $DB->get_record('local_evaluation_item', array('id'=>$itemid));

    //deleting the files from the item
    $fs = get_file_storage();

    if ($template) {
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $templatefiles = $fs->get_area_files($context->id,
                                    'local_evaluation',
                                    'template',
                                    $item->id,
                                    "id",
                                    false);

        if ($templatefiles) {
            $fs->delete_area_files($context->id, 'local_evaluations', 'template', $item->id);
        }
    } else {

        $context = (new \local_evaluation\lib\accesslib())::get_module_context();

        $itemfiles = $fs->get_area_files($context->id,
                                    'local_evaluation',
                                    'item',
                                    $item->id,
                                    "id", false);

        if ($itemfiles) {
            $fs->delete_area_files($context->id, 'local_evaluation', 'item', $item->id);
        }
    }

    $DB->delete_records("local_evaluation_value", array("item"=>$itemid));
    $DB->delete_records("local_eval_valuetmp", array("item"=>$itemid));

    //remove all depends
    $DB->set_field('local_evaluation_item', 'dependvalue', '', array('dependitem'=>$itemid));
    $DB->set_field('local_evaluation_item', 'dependitem', 0, array('dependitem'=>$itemid));

    $DB->delete_records("local_evaluation_item", array("id"=>$itemid));
    if ($renumber) {
        evaluation_renumber_items($item->evaluation);
    }
}

/**
 * deletes all items of the given evaluationid
 *
 * @global object
 * @param int $evaluationid
 * @return void
 */
function evaluation_delete_all_items($evaluationid) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if (!$evaluation = $DB->get_record('local_evaluations', array('id'=>$evaluationid))) {
        return false;
    }

    if (!$items = $DB->get_records('local_evaluation_item', array('evaluation'=>$evaluationid))) {
        return;
    }
    foreach ($items as $item) {
        evaluation_delete_item($item->id, false);
    }
    if ($completeds = $DB->get_records('local_evaluation_completed', array('evaluation'=>$evaluation->id))) {
        foreach ($completeds as $completed) {
            $DB->delete_records('local_evaluation_completed', array('id' => $completed->id));
        }
    }

    $DB->delete_records('local_eval_completedtmp', array('evaluation'=>$evaluationid));

}

/**
 * this function toggled the item-attribute required (yes/no)
 *
 * @global object
 * @param object $item
 * @return boolean
 */
function evaluation_switch_item_required($item) {
    global $DB, $CFG;

    $itemobj = evaluation_get_item_class($item->typ);

    if ($itemobj->can_switch_require()) {
        $new_require_val = (int)!(bool)$item->required;
        $params = array('id'=>$item->id);
        $DB->set_field('local_evaluation_item', 'required', $new_require_val, $params);
    }
    return true;
}

/**
 * renumbers all items of the given evaluationid
 *
 * @global object
 * @param int $evaluationid
 * @return void
 */
function evaluation_renumber_items($evaluationid) {
    global $DB;

    $items = $DB->get_records('local_evaluation_item', array('evaluation'=>$evaluationid), 'position');
    $pos = 1;
    if ($items) {
        foreach ($items as $item) {
            $DB->set_field('local_evaluation_item', 'position', $pos, array('id'=>$item->id));
            $pos++;
        }
    }
}

/**
 * this decreases the position of the given item
 *
 * @global object
 * @param object $item
 * @return bool
 */
function evaluation_moveup_item($item) {
    global $DB;

    if ($item->position == 1) {
        return true;
    }

    $params = array('evaluation'=>$item->evaluation);
    if (!$items = $DB->get_records('local_evaluation_item', $params, 'position')) {
        return false;
    }

    $itembefore = null;
    foreach ($items as $i) {
        if ($i->id == $item->id) {
            if (is_null($itembefore)) {
                return true;
            }
            $itembefore->position = $item->position;
            $item->position--;
            evaluation_update_item($itembefore);
            evaluation_update_item($item);
            evaluation_renumber_items($item->evaluation);
            return true;
        }
        $itembefore = $i;
    }
    return false;
}

/**
 * this increased the position of the given item
 *
 * @global object
 * @param object $item
 * @return bool
 */
function evaluation_movedown_item($item) {
    global $DB;

    $params = array('evaluation'=>$item->evaluation);
    if (!$items = $DB->get_records('local_evaluation_item', $params, 'position')) {
        return false;
    }

    $movedownitem = null;
    foreach ($items as $i) {
        if (!is_null($movedownitem) AND $movedownitem->id == $item->id) {
            $movedownitem->position = $i->position;
            $i->position--;
            evaluation_update_item($movedownitem);
            evaluation_update_item($i);
            evaluation_renumber_items($item->evaluation);
            return true;
        }
        $movedownitem = $i;
    }
    return false;
}

/**
 * here the position of the given item will be set to the value in $pos
 *
 * @global object
 * @param object $moveitem
 * @param int $pos
 * @return boolean
 */
function evaluation_move_item($moveitem, $pos) {
    global $DB;

    $params = array('evaluation'=>$moveitem->evaluation);
    if (!$allitems = $DB->get_records('local_evaluation_item', $params, 'position')) {
        return false;
    }
    if (is_array($allitems)) {
        $index = 1;
        foreach ($allitems as $item) {
            if ($index == $pos) {
                $index++;
            }
            if ($item->id == $moveitem->id) {
                $moveitem->position = $pos;
                evaluation_update_item($moveitem);
                continue;
            }
            $item->position = $index;
            evaluation_update_item($item);
            $index++;
        }
        return true;
    }
    return false;
}

/**
 * prints the given item as a preview.
 * each item-class has an own print_item_preview function implemented.
 *
 * @deprecated since Moodle 3.1
 * @global object
 * @param object $item the item what we want to print out
 * @return void
 */
function evaluation_print_item_preview($item) {
    debugging('Function evaluation_print_item_preview() is deprecated and does nothing. '
            . 'Items must implement complete_form_element()', DEBUG_DEVELOPER);
}

/**
 * prints the given item in the completion form.
 * each item-class has an own print_item_complete function implemented.
 *
 * @deprecated since Moodle 3.1
 * @param object $item the item what we want to print out
 * @param mixed $value the value
 * @param boolean $highlightrequire if this set true and the value are false on completing so the item will be highlighted
 * @return void
 */
function evaluation_print_item_complete($item, $value = false, $highlightrequire = false) {
    debugging('Function evaluation_print_item_complete() is deprecated and does nothing. '
            . 'Items must implement complete_form_element()', DEBUG_DEVELOPER);
}

/**
 * prints the given item in the show entries page.
 * each item-class has an own print_item_show_value function implemented.
 *
 * @deprecated since Moodle 3.1
 * @param object $item the item what we want to print out
 * @param mixed $value
 * @return void
 */
function evaluation_print_item_show_value($item, $value = false) {
    debugging('Function evaluation_print_item_show_value() is deprecated and does nothing. '
            . 'Items must implement complete_form_element()', DEBUG_DEVELOPER);
}

/**
 * if the user completes a evaluation and there is a pagebreak so the values are saved temporary.
 * the values are not saved permanently until the user click on save button
 *
 * @global object
 * @param object $evaluationcompleted
 * @return object temporary saved completed-record
 */
function evaluation_set_tmp_values($evaluationcompleted) {
    global $DB;

    //first we create a completedtmp
    $tmpcpl = new stdClass();
    foreach ($evaluationcompleted as $key => $value) {
        $tmpcpl->{$key} = $value;
    }
    unset($tmpcpl->id);
    $tmpcpl->timemodified = time();
    $tmpcpl->id = $DB->insert_record('local_eval_completedtmp', $tmpcpl);
    //get all values of original-completed
    if (!$values = $DB->get_records('local_evaluation_value', array('completed'=>$evaluationcompleted->id))) {
        return;
    }
    foreach ($values as $value) {
        unset($value->id);
        $value->completed = $tmpcpl->id;
        $DB->insert_record('local_eval_valuetmp', $value);
    }
    return $tmpcpl;
}

/**
 * this saves the temporary saved values permanently
 *
 * @global object
 * @param object $evaluationcompletedtmp the temporary completed
 * @param object $evaluationcompleted the target completed
 * @return int the id of the completed
 */
function evaluation_save_tmp_values($evaluationcompletedtmp, $evaluationcompleted) {
    global $DB, $CFG, $USER;

    $tmpcplid = $evaluationcompletedtmp->id;
    if ($evaluationcompleted) {
        //first drop all existing values
        $DB->delete_records('local_evaluation_value', array('completed'=>$evaluationcompleted->id));
        //update the current completed
        $evaluationcompleted->evaluatedby = $USER->id;
        $evaluationcompleted->timemodified = time();
        $DB->update_record('local_evaluation_completed', $evaluationcompleted);
    } else {
        $evaluationcompleted = clone($evaluationcompletedtmp);
        $evaluationcompleted->id = '';
        $evaluationcompleted->evaluatedby = $USER->id;
        $evaluationcompleted->timemodified = time();
        $evaluationcompleted->id = $DB->insert_record('local_evaluation_completed', $evaluationcompleted);
        if($evaluationcompleted->id){
            $evaluation=$DB->get_record_sql("SELECT id,plugin,instance
                                            FROM {local_evaluations} WHERE id = $evaluationcompleted->evaluation");

            if ($evaluation->instance>0) {

                require_once($CFG->dirroot . '/local/'.$evaluation->plugin.'/lib.php');

                $function = $evaluation->plugin.'_evaluation_completed';
                if(function_exists($function)) {
                $function($evaluationcompleted->evaluation,$evaluationcompleted->userid,'add');
                }
            }
         }
    }

    $allitems = $DB->get_records('local_evaluation_item', array('evaluation' => $evaluationcompleted->evaluation));

    //save all the new values from evaluation_valuetmp
    //get all values of tmp-completed
    $params = array('completed'=>$evaluationcompletedtmp->id);
    $values = $DB->get_records('local_eval_valuetmp', $params);
    foreach ($values as $value) {
        //check if there are depend items
        $item = $DB->get_record('local_evaluation_item', array('id'=>$value->item));
        if ($item->dependitem > 0 && isset($allitems[$item->dependitem])) {
            $check = evaluation_compare_item_value($tmpcplid,
                                        $allitems[$item->dependitem],
                                        $item->dependvalue,
                                        true);
        } else {
            $check = true;
        }
        if ($check) {
            unset($value->id);
            $value->completed = $evaluationcompleted->id;
            $DB->insert_record('local_evaluation_value', $value);
        }
    }
    //drop all the tmpvalues
    $DB->delete_records('local_eval_valuetmp', array('completed'=>$tmpcplid));
    $DB->delete_records('local_eval_completedtmp', array('id'=>$tmpcplid));

    return $evaluationcompleted->id;

}

/**
 * deletes the given temporary completed and all related temporary values
 *
 * @deprecated since Moodle 3.1
 *
 * @param int $tmpcplid
 * @return void
 */
function evaluation_delete_completedtmp($tmpcplid) {
    global $DB;

    debugging('Function evaluation_delete_completedtmp() is deprecated because it is no longer used',
            DEBUG_DEVELOPER);

    $DB->delete_records('local_eval_valuetmp', array('completed'=>$tmpcplid));
    $DB->delete_records('local_eval_completedtmp', array('id'=>$tmpcplid));
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle the pagebreaks
////////////////////////////////////////////////

/**
 * this creates a pagebreak.
 * a pagebreak is a special kind of item
 *
 * @global object
 * @param int $evaluationid
 * @return mixed false if there already is a pagebreak on last position or the id of the pagebreak-item
 */
function evaluation_create_pagebreak($evaluationid) {
    global $DB;

    //check if there already is a pagebreak on the last position
    $lastposition = $DB->count_records('local_evaluation_item', array('evaluation'=>$evaluationid));
    if ($lastposition == evaluation_get_last_break_position($evaluationid)) {
        return false;
    }

    $item = new stdClass();
    $item->evaluation = $evaluationid;

    $item->template=0;

    $item->name = '';

    $item->presentation = '';
    $item->hasvalue = 0;

    $item->typ = 'pagebreak';
    $item->position = $lastposition + 1;

    $item->required=0;

    return $DB->insert_record('local_evaluation_item', $item);
}

/**
 * get all positions of pagebreaks in the given evaluation
 *
 * @global object
 * @param int $evaluationid
 * @return array all ordered pagebreak positions
 */
function evaluation_get_all_break_positions($evaluationid) {
    global $DB;

    $params = array('typ'=>'pagebreak', 'evaluation'=>$evaluationid);
    $allbreaks = $DB->get_records_menu('local_evaluation_item', $params, 'position', 'id, position');
    if (!$allbreaks) {
        return false;
    }
    return array_values($allbreaks);
}

/**
 * get the position of the last pagebreak
 *
 * @param int $evaluationid
 * @return int the position of the last pagebreak
 */
function evaluation_get_last_break_position($evaluationid) {
    if (!$allbreaks = evaluation_get_all_break_positions($evaluationid)) {
        return false;
    }
    return $allbreaks[count($allbreaks) - 1];
}

/**
 * this returns the position where the user can continue the completing.
 *
 * @deprecated since Moodle 3.1
 * @global object
 * @global object
 * @global object
 * @param int $evaluationid
 * @param int $courseid
 * @param string $guestid this id will be saved temporary and is unique
 * @return int the position to continue
 */
function evaluation_get_page_to_continue($evaluationid, $courseid = false, $guestid = false) {
    global $CFG, $USER, $DB;

    debugging('Function evaluation_get_page_to_continue() is deprecated and since it is '
            . 'no longer used in local_evaluation', DEBUG_DEVELOPER);

    //is there any break?

    if (!$allbreaks = evaluation_get_all_break_positions($evaluationid)) {
        return false;
    }

    $params = array();
    if ($courseid) {
        $courseselect = "AND fv.course_id = :courseid";
        $params['courseid'] = $courseid;
    } else {
        $courseselect = '';
    }

    if ($guestid) {
        $userselect = "AND fc.guestid = :guestid";
        $usergroup = "GROUP BY fc.guestid";
        $params['guestid'] = $guestid;
    } else {
        $userselect = "AND fc.userid = :userid";
        $usergroup = "GROUP BY fc.userid";
        $params['userid'] = $USER->id;
    }

    $sql =  "SELECT MAX(fi.position)
               FROM {local_eval_completedtmp} fc, {local_eval_valuetmp} fv, {local_evaluation_item} fi
              WHERE fc.id = fv.completed
                    $userselect
                    AND fc.evaluation = :evaluationid
                    $courseselect
                    AND fi.id = fv.item
         $usergroup";
    $params['evaluationid'] = $evaluationid;

    $lastpos = $DB->get_field_sql($sql, $params);

    //the index of found pagebreak is the searched pagenumber
    foreach ($allbreaks as $pagenr => $br) {
        if ($lastpos < $br) {
            return $pagenr;
        }
    }
    return count($allbreaks);
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle the values
////////////////////////////////////////////////

/**
 * cleans the userinput while submitting the form.
 *
 * @deprecated since Moodle 3.1
 * @param mixed $value
 * @return mixed
 */
function evaluation_clean_input_value($item, $value) {
    debugging('Function evaluation_clean_input_value() is deprecated and does nothing. '
            . 'Items must implement complete_form_element()', DEBUG_DEVELOPER);
}

/**
 * this saves the values of an completed.
 * if the param $tmp is set true so the values are saved temporary in table evaluation_valuetmp.
 * if there is already a completed and the userid is set so the values are updated.
 * on all other things new value records will be created.
 *
 * @deprecated since Moodle 3.1
 *
 * @param int $usrid
 * @param boolean $tmp
 * @return mixed false on error or the completeid
 */
function evaluation_save_values($usrid, $tmp = false) {
    global $DB;

    debugging('Function evaluation_save_values() was deprecated because it did not have '.
            'enough arguments, was not suitable for non-temporary table and was taking '.
            'data directly from input', DEBUG_DEVELOPER);

    $completedid = optional_param('completedid', 0, PARAM_INT);
    $tmpstr = $tmp ? 'tmp' : '';
    // $time = time();
    // $timemodified = mktime(0, 0, 0, \local_costcenter\lib::get_userdate('m', $time), \local_costcenter\lib::get_userdate('d', $time), \local_costcenter\lib::get_userdate('Y', $time));
    $timemodified = strtotime(date('d/m/Y', time()));

    if ($usrid == 0) {
        return evaluation_create_values($usrid, $timemodified, $tmp);
    }
    $completed = $DB->get_record('local_evaluation_completed'.$tmpstr, array('id'=>$completedid));
    if (!$completed) {
        return evaluation_create_values($usrid, $timemodified, $tmp);
    } else {
        $completed->timemodified = $timemodified;
        return evaluation_update_values($completed, $tmp);
    }
}

/**
 * this saves the values from anonymous user such as guest on the main-site
 *
 * @deprecated since Moodle 3.1
 *
 * @param string $guestid the unique guestidentifier
 * @return mixed false on error or the completeid
 */
function evaluation_save_guest_values($guestid) {
    global $DB;

    debugging('Function evaluation_save_guest_values() was deprecated because it did not have '.
            'enough arguments, was not suitable for non-temporary table and was taking '.
            'data directly from input', DEBUG_DEVELOPER);

    $completedid = optional_param('completedid', false, PARAM_INT);

    $timemodified = time();
    if (!$completed = $DB->get_record('local_eval_completedtmp', array('id'=>$completedid))) {
        return evaluation_create_values(0, $timemodified, true, $guestid);
    } else {
        $completed->timemodified = $timemodified;
        return evaluation_update_values($completed, true);
    }
}

/**
 * get the value from the given item related to the given completed.
 * the value can come as temporary or as permanently value. the deciding is done by $tmp
 *
 * @global object
 * @param int $completeid
 * @param int $itemid
 * @param boolean $tmp
 * @return mixed the value, the type depends on plugin-definition
 */
function evaluation_get_item_value($completedid, $itemid, $tmp = false) {
    global $DB;

    $tmpstr = $tmp ? 'tmp' : '';
    $params = array('completed'=>$completedid, 'item'=>$itemid);
    return $DB->get_field('local_eval_value'.$tmpstr, 'value', $params);
}

/**
 * compares the value of the itemid related to the completedid with the dependvalue.
 * this is used if a depend item is set.
 * the value can come as temporary or as permanently value. the deciding is done by $tmp.
 *
 * @param int $completedid
 * @param stdClass|int $item
 * @param mixed $dependvalue
 * @param bool $tmp
 * @return bool
 */
function evaluation_compare_item_value($completedid, $item, $dependvalue, $tmp = false) {
    global $DB;

    if (is_int($item)) {
        $item = $DB->get_record('local_evaluation_item', array('id' => $item));
    }

    $dbvalue = evaluation_get_item_value($completedid, $item->id, $tmp);

    $itemobj = evaluation_get_item_class($item->typ);
    return $itemobj->compare_value($item, $dbvalue, $dependvalue); //true or false
}

/**
 * this function checks the correctness of values.
 * the rules for this are implemented in the class of each item.
 * it can be the required attribute or the value self e.g. numeric.
 * the params first/lastitem are given to determine the visible range between pagebreaks.
 *
 * @global object
 * @param int $firstitem the position of firstitem for checking
 * @param int $lastitem the position of lastitem for checking
 * @return boolean
 */
function evaluation_check_values($firstitem, $lastitem) {
    debugging('Function evaluation_check_values() is deprecated and does nothing. '
            . 'Items must implement complete_form_element()', DEBUG_DEVELOPER);
    return true;
}

/**
 * this function create a complete-record and the related value-records.
 * depending on the $tmp (true/false) the values are saved temporary or permanently
 *
 * @deprecated since Moodle 3.1
 *
 * @param int $userid
 * @param int $timemodified
 * @param boolean $tmp
 * @param string $guestid a unique identifier to save temporary data
 * @return mixed false on error or the completedid
 */
function evaluation_create_values($usrid, $timemodified, $tmp = false, $guestid = false) {
    global $DB,$CFG;
    debugging('Function evaluation_create_values() was deprecated because it did not have '.
            'enough arguments, was not suitable for non-temporary table and was taking '.
            'data directly from input', DEBUG_DEVELOPER);

    $tmpstr = $tmp ? 'tmp' : '';
    //first we create a new completed record
    $completed = new stdClass();
    $completed->evaluation           = $evaluationid;
    $completed->userid             = $usrid;
    $completed->guestid            = $guestid;
    $completed->timemodified       = $timemodified;
    $completed->anonymous_response = $anonymous_response;

    $completedid = $DB->insert_record('local_evaluation_completed'.$tmpstr, $completed);

   if($completedid){
     $evaluation=$DB->get_record_sql("SELECT id,plugin,instance
                                            FROM {local_evaluations} WHERE id =$evaluationid");

            if ($evaluation->instance>0) {

                require_once($CFG->dirroot . '/local/'.$evaluation->plugin.'/lib.php');

                $function = $evaluation->plugin.'_evaluation_completed';
                if(function_exists($function)) {
                $function($evaluationid,$usrid,'add');
                }
            }
   }

    $completed = $DB->get_record('local_evaluation_completed'.$tmpstr, array('id'=>$completedid));

    //the keys are in the form like abc_xxx
    //with explode we make an array with(abc, xxx) and (abc=typ und xxx=itemnr)

    //get the items of the evaluation
    if (!$allitems = $DB->get_records('local_evaluation_item', array('evaluation'=>$completed->evaluation))) {
        return false;
    }
    foreach ($allitems as $item) {
        if (!$item->hasvalue) {
            continue;
        }
        //get the class of item-typ
        $itemobj = evaluation_get_item_class($item->typ);

        $keyname = $item->typ.'_'.$item->id;

        if ($item->typ === 'multichoice') {
            $itemvalue = optional_param_array($keyname, null, PARAM_INT);
        } else {
            $itemvalue = optional_param($keyname, null, PARAM_NOTAGS);
        }

        if (is_null($itemvalue)) {
            continue;
        }

        $value = new stdClass();
        $value->item = $item->id;
        $value->completed = $completed->id;
        $value->course_id = $courseid;

        //the kind of values can be absolutely different
        //so we run create_value directly by the item-class
        $value->value = $itemobj->create_value($itemvalue);
        $DB->insert_record('local_evaluation_value'.$tmpstr, $value);
    }
    return $completed->id;
}

/**
 * this function updates a complete-record and the related value-records.
 * depending on the $tmp (true/false) the values are saved temporary or permanently
 *
 * @global object
 * @param object $completed
 * @param boolean $tmp
 * @return int the completedid
 */
function evaluation_update_values($completed, $tmp = false) {
    global $DB;

    debugging('Function evaluation_update_values() was deprecated because it did not have '.
            'enough arguments, was not suitable for non-temporary table and was taking '.
            'data directly from input', DEBUG_DEVELOPER);

    $courseid = optional_param('courseid', false, PARAM_INT);
    $tmpstr = $tmp ? 'tmp' : '';

    $DB->update_record('local_evaluation_completed'.$tmpstr, $completed);
    //get the values of this completed
    $values = $DB->get_records('local_evaluation_value'.$tmpstr, array('completed'=>$completed->id));

    //get the items of the evaluation
    if (!$allitems = $DB->get_records('local_evaluation_item', array('evaluation'=>$completed->evaluation))) {
        return false;
    }
    foreach ($allitems as $item) {
        if (!$item->hasvalue) {
            continue;
        }
        //get the class of item-typ
        $itemobj = evaluation_get_item_class($item->typ);

        $keyname = $item->typ.'_'.$item->id;

        if ($item->typ === 'multichoice') {
            $itemvalue = optional_param_array($keyname, null, PARAM_INT);
        } else {
            $itemvalue = optional_param($keyname, null, PARAM_NOTAGS);
        }

        //is the itemvalue set (could be a subset of items because pagebreak)?
        if (is_null($itemvalue)) {
            continue;
        }

        $newvalue = new stdClass();
        $newvalue->item = $item->id;
        $newvalue->completed = $completed->id;
        $newvalue->course_id = $courseid;

        //the kind of values can be absolutely different
        //so we run create_value directly by the item-class
        $newvalue->value = $itemobj->create_value($itemvalue);

        //check, if we have to create or update the value
        $exist = false;
        foreach ($values as $value) {
            if ($value->item == $newvalue->item) {
                $newvalue->id = $value->id;
                $exist = true;
                break;
            }
        }
        if ($exist) {
            $DB->update_record('local_evaluation_value'.$tmpstr, $newvalue);
        } else {
            $DB->insert_record('local_evaluation_value'.$tmpstr, $newvalue);
        }
    }

    return $completed->id;
}

/**
 * get the values of an item depending on the given groupid.
 * if the evaluation is anonymous so the values are shuffled
 *
 * @global object
 * @global object
 * @param object $item
 * @param int $groupid
 * @param int $courseid
 * @param bool $ignore_empty if this is set true so empty values are not delivered
 * @return array the value-records
 */
function evaluation_get_group_values($item,
                                   $groupid = false,
                                   $courseid = false,
                                   $ignore_empty = false) {

    global $CFG, $DB;

    //if the groupid is given?
    if (intval($groupid) > 0) {
        $params = array();
        if ($ignore_empty) {
            $value = $DB->sql_compare_text('fbv.value');
            $ignore_empty_select = "AND $value != :emptyvalue AND $value != :zerovalue";
            $params += array('emptyvalue' => '', 'zerovalue' => '0');
        } else {
            $ignore_empty_select = "";
        }

        $query = 'SELECT fbv .  *
                    FROM {local_evaluation_value} fbv, {local_evaluation_completed} fbc, {groups_members} gm
                   WHERE fbv.item = :itemid
                         AND fbv.completed = fbc.id
                         AND fbc.userid = gm.userid
                         '.$ignore_empty_select.'
                         AND gm.groupid = :groupid
                ORDER BY fbc.timemodified';
        $params += array('itemid' => $item->id, 'groupid' => $groupid);
        $values = $DB->get_records_sql($query, $params);

    } else {
        $params = array();
        if ($ignore_empty) {
            $value = $DB->sql_compare_text('value');
            $ignore_empty_select = "AND $value != :emptyvalue AND $value != :zerovalue";
            $params += array('emptyvalue' => '', 'zerovalue' => '0');
        } else {
            $ignore_empty_select = "";
        }

        if ($courseid) {
            $select = "item = :itemid AND course_id = :courseid ".$ignore_empty_select;
            $params += array('itemid' => $item->id, 'courseid' => $courseid);
            $values = $DB->get_records_select('evaluation_value', $select, $params);
        } else {
            $select = "item = :itemid ".$ignore_empty_select;
            $params += array('itemid' => $item->id);
            $values = $DB->get_records_select('local_evaluation_value', $select, $params);
        }
    }
    $params = array('id'=>$item->evaluation);
    if ($DB->get_field('local_evaluations', 'anonymous', $params) == EVALUATION_ANONYMOUS_YES) {
        if (is_array($values)) {
            shuffle($values);
        }
    }
    return $values;
}

/**
 * check for multiple_submit = false.
 * if the evaluation is global so the courseid must be given
 *
 * @global object
 * @global object
 * @param int $evaluationid
 * @param int $courseid
 * @return boolean true if the evaluation already is submitted otherwise false
 */
function evaluation_is_already_submitted($evaluationid, $courseid = false) {
    global $USER, $DB;

    if (!isloggedin() || isguestuser()) {
        return false;
    }

    $params = array('userid' => $USER->id, 'evaluation' => $evaluationid);
    if ($courseid) {
        $params['courseid'] = $courseid;
    }
    return $DB->record_exists('local_evaluation_completed', $params);
}

/**
 * if the completion of a evaluation will be continued eg.
 * by pagebreak or by multiple submit so the complete must be found.
 * if the param $tmp is set true so all things are related to temporary completeds
 *
 * @deprecated since Moodle 3.1
 * @param int $evaluationid
 * @param boolean $tmp
 * @param int $courseid
 * @param string $guestid
 * @return int the id of the found completed
 */
function evaluation_get_current_completed($evaluationid,
                                        $tmp = false,
                                        $courseid = false,
                                        $guestid = false) {

    debugging('Function evaluation_get_current_completed() is deprecated. Please use either '.
            'evaluation_get_current_completed_tmp() or evaluation_get_last_completed()',
            DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;

    $tmpstr = $tmp ? 'tmp' : '';

    if (!$courseid) {
        if ($guestid) {
            $params = array('evaluation'=>$evaluationid, 'guestid'=>$guestid);
            return $DB->get_record('local_evaluation_completed'.$tmpstr, $params);
        } else {
            $params = array('evaluation'=>$evaluationid, 'userid'=>$USER->id);
            return $DB->get_record('local_evaluation_completed'.$tmpstr, $params);
        }
    }

    $params = array();

    if ($guestid) {
        $userselect = "AND fc.guestid = :guestid";
        $params['guestid'] = $guestid;
    } else {
        $userselect = "AND fc.userid = :userid";
        $params['userid'] = $USER->id;
    }
    //if courseid is set the evaluation is global.
    //there can be more than one completed on one evaluation
    $sql =  "SELECT DISTINCT fc.*
               FROM {local_eval_value{$tmpstr}} fv, {local_eval_completed{$tmpstr}} fc
              WHERE fv.course_id = :courseid
                    AND fv.completed = fc.id
                    $userselect
                    AND fc.evaluation = :evaluationid";
    $params['courseid']   = intval($courseid);
    $params['evaluationid'] = $evaluationid;

    if (!$sqlresult = $DB->get_records_sql($sql, $params)) {
        return false;
    }
    foreach ($sqlresult as $r) {
        return $DB->get_record('local_evaluation_completed'.$tmpstr, array('id'=>$r->id));
    }
}

/**
 * get the completeds depending on the given groupid.
 *
 * @global object
 * @global object
 * @param object $evaluation
 * @param int $groupid
 * @param int $courseid
 * @return mixed array of found completeds otherwise false
 */
function evaluation_get_completeds_group($evaluation, $groupid = false, $courseid = false) {
    global $CFG, $DB;
    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
    if (intval($groupid) > 0) {
        $query = "SELECT fbc.*
                    FROM {local_evaluation_completed} fbc, {groups_members} gm, {users} u
                   WHERE fbc.evaluation = ?
                         AND gm.groupid = ?
                         AND fbc.userid = gm.userid
                         AND u.id=fbc.userid $costcenterpathconcatsql";
        if ($values = $DB->get_records_sql($query, array($evaluation->id, $groupid))) {
            return $values;
        } else {
            return false;
        }
    } else {
        if ($courseid) {
            $query = "SELECT DISTINCT fbc.*
                        FROM {local_evaluation_completed} fbc, {local_evaluation_value} fbv
                        WHERE fbc.id = fbv.completed
                            AND fbc.evaluation = ?
                            AND fbv.course_id = ?
                        ORDER BY random_response";
            if ($values = $DB->get_records_sql($query, array($evaluation->id, $courseid))) {
                return $values;
            } else {
                return false;
            }
        } else {
            if ($values = $DB->get_records_sql('SELECT ec.id from {local_evaluation_completed} ec, {user} u   where u.id =ec.userid AND u.suspended = 0 AND u.deleted = 0 AND  ec.evaluation = ? ', array($evaluation->id))) {
                return $values;
            } else {
                return false;
            }
        }
    }
}

/**
 * get the count of completeds depending on the given groupid.
 *
 * @global object
 * @global object
 * @param object $evaluation
 * @param int $groupid
 * @param int $courseid
 * @return mixed count of completeds or false
 */
function evaluation_get_completeds_group_count($evaluation, $groupid = false, $courseid = false) {
    global $CFG, $DB;

    if ($courseid > 0 AND !$groupid <= 0) {
        $sql = "SELECT id, COUNT(item) AS ci
                  FROM {local_evaluation_value}
                 WHERE course_id  = ?
              GROUP BY item ORDER BY ci DESC";
        if ($foundrecs = $DB->get_records_sql($sql, array($courseid))) {
            $foundrecs = array_values($foundrecs);
            return $foundrecs[0]->ci;
        }
        return false;
    }
    if ($values = evaluation_get_completeds_group($evaluation, $groupid)) {
        return count($values);
    } else {
        return false;
    }
}

/**
 * deletes all completed-recordsets from a evaluation.
 * all related data such as values also will be deleted
 *
 * @param stdClass|int $evaluation
 * @return void
 */
function evaluation_delete_all_completeds($evaluation) {
    global $DB;

    if (is_int($evaluation)) {
        $evaluation = $DB->get_record('local_evaluations', array('id' => $evaluation));
    }

    if (!$completeds = $DB->get_records('local_evaluation_completed', array('evaluation' => $evaluation->id))) {
        return;
    }

    foreach ($completeds as $completed) {
        evaluation_delete_completed($completed, $evaluation);
    }
}

/**
 * deletes a completed given by completedid.
 * all related data such values or tracking data also will be deleted
 *
 * @param int|stdClass $completed
 * @param stdClass $evaluation
 * @return boolean
 */
function evaluation_delete_completed($completed, $evaluation = null) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if (!isset($completed->id)) {
        if (!$completed = $DB->get_record('local_evaluation_completed', array('id' => $completed))) {
            return false;
        }
    }

    if (!$evaluation && !($evaluation = $DB->get_record('local_evaluations', array('id' => $completed->evaluation)))) {
        return false;
    }

    //first we delete all related values
    $DB->delete_records('local_evaluation_value', array('completed' => $completed->id));

    // Delete the completed record.
    $return = $DB->delete_records('local_evaluation_completed', array('id' => $completed->id));

    //// Trigger event for the delete action we performed.
    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    $params = array(
        'context' => $context,
        'completedid' => $completed->id,
        'relateduserid' => $completed->userid,
        'objectid' => $evaluation->id
    );
    $event = \local_evaluation\event\response_deleted::create($params);
    $event->trigger();

    return $return;
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//not relatable functions
////////////////////////////////////////////////

/**
 * prints the option items of a selection-input item (dropdownlist).
 * @deprecated since 3.1
 * @param int $startval the first value of the list
 * @param int $endval the last value of the list
 * @param int $selectval which item should be selected
 * @param int $interval the stepsize from the first to the last value
 * @return void
 */
function evaluation_print_numeric_option_list($startval, $endval, $selectval = '', $interval = 1) {
    debugging('Function evaluation_print_numeric_option_list() is deprecated because it was not used.',
            DEBUG_DEVELOPER);
    for ($i = $startval; $i <= $endval; $i += $interval) {
        if ($selectval == ($i)) {
            $selected = 'selected="selected"';
        } else {
            $selected = '';
        }
        echo '<option '.$selected.'>'.$i.'</option>';
    }
}


/**
 * @param string $url
 * @return string
 */
function evaluation_encode_target_url($url) {
    if (strpos($url, '?')) {
        list($part1, $part2) = explode('?', $url, 2); //maximal 2 parts
        return $part1 . '?' . htmlentities($part2);
    } else {
        return $url;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $evaluationnode The node to add module settings to
 */
function evaluation_extend_settings_navigation(settings_navigation $settings, navigation_node $evaluationnode) {

    // we are not providing any settings
}

function evaluation_init_evaluation_session() {
    //initialize the evaluation-Session - not nice at all!!
    global $SESSION;
    if (!empty($SESSION)) {
        if (!isset($SESSION->evaluation) OR !is_object($SESSION->evaluation)) {
            $SESSION->evaluation = new stdClass();
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function evaluation_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('local-evaluation-*'=>get_string('page-mod-evaluation-x', 'local_evaluation'));
    return $module_pagetype;
}

/**
 * Move save the items of the given $evaluation in the order of $itemlist.
 * @param string $itemlist a comma separated list with item ids
 * @param stdClass $evaluation
 * @return bool true if success
 */
function evaluation_ajax_saveitemorder($itemlist, $evaluation) {
    global $DB;

    $result = true;
    $position = 0;
    foreach ($itemlist as $itemid) {
        $position++;
        $result = $result && $DB->set_field('local_evaluation_item',
                                            'position',
                                            $position,
                                            array('id'=>$itemid, 'evaluation'=>$evaluation->id));
    }
    return $result;
}

/**
 * Get icon mapping for font-awesome.
 */
function local_evaluation_get_fontawesome_icon_map() {
    return [
        'local_evaluation:required' => 'fa-exclamation-circle',
        'local_evaluation:notrequired' => 'fa-question-circle-o',
    ];
}

/**
 * This function returns all evaluations
 *
 * @return evaluations list
 */
function get_all_evaluations() {
    global $DB, $USER;

    // get evaluations
    $sql = "SELECT * FROM {local_evaluations}";
    $sql .= " WHERE visible = 1";
    $evaluations = $DB->get_records_sql($sql);

    return $evaluations;
}

/**
* [available_enrolled_users description]
* @param  string  $type       [description]
* @param  integer $evaluationid [description]
* @param  [type]  $params     [description]
* @param  integer $total      [description]
* @param  integer $offset    [description]
* @param  integer $perpage    [description]
* @param  integer $lastitem   [description]
* @return [type]              [description]
*/
function evaluation_enrolled_users($type = null, $evaluationid = 0,$params, $total=0, $offset=-1, $perpage=-1, $lastitem=0){
    global $DB, $USER;
    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $evaluation = $DB->get_record('local_evaluations', array('id' => $evaluationid));
    $params['suspended'] = 0;
    $params['deleted'] = 0;

    if($total==0){
         $sql = "SELECT u.id,concat(u.firstname,' ',u.lastname,' ','(',u.email,')') as fullname";
    }else{
        $sql = "SELECT count(u.id) as total";
    }
     $sql.=" FROM {user} AS u WHERE  u.id > 2 AND u.suspended = :suspended AND u.deleted = :deleted $costcenterpathconcatsql";
    if($lastitem!=0){
        $sql.=" AND u.id > $lastitem";
    }
    $sql .=" AND u.id <> $USER->id";
    if (!empty($params['email'])) {
        $sql.=" AND u.id IN ({$params['email']})";
    }
    if (!empty($params['uname'])) {
        $sql .=" AND u.id IN ({$params['uname']})";
    }
    if (!empty($params['idnumber'])) {
        $sql .=" AND u.id IN ({$params['idnumber']})";
    }
    if (!empty($params['location'])) {

        $locations = explode(',',$params['location']);
        list($locationsql, $locationparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
        $params = array_merge($params,$locationparams);            
        $sql .= " AND u.open_location {$locationsql} ";
    }

    if (!empty($params['hrmsrole'])) {

        $hrmsroles = explode(',',$params['hrmsrole']);
        list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
        $params = array_merge($params,$hrmsroleparams);            
        $sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
    }
    if (!empty($params['organization'])) {
        $organizations = explode(',', $params['organization']);
        $orgsql = [];
        foreach ($organizations as $organisation) {
            $orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
            $params["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
        }
        if (!empty($orgsql)) {
            $sql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
        }
    }
    if (!empty($params['department'])) {
        $departments = explode(',', $params['department']);
        $deptsql = [];
        foreach ($departments as $department) {
            $deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $params["departmentparam_{$department}"] = '%/' . $department . '/%';
        }
        if (!empty($deptsql)) {
            $sql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
        }
    }

    if (!empty($params['subdepartment'])) {
        $subdepartments = explode(',', $params['subdepartment']);
        $subdeptsql = [];
        foreach ($subdepartments as $subdepartment) {
            $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $params["subdepartmentparam_{$subdepartment}"] = '%/' . $subdepartment . '/%';
        }
        if (!empty($subdeptsql)) {
            $sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
        }
    }
    if (!empty($params['department4level'])) {
        $depart4level = explode(',', $params['department4level']);
        $department4levelsql = [];
        foreach ($depart4level as $department4level) {
            $department4levelsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
            $params["department4levelparam_{$department4level}"] = '%/' . $department4level . '/%';
        }
        if (!empty($department4levelsql)) {
            $sql .= " AND ( " . implode(' OR ', $department4levelsql) . " ) ";
        }
    }
    if (!empty($params['groups'])) {
         $group_list = $DB->get_records_sql_menu("select cm.id, cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$params['groups']})");

         $groups_members = implode(',', $group_list);
         if (!empty($groups_members))
         $sql .=" AND u.id IN ({$groups_members})";
         else
         $sql .=" AND u.id =0";
    }
    $sql .=" AND u.id <> $USER->id";

    if ($type=='add') {
        $sql .= " AND u.id NOT IN (SELECT lcu.userid as userid
                               FROM {local_evaluation_users} AS lcu
                               WHERE lcu.evaluationid = $evaluationid)";
    }elseif ($type=='remove') {
        $sql .= " AND u.id IN (SELECT lcu.userid as userid
                               FROM {local_evaluation_users} AS lcu
                               WHERE lcu.evaluationid = $evaluationid)";
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
 * evaluations info of the user.
 *
 * @param int $userid user id
 * @param int $tabstatus completed / pending tab value
 * @return array contains enrolled online tests details
 */
function user_evaluations($userid, $tabstatus) {
    global $DB, $OUTPUT;
    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
    $sql ="SELECT a.*, eu.creatorid, eu.timemodified, eu.timecreated as joinedate from {local_evaluations} a, {local_evaluation_users} eu where a.id = eu.evaluationid AND eu.userid = ? AND instance = 0 AND a.visible = 1 $costcenterpathconcatsql";
    $sql .= " ORDER BY eu.timecreated DESC";
    $evaluations = $DB->get_records_sql($sql, [$userid]);
    $data = array();
    if ($evaluations) {
        foreach($evaluations as $record) {
            $row = array();
            $buttons = array();
            $showcompleted = $DB->get_field('local_evaluation_completed', 'id', array('userid'=>$userid, 'evaluation'=>$record->id));
			$time = time();
			if ($record->timeclose !=0 AND $time >= $record->timeclose)
			$buttons[] = '';
			elseif ($record->timeopen !=0 AND $time <= $record->timeopen)
			$buttons[] = '';
            elseif ($showcompleted AND $record->multiple_submit == 0 )
			$buttons[] = '';
			else
            $buttons[] = html_writer::link(new moodle_url('/local/evaluation/complete.php', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('t/go', get_string('answerquestions', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')));
            if ($showcompleted)
			$buttons[] = html_writer::link(new moodle_url('/local/evaluation/show_entries.php', array('id' => $record->id,'userid'=>$USER->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/preview', get_string('responses', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')));

            $enrolledon = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->joinedate);
			$compeltionrecord = $DB->get_record('local_evaluation_completed', array('evaluation'=>$record->id, 'userid'=>$userid));
			if ($compeltionrecord) {
                if ($tabstatus == 2)
                continue;
                $completedon = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $compeltionrecord->timemodified);
            } else {
                if ($tabstatus == 1)
                continue;
                $completedon = '-';
            }

            $buttons = implode('',$buttons);

            if($record->timeopen==0 AND $record->timeclose==0) {
                $dates= get_string('open', 'local_evaluation');
            } elseif(!empty($record->timeopen) AND empty($record->timeclose)) {
                $dates = 'From '.date('d/m/Y', $record->timeopen);
            } elseif (empty($record->timeopen) AND !empty($record->timeclose)) {
                $dates = 'Ends on '. \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timeclose);
            } else {
                $dates = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timeopen).  ' to '  . \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timeclose);
            }
			$evaltype = ($record->type == 1)? get_string('feedback', 'local_evaluation'):get_string('survey', 'local_evaluation');
            $row[] = $record->name;
            $row[] = $evaltype;
            $row[] = $dates;
            $row[] = $enrolledon;
            $row[] = $completedon;
            $row[] = $buttons;
            $data[] = $row;
        }
    }
    return $data;
}
/*
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_evaluation_leftmenunode(){
    $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
    $evaluationnode = '';
    if(has_capability('local/evaluation:edititems',$systemcontext) || is_siteadmin()){
        $evaluationnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browseevaluations', 'class'=>'pull-left user_nav_div browseevaluations'));
            $eval_url = new moodle_url('/local/evaluation/index.php');
            if(has_capability('local/evaluation:edititems', $systemcontext)) {
                $eval_label = get_string('left_menu_evaluations','local_evaluation');
            }else{
                $eval_label = get_string('left_menu_myevaluations','local_evaluation');
            }
            $eval = html_writer::link($eval_url, '<i class="fa fa-clipboard" aria-hidden="true"></i><span class="user_navigation_link_text">'.$eval_label.'</span>',array('class'=>'user_navigation_link'));
            $evaluationnode .= $eval;
        $evaluationnode .= html_writer::end_tag('li');
    }

    return array('13' => $evaluationnode);
}
function local_evaluation_quicklink_node(){
    global $CFG, $PAGE, $OUTPUT;
    $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
    $content = '';
    if (is_siteadmin() || has_capability('local/evaluation:addinstance',$systemcontext)) {
            $evalid = -1; //default for local/evaluation/index.php
            $classid = 0; //default for local/evaluation/index.php
            $eval_plugin = 'site'; //default for local/evaluation/index.php
            $PAGE->requires->js_call_amd('local_evaluation/newevaluation', 'init', array('[data-action=createevaluationmodal]', $systemcontext->id, $evalid, $classid, $eval_plugin));
        $evaldata = array();
        $evaldata['node_header_string'] = get_string('manage_br_evaluation', 'local_evaluation');
        $evaldata['pluginname'] = 'feedbacks';
        $evaldata['plugin_icon_class'] = 'fa fa-clipboard';
        if(has_capability('local/evaluation:addinstance', $systemcontext) ||  is_siteadmin()){
            $evaldata['create'] = TRUE;
            $evaldata['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('class' => 'quick_nav_link goto_local_evaluations', 'data-action' => "createevaluationmodal"));
        }
        if(has_capability('local/courses:view', $systemcontext) || has_capability('local/courses:manage', $systemcontext)){
            $evaldata['viewlink_url'] = $CFG->wwwroot.'/local/evaluation/index.php';
            $evaldata['view'] = TRUE;
            $evaldata['viewlink_title'] = get_string("view_feedbacks", "local_evaluation");
        }
        $evaldata['space_count'] = 'one';
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $evaldata);
    }
    return array('2' => $content);
}

/*
* return count of feedbacks under selected costcenter
* @return  [type] int count of feedbacks
*/
function costcenterwise_evaluation_count($costcenter, $department = false,$subdepartment = false, $l4department=false, $l5department=false){
    global $USER, $DB,$CFG;
        $params = array();
        $params['costcenter'] = '%/'.$costcenter.'/%';
        $countfeedbacksql = "SELECT count(id) FROM {local_evaluations} WHERE concat('/',open_path,'/') LIKE :costcenter ";
        if($department){
            $countfeedbacksql .= " AND concat('/',open_path,'/') LIKE  :department ";
            $params['department'] = '%/'.$department.'/%';
        }
        if ($subdepartment) {
            $countfeedbacksql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
            $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
        }
        if ($l4department) {
            $countfeedbacksql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
            $params['l4departmentpath'] = '%/'.$l4department.'/%';
        }
        if ($l5department) {
            $countfeedbacksql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
            $params['l5departmentpath'] = '%/'.$l5department.'/%';
        }
        $activesql = " AND visible = 1 ";
        $inactivesql = " AND visible= 0 ";

        $countfeedback = $DB->count_records_sql($countfeedbacksql, $params);
        $activefeedback = $DB->count_records_sql($countfeedbacksql.$activesql, $params);
        $inactivefeedback = $DB->count_records_sql($countfeedbacksql.$inactivesql, $params);

         if($countfeedback >= 0){
            if($costcenter){
                $viewfeedbacklink_url = $CFG->wwwroot.'/local/evaluation/index.php?costcenterid='.$costcenter; 
            }
            if($department){
                $viewfeedbacklink_url = $CFG->wwwroot.'/local/evaluation/index.php?departmentid='.$department; 
            }
           
        }
        if($activefeedback >= 0){
            if($costcenter){
                $count_feedbackactivelink_url = $CFG->wwwroot.'/local/evaluation/index.php?status=active&costcenterid='.$costcenter; 
            }
            if($department){
                $count_feedbackactivelink_url = $CFG->wwwroot.'/local/evaluation/index.php?status=active&departmentid='.$department; 
            }        
        }
        if($inactivefeedback >= 0){
            if($costcenter){
                $count_feedbackinactivelink_url = $CFG->wwwroot.'/local/evaluation/index.php?status=inactive&costcenterid='.$costcenter; 
            }
            if($department){
                $count_feedbackinactivelink_url = $CFG->wwwroot.'/local/evaluation/index.php?status=inactive&departmentid='.$department; 
            }
        }

    return array('dept_feedback' => $countfeedback,'dept_feedbackactive' => $activefeedback,'dept_feedbackinactive' => $inactivefeedback,'viewfeedbacklink_url'=>$viewfeedbacklink_url,'count_feedbackactivelink_url' => $count_feedbackactivelink_url,'count_feedbackinactivelink_url' => $count_feedbackinactivelink_url);
}


/**
* return list of feedbacks
* @return array feedbacks
*/
function get_listof_evalautions($stable, $filtervalues){
    global $DB, $USER, $OUTPUT;
    $context = (new \local_evaluation\lib\accesslib())::get_module_context();
    $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $statustype=$stable->status;
    $data = array();
    $userarray = array();
    $params = array();
    $filtervalues->filteropen_costcenterid = (ltrim($filtervalues->filteropen_costcenterid, ','));
    $countsql = "SELECT count(a.id) ";
    if (is_siteadmin() || has_capability('local/evaluation:edititems',$context)) {
       $sql ="SELECT a.* ";
       $fromsql = " from {local_evaluations} a where a.id > 0 AND deleted=0 AND instance = 0 $costcenterpathconcatsql";
    } else { // check for users
       $sql ="SELECT a.*, eu.creatorid, eu.timemodified as joinedate ";
       $fromsql =" from {local_evaluations} a, {local_evaluation_users} eu where a.id = eu.evaluationid AND eu.userid = :userid AND instance = 0 AND a.visible=1 AND a.evaluationmode LIKE 'SE' ";
       $userorder = 1;
       $userarray['userid'] = $USER->id;
    }
    $fromsql .= " AND a.deleted = 0 ";
    if(isset($filtervalues->search_query) && trim($filtervalues->search_query) != ''){
        $fromsql .= " AND a.name LIKE :search";
        $userarray['search'] = '%'.trim($filtervalues->search_query).'%';
    }
    if(isset($filtervalues->evaluation) && !empty($filtervalues->evaluation)){
        $evalids = is_array($filtervalues->evaluation) ? implode($filtervalues->evaluation) : $filtervalues->evaluation ;
        $fromsql .= " AND a.id IN ($evalids)";
    }
    if(isset($filtervalues->eval_type) && !empty($filtervalues->eval_type)){
        $eval_types = is_array($filtervalues->eval_type) ? implode($filtervalues->eval_type) : $filtervalues->eval_type ;
        $fromsql .= " AND a.type IN ($eval_types) ";
    }
    if(!empty($filtervalues->status)){
        $status = explode(',',$filtervalues->status);
        if(!(in_array('active',$status) && in_array('inactive',$status))){
            if(in_array('active' ,$status)){
                $fromsql .= " AND a.visible = 1 ";
            }else if(in_array('inactive' ,$status)){
                $fromsql .= " AND a.visible = 0 ";
            }
        }
    }
    $filterparams=array();
    if (!empty($filtervalues->filteropen_costcenterid)) {

        $organizations = explode(',', $filtervalues->filteropen_costcenterid);
        $orgsql = [];
        foreach($organizations AS $organisation){
            $orgsql[] = " concat('/',a.open_path,'/') LIKE :organisationparam_{$organisation}";
            $filterparams["organisationparam_{$organisation}"] = '%/'.$organisation.'/%';

        }
        if(!empty($orgsql)){
            $fromsql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
        }
    }
    if (!empty($filtervalues->filteropen_department)) {

        $departments = explode(',', $filtervalues->filteropen_department);
        $departmentsql = [];
        foreach($departments AS $department){
            $departmentsql[] = "concat('/',a.open_path,'/') LIKE :departmentparam_{$department}";
            $filterparams["departmentparam_{$department}"] = '%/'.$department.'/%';

        }
        if(!empty($departmentsql)){
            $fromsql .= " AND ( ".implode(' OR ', $departmentsql)." ) ";
        }
    }
    if (!empty($filtervalues->filteropen_subdepartment)) {

        $subdepartments = explode(',', $filtervalues->filteropen_subdepartment);
        $subdepartmentsql = [];
        foreach($subdepartments AS $subdepartment){
            $subdepartmentsql[] = "concat('/',a.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $filterparams["subdepartmentparam_{$subdepartment}"] = '%/'.$subdepartment.'/%';
        }
        if(!empty($subdepartmentsql)){
            $fromsql .= " AND ( ".implode(' OR ', $subdepartmentsql)." ) ";
        }
    }
    if (!empty($filtervalues->filteropen_level4department)) {

        $department4levels = explode(',', $filtervalues->filteropen_level4department);
        $departmentlevel4sql = [];
        foreach($department4levels AS $department4level){
            $departmentlevel4sql[] = "concat('/',a.open_path,'/') LIKE :departmentlevel4param_{$department4level}";
            $filterparams["departmentlevel4param_{$department4level}"] = '%/'.$department4level.'/%';
        }
        if(!empty($departmentlevel4sql)){
            $fromsql .= " AND ( ".implode(' OR ', $departmentlevel4sql)." ) ";
        }
    }
    if (!empty($filtervalues->filteropen_department5level)) {

        $department5level = explode(',', $filtervalues->filteropen_department5level);
        $departmentlevel5sql = [];
        foreach($department5level AS $department5levels){

            $departmentlevel5sql[] = "concat('/',a.open_path,'/') LIKE :departmentlevel5param_{$department5levels}";
            $filterparams["departmentlevel5param_{$department5levels}"] = '%/'.$department5levels.'/%';
        }
        if(!empty($departmentlevel5sql)){
            $fromsql .= " AND ( ".implode(' OR ', $departmentlevel5sql)." ) ";
        }
    }
    if ($userorder == 1)
    $ordersql = " order by eu.timecreated DESC ";
    else
    $ordersql = " order by a.id DESC ";

    $params = array_merge($userarray,$filterparams);
    $feedbackcount = $DB->count_records_sql($countsql.$fromsql, $params);
    $records = $DB->get_records_sql($sql.$fromsql.$ordersql, $params, $stable->start, $stable->length);
    foreach($records as $record) {
        $line = array();
        $localcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
        $attendcount = $DB->count_records_sql('select count(ou.id) from {local_evaluation_users} ou, {user} u where u.id = ou.userid '. $costcenterpathconcatsql .' AND u.deleted = 0 AND u.suspended = 0 AND ou.evaluationid=?', array($record->id));
        $completedevaluationcount = intval(evaluation_get_completeds_group_count($record));
        $buttons='';

        if (is_siteadmin() OR has_capability('local/evaluation:edititems', $context) ) {
            $buttons .= '<li>'.html_writer::link( "javascript:void(0)",$OUTPUT->pix_icon('t/editinline', get_string('edit'), 'moodle', array('class' => 'iconsmall', 'title' => '')).get_string('edit'), array('class'=>'dropdown-item','data-action'=>"createevaluationmodal", 'data-value'=>$record->id)).'</li>';
            $edit_eval= html_writer::link( "javascript:void(0)",$OUTPUT->pix_icon('t/editinline', get_string('edit'), 'moodle', array('class' => 'iconsmall', 'title' => '')), array('data-action'=>"createevaluationmodal", 'data-value'=>$record->id));
            if (has_capability('local/evaluation:enroll_users', $context)){
                $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/users_assign.php', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/assignroles', get_string('assignusers', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '', 'target'=>'_blank')).get_string('assignusers', 'local_evaluation'),array('class' => 'dropdown-item')).'</li>';
                $eval_enrol=html_writer::link(new moodle_url('/local/evaluation/users_assign.php', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/assignroles', get_string('assignusers', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '', 'target'=>'_blank')));
            } 
            if($record->visible){
                $icon = 't/hide';
                $string = get_string('le_inactive','local_evaluation');
            }else{
                $icon = 't/show';
                $string = get_string('le_active','local_evaluation');
            }
            $image = $OUTPUT->pix_icon($icon, $string, 'moodle', array('class' => 'iconsmall', 'title' => '')).$string;
            $image1 = $OUTPUT->pix_icon($icon, $string, 'moodle', array('class' => 'iconsmall', 'title' => ''));
            $params = json_encode(array('eval_status' => $record->visible, 'evalname' => $record->name, 'published' => 1));
            $buttons .= '<li>'.html_writer::link("javascript:void(0)", $image, array('class'=>'dropdown-item','data-fg'=>"d", 'data-method' => 'evaluation_update_status','data-plugin' => 'local_evaluation', 'data-params' => $params, 'data-id'=>$record->id)).'</li>';
            $eval_hideshow=html_writer::link("javascript:void(0)", $image1, array('data-fg'=>"d", 'data-method' => 'evaluation_update_status','data-plugin' => 'local_evaluation', 'data-params' => $params, 'data-id'=>$record->id));
           
            if (has_capability('local/evaluation:createpublictemplate', $context)) {
                 $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/eval_view.php#edit', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/questions', get_string('questions', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')).get_string('questions', 'local_evaluation'),array('class' => 'dropdown-item')).'</li>';

                 $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/eval_view.php#tempaltes', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('t/copy', get_string('templates', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')).get_string('templates', 'local_evaluation'),array('class' => 'dropdown-item')).'</li>';
                 $edit_question=html_writer::link(new moodle_url('/local/evaluation/eval_view.php#edit', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/questions', get_string('questions', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')));
                 $edit_template=html_writer::link(new moodle_url('/local/evaluation/eval_view.php#tempaltes', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('t/copy', get_string('templates', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')));
                } 
            
                if (has_capability('local/evaluation:viewreports', $context)) 
            // $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/analysis.php', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/grades', get_string('overview', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => ''))).'</li>';

            if (has_capability('local/evaluation:viewanalysepage', $context)){
            $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/show_entries.php',  array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/preview', get_string('responses', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')).get_string('responses', 'local_evaluation'),array('class' => 'dropdown-item')).'</li>';
            $eval_analys=html_writer::link(new moodle_url('/local/evaluation/show_entries.php',  array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/preview', get_string('responses', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')));
            }
            
            // check for deletion
            if (is_siteadmin() OR has_capability('local/evaluation:delete', $context)) {
              $candelete = check_evaluationdeletion($record->id);
              $buttons .= '<li>'.html_writer::link(
              "javascript:void(0)",
              $OUTPUT->pix_icon('i/delete', get_string('delete'), 'moodle', array('class' => 'iconsmall', 'title' => '')).get_string('delete'),
              array('class'=>'dropdown-item','id' => 'deleteconfirm' . $record->id . '', 'onclick' => '(
                  function(e){
                  require("local_evaluation/newevaluation").deleteevaluation("' . $record->id . '","'.$record->name.'")
                  })(event)')).'</li>';
                  $eval_delete=html_writer::link(
                    "javascript:void(0)",
                    $OUTPUT->pix_icon('i/delete', get_string('delete'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
                    array('id' => 'deleteconfirm' . $record->id . '', 'onclick' => '(
                        function(e){
                        require("local_evaluation/newevaluation").deleteevaluation("' . $record->id . '","'.$record->name.'")
                        })(event)'));
            }
            $completedurl = new moodle_url('/local/evaluation/show_entries.php', array('id' => $record->id, 'sesskey' => sesskey()));
        } else {
            $showcompleted = $DB->get_field('local_evaluation_completed', 'id', array('userid'=>$USER->id, 'evaluation'=>$record->id));
            $time = time();
            if ($record->timeclose !=0 AND $time >= $record->timeclose)
            $buttons .= '';
            elseif ($record->timeopen !=0 AND $time <= $record->timeopen)
            $buttons .= '';
            elseif ($showcompleted AND $record->multiple_submit == 0 )
            $buttons .= '';
            else
            $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/complete.php', array('id' => $record->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('t/go', get_string('answerquestions', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => ''))).'</li>';
            $previewurl = new moodle_url('/local/evaluation/complete.php', array('id' => $record->id, 'sesskey' => sesskey()));
            if ($showcompleted)
            $buttons .= '<li>'.html_writer::link(new moodle_url('/local/evaluation/show_entries.php', array('id' => $record->id,'userid'=>$USER->id, 'sesskey' => sesskey())), $OUTPUT->pix_icon('i/preview', get_string('responses', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => ''))).'</li>';
            $completedurl = new moodle_url('/local/evaluation/show_entries.php', array('id' => $record->id,'userid'=>$USER->id, 'sesskey' => sesskey()));
        }

        if(!empty($buttons)){
            $buttons_container = '<div class="dropdown-menu dropdown-menu-right shadow-sm" id = "showoptions'.$record->id.'">
                        '.$buttons.'
                    </div>';
        } else {
          $buttons_container = '';
        }
        $extrainfo = '';
        if($record->timeopen == 0 AND $record->timeclose == 0) {
           $dates= get_string('open', 'local_evaluation');
        } elseif(!empty($record->timeopen) AND empty($record->timeclose)) {
           $dates = 'From '. \local_costcenter\lib::get_userdate('d/m/Y H:i ', $record->timeopen);
        } elseif (empty($record->timeopen) AND !empty($record->timeclose)) {
           $dates = 'Ends on '. \local_costcenter\lib::get_userdate('d/m/Y H:i ', $record->timeclose);
        } else {
           $dates = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timeopen).  ' - '  . \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timeclose);
        }
        $not_yetstarted = false;
        $closed_feedback = false;
        if(($record->timeclose < time() || $record->timeopen > time())
            && (!empty($record->timeopen) && !empty($record->timeclose))){
            $closed_feedback = true;
        }else if($record->timeopen > time() && !empty($record->timeopen)){
            $not_yetstarted = true;
        }
        $current_feedback = true;
        if($closed_feedback || $not_yetstarted){
            $current_feedback = false;
        }

        $eval_name = $record->name;
        $evalname = strlen($eval_name) > 15 ? substr($eval_name, 0, 15)."..." : $eval_name;
        $evaltype = ($record->type == 1)? get_string('feedback', 'local_evaluation'):get_string('survey', 'local_evaluation');
        if (is_siteadmin() OR has_capability('local/evaluation:edititems', $context)) {
            $has_evalcap = true;
            $line['has_evalcap'] = $has_evalcap;
            $line['evalname'] = html_writer::link(new moodle_url('/local/evaluation/eval_view.php', array('id' => $record->id, 'sesskey' => sesskey())), $evalname);

            $evalurl = new moodle_url('/local/evaluation/eval_view.php', array('id' => $record->id, 'sesskey' => sesskey()));
            $line['evalurl'] = $evalurl->out();
            $line['eval_name'] = $eval_name;
            $line['edit_eval'] = $edit_eval;
            $line['eval_delete'] = $eval_delete;
            $line['eval_enrol'] = $eval_enrol;
            $line['eval_analys'] = $eval_analys;
            $line['eval_hideshow'] = $eval_hideshow;
            $line['edit_template'] = $edit_template;
            $line['edit_question'] = $edit_question;
            $line['schedule'] = $dates;
            $line['evaltype'] = $evaltype;
            $line['enrolled'] = html_writer::link("javascript:void(0)",$attendcount, array('onclick'=>'(
                    function(e){
            require("local_evaluation/newevaluation").enrolledusers("' . $record->id . '","1", "'.$context->id.'", "'.$eval_name.'")
            })(event)'));
            $line['completed'] = html_writer::link("javascript:void(0)",$completedevaluationcount, array('onclick'=>'(
                    function(e){
            require("local_evaluation/newevaluation").enrolledusers("' . $record->id . '","2", "'.$context->id.'", "'.$eval_name.'")
            })(event)'));;
            $line['actions'] = $buttons_container;
            // default values
            $line['enrolledon'] = '';
            $line['completedon'] = '';
            $line['completedurl'] = is_object($completedurl) ? $completedurl->out() : 'javascript:void(0)';
            $line['previewurl'] = '';
            $line['previewstring'] = get_string('preview', 'local_evaluation');
            $line['closed_feedback'] = $closed_feedback;
            $line['current_feedback'] = $current_feedback;
            $line['not_yetstarted'] = $not_yetstarted;
        } else {
            $has_evalcap = false;
            $enrolledon = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->joinedate);
            $compeltionrecord = $DB->get_record('local_evaluation_completed', array('evaluation'=>$record->id, 'userid'=>$USER->id));
            if ($compeltionrecord){
              $completed = true;
              $completedon = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $compeltionrecord->timemodified);
           }
            else{
              $completedon = '-';
            }
            $line['not_yetstarted'] = $not_yetstarted;
            $line['has_evalcap'] = $has_evalcap;
            $evalname = strlen($record->name) > 15 ? substr($record->name, 0, 15)."..." : $record->name;
            $line['evalname'] = $evalname;
            $line['evalurl'] = false;
            $line['schedule'] = $dates;
            $line['evaltype'] = $evaltype;
            $line['enrolledon'] = $enrolledon;
            $line['completedon'] = $completedon;
            $line['completedurl'] = is_object($completedurl) ? $completedurl->out() : 'javascript:void(0)';
            $line['previewurl'] = is_object($previewurl) ? $previewurl->out() : 'javascript:void(0)';
            $line['previewstring'] = get_string('submit', 'local_evaluation');
            $line['completed'] = $completed;
            $line['actions'] = $buttons_container;
            // default values
            $line['eval_name'] ='';
            $line['closed_feedback'] = $closed_feedback;
            $line['current_feedback'] = $current_feedback;
            $line['enrolled'] ='';
        }
        $line['status'] = $record->visible ? TRUE : FALSE;
        $data[] = $line;
    }
    return array('totalrecords' => $feedbackcount,'records' => $data);
}

/**
 * Serve the selection popup form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_evaluation_output_fragment_addquestions_or_enrol($args) {
    global $CFG, $DB, $OUTPUT;
    $args = (object) $args;
    $context =  (new \local_evaluation\lib\accesslib())::get_module_context();
    $id = $args->id;
    $evaluation = $DB->get_record('local_evaluations', array('id'=>$id));

    require_capability('local/evaluation:edititems', $context);

    $path = evaluation_return_url($evaluation->plugin, $evaluation);

    $iconimage=html_writer::empty_tag('img', array('src'=>$OUTPUT->image_url('i/checked'),'size'=>'15px'));
    $out = "<div class='success_icon'><span class='iconimage'>".$iconimage."</span><span>".get_string('createdsuccessfully', 'local_evaluation')."</span></div>";
    $out .= "<table class = 'generaltable'>
    <tr><td>".get_string('doaddquestions', 'local_evaluation')."</td><td><a class='btn btn-primary' href='$CFG->wwwroot/local/evaluation/eval_view.php?id=$id#edit'>".get_string('questions', 'local_evaluation')."</a></td></tr>
    <tr><td>".get_string('doaddtemplates', 'local_evaluation')."</td><td><a class='btn btn-primary' href='$CFG->wwwroot/local/evaluation/eval_view.php?id=$id#tempaltes'>".get_string('templates', 'local_evaluation')."</a></td></tr>";
    if ($evaluation->instance == 0)
    $out .= "<tr><td>".get_string('doenrollusers', 'local_evaluation')."</td><td><a class='btn btn-primary' href='$CFG->wwwroot/local/evaluation/users_assign.php?id=$id'>".get_string('assignusers', 'local_evaluation')."</a></td></tr>";
    $out .= "</table>";
    $out .= "<div style='text-align:center;'><a id='page_reload_forced' class='btn btn-primary' href='javascript:void(0)'>".get_string('skip', 'local_evaluation')."</a></div>";
    return $out;
}

/**
 * Serve the enrolledusers popup form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_evaluation_output_fragment_enrolledusers($args) {
    global $CFG, $DB, $OUTPUT;
    $record = (object) $args;
    $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
    if ($record->type == 1) {

        $sql ="SELECT u.id as userid,u.firstname,u.lastname,u.email, f.id as evaluationid, fu.timecreated
                from {local_evaluation_users} fu
                JOIN {local_evaluations} f ON fu.evaluationid = f.id
                JOIN {user} u ON fu.userid=u.id AND u.deleted = 0 AND u.suspended = 0 $costcenterpathconcatsql
                where fu.evaluationid = ? ";

        $assignedusers= $DB->get_records_sql($sql, array($record->id));
        $out='';
        $data=array();
        if(!empty($assignedusers)){
            foreach($assignedusers as $assigneduser){
                $row=array();
                $user=$DB->get_record_sql("SELECT * FROM {user} WHERE id=$assigneduser->userid");
                if($user){
                    $row[] = $user->firstname. ' '. $user->lastname;
                    $row[] = $user->email;
                    $row[] = ($user->open_employeeid) ? $user->open_employeeid:'-';
                    $row[] = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $assigneduser->timecreated);
                    $completed_status = $DB->get_record_sql("SELECT id, timemodified from {local_evaluation_completed} where evaluation= $assigneduser->evaluationid and userid={$assigneduser->userid} ");
                    if ($completed_status) {
                        $status = get_string('completed', 'local_evaluation');
                        $submitteddate = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $completed_status->timemodified);
                    } else {
                        $status = get_string('pending', 'local_evaluation');
                        $submitteddate = '-';
                    }
                    $row[] =$submitteddate;
                    $row[] =$status;
                }
                $data[]=$row;
            }
        }
        $table = new html_table();
        $head = array('<b>'.get_string('username', 'local_evaluation').'</b>', '<b>'.get_string('email').'</b>','<b>'.get_string('employeeid', 'local_users').'</b>','<b>'.get_string('enrolledon', 'local_evaluation').'</b>','<b>'.get_string('submitdate', 'local_evaluation').'</b>','<b>'.get_string('status', 'local_evaluation').'</b>');
        $table->head = $head;
        $table->width = '100%';
        $table->align = array('left', 'left', 'center', 'center', 'center', 'center');
        if ($data)
        $table->data = $data;
        else
        $table->data = 'No users';
        $table->id ='assignedusers_view'.$record->id.'';
        $table->attr['class'] ='assignedusers_view';
        $out.= html_writer::table($table);
        $out.=html_writer::script('$(document).ready(function() {
             $("#assignedusers_view'.$record->id.'").dataTable({
                retrieve: true,
                bInfo : false,
                lengthMenu: [5, 10, 25, 50, -1],
                    language: {
                              emptyTable: "No Records Found",
                                paginate: {
                                            previous: "<",
                                            next: ">"
                                        }
                         },
             });
        });');

    } else {
        $sql = "SELECT u.id as userid, u.firstname, u.lastname, u.email,
            e.id as evaluationid, eu.timecreated, ec.timemodified AS completedon
            FROM {local_evaluation_users} AS eu
            JOIN {local_evaluations} AS e ON e.id=eu.evaluationid
            JOIN {local_evaluation_completed} AS ec ON ec.userid=eu.userid AND ec.evaluation=eu.evaluationid
            JOIN {user} AS u ON u.id = ec.userid AND u.deleted = 0 AND u.suspended = 0
            WHERE e.id = ? $costcenterpathconcatsql";
        $assignedusers = $DB->get_records_sql($sql, array($record->id));
        $out='';
        $data=array();
        if(!empty($assignedusers)){
            foreach($assignedusers as $assigneduser){
                $row=array();
                $user=$DB->get_record_sql("SELECT * FROM {user} WHERE id=$assigneduser->userid");
                if($user){
                    $row[] = $user->firstname. ' '. $user->lastname;
                    $row[] = $user->email;
                    $row[] = ($user->open_employeeid) ? $user->open_employeeid:'-';
                    $row[] = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $assigneduser->timecreated);
                        $status = get_string('completed', 'local_evaluation');
                        $submitteddate = \local_costcenter\lib::get_userdate("d/m/Y H:i ", $assigneduser->completedon);
                    $row[] =$submitteddate;
                }
                $data[]=$row;
            }
        }
        $table = new html_table();
        $head = array('<b>'.get_string('username', 'local_evaluation').'</b>', '<b>'.get_string('email').'</b>','<b>'.get_string('employeeid', 'local_users').'</b>','<b>'.get_string('enrolledon', 'local_evaluation').'</b>','<b>'.get_string('submitdate', 'local_evaluation').'</b>');
        $table->head = $head;
        if ($data)
        $table->data = $data;
        else
        $table->data = 'No users';
        $table->width = '100%';
        $table->align = array('left', 'left', 'center', 'center', 'center');
        $table->id ='completed_users_view'.$record->id.'';
        $table->attr['class'] ='completed_users_view';
        $out.= html_writer::table($table);
        $out.=html_writer::script('$(document).ready(function() {
             $("#completed_users_view'.$record->id.'").dataTable({
                retrieve: true,
                bInfo : false,
                lengthMenu: [5, 10, 25, 50, -1],
                    language: {
                              emptyTable: "No Records Found",
                                paginate: {
                                            previous: "<",
                                            next: ">"
                                        }
                         },
             });
        });');
    }
    return $out;
}

/**
* [evaluation_return_url ]
* @param  string  $pluign
* @param  integer $instance
* @return [string] [return url for different plugins]
*/
function evaluation_return_url($plugin, $evaluation) {
    global $CFG;
    if ($evaluation->instance != 0) {
      if ($plugin === "classroom")
      $backurl = new moodle_url('/local/'.$plugin.'/view.php?cid='.$evaluation->instance.'');
      elseif ($plugin === "program")
      $backurl = new moodle_url('/local/'.$plugin.'/view.php?pid='.$evaluation->instance.'');
      elseif ($plugin === "certification")
      $backurl = new moodle_url('/local/'.$plugin.'/view.php?ctid='.$evaluation->instance.'');
      else
      $backurl = new moodle_url('/local/'.$plugin.'/view.php?id='.$evaluation->instance.'');
    } else {
        // $pageurl = ($PAGE->url->out())
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $pageurl = "https";
        else
            $pageurl = "http";
        $pageurl .= "://";
        $pageurl .= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $string = strpos($pageurl, '?');
        $newpageurl =  substr($pageurl,0 , $string);
        $capability_array = array();
        $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
        if($evaluation->evaluationmode == 'SP' && !($newpageurl == $CFG->wwwroot.'/local/evaluation/eval_view.php' || $newpageurl == $CFG->wwwroot.'local/evaluation/users_assign.php' || has_any_capability($capability_array, $systemcontext))){
            $backurl = new moodle_url('/local/myteam/team.php');
        }else{
            $backurl = new moodle_url('/local/evaluation/index.php');
        }
    }
    return $backurl;
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_evaluation_list(){
    return 'Feedbacks';
}

function evaluation_filter($mform,$query='',$searchanywhere=false, $page=0, $perpage=25){
    global $DB,$USER;
    $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
    $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $evaluationlist=array();
    $data=data_submitted();

    if(is_siteadmin()|| has_capability('local/evaluation:addinstance', $systemcontext)){
        $evaluation_sql="SELECT id, name AS fullname FROM {local_evaluations} WHERE deleted=0 $costcenterpathconcatsql";
    }else{
        $evaluation_sql="SELECT id, name AS fullname FROM {local_evaluations} WHERE id IN (SELECT evaluationid
        FROM {local_evaluation_users} WHERE userid = {$USER->id}) AND visible=1 AND evaluationmode LIKE 'SE' AND deleted=0";
    }
    $evaluation_sql.=" AND instance=0 ";
    if(!empty($query)){
        if ($searchanywhere) {
            $evaluation_sql.=" AND name LIKE '%$query%' ";
        } else {
            $evaluation_sql.=" AND name LIKE '$query%' ";
        }
    }
    if(isset($data->evaluation)&&!empty(($data->evaluation))){

        $implode=implode(',',$data->evaluation);

        $evaluation_sql.=" AND id in ($implode) ";
    }
    if(!empty($query)||empty($mform)){
        $evaluationlist = $DB->get_records_sql($evaluation_sql, array(), $page, $perpage);
        return $evaluationlist;
    }
    if((isset($data->departments)&&!empty($data->departments))){
        $evaluationlist = $DB->get_records_sql_menu($evaluation_sql, array(), $page, $perpage);
    }

    $options = array(
            'ajax' => 'local_courses/form-options-selector',
            'multiple' => true,
            'data-action' => 'evaluation',
            'data-options' => json_encode(array('id' => 0)),
            'placeholder' => get_string('evaluation','local_evaluation')
    );

    $select = $mform->addElement('autocomplete', 'evaluation', get_string('pluginname','local_evaluation'), $evaluationlist,$options);
    $mform->setType('evaluation', PARAM_RAW);
}
function evaluation_type_filter($mform){
    $eval_type_arr = array('1'=>get_string('feedback', 'local_evaluation'),
                         '2'=>get_string('survey', 'local_evaluation'));
    $select = $mform->addElement('autocomplete', 'eval_type',get_string('types','local_evaluation'), $eval_type_arr, array('placeholder' => get_string('type', 'local_evaluation')));
    $mform->setType('eval_type', PARAM_RAW);
    $select->setMultiple(true);
}
function local_evaluation_output_fragment_addnew_question($args){
    global $DB;
    $args = (object)$args;
    $id = $args->id;
    $params = json_decode($args->params);
    $output = '';
    if(!isset($params->itemid) && $params->itemid == 0){
        $evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
        $types = evaluation_load_evaluation_items_options($evaluation->type);
        unset($types['info']);
        // get the selected type question form and display it instead of using edit_item.php
        $output.= '<div class="form-group row fitem cs_question_type">
                        <div id="select_question_type_survey" class="col-md-3">
                        <lable class="col-form-label d-inline pull-left pb-1 pt-1" for="type">Add Question of Type</lable>
                        </div>
                        <div class="col-md-9 form-inline felement" ><select name="type" class="target custom-select" id="id_questiontyp" value='.$id.'>
        <option data-ignore="" value="" selected="">Choose...</option>';
        foreach($types as $key=>$type) {
          $output.= "<option value=$key>$type</option>";
        }
        $output.= '</select></div></div>';
    }

    $output .= '<div id="displayform"></div>';
    return $output;
}

/**
 * Returns evaluations tagged with a specified tag.
 *
 * @param local_tags_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \local_tags\output\tagindex
 */
function local_evaluation_get_tagged_evaluations($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '') {
    global $CFG, $PAGE;
    // prepare for display of tags related to evaluations
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_evaluation');
    $totalcount = $renderer->tagged_evaluations($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1,$sort);
    $content = $renderer->tagged_evaluations($tag->id, $exclusivemode, $ctx, $rec, $displayoptions,0,$sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
    return new local_tags\output\tagindex($tag, 'local_evaluation', 'evaluation', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    else
    return '';
}

function get_evaluation_details($testid) {
    global $USER, $DB;
    $context = (new \local_evaluation\lib\accesslib())::get_module_context();

    $details = array();
    $joinsql = '';
        $selectsql = "select eu.* ";

        $fromsql = " from {local_evaluation_users} eu
        JOIN {local_evaluations} o ON o.id = eu.evaluationid ";

        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = o.id AND r.ratearea = 'local_evaluation' ";
        }

        $wheresql = " where 1 = 1 AND eu.userid = ? AND o.id = ? ";

        $record = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$USER->id, $testid]);
        $details['manage'] = 0;
        $details['status'] = ($record->status == 1) ? get_string('completed', 'local_onlinetests'):get_string('pending', 'local_onlinetests');
        $details['enrolled'] = ($record->timecreated) ? \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timecreated): '-';
        $details['completed'] = ($record->timemodified) ? \local_costcenter\lib::get_userdate("d/m/Y H:i ", $record->timemodified): '-';

    return $details;
}
