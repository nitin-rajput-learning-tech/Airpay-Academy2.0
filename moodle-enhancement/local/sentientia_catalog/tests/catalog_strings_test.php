<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * V8-B (Phase C, 2026-11-19): the catalogue page in Hindi.
 *
 * The Hindi catalogue at /local/sentientia_catalog/index.php showed the
 * course type chip ("E-LEARNING"), the category count ("184 सभी कोर्स", which
 * read "184 All Courses" in English), "Filters & Sort", "Showing x-y of z",
 * "% complete", the NEW badge and several aria-labels in English, because they
 * were literals in the templates and in catalog_manager::format_course().
 *
 * Each test renders the real template through core's renderer and compares it
 * with get_string(), so it holds in any language. No database access.
 *
 * @coversNothing
 */
final class catalog_strings_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** @return array a course card context, as format_course() builds it. */
    private function card(array $override = []): array {
        return $override + [
            'id' => 7, 'fullname' => 'Phishing Awareness Training', 'shortname' => 'CS03',
            'summary' => '', 'categoryname' => 'Compliance', 'enrolled_count' => 3,
            'level' => '', 'level_class' => '', 'has_level' => false, 'type' => 'E-Learning',
            'is_enrolled' => false, 'is_completed' => false, 'is_new' => false,
            'is_borrowed' => false, 'is_bookmarked' => false, 'viewurl' => '#', 'detailurl' => '#',
            'has_image' => false, 'imageurl' => '', 'thumb_variant' => 1,
        ];
    }

    /** @return string the catalog page with every section V8-B touched on it. */
    private function render_catalog(): string {
        global $OUTPUT;
        $inprogress = $this->card(['id' => 8, 'has_progress' => true, 'progress' => 40, 'progress_offset' => 56.55]);
        return $OUTPUT->render_from_template('local_sentientia_catalog/catalog', [
            'searchurl' => '#', 'baseurl' => '#', 'search_query' => '', 'has_search' => false,
            'has_in_progress' => true, 'in_progress' => [$inprogress],
            'has_trending' => false, 'has_new_courses' => false,
            'has_categories' => true, 'categories' => [['id' => 3, 'name' => 'Compliance', 'course_count' => 184]],
            'total' => 30, 'has_active_filters_badge' => true, 'filter_count' => 2, 'active_filters' => [],
            'sort_options' => [['url' => '#', 'label' => 'Popular', 'is_active' => true]],
            'has_courses' => true, 'courses' => [$this->card()],
            'has_more' => true, 'showing_from' => 1, 'showing_to' => 12, 'current_page' => 1, 'sort' => 'popular',
        ]);
    }

    public function test_catalog_page_text_comes_from_strings(): void {
        $html = $this->render_catalog();
        $str = fn(string $key, $a = null) => get_string($key, 'local_sentientia_catalog', $a);

        $this->assertStringContainsString($str('mycourses_percentcomplete', 40), $html);
        // The count and its noun are one string: "184 courses", not "184 All Courses".
        $this->assertStringContainsString('<span>' . $str('category_coursecount', 184) . '</span>', $html);
        $this->assertStringContainsString('<span>' . $str('filters_sort') . '</span>', $html);
        $this->assertStringContainsString('aria-label="' . $str('filters_active_aria', 2) . '"', $html);
        $this->assertStringContainsString('aria-label="' . $str('filters_group_aria') . '"', $html);
        $this->assertStringContainsString(
            $str('mycourses_showing', (object) ['from' => 1, 'to' => 12, 'total' => 30]), $html);
        $this->assertStringContainsString('aria-label="' . $str('public_scrollleft') . '"', $html);
        $this->assertStringContainsString('aria-label="' . $str('public_scrollright') . '"', $html);
    }

    public function test_course_card_badges_and_labels_come_from_strings(): void {
        global $OUTPUT;
        $str = fn(string $key, $a = null) => get_string($key, 'local_sentientia_catalog', $a);

        $new = $OUTPUT->render_from_template('local_sentientia_catalog/course_card', $this->card(['is_new' => true]));
        $this->assertStringContainsString(
            'aria-label="' . $str('badge_new_aria') . '">' . $str('badge_new') . '</span>', $new);
        $this->assertStringContainsString(
            'aria-label="' . $str('card_viewdetails_aria', 'Phishing Awareness Training') . '"', $new);
        $this->assertStringContainsString('aria-label="' . $str('card_enrolled_aria', 3) . '"', $new);

        $done = $OUTPUT->render_from_template('local_sentientia_catalog/course_card',
            $this->card(['is_completed' => true]));
        $this->assertStringContainsString('aria-label="' . $str('mycourses_completed') . '"', $done);
    }

    public function test_no_english_literals_are_left_in_the_touched_places(): void {
        $catalog = file_get_contents(__DIR__ . '/../templates/catalog.mustache');
        $card = file_get_contents(__DIR__ . '/../templates/course_card.mustache');
        foreach (['aria-label="Scroll left"', 'aria-label="Scroll right"', '% complete', 'Filters &amp; Sort',
                '>Showing ', ' active"', 'aria-label="Active filters and sort"'] as $literal) {
            $this->assertStringNotContainsString($literal, $catalog, "catalog.mustache still prints '$literal'");
        }
        foreach (['>NEW<', 'aria-label="New course"', 'aria-label="Completed"', 'learners enrolled',
                'View details for'] as $literal) {
            $this->assertStringNotContainsString($literal, $card, "course_card.mustache still prints '$literal'");
        }

        // The type chip: format_course() reads the labels from strings, no English literal is left.
        $manager = file_get_contents(__DIR__ . '/../classes/catalog_manager.php');
        $this->assertStringNotContainsString("'E-Learning'", $manager);
        $this->assertStringNotContainsString("'Classroom'", $manager);
        $this->assertStringContainsString("get_string('coursetype_elearning', 'local_sentientia_catalog')", $manager);
        $this->assertStringContainsString("get_string('coursetype_classroom', 'local_sentientia_catalog')", $manager);
    }
}
