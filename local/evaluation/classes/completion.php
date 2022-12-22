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

/**
 *
 * @package   local_evaluation
 * @copyright 2019 eabyas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Collects information and methods about evaluation completion (either complete.php or show_entries.php)
 *
 * @package   local_evaluation
 * @author    2019 eabyas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_evaluation_completion extends local_evaluation_structure {
    /** @var stdClass */
    protected $completed;
    /** @var stdClass */
    protected $completedtmp = null;
    /** @var stdClass[] */
    protected $valuestmp = null;
    /** @var stdClass[] */
    protected $values = null;
    /** @var bool */
    protected $iscompleted = false;
    /** @var local_evaluation_complete_form the form used for completing the evaluation */
    protected $form = null;
    /** @var bool true when the evaluation has been completed during the request */
    protected $justcompleted = false;
    /** @var int the next page the user should jump after processing the form */
    protected $jumpto = null;


    /**
     * Constructor
     *
     * @param stdClass $evaluation evaluation object
     * @param bool $iscompleted has evaluation been already completed? If yes either completedid or userid must be specified.
     * @param int $completedid id in the table evaluation_completed, may be omitted if userid is specified
     *     but it is highly recommended because the same user may have multiple responses to the same evaluation
     *     for different courses
     * @param int $userid id of the user - if specified only non-anonymous replies will be returned. If not
     *     specified only anonymous replies will be returned and the $completedid is mandatory.
     */
    public function __construct($evaluation, $iscompleted = false, $completedid = null, $userid = null) {
        global $DB;
        parent::__construct($evaluation);
        if ($iscompleted) {
            // Retrieve information about the completion.
            $this->iscompleted = true;
            $params = array('evaluation' => $this->evaluation->id);
            if (!$userid && !$completedid) {
                throw new coding_exception('Either $completedid or $userid must be specified for completed evaluations');
            }
            if ($completedid) {
                $params['id'] = $completedid;
            }
            if ($userid) {
                // We must respect the anonymousity of the reply that the user saw when they were completing the evaluation,
                // not the current state that may have been changed later by the teacher.
                //$params['anonymous_response'] = EVALUATION_ANONYMOUS_NO;
                $params['userid'] = $userid;
            }
            $this->completed = $DB->get_record('local_evaluation_completed', $params);
            $this->courseid = $this->completed->courseid;
            $this->evaluation = $evaluation;
            
        }
    }

    /**
     * Returns a record from 'evaluation_completed' table
     * @return stdClass
     */
    public function get_completed() {
        return $this->completed;
    }

    /**
     * Check if the evaluation was just completed.
     *
     * @return bool true if the evaluation was just completed.
     * @since  Moodle 3.3
     */
    public function just_completed() {
        return $this->justcompleted;
    }

    /**
     * Return the jumpto property.
     *
     * @return int the next page to jump.
     * @since  Moodle 3.3
     */
    public function get_jumpto() {
        return $this->jumpto;
    }

    /**
     * Returns the temporary completion record for the current user or guest session
     *
     * @return stdClass|false record from evaluation_completedtmp or false if not found
     */
    public function get_current_completed_tmp() {
        global $USER, $DB;
        if ($this->completedtmp === null) {
            $params = array('evaluation' => $this->get_evaluation()->id);
            if (isloggedin() && !isguestuser()) {
                $params['userid'] = $USER->id;
            } else {
                $params['guestid'] = sesskey();
            }
            $this->completedtmp = $DB->get_record('local_eval_completedtmp', $params);
        }
        return $this->completedtmp;
    }

    /**
     * Can the current user see the item, if dependency is met?
     *
     * @param stdClass $item
     * @return bool whether user can see item or not,
     *     null if dependency is broken or dependent question is not answered.
     */
    protected function can_see_item($item) {
        if (empty($item->dependitem)) {
            return true;
        }
        if ($this->dependency_has_error($item)) {
            return null;
        }
        $allitems = $this->get_items();
        $ditem = $allitems[$item->dependitem];
        $itemobj = evaluation_get_item_class($ditem->typ);
        if ($this->iscompleted) {
            $value = $this->get_values($ditem);
        } else {
            $value = $this->get_values_tmp($ditem);
        }
        if ($value === null) {
            return null;
        }
        return $itemobj->compare_value($ditem, $value, $item->dependvalue) ? true : false;
    }

    /**
     * Dependency condition has an error
     * @param stdClass $item
     * @return bool
     */
    protected function dependency_has_error($item) {
        if (empty($item->dependitem)) {
            // No dependency - no error.
            return false;
        }
        $allitems = $this->get_items();
        if (!array_key_exists($item->dependitem, $allitems)) {
            // Looks like dependent item has been removed.
            return true;
        }
        $itemids = array_keys($allitems);
        $index1 = array_search($item->dependitem, $itemids);
        $index2 = array_search($item->id, $itemids);
        if ($index1 >= $index2) {
            // Dependent item is after the current item in the evaluation.
            return true;
        }
        for ($i = $index1 + 1; $i < $index2; $i++) {
            if ($allitems[$itemids[$i]]->typ === 'pagebreak') {
                return false;
            }
        }
        // There are no page breaks between dependent items.
        return true;
    }

    /**
     * Returns a value stored for this item in the evaluation (temporary or not, depending on the mode)
     * @param stdClass $item
     * @return string
     */
    public function get_item_value($item) {
        if ($this->iscompleted) {
            return $this->get_values($item);
        } else {
            return $this->get_values_tmp($item);
        }
    }

    /**
     * Retrieves responses from an unfinished attempt.
     *
     * @return array the responses (from the evaluation_valuetmp table)
     * @since  Moodle 3.3
     */
    public function get_unfinished_responses() {
        global $DB;
        $responses = array();

        $completedtmp = $this->get_current_completed_tmp();
        if ($completedtmp) {
            $responses = $DB->get_records('local_eval_valuetmp', ['completed' => $completedtmp->id]);
        }
        return $responses;
    }

    /**
     * Returns all temporary values for this evaluation or just a value for an item
     * @param stdClass $item
     * @return array
     */
    protected function get_values_tmp($item = null) {
        global $DB;
        if ($this->valuestmp === null) {
            $this->valuestmp = array();
            $responses = $this->get_unfinished_responses();
            foreach ($responses as $r) {
                $this->valuestmp[$r->item] = $r->value;
            }
        }
        if ($item) {
            return array_key_exists($item->id, $this->valuestmp) ? $this->valuestmp[$item->id] : null;
        }
        return $this->valuestmp;
    }

    /**
     * Retrieves responses from an finished attempt.
     *
     * @return array the responses (from the evaluation_value table)
     * @since  Moodle 3.3
     */
    public function get_finished_responses() {
        global $DB;
        $responses = array();

        if ($this->completed) {
            $responses = $DB->get_records('local_evaluation_value', ['completed' => $this->completed->id]);
        }
        return $responses;
    }

    /**
     * Returns all completed values for this evaluation or just a value for an item
     * @param stdClass $item
     * @return array
     */
    protected function get_values($item = null) {
        global $DB;
        if ($this->values === null) {
            $this->values = array();
            $responses = $this->get_finished_responses();
            foreach ($responses as $r) {
                $this->values[$r->item] = $r->value;
            }
        }
        if ($item) {
            return array_key_exists($item->id, $this->values) ? $this->values[$item->id] : null;
        }
        return $this->values;
    }

    /**
     * Splits the evaluation items into pages
     *
     * Items that we definitely know at this stage as not applicable are excluded.
     * Items that are dependent on something that has not yet been answered are
     * still present, as well as items with broken dependencies.
     *
     * @return array array of arrays of items
     */
    public function get_pages() {
        $pages = [[]]; // The first page always exists.
        $items = $this->get_items();
        foreach ($items as $item) {
            if ($item->typ === 'pagebreak') {
                $pages[] = [];
            } else if ($this->can_see_item($item) !== false) {
                $pages[count($pages) - 1][] = $item;
            }
        }
        return $pages;
    }

    /**
     * Returns the last page that has items with the value (i.e. not label) which have been answered
     * as well as the first page that has items with the values that have not been answered.
     *
     * Either of the two return values may be null if there are no answered page or there are no
     * unanswered pages left respectively.
     *
     * Two pages may not be directly following each other because there may be empty pages
     * or pages with information texts only between them
     *
     * @return array array of two elements [$lastcompleted, $firstincompleted]
     */
    protected function get_last_completed_page() {
        $completed = [];
        $incompleted = [];
        $pages = $this->get_pages();
        foreach ($pages as $pageidx => $pageitems) {
            foreach ($pageitems as $item) {
                if ($item->hasvalue) {
                    if ($this->get_values_tmp($item) !== null) {
                        $completed[$pageidx] = true;
                    } else {
                        $incompleted[$pageidx] = true;
                    }
                }
            }
        }
        $completed = array_keys($completed);
        $incompleted = array_keys($incompleted);
        // If some page has both completed and incompleted items it is considered incompleted.
        $completed = array_diff($completed, $incompleted);
        // If the completed page follows an incompleted page, it does not count.
        $firstincompleted = $incompleted ? min($incompleted) : null;
        if ($firstincompleted !== null) {
            $completed = array_filter($completed, function($a) use ($firstincompleted) {
                return $a < $firstincompleted;
            });
        }
        $lastcompleted = $completed ? max($completed) : null;
        return [$lastcompleted, $firstincompleted];
    }

    /**
     * Get the next page for the evaluation
     *
     * This is normally $gopage+1 but may be bigger if there are empty pages or
     * pages without visible questions.
     *
     * This method can only be called when questions on the current page are
     * already answered, otherwise it may be inaccurate.
     *
     * @param int $gopage current page
     * @param bool $strictcheck when gopage is the user-input value, make sure we do not jump over unanswered questions
     * @return int|null the index of the next page or null if this is the last page
     */
    public function get_next_page($gopage, $strictcheck = true) {
        if ($strictcheck) {
            list($lastcompleted, $firstincompleted) = $this->get_last_completed_page();
            if ($firstincompleted !== null && $firstincompleted <= $gopage) {
                return $firstincompleted;
            }
        }
        $pages = $this->get_pages();
        for ($pageidx = $gopage + 1; $pageidx < count($pages); $pageidx++) {
            if (!empty($pages[$pageidx])) {
                return $pageidx;
            }
        }
        // No further pages in the evaluation have any visible items.
        return null;
    }

    /**
     * Get the previous page for the evaluation
     *
     * This is normally $gopage-1 but may be smaller if there are empty pages or
     * pages without visible questions.
     *
     * @param int $gopage current page
     * @param bool $strictcheck when gopage is the user-input value, make sure we do not jump over unanswered questions
     * @return int|null the index of the next page or null if this is the first page with items
     */
    public function get_previous_page($gopage, $strictcheck = true) {
        if (!$gopage) {
            // If we are already on the first (0) page, there is definitely no previous page.
            return null;
        }
        $pages = $this->get_pages();
        $rv = null;
        // Iterate through previous pages and find the closest one that has any items on it.
        for ($pageidx = $gopage - 1; $pageidx >= 0; $pageidx--) {
            if (!empty($pages[$pageidx])) {
                $rv = $pageidx;
                break;
            }
        }
        if ($rv === null) {
            // We are on the very first page that has items.
            return null;
        }
        if ($rv > 0 && $strictcheck) {
            // Check if this page is actually not past than first incompleted page.
            list($lastcompleted, $firstincompleted) = $this->get_last_completed_page();
            if ($firstincompleted !== null && $firstincompleted < $rv) {
                return $firstincompleted;
            }
        }
        return $rv;
    }

    /**
     * Page index to resume the evaluation
     *
     * When user abandones answering evaluation and then comes back to it we should send him
     * to the first page after the last page he fully completed.
     * @return int
     */
    public function get_resume_page() {
        list($lastcompleted, $firstincompleted) = $this->get_last_completed_page();
        return $lastcompleted === null ? 0 : $this->get_next_page($lastcompleted, false);
    }

    /**
     * Creates a new record in the 'evaluation_completedtmp' table for the current user/guest session
     *
     * @return stdClass record from evaluation_completedtmp or false if not found
     */
    protected function create_current_completed_tmp() {
        global $USER, $DB;
        $record = (object)['evaluation' => $this->evaluation->id];
        if (isloggedin() && !isguestuser()) {
            $record->userid = $USER->id;
        } else {
            $record->guestid = sesskey();
        }
        $record->timemodified = time();
        $record->anonymous_response = $this->evaluation->anonymous;
        $id = $DB->insert_record('local_eval_completedtmp', $record);
        $this->completedtmp = $DB->get_record('local_eval_completedtmp', ['id' => $id]);
        $this->valuestmp = null;
        return $this->completedtmp;
    }

    /**
     * If user has already completed the evaluation, create the temproray values from last completed attempt
     *
     * @return stdClass record from evaluation_completedtmp or false if not found
     */
    public function create_completed_tmp_from_last_completed() {
        if (!$this->get_current_completed_tmp()) {
            $lastcompleted = $this->find_last_completed();
            if ($lastcompleted) {
                $this->completedtmp = evaluation_set_tmp_values($lastcompleted);
            }
        }
        return $this->completedtmp;
    }

    /**
     * Saves unfinished response to the temporary table
     *
     * This is called when user proceeds to the next/previous page in the complete form
     * and also right after the form submit.
     * After the form submit the {@link save_response()} is called to
     * move response from temporary table to completion table.
     *
     * @param stdClass $data data from the form local_evaluation_complete_form
     */
    public function save_response_tmp($data) {
        global $DB;
        if (!$completedtmp = $this->get_current_completed_tmp()) {
            $completedtmp = $this->create_current_completed_tmp();
        } else {
            $currentime = time();
            $DB->update_record('local_eval_completedtmp',
                    ['id' => $completedtmp->id, 'timemodified' => $currentime]);
            $completedtmp->timemodified = $currentime;
        }

        // Find all existing values.
        $existingvalues = $DB->get_records_menu('local_eval_valuetmp',
                ['completed' => $completedtmp->id], '', 'item, id');

        // Loop through all evaluation items and save the ones that are present in $data.
        $allitems = $this->get_items();
        foreach ($allitems as $item) {
            if (!$item->hasvalue) {
                continue;
            }
            $keyname = $item->typ . '_' . $item->id;
            if (!isset($data->$keyname)) {
                // This item is either on another page or dependency was not met - nothing to save.
                continue;
            }

            $newvalue = ['item' => $item->id, 'completed' => $completedtmp->id];

            // Convert the value to string that can be stored in 'evaluation_valuetmp' or 'evaluation_value'.
            $itemobj = evaluation_get_item_class($item->typ);
            $newvalue['value'] = $itemobj->create_value($data->$keyname);

            // Update or insert the value in the 'evaluation_valuetmp' table.
            if (array_key_exists($item->id, $existingvalues)) {
                $newvalue['id'] = $existingvalues[$item->id];
                $DB->update_record('local_eval_valuetmp', $newvalue);
            } else {
                $DB->insert_record('local_eval_valuetmp', $newvalue);
            }
        }

        // Reset valuestmp cache.
        $this->valuestmp = null;
    }

    /**
     * Saves the response
     *
     * The form data has already been stored in the temporary table in
     * {@link save_response_tmp()}. This function copies the values
     * from the temporary table to the completion table.
     */
    public function save_response() {
        global $USER, $SESSION, $DB;

        $evaluationcompleted = $this->find_last_completed();
        $evaluationcompletedtmp = $this->get_current_completed_tmp();

        $teamuserid=optional_param('teamuserid', 0, PARAM_INT);
        if($teamuserid){
            $evaluationuserscheck=$DB->record_exists_sql("SELECT id FROM {local_evaluation_users} WHERE evaluationid = :evaluationid AND userid = :userid",array('evaluationid' => $evaluationcompletedtmp->evaluation,'userid' => $teamuserid));
            if($evaluationuserscheck){
                $evaluationcompletedtmp->userid=$teamuserid;
            }else{
                return;
            }
        }
        if (evaluation_check_is_switchrole()) {
            // We do not actually save anything if the role is switched, just delete temporary values.
            $this->delete_completedtmp();
            return;
        }

        // Save values.
        $completedid = evaluation_save_tmp_values($evaluationcompletedtmp, $evaluationcompleted);
        $this->completed = $DB->get_record('local_evaluation_completed', array('id' => $completedid));

        unset($SESSION->evaluation->is_started);
    }

    /**
     * Deletes the temporary completed and all related temporary values
     */
    protected function delete_completedtmp() {
        global $DB;

        if ($completedtmp = $this->get_current_completed_tmp()) {
            $DB->delete_records('local_eval_valuetmp', ['completed' => $completedtmp->id]);
            $DB->delete_records('local_eval_completedtmp', ['id' => $completedtmp->id]);
            $this->completedtmp = null;
        }
    }

    /**
     * Retrieves the last completion record for the current user
     *
     * @return stdClass record from evaluation_completed or false if not found
     */
    public function find_last_completed() {
        global $USER, $DB;
        if (!isloggedin() || isguestuser()) {
            // Not possible to retrieve completed evaluation for guests.
            return false;
        }
        if ($this->is_anonymous()) {
            // Not possible to retrieve completed anonymous evaluation.
            return false;
        }
        $params = array('evaluation' => $this->evaluation->id, 'userid' => $USER->id, 'anonymous_response' => EVALUATION_ANONYMOUS_NO);
        $this->completed = $DB->get_record('local_evaluation_completed', $params);
        return $this->completed;
    }

    /**
     * Checks if current user has capability to submit the evaluation
     *
     * There is an exception for fully anonymous evaluations when guests can complete
     * evaluation without the proper capability.
     *
     * This should be followed by checking {@link can_submit()} because even if
     * user has capablity to complete, they may have already submitted evaluation
     * and can not re-submit
     *
     * @return bool
     */
    public function can_complete() {
        global $CFG;
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        if (has_capability('local/evaluation:complete', $context)) {
            return true;
        }

        if (!empty($CFG->evaluation_allowfullanonymous)
                    AND $this->evaluation->course == SITEID
                    AND $this->evaluation->anonymous == EVALUATION_ANONYMOUS_YES
                    AND (!isloggedin() OR isguestuser())) {
            // Guests are allowed to complete fully anonymous evaluation without having 'mod/evaluation:complete' capability.
            return true;
        }

        return false;
    }

    /**
     * Checks if user is prevented from re-submission.
     *
     * This must be called after {@link can_complete()}
     *
     * @return bool
     */
    public function can_submit() {
        if ($this->get_evaluation($this->evaluation)->multiple_submit == 0 ) {
            if ($this->is_already_submitted()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Process a page jump via the local_evaluation_complete_form.
     *
     * This function initializes the form and process the submission.
     *
     * @param  int $gopage         the current page
     * @param  int $gopreviouspage if the user chose to go to the previous page
     * @param  int $mode mode of the form
     * @return string the url to redirect the user (if any)
     * @since  Moodle 3.3
     */
    public function process_page($gopage, $gopreviouspage = false,$classid = 0,$teamuserid=0) {
        global $DB, $CFG, $PAGE, $SESSION;
        $urltogo = null;

        // Save the form for later during the request.
        $this->create_completed_tmp_from_last_completed();
        $this->form = new local_evaluation_complete_form(local_evaluation_complete_form::MODE_COMPLETE,
            $this, 'evaluation_complete_form', array('gopage' => $gopage, 'evalid'=>$this->get_evaluation()->id,'teamuserid'=>$teamuserid));

        if ($this->form->is_cancelled()) {
            // Form was cancelled - return will be different based on plugin.
            $evaluation = $DB->get_record("local_evaluations", array("id" => $this->get_evaluation()->id), '*', MUST_EXIST);
            $urltogo = evaluation_return_url($evaluation->plugin, $evaluation);
            
        } else if ($this->form->is_submitted() &&
                ($this->form->is_validated() || $gopreviouspage)) {
            
            // Form was submitted (skip validation for "Previous page" button).
            $data = $this->form->get_submitted_data();
            if (!isset($SESSION->evaluation->is_started) OR !$SESSION->evaluation->is_started == true) {
                print_error('error', '', $CFG->wwwroot.'/local/evaluation/eval_view.php?id='.$this->evaluation->id);
            }

            $this->save_response_tmp($data);
            
            if (!empty($data->savevalues) || !empty($data->gonextpage)) {
                if (($nextpage = $this->get_next_page($gopage)) !== null) {
                    if ($PAGE->has_set_url()) {
                        $urltogo = new moodle_url($PAGE->url, array('gopage' => $nextpage,'teamuserid'=>$teamuserid));
                    }
                    $this->jumpto = $nextpage;
                } else {
                    $this->save_response();
                    if (!$this->get_evaluation()->page_after_submit) {
                        \core\notification::success(get_string('entries_saved', 'local_evaluation'));
                    }
                    $this->justcompleted = true;
                }
            } else if (!empty($gopreviouspage)) {
                $prevpage = intval($this->get_previous_page($gopage));
                if ($PAGE->has_set_url()) {
                    $urltogo = new moodle_url($PAGE->url, array('gopage' => $prevpage,'teamuserid'=>$teamuserid));
                }
                $this->jumpto = $prevpage;
            }
        }
        return $urltogo;
    }

    /**
     * Render the form with the questions.
     *
     * @return string the form rendered
     * @since Moodle 3.3
     */
    public function render_items() {
        global $SESSION;

        // Print the items.
        $SESSION->evaluation->is_started = true;
        return $this->form->render();
    }
}
