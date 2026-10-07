<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * Owner decision CRS-11 (2026-10-07): the course-page rating stars sit behind the flag sentientia.ratings.widget.
 *
 * The theme used to render the stars as interactive buttons and never initialised the AMD widget, so a learner saw
 * focusable controls that did nothing. With the flag OFF (the default) the stars are read-only, one image with a text
 * alternative; ON, a signed-in learner who may rate gets interactive stars and the widget is loaded exactly once for the
 * page, however many places show stars. A guest, or someone without the rate capability, never gets a control that would
 * only fail.
 *
 * @package    local_sentientia_ratings
 * @category   test
 * @covers     \local_sentientia_ratings\rating_manager
 * @group      local_sentientia_ratings
 */
final class widget_flag_test extends \advanced_testcase {

    private const AREA = 'local_sentientia_courses';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        rating_manager::reset_widget_request();
    }

    private function flag(bool $on): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        \local_sentientia_platform\feature_flags::set(rating_manager::FLAG_WIDGET, 0, $on, null, 'phpunit', 0);
        // The statics survive resetAfterTest.
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    private function amd_calls(): string {
        global $PAGE;
        $reflection = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $reflection->setAccessible(true);
        return implode("\n", (array) $reflection->getValue($PAGE->requires));
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        $this->assertArrayHasKey(rating_manager::FLAG_WIDGET, $registry);
        $this->assertFalse($registry[rating_manager::FLAG_WIDGET]['default'], 'ships OFF');
        $this->assertSame('sentientia.ratings.widget', rating_manager::FLAG_WIDGET);
    }

    public function test_off_renders_read_only_stars_with_a_text_alternative_and_loads_no_script(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $html = rating_manager::render_for_viewer(7, self::AREA);

        $this->assertStringNotContainsString('<button', $html, 'no focusable control that does nothing');
        $this->assertStringNotContainsString('data-airpay-rating', $html, 'the widget has nothing to bind to');
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('aria-label="' . s(get_string('noratings', 'local_sentientia_ratings')) . '"', $html);
        $this->assertStringNotContainsString('rating_widget', $this->amd_calls(), 'OFF loads no script');
        $this->assertFalse(rating_manager::widget_enabled());
    }

    public function test_the_read_only_text_alternative_carries_the_average(): void {
        $learner = $this->getDataGenerator()->create_user();
        rating_manager::submit_rating(7, self::AREA, (int) $learner->id, 4);
        $this->setUser($this->getDataGenerator()->create_user());

        $html = rating_manager::render_for_viewer(7, self::AREA);

        $label = get_string('averagesummary', 'local_sentientia_ratings', (object) ['average' => 4.0, 'count' => 1]);
        $this->assertStringContainsString('aria-label="' . s($label) . '"', $html);
    }

    public function test_on_renders_interactive_stars_and_loads_the_widget_once(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->flag(true);

        $header = rating_manager::render_for_viewer(7, self::AREA);
        $drawer = rating_manager::render_for_viewer(7, self::AREA);

        foreach ([$header, $drawer] as $html) {
            $this->assertStringContainsString('<button type="button" class="airpay-rating__star', $html);
            $this->assertStringContainsString('data-airpay-rating', $html);
            $this->assertStringContainsString('data-itemid="7"', $html);
            $this->assertStringNotContainsString('role="img"', $html);
        }
        $this->assertSame(1, substr_count($this->amd_calls(), "require(['local_sentientia_ratings/rating_widget']"),
            'one init() binds every widget on the page; a second would bind each star twice');
        $this->assertTrue(rating_manager::widget_enabled());
    }

    public function test_a_guest_never_gets_a_control_even_with_the_flag_on(): void {
        $this->flag(true);
        $this->setGuestUser();

        $html = rating_manager::render_for_viewer(7, self::AREA);

        $this->assertFalse(rating_manager::widget_enabled());
        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringNotContainsString('rating_widget', $this->amd_calls());
    }

    public function test_a_learner_without_the_rate_capability_gets_read_only_stars(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->flag(true);
        // Prohibit the capability at system level for the authenticated-user role every signed-in user inherits.
        assign_capability('local/sentientia_ratings:rate', CAP_PROHIBIT, (int) $CFG->defaultuserroleid,
            \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $this->assertFalse(rating_manager::widget_enabled(), 'the submit web service would refuse a click');
        $this->assertStringNotContainsString('<button', rating_manager::render_for_viewer(7, self::AREA));
    }

    public function test_the_render_method_itself_keeps_its_interactive_default(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $html = rating_manager::render(7, self::AREA);

        $this->assertStringContainsString('data-airpay-rating', $html, 'explicit callers of render() are unchanged');
    }

    private function reaction_flag(bool $on): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        \local_sentientia_platform\feature_flags::set(reaction_manager::FLAG, 0, $on, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    private function react(int $userid, int $status, int $item = 7): void {
        global $DB;
        $DB->insert_record('local_sentientia_ratings_reactions', (object) [
            'itemid' => $item, 'ratearea' => self::AREA, 'userid' => $userid, 'likestatus' => $status,
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]);
    }

    public function test_owner_decision_crs12_the_reaction_counts_sit_beside_the_stars_only_behind_their_flag(): void {
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $c = $this->getDataGenerator()->create_user();
        $this->react((int) $a->id, 1);
        $this->react((int) $b->id, 1);
        $this->react((int) $c->id, 2);
        // A value that is neither a like nor a dislike is carried by the import and never counted.
        $this->react((int) $this->getDataGenerator()->create_user()->id, 5);
        $this->setUser($this->getDataGenerator()->create_user());

        // OFF (the default): nothing.
        $this->assertStringNotContainsString('airpay-rating__reactions', rating_manager::render_for_viewer(7, self::AREA));
        $this->assertSame('', rating_manager::render_reactions(7, self::AREA));

        // ON: the counts, with a text alternative, in the same element as the stars.
        $this->reaction_flag(true);
        $html = rating_manager::render_for_viewer(7, self::AREA);
        $this->assertStringContainsString('airpay-rating__reactions', $html);
        $label = get_string('reactionlikes', 'local_sentientia_ratings', 2) . ', '
            . get_string('reactiondislikes', 'local_sentientia_ratings', 1);
        $this->assertStringContainsString('aria-label="' . s($label) . '"', $html);
        $this->assertStringContainsString('fa-thumbs-up', $html);
        $this->assertSame(1, substr_count($html, '<div class="airpay-rating"'), 'one element, the counts are inside it');
        $this->assertStringEndsWith('</span></div>', $html);

        // Nothing liked or disliked: nothing shown, whatever the flag says.
        $this->assertSame('', rating_manager::render_reactions(8, self::AREA));
    }

    public function test_the_lib_helper_follows_the_flag(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/sentientia_ratings/lib.php');
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertStringNotContainsString('<button', airpay_display_rating(7, self::AREA));
        $this->flag(true);
        $this->assertStringContainsString('<button', airpay_display_rating(7, self::AREA));
    }
}
