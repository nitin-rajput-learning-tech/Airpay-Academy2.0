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
 * The local_program post users_deleted event.
 *
 * @package    local_program
 * @copyright  2018 Arun Kumar M <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_program\event;
use stdclass;
defined('MOODLE_INTERNAL') || die();

/**
 * The local_program post users_deleted event class.
 *
 * @package    local_program
 * @since      Moodle 3.4
 * @copyright  2018 Arun Kumar M <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_users_unenrol extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_program_users';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        // global $DB;
        
        $stringHelpers = new stdClass();
        $stringHelpers->userid = $this->userid;
        $stringHelpers->relateduserid = $this->relateduserid;
        $stringHelpers->objectid = $this->objectid;
//        return "The user with id '$this->userid' has unenrolled user with id '$this->relateduserid' from program with id '$this->objectid'.";
        return get_string('user_unenrolled_user_from_program', 'local_program', $stringHelpers);
        
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventprogramusers_deleted', 'local_program');
    }
}
