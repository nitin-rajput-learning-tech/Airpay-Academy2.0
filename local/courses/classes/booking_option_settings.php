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
use html_writer;
use stdClass;
use moodle_url;

/**
 * Settings class for booking option instances.
 *
 * @package local_courses
 * @copyright 2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booking_option_settings {

    /** @var array $sessions */
    public $sessions = [];

    // /** @var array $sessioncustomfields */
    // public $sessioncustomfields = [];

    /** @var array $teachers */
    public $teachers = [];


    /**
     * Constructor for the booking option settings class.
     * The constructor can take the dbrecord stdclass which is the initial DB request for this option.
     * This permits performance increase, because we can request all the records once and then
     *
     * @param int $optionid Booking option id.
     * @param stdClass|null $dbrecord of bookig option.
     * @throws dml_exception
     */
    public function __construct(int $optionid, ?stdClass $dbrecord = null) {
        // Even if we have a record, we still get the cache...
        // Because in the cache, we have also information from other tables.
        $cache = \cache::make('local_courses', 'bookingoptionsettings');
        $cachedoption = $cache->get($optionid);
      //  print_r($cache->get($optionid));exit;
        if (!$cachedoption = $cache->get($optionid)) {
            $savecache = true;
        } else {
            $savecache = false;
        }
        // If there is no cache present...
        // We try to fall back on the dbrecord.
        if (!$cachedoption) {
            if (!$dbrecord) {
                $cachedoption = null;
            } else {
                $cachedoption = $dbrecord;
            }
        }
     
        // If we have no object to pass to set values, the function will retrieve the values from db.
        if ($data = $this->set_values($optionid, $cachedoption)) {
            // Only if we didn't pass anything to cachedoption, we set the cache now.
            if ($savecache) {
                $cache->set($optionid, $data);
            }
        }
    }

    /**
     * Set all the values from DB, if necessary.
     * If we have passed on the cached object, we use this one.
     *
     * @param int $optionid
     * @param object|null $dbrecord
     * @return stdClass|null
     */
    private function set_values(int $optionid, ?object $dbrecord = null) {
        global $DB;

        if (empty($optionid)) {
            return;
        }
        // If we don't get the cached object, we have to fetch it here.
        if ($dbrecord === null) {
            $params['id'] = $optionid;
            $sql = "SELECT *
                    FROM {course} c
                    WHERE c.price_status= 1
                    AND c.id=:id";
            $dbrecord = $DB->get_record_sql($sql, $params);
        }
        if ($dbrecord) {
            return $dbrecord;
        }

        // If record is not found in DB, we return null.
        return null;
    }
}
