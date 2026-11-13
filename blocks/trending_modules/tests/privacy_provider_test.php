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
 * Privacy provider tests for block_trending_modules (2026-11-13).
 *
 * @package    block_trending_modules
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_trending_modules;

defined('MOODLE_INTERNAL') || die();

use block_trending_modules\privacy\provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * @covers \block_trending_modules\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    public function test_metadata_declares_both_preferences(): void {
        $names = array_map(fn($item) => $item->get_name(),
            provider::get_metadata(new collection('block_trending_modules'))->get_collection());
        $this->assertSame(['show_trending_modules', 'force_dontshow_trending_modules'], $names);
    }

    /**
     * Both preferences are exported for the user who holds them; another user's are not.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        set_user_preference('show_trending_modules', 1, $user);
        set_user_preference('force_dontshow_trending_modules', 1, $user);
        set_user_preference('show_trending_modules', 1, $other);

        provider::export_user_preferences((int) $user->id);

        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('block_trending_modules');
        $this->assertSame(get_string('yes'), $prefs->show_trending_modules->value);
        $this->assertSame(get_string('yes'), $prefs->force_dontshow_trending_modules->value);
    }

    public function test_nothing_exported_without_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences((int) $user->id);

        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }
}
