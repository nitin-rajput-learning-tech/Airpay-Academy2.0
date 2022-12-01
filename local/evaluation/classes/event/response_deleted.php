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
 * The local_evaluation response deleted event.
 *
 * @package    local_evaluation
 * @copyright   2018 Sreenivas <seenivasula@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace local_evaluation\event;
use stdclass;
defined('MOODLE_INTERNAL') || die();

/**
 * The local_evaluation response deleted event class.
 *
 * This event is triggered when a feedback response is deleted.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int anonymous: if feedback is anonymous.
 *      - int cmid: course module id.
 *      - int instanceid: id of instance.
 * }
 *
 * @package    local_feedback
 * @since      Moodle 2.6
 * @copyright  2018 Sreenivas <seenivasula@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class response_deleted extends \core\event\base {

    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_evaluation';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Creates an instance from the record from db table feedback_completed
     *
     * @param stdClass $completed
     * @param stdClass|cm_info $cm
     * @param stdClass $feedback
     * @return self
     */
    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventresponsedeleted', 'local_evaluation');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {        
        $stringHelpers=new stdClass();
        $stringHelpers->userid=$this->userid;
        $stringHelpers->relateduserid=$this->relateduserid;   
        $stringHelpers->objectid=$this->objectid;   
//        return "The user with id '$this->userid' deleted the feedback for the user with id '$this->relateduserid' " .
//            "for the feedback activity with id '$this->objectid'.";
         return get_string('user_deleted_feedback_activity', 'local_evaluation', $stringHelpers);
    }

    /**
     * Replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        return array($this->objectid, 'evaluation', 'delete', 'eval_view.php?id=' . $this->objectid, $this->objectid,
                $this->objectid);
    }

    /**
     * Define whether a user can view the event or not. Make sure no one except admin can see details of an anonymous response.
     *
     * @deprecated since 2.7
     *
     * @param int|\stdClass $userorid ID of the user.
     * @return bool True if the user can view the event, false otherwise.
     */
    public function can_view($userorid = null) {
        global $USER;
        debugging('can_view() method is deprecated, use anonymous flag instead if necessary.', DEBUG_DEVELOPER);

        if (empty($userorid)) {
            $userorid = $USER;
        }
        if ($this->anonymous) {
            return is_siteadmin($userorid);
        } else {
            return has_capability('local/evaluation:viewreports', $this->context, $userorid);
        }
    }

    /**
     * Custom validations
     *
     * @throws \coding_exception in case of any problems.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        
        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'instanceid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'local_evaluation_completed', 'restore' => 'local_evaluation_completed');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['instanceid'] = array('db' => 'local_evaluations', 'restore' => 'local_evaluations');

        return $othermapped;
    }
}

