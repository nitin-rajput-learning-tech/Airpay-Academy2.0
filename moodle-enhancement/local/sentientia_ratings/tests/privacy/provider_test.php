<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider lock-in tests for local_sentientia_ratings.
 *
 * The plugin holds three tables keyed on a user id: the star ratings, and (since the ADR-032 BizLMS import)
 * the written reviews and the like or dislike reactions. Each has to be declared, exported and erased, and the
 * provider must never claim the plugin holds no personal data.
 *
 * @package    local_sentientia_ratings
 * @category   test
 * @covers     \local_sentientia_ratings\privacy\provider
 * @group      local_sentientia_ratings
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    /** The three tables, in the order the provider declares them. */
    private const TABLES = [
        'local_sentientia_ratings',
        'local_sentientia_ratings_reviews',
        'local_sentientia_ratings_reactions',
    ];

    /**
     * One row in each table for a user.
     *
     * @param int $userid
     * @return void
     */
    private function seed(int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_sentientia_ratings', (object) [
            'itemid' => 7, 'ratearea' => 'local_sentientia_courses', 'userid' => $userid, 'rating' => 4,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_ratings_reviews', (object) [
            'itemid' => 7, 'ratearea' => 'local_sentientia_courses', 'userid' => $userid,
            'review' => 'Worth the time', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_ratings_reactions', (object) [
            'itemid' => 7, 'ratearea' => 'local_sentientia_courses', 'userid' => $userid, 'likestatus' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    public function test_the_provider_declares_every_table_and_never_claims_to_hold_nothing(): void {
        $this->assertFalse(in_array(\core_privacy\local\metadata\null_provider::class,
            class_implements(provider::class) ?: [], true));

        $collection = provider::get_metadata(new collection('local_sentientia_ratings'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertSame(self::TABLES, array_keys($declared));
        foreach (self::TABLES as $table) {
            $this->assertContains('userid', $declared[$table], "{$table}.userid is declared");
            $this->assertContains('itemid', $declared[$table]);
            $this->assertContains('ratearea', $declared[$table]);
        }
        $this->assertContains('review', $declared['local_sentientia_ratings_reviews']);
        $this->assertContains('likestatus', $declared['local_sentientia_ratings_reactions']);
    }

    public function test_every_declared_field_has_an_english_and_a_hindi_string(): void {
        global $CFG;
        $collection = provider::get_metadata(new collection('local_sentientia_ratings'));
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include($CFG->dirroot . '/local/sentientia_ratings/lang/' . $lang . '/local_sentientia_ratings.php');
            foreach ($collection->get_collection() as $item) {
                $this->assertArrayHasKey($item->get_summary(), $string, "{$lang}: summary of {$item->get_name()}");
                foreach ($item->get_privacy_fields() as $field => $key) {
                    $this->assertArrayHasKey($key, $string, "{$lang}: {$item->get_name()}.{$field}");
                }
            }
        }
    }

    public function test_a_user_with_only_a_review_or_a_reaction_is_found(): void {
        global $DB;
        $this->resetAfterTest();
        $reviewer = $this->getDataGenerator()->create_user();
        $reactor = $this->getDataGenerator()->create_user();
        $nobody = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_sentientia_ratings_reviews', (object) [
            'itemid' => 7, 'ratearea' => 'local_sentientia_courses', 'userid' => $reviewer->id,
            'review' => 'Only a review', 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $DB->insert_record('local_sentientia_ratings_reactions', (object) [
            'itemid' => 7, 'ratearea' => 'local_sentientia_courses', 'userid' => $reactor->id, 'likestatus' => 2,
            'timecreated' => 1, 'timemodified' => 1,
        ]);

        $this->assertNotEmpty(provider::get_contexts_for_userid((int) $reviewer->id)->get_contextids());
        $this->assertNotEmpty(provider::get_contexts_for_userid((int) $reactor->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_ratings');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $reviewer->id, $userlist->get_userids());
        $this->assertContains((int) $reactor->id, $userlist->get_userids());
        $this->assertNotContains((int) $nobody->id, $userlist->get_userids());
    }

    public function test_the_export_carries_the_review_text_and_the_reaction(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);

        $context = \context_system::instance();
        provider::export_user_data(new approved_contextlist($user, 'local_sentientia_ratings', [$context->id]));

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $root = get_string('pluginname', 'local_sentientia_ratings');
        $reviews = $writer->get_data([$root, get_string('privacy:metadata:reviews', 'local_sentientia_ratings')]);
        $this->assertSame('Worth the time', $reviews->reviewsgiven[0]['review']);
        $reactions = $writer->get_data([$root, get_string('privacy:metadata:reactions', 'local_sentientia_ratings')]);
        $this->assertEquals(1, $reactions->reactionsgiven[0]['likestatus']);
        $ratings = $writer->get_data([$root, get_string('privacy:metadata:ratings', 'local_sentientia_ratings')]);
        $this->assertEquals(4, $ratings->ratingsgiven[0]['rating']);
    }

    public function test_erasing_a_user_removes_all_three_and_touches_nobody_else(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $this->seed((int) $other->id);

        provider::delete_data_for_user(
            new approved_contextlist($user, 'local_sentientia_ratings', [\context_system::instance()->id]));

        foreach (self::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user->id]), "{$table}: the user's rows are gone");
            $this->assertTrue($DB->record_exists($table, ['userid' => $other->id]), "{$table}: nobody else's are");
        }
    }

    public function test_erasing_several_users_and_a_whole_context(): void {
        global $DB;
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $three = $this->getDataGenerator()->create_user();
        foreach ([$one, $two, $three] as $user) {
            $this->seed((int) $user->id);
        }

        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), 'local_sentientia_ratings',
            [(int) $one->id, (int) $two->id]));
        foreach (self::TABLES as $table) {
            $this->assertSame(1, $DB->count_records($table), "{$table}: only the third user is left");
            $this->assertTrue($DB->record_exists($table, ['userid' => $three->id]));
        }

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        foreach (self::TABLES as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
    }
}
