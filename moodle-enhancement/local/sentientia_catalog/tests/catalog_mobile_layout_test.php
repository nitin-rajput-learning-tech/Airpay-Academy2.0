<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Persona pass 2026-09-30, TRIAGE bundle "Catalog mobile" (D8, D10, D12).
 *
 * The three defects are visual, so the unit-testable part is the contract
 * between the rendered markup and styles.css that produces them. Each test
 * renders the real Mustache template through core's renderer and reads the
 * real stylesheet; nothing is mocked.
 *
 *  - D8: at <=590px an open <details> is a fixed bottom sheet, and the
 *    template hard-codes `open` (desktop needs it, the summary is hidden
 *    there). The sheet therefore covered the bottom quarter of the phone on
 *    load. An inline script right after the element must close it at the same
 *    breakpoint the CSS uses, before DOMContentLoaded.
 *  - D12: the NEW / completed badge and the bookmark heart both sat at
 *    top:8px/right:8px of the card.
 *  - D10: the single mobile category column was as wide as the longest
 *    category name (nowrap), 10px past a 390px viewport once the shell's
 *    16px gutter was added.
 *
 * Vanilla schema, no BizLMS fixture needed: no database access.
 *
 * @coversNothing
 */
final class catalog_mobile_layout_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** Contents of the plugin's real stylesheet. */
    private function css(): string {
        $css = file_get_contents(__DIR__ . '/../styles.css');
        $this->assertNotFalse($css);
        return $css;
    }

    /**
     * Every brace-balanced block whose header (text before the opening brace)
     * begins a line with $header. Anchoring to the line start keeps
     * '.airpay-catalog__card {' from also matching
     * '.airpay-catalog__grid .airpay-catalog__card {'. Returns the block
     * bodies, without the outer braces.
     *
     * @return string[]
     */
    private function blocks(string $css, string $header): array {
        $found = [];
        preg_match_all('/^[ \t]*' . preg_quote($header, '/') . '/m', $css, $matches, PREG_OFFSET_CAPTURE);
        $len = strlen($css);
        foreach ($matches[0] as [$text, $start]) {
            $open = strpos($css, '{', $start);
            $depth = 0;
            for ($i = $open; $i < $len; $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } else if ($css[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $found[] = substr($css, $open + 1, $i - $open - 1);
                        break;
                    }
                }
            }
        }
        return $found;
    }

    /** The one block for a selector; fails when it is missing or ambiguous. */
    private function rule(string $css, string $selector): string {
        $blocks = $this->blocks($css, $selector . ' {');
        $this->assertCount(1, $blocks, "styles.css must define exactly one '$selector' rule");
        return $blocks[0];
    }

    /** @return string the catalog template rendered with one course on the page. */
    private function render_catalog(): string {
        global $OUTPUT;
        $card = [
            'id' => 7, 'fullname' => 'Phishing Awareness Training', 'shortname' => 'CS03',
            'summary' => 'Spot and report phishing.', 'categoryname' => 'Compliance',
            'enrolled_count' => 3, 'level' => 'Beginner', 'level_class' => 'beginner', 'has_level' => true,
            'type' => 'E-Learning', 'is_enrolled' => false, 'is_completed' => false, 'is_new' => true,
            'is_borrowed' => false, 'is_bookmarked' => false, 'viewurl' => '#', 'detailurl' => '#',
            'has_image' => false, 'imageurl' => '', 'thumb_variant' => 1,
        ];
        return $OUTPUT->render_from_template('local_sentientia_catalog/catalog', [
            'searchurl' => '#', 'baseurl' => '#', 'search_query' => '', 'has_search' => false,
            'has_in_progress' => false, 'has_trending' => false, 'has_new_courses' => false,
            'has_categories' => false, 'total' => 1, 'has_active_filters_badge' => false,
            'sort_options' => [['url' => '#', 'label' => 'Popular', 'is_active' => true]],
            'has_courses' => true, 'courses' => [$card], 'has_more' => false,
        ]);
    }

    // ---------------------------------------------------------------- D8

    public function test_filter_sheet_keeps_open_for_desktop_and_is_closed_on_mobile_before_domcontentloaded(): void {
        $html = $this->render_catalog();

        // Desktop needs `open`: display:contents on a closed <details> hides
        // the filters and the summary is display:none there.
        $this->assertMatchesRegularExpression(
            '/<details class="airpay-catalog__filter-details" id="ap-catalog-filter-details" open>/', $html);

        $detailsend = strpos($html, '</details>');
        $script = strpos($html, "getElementById('ap-catalog-filter-details')");
        $this->assertNotFalse($detailsend);
        $this->assertNotFalse($script, 'the closing script must look the disclosure up by its id');
        $this->assertGreaterThan($detailsend, $script, 'the script must follow the element it closes');

        // It must not wait for DOMContentLoaded (the catalog script does), or
        // the sheet is painted open first and then snaps shut.
        $this->assertLessThan(strpos($html, 'DOMContentLoaded'), $script);

        $this->assertStringContainsString("window.matchMedia('(max-width: 590px)')", $html);
        $this->assertStringContainsString('sheet.open = !mobile.matches', $html);
        // Rotating a tablet or resizing a window across the breakpoint re-syncs the state.
        $this->assertStringContainsString("addEventListener('change', sync)", $html);
    }

    public function test_script_breakpoint_is_the_one_the_bottom_sheet_css_uses(): void {
        $html = $this->render_catalog();
        $this->assertSame(1, preg_match(
            "/matchMedia\\('\\(max-width: (\\d+)px\\)'\\)/", $html, $m), 'script breakpoint not found');

        // The media block that turns an open <details> into a fixed sheet.
        $sheetblocks = [];
        foreach ([590, 768, 480, 380] as $px) {
            foreach ($this->blocks($this->css(), "@media (max-width: {$px}px)") as $body) {
                if (strpos($body, '.airpay-catalog__filter-details[open] .airpay-catalog__filters') !== false) {
                    $sheetblocks[] = $px;
                }
            }
        }
        $this->assertSame([(int) $m[1]], $sheetblocks,
            'the script and the sheet CSS must switch at the same width');
    }

    // ---------------------------------------------------------------- D12

    public function test_badges_are_moved_clear_of_the_bookmark_heart(): void {
        global $OUTPUT;
        $base = [
            'id' => 9, 'fullname' => 'Brand EMI', 'shortname' => 'PG08', 'summary' => '',
            'categoryname' => 'Payments', 'enrolled_count' => 1, 'has_level' => false, 'type' => 'E-Learning',
            'is_enrolled' => false, 'is_borrowed' => false, 'is_bookmarked' => false, 'viewurl' => '#',
            'detailurl' => '#', 'has_image' => false, 'thumb_variant' => 0,
        ];
        $new = $OUTPUT->render_from_template('local_sentientia_catalog/course_card',
            $base + ['is_new' => true, 'is_completed' => false]);
        $done = $OUTPUT->render_from_template('local_sentientia_catalog/course_card',
            $base + ['is_new' => false, 'is_completed' => true]);

        $this->assertStringContainsString(
            'airpay-catalog__badge airpay-catalog__badge--new airpay-catalog__badge--beside-heart', $new);
        $this->assertStringContainsString(
            'airpay-catalog__badge airpay-catalog__badge--done airpay-catalog__badge--beside-heart', $done);
        // The heart is still there, in the same corner as before.
        $this->assertStringContainsString('class="airpay-catalog__bookmark"', $new);

        $css = $this->css();
        $offset = $this->rule($css, '.airpay-catalog__badge--beside-heart');
        $this->assertStringContainsString('--airpay-catalog-heart-size', $offset);
        $this->assertMatchesRegularExpression('/right:\s*calc\(/', $offset);

        // Heart width and badge offset read one variable, so they cannot drift.
        $heart = $this->rule($css, '.airpay-catalog__bookmark');
        $this->assertStringContainsString('width: var(--airpay-catalog-heart-size', $heart);
        $this->assertStringContainsString('height: var(--airpay-catalog-heart-size', $heart);
        $this->assertStringContainsString('--airpay-catalog-heart-size: 30px',
            $this->rule($css, '.airpay-catalog__card'));

        // The modifier must come after the base badge rule, or it loses on source order.
        $this->assertGreaterThan(
            strpos($css, '.airpay-catalog__badge {'), strpos($css, '.airpay-catalog__badge--beside-heart {'));
    }

    public function test_public_storefront_badge_keeps_its_corner(): void {
        // public.php renders the same badge with no heart, so it must not take the modifier.
        $this->assertStringNotContainsString('beside-heart', file_get_contents(__DIR__ . '/../public.php'));
        $base = $this->rule($this->css(), '.airpay-catalog__badge');
        $this->assertMatchesRegularExpression('/right:\s*var\(--ap-space-2\)/', $base);
    }

    // ---------------------------------------------------------------- D10

    public function test_category_tile_can_shrink_below_its_nowrap_name(): void {
        $css = $this->css();
        $this->assertMatchesRegularExpression('/min-width:\s*0\s*;/',
            $this->rule($css, '.airpay-catalog__category-card'));

        // No breakpoint may size the category tracks with a bare 1fr (minmax(auto, 1fr)).
        foreach ([768, 590] as $px) {
            $found = false;
            foreach ($this->blocks($css, "@media (max-width: {$px}px)") as $body) {
                if (preg_match('/\.airpay-catalog__categories\s*\{([^}]*)\}/', $body, $m)) {
                    $found = true;
                    $this->assertMatchesRegularExpression('/grid-template-columns:[^;]*minmax\(0,\s*1fr\)/', $m[1],
                        "category tracks at <= {$px}px must be minmax(0, 1fr)");
                    $this->assertDoesNotMatchRegularExpression('/grid-template-columns:\s*(repeat\(2,\s*)?1fr/', $m[1]);
                }
            }
            $this->assertTrue($found, "no .airpay-catalog__categories rule at <= {$px}px");
        }
    }

    public function test_category_name_wraps_on_phones(): void {
        $wrapped = false;
        foreach ($this->blocks($this->css(), '@media (max-width: 590px)') as $body) {
            if (preg_match('/\.airpay-catalog__category-card strong\s*\{([^}]*)\}/', $body, $m)) {
                $wrapped = true;
                $this->assertMatchesRegularExpression('/white-space:\s*normal/', $m[1]);
                $this->assertMatchesRegularExpression('/overflow-wrap:\s*anywhere/', $m[1]);
            }
        }
        $this->assertTrue($wrapped, 'a phone must be able to read the whole category name');
        // Desktop keeps the ellipsis the tile always had: the top-level rule is
        // the first one in the file, the phone override above sits in a media block.
        $top = $this->blocks($this->css(), '.airpay-catalog__category-card strong {')[0];
        $this->assertStringContainsString('white-space: nowrap', $top);
        $this->assertStringContainsString('text-overflow: ellipsis', $top);
    }

    public function test_catalog_version_was_bumped_past_the_adr031_release(): void {
        $plugin = new \stdClass();
        require(__DIR__ . '/../version.php');
        $this->assertGreaterThan(2026092500, $plugin->version);
    }
}
