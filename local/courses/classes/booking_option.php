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
 * Managing a single booking option
 *
 * @package local_courses
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author 2014 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courses;

use cache;
use cache_helper;
use coding_exception;
use completion_info;
use context_module;
use context_system;
use context;
use dml_exception;
use Exception;
use html_writer;
use invalid_parameter_exception;
use local_entities\entitiesrelation_handler;
use local_courses\bo_availability\conditions\customform;
use local_courses\event\booking_rulesexecutionfailed;
use local_courses\option\dates_handler;
use local_courses\bo_actions\actions_info;
use local_courses\booking_rules\rules_info;
use stdClass;
use moodle_url;
use local_courses\booking_utils;
use local_courses\calendar;
use mod_blocal_coursesooking\teachers_handler;
use local_courses\customfield\booking_handler;
use local_courses\event\booking_afteractionsfailed;
use local_courses\event\bookinganswer_cancelled;
use local_courses\event\bookingoption_freetobookagain;
use local_courses\message_controller;
use local_courses\option\fields\credits;
use local_courses\option\fields_info;
use local_courses\placeholders\placeholders_info;
use local_courses\subbookings\subbookings_info;
use local_courses\task\send_completion_mails;
use moodle_exception;
use MoodleQuickForm;

use function get_config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/local/courses/lib.php');

/**
 * Class to managing a single booking option
 *
 * @package mod_booking
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author 2014 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booking_option {

    /**
     * Booking options should always be created via singleton service.
     * The only usage of this constructor should therefore be in singleton service.
     *
     * @param int $cmid
     * @param int $optionid
     */
    public function __construct(int $cmid, int $optionid) {
    }

    /**
     * Returns a booking_option object when optionid is passed along.
     * Saves db query when booking id is given as well, but uses already cached settings.
     *
     * @param int $optionid
     * @param ?int $bookingid booking id
     *
     * @return booking_option
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function create_option_from_optionid(int $optionid, ?int $bookingid = null) {
        global $DB;

        if (empty($bookingid)) {
            if ($settings = singleton_service::get_instance_of_booking_option_settings($optionid)) {
                $bookingid = $settings->bookingid;
            } else {
                $bookingid = $optionid;
            }
        }

        // If we could not retrieve it, we have to return null.
        if (empty($bookingid)) {
            return null;
        }

        $cm = get_coursemodule_from_instance('booking', $bookingid);
        return singleton_service::get_instance_of_booking_option($cm->id, $optionid);
    }

}
