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
 * Privacy provider for block_trending_modules.
 *
 * @package    block_trending_modules
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_trending_modules\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * The block keeps two user preferences; its own table (block_trending_modules) holds module statistics, no person.
 *
 * Added 2026-11-13. The plugin had no privacy provider, so core's test_all_providers_compliant failed for it and the
 * two preferences were neither declared nor exported. Core deletes a user's preferences with the user, so there is
 * nothing to delete here.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {

    /** @var string[] Preferences this block sets. */
    private const PREFERENCES = ['show_trending_modules', 'force_dontshow_trending_modules'];

    /**
     * The two preferences.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::PREFERENCES as $name) {
            $collection->add_user_preference($name, 'privacy:metadata:preference:' . $name);
        }
        return $collection;
    }

    /**
     * Export the user's values of the two preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        foreach (self::PREFERENCES as $name) {
            $value = get_user_preferences($name, null, $userid);
            if ($value === null) {
                continue;
            }
            writer::export_user_preference('block_trending_modules', $name, transform::yesno((bool) $value),
                get_string('privacy:metadata:preference:' . $name, 'block_trending_modules'));
        }
    }
}
