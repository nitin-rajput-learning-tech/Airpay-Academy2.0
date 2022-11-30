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
 * The local_evaluation deleted event.
 *
 * @package    local_evaluation
 * @copyright  2018 sreenivasula@eabyas.in
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evaluation\event;
use stdclass;
defined('MOODLE_INTERNAL') || die();

class evaluation_deleted extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_evaluation';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $stringHelpers=new stdClass();
        $stringHelpers->userid=$this->userid;
        $stringHelpers->objectid=$this->objectid;
//        return "The user with id '$this->userid' has deleted the feedback with id '$this->objectid'.";
        return get_string('user_deleted_feedback', 'local_evaluation', $stringHelpers);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventevaluationdeleted', 'local_evaluation');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        $url = new \moodle_url('/local/evaluation/index.php');
        $url->set_anchor('p'.$this->objectid);
        return $url;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        // The legacy log table expects a relative path to /local/evaluation/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/local/evaluation/'));

        return array($this->objectid, 'evaluation', 'add post', $logurl, $this->objectid, $this->contextinstanceid);
    }

    

    public static function get_objectid_mapping() {
        return array('db' => 'local_evaluations', 'restore' => 'local_evaluation');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['evaluationid'] = array('db' => 'local_evaluations', 'restore' => 'local_evaluation');

        return $othermapped;
    }
}
