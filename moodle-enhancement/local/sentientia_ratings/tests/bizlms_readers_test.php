<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * The readers that show what the BizLMS ratings import brought over (ADR-032, mapping doc section 20, code
 * fixes 3 and 5): the web service whitelist, the review list and the reaction counts, each behind a flag that
 * is OFF by default.
 *
 * The review list shows names, so it is limited to the viewer's tenant (ADR-031, fail closed), hides blank
 * reviews and deleted users, and escapes every review: BizLMS printed them raw.
 *
 * @package    local_sentientia_ratings
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_ratings\review_manager
 * @covers     \local_sentientia_ratings\reaction_manager
 * @covers     \local_sentientia_ratings\item_summary
 * @covers     \local_sentientia_ratings\external\submit_rating
 *
 * @group local_sentientia_ratings
 * @group tenant_isolation
 */
final class bizlms_readers_test extends \advanced_testcase {
    use \local_sentientia_org\test\bizlms_fixture;

    /** Item every review of the list tests is about. */
    private const ITEM = 5;

    /** Area every row of these tests is filed under. */
    private const AREA = 'local_sentientia_courses';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    // Flags.

    public function test_both_surfaces_are_registered_and_off_by_default(): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        foreach ([review_manager::FLAG, reaction_manager::FLAG] as $key) {
            $this->assertArrayHasKey($key, $registry, "{$key} is registered in db/feature_flags.php");
            $this->assertFalse($registry[$key]['default'], "{$key} ships OFF");
        }
        $this->assertFalse(review_manager::enabled());
        $this->assertFalse(reaction_manager::enabled());
    }

    public function test_the_page_does_not_exist_while_both_flags_are_off(): void {
        $viewer = $this->viewer('/1');
        $this->assertNull(item_summary::export(self::ITEM, self::AREA, $viewer));

        $this->flag(review_manager::FLAG, true);
        $this->assertIsArray(item_summary::export(self::ITEM, self::AREA, $viewer));
        $this->flag(review_manager::FLAG, false);
        $this->assertNull(item_summary::export(self::ITEM, self::AREA, $viewer));
    }

    // The review list.

    public function test_the_list_shows_only_what_the_viewer_may_see_newest_first(): void {
        $t = 1700000000;
        $reviewer = $this->viewer('/1');
        $public = $this->viewer('/77');
        $gone = $this->viewer('/1', true);
        $untenanted = $this->viewer(null);
        $this->rate($reviewer->id, 4);
        $this->review($reviewer->id, 'Clear and practical', $t + 1);
        $this->review($public->id, 'Public tenant view', $t + 2);
        $this->review($gone->id, 'Left already', $t + 3);
        $this->review($reviewer->id, "<b>bold</b>\nnext line", $t + 4);
        $this->review($reviewer->id, '', $t + 5);
        $this->review($reviewer->id, null, $t + 6);
        $this->review($reviewer->id, '   ', $t + 7);
        $this->review($untenanted->id, 'No tenant at all', $t + 8);

        $page = review_manager::get_page(self::ITEM, self::AREA, $this->viewer('/1'));
        // Newest first; the blank, the NULL, the white space, the deleted user, the /77 and the untenanted reviewer are out.
        $this->assertCount(2, $page['reviews']);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $page['reviews'][0]['reviewhtml']);
        $this->assertStringNotContainsString('<b>', $page['reviews'][0]['reviewhtml'], 'the review is escaped, never raw');
        $this->assertStringContainsString('<br', $page['reviews'][0]['reviewhtml'], 'line breaks are kept');
        $this->assertStringContainsString('Clear and practical', $page['reviews'][1]['reviewhtml']);
        $this->assertSame(fullname($reviewer), $page['reviews'][1]['reviewer']);
        // The rating beside a review is the reviewer's own rating of the item, joined on user, item and area.
        foreach ($page['reviews'] as $review) {
            $this->assertTrue($review['hasrating']);
            $this->assertSame(4, $review['rating']);
        }
        // A white-space-only review is never listed. Whether the SQL already leaves it out depends on the
        // collation (PAD SPACE ignores trailing spaces when it compares with ''), so the total is 2 or 3.
        $this->assertGreaterThanOrEqual(2, $page['total']);
        $this->assertLessThanOrEqual(3, $page['total']);
    }

    public function test_a_site_administrator_sees_every_tenant(): void {
        $t = 1700000000;
        $this->review($this->viewer('/1')->id, 'Airpay review', $t + 1);
        $this->review($this->viewer('/77')->id, 'Public review', $t + 2);
        $this->review($this->viewer(null)->id, 'No tenant review', $t + 3);
        $this->review($this->viewer('/1', true)->id, 'Deleted user review', $t + 4);

        $page = review_manager::get_page(self::ITEM, self::AREA, get_admin());
        $this->assertCount(3, $page['reviews'], 'a cross-tenant viewer sees every reviewer, but never a deleted user');
    }

    public function test_a_viewer_with_no_tenant_sees_nothing(): void {
        $this->review($this->viewer('/1')->id, 'Airpay review', 1700000001);
        $this->review($this->viewer(null)->id, 'No tenant review', 1700000002);

        $page = review_manager::get_page(self::ITEM, self::AREA, $this->viewer(null));
        $this->assertSame(['total' => 0, 'reviews' => []], $page, 'fail closed: never everything');
    }

    public function test_the_list_is_paged(): void {
        $reviewer = $this->viewer('/1');
        for ($i = 1; $i <= 25; $i++) {
            $this->review($reviewer->id, "Review {$i}", 1700000000 + $i);
        }
        $first = review_manager::get_page(self::ITEM, self::AREA, $reviewer, 0);
        $second = review_manager::get_page(self::ITEM, self::AREA, $reviewer, 1);
        $this->assertSame(25, $first['total']);
        $this->assertCount(20, $first['reviews']);
        $this->assertCount(5, $second['reviews']);
        $this->assertStringContainsString('Review 25', $first['reviews'][0]['reviewhtml']);
        $this->assertStringContainsString('Review 1<', $second['reviews'][4]['reviewhtml'] . '<');
    }

    public function test_a_review_of_another_item_or_area_is_not_listed(): void {
        $reviewer = $this->viewer('/1');
        $this->review($reviewer->id, 'This item', 1700000001);
        $this->review($reviewer->id, 'Another item', 1700000002, 6);
        $this->review($reviewer->id, 'Another area', 1700000003, self::ITEM, 'local_sentientia_classroom');

        $page = review_manager::get_page(self::ITEM, self::AREA, $reviewer);
        $this->assertCount(1, $page['reviews']);
        $this->assertStringContainsString('This item', $page['reviews'][0]['reviewhtml']);
    }

    // The reaction counts.

    public function test_reactions_count_likes_and_dislikes_only(): void {
        $this->react($this->viewer('/1')->id, 1);
        $this->react($this->viewer('/1')->id, 1);
        $this->react($this->viewer('/77')->id, 2);
        $this->react($this->viewer('/1')->id, 0);
        $this->react($this->viewer('/1')->id, 5);
        $this->react($this->viewer('/1')->id, 1, 6);

        $counts = reaction_manager::get_counts(self::ITEM, self::AREA);
        $this->assertSame(2, $counts->likes);
        $this->assertSame(1, $counts->dislikes, 'status 0 and 5 are carried over by the import and never counted');
        $none = reaction_manager::get_counts(99, self::AREA);
        $this->assertSame([0, 0], [$none->likes, $none->dislikes]);
    }

    // The page's data.

    public function test_each_surface_is_gated_by_its_own_flag(): void {
        $viewer = $this->viewer('/1');
        $this->review($viewer->id, 'A review', 1700000001);
        $this->react($viewer->id, 1);
        $this->rate($viewer->id, 5);

        $this->flag(review_manager::FLAG, true);
        $data = item_summary::export(self::ITEM, self::AREA, $viewer);
        $this->assertTrue($data['showreviews']);
        $this->assertFalse($data['showreactions']);
        $this->assertArrayNotHasKey('likestext', $data, 'no counts while the reaction flag is off');
        $this->assertTrue($data['hasreviews']);
        $this->assertTrue($data['hasaverage']);

        $this->flag(review_manager::FLAG, false);
        $this->flag(reaction_manager::FLAG, true);
        $data = item_summary::export(self::ITEM, self::AREA, $viewer);
        $this->assertFalse($data['showreviews']);
        $this->assertTrue($data['showreactions']);
        $this->assertSame([], $data['reviews'], 'no review is read while the review flag is off');
        $this->assertSame(get_string('reactionlikes', 'local_sentientia_ratings', 1), $data['likestext']);
        $this->assertSame(get_string('reactiondislikes', 'local_sentientia_ratings', 0), $data['dislikestext']);

        $this->flag(review_manager::FLAG, true);
        $data = item_summary::export(self::ITEM, self::AREA, $viewer);
        $this->assertTrue($data['showreviews'] && $data['showreactions']);
    }

    public function test_the_page_refuses_an_area_or_item_it_does_not_know(): void {
        $this->flag(review_manager::FLAG, true);
        $viewer = $this->viewer('/1');
        foreach (['local_courses', 'mdl_user', ''] as $area) {
            try {
                item_summary::export(self::ITEM, $area, $viewer);
                $this->fail("area '{$area}' must be refused");
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidratearea', $e->errorcode);
            }
        }
        try {
            item_summary::export(0, self::AREA, $viewer);
            $this->fail('item 0 must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('invaliditemid', $e->errorcode);
        }
    }

    public function test_the_template_prints_the_escaped_review_and_nothing_raw(): void {
        global $PAGE;
        $viewer = $this->viewer('/1');
        $this->review($viewer->id, '<script>alert(1)</script>fine', 1700000001);
        $this->flag(review_manager::FLAG, true);
        $this->setUser($viewer);

        $PAGE->set_url('/local/sentientia_ratings/reviews.php');
        $PAGE->set_context(\context_system::instance());
        $html =$PAGE->get_renderer('core')->render_from_template('local_sentientia_ratings/review_list',
            item_summary::export(self::ITEM, self::AREA, $viewer));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;fine', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }

    // The web service.

    public function test_the_web_service_no_longer_files_a_rating_under_a_bizlms_area(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        foreach (['local_courses', 'local_classroom', 'local_program', 'local_learningplan'] as $old) {
            try {
                external\submit_rating::execute(self::ITEM, $old, 4);
                $this->fail("{$old} must be refused: the import files those rows under the Sentientia names");
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidratearea', $e->errorcode, $old);
            }
        }
        foreach (rating_manager::AREAS as $area) {
            $result = external\submit_rating::execute(self::ITEM, $area, 4);
            $this->assertTrue($result['success'], $area);
        }
    }

    // Helpers.

    /**
     * A user with a tenant path (null for none), optionally deleted.
     *
     * @param string|null $path
     * @param bool $deleted
     * @return \stdClass The user record with open_path.
     */
    private function viewer(?string $path, bool $deleted = false): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        if ($deleted) {
            $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
        }
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    private function rate(int $userid, int $rating, int $item = self::ITEM, string $area = self::AREA): void {
        global $DB;
        $DB->insert_record('local_sentientia_ratings', (object) [
            'itemid' => $item, 'ratearea' => $area, 'userid' => $userid, 'rating' => $rating,
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]);
    }

    private function review(int $userid, ?string $text, int $time, int $item = self::ITEM, string $area = self::AREA): void {
        global $DB;
        $DB->insert_record('local_sentientia_ratings_reviews', (object) [
            'itemid' => $item, 'ratearea' => $area, 'userid' => $userid, 'review' => $text,
            'timecreated' => $time, 'timemodified' => $time,
        ]);
    }

    private function react(int $userid, int $status, int $item = self::ITEM, string $area = self::AREA): void {
        global $DB;
        $DB->insert_record('local_sentientia_ratings_reactions', (object) [
            'itemid' => $item, 'ratearea' => $area, 'userid' => $userid, 'likestatus' => $status,
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]);
    }

    /**
     * Put a flag in a known state with a global override row.
     *
     * @param string $key
     * @param bool $on
     * @return void
     */
    private function flag(string $key, bool $on): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        \local_sentientia_platform\feature_flags::set($key, 0, $on, null, 'phpunit', 0);
        // The statics survive resetAfterTest.
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }
}
