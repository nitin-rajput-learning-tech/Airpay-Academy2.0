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
 * @subpackage local_request
 */

namespace local_request\event;
use stdclass;
defined('MOODLE_INTERNAL') || die();

/**
 * The local_request post created event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int discussionid: The discussion id the post is part of.
 *      - int classroomid: The classroom id the post is part of.
 *      - string classroomtype: The type of classroom the post is part of.
 * }
 *
 * @package    local_request
 * @since      Moodle 2.7
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_rejected extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_request_records';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
               global $DB;
        $firstname=$DB->get_field_sql("SELECT concat(firstname,' ',lastname) FROM {user} where id={$this->userid}");
        $requestinfo=$DB->get_record_sql("SELECT * FROM {local_request_records} where id={$this->objectid}");
        if($requestinfo){
            return "The user with id '$firstname ($this->userid)' has requested for component($requestinfo->compname), componentid($requestinfo->componentid), action(Rejected) and requestedid ($requestinfo->id).";
        }else{
            return 'went wrong';
        }     
        
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
         return get_string('eventrequestrejected', 'local_request');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        global $DB;
        $url = new \moodle_url('/local/request/index.php');
        $url->set_anchor('p'.$this->objectid);
        return $url;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        // The legacy log table expects a relative path to /local/forum/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/local/request/'));

        return array($this->objectid, 'request', 'rejected', $logurl, $this->other['component'], $this->other['componentid']);
    }

    

    public static function get_objectid_mapping() {
        return array('db' => 'local_request_records', 'restore' => 'local_request_records');
    }


}
