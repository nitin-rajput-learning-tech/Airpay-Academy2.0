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

namespace local_courses;
use Exception;
use local_entities\entitiesrelation_handler;
use stdClass;

/**
 * Singleton Service to improve performance.
 *
 * @package local_courses
 * @since Moodle 3.11
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class singleton_service {
    // Hold the class instance of the singleton service.

    /** @var singleton_service $instance */
    private static $instance = null;

    /**
     * Constructor
     *
     * The constructor is private to prevent initiation with outer code.
     *
     * @return void
     */
    private function __construct() {
        // The expensive process (e.g.,db connection) goes here.
    }

    /**
     * The object is created from within the class itself only if the class has no instance.
     *
     * @return singleton_service
     *
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new singleton_service();
        }

        return self::$instance;
    }

    /**
     * Service to create and return singleton instance of booking_option.
     *
     * @param int $cmid
     * @param int $optionid
     *
     * @return booking_option|null
     */
    public static function get_instance_of_booking_option(int $cmid, int $optionid) {

        $instance = self::get_instance();

        if (isset($instance->bookingoptions[$optionid])) {
        print_r($instance->bookingoptions[$optionid]);exit;

            return $instance->bookingoptions[$optionid];

        } else {
            try {
                $option = new booking_option($cmid, $optionid);
                $instance->bookingoptions[$optionid] = $option;
        print_r($option);exit;

                return $option;
            } catch (Exception $e) {
                return null;
            }

        }
    }

    /**
     * Service to create and return singleton instance of booking_option_settings.
     *
     * @param int $optionid
     * @param ?stdClass $dbrecord
     *
     * @return booking_option_settings
     */
    public static function get_instance_of_booking_option_settings($optionid, ?stdClass $dbrecord = null): booking_option_settings {
        $instance = self::get_instance();
        if (empty($optionid)) {
            return new booking_option_settings(0);
        }

        if (isset($instance->bookingoptionsettings[$optionid])) {
            
            return $instance->bookingoptionsettings[$optionid];
        } else {
            $settings = new booking_option_settings($optionid, $dbrecord);
            $instance->bookingoptionsettings[$optionid] = $settings;
            return $settings;
        }
    }
}
