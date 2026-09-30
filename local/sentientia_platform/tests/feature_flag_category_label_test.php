<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Switchboard category labels (persona pass 2026-09-30, D11).
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * The Switchboard groups flags by the first dotted segment of their key and
 * prints a heading per group. Two groups had no lang string, so the page showed
 * the raw placeholders [[FLAG_CATEGORY_LIVE]] and [[FLAG_CATEGORY_OTHER]]:
 *
 *   - 'live'  - the nine live.* keys registered by local_sentientia_live;
 *   - 'other' - keys with no dot (sentientia_m365_enabled and
 *               sentientia_whatsapp_content_notifications).
 *
 * The page had a fallback for exactly this, `get_string(id, component, null,
 * true) ?: ucfirst($cat)`, but with lazyload=true get_string() returns a
 * lang_string OBJECT, which is always truthy, so the fallback was dead code.
 *
 * Two things are locked in here:
 *   1. feature_flags::category_label() really falls back (the fallback runs).
 *   2. The next flag registered under a new prefix cannot silently reintroduce
 *      the placeholder: every category that exists in the live registry must
 *      have a label in BOTH the English and the Hindi pack.
 *
 * @package    local_sentientia_platform
 * @category   test
 */
final class feature_flag_category_label_test extends \advanced_testcase {

    /**
     * Load the $string array of one of the plugin's lang packs.
     *
     * Reads the file directly rather than through the string manager, because
     * the string manager falls back to English for a missing Hindi string and
     * would hide exactly the gap this test looks for.
     *
     * @param string $lang 'en' or 'hi'
     * @return array<string, string>
     */
    private function load_pack(string $lang): array {
        global $CFG;
        $string = [];
        $file = $CFG->dirroot . '/local/sentientia_platform/lang/' . $lang
            . '/local_sentientia_platform.php';
        $this->assertFileExists($file);
        include($file);
        return $string;
    }

    /**
     * Every distinct category in the live flag registry.
     *
     * @return string[]
     */
    private function registered_categories(): array {
        $categories = [];
        foreach (feature_flags::all() as $flag) {
            $categories[$flag['category']] = true;
        }
        return array_keys($categories);
    }

    /**
     * The two categories the persona pass found unlabelled now have labels.
     */
    public function test_live_and_other_have_real_labels(): void {
        $this->resetAfterTest();
        $this->assertSame('Live Engagement', feature_flags::category_label('live'));
        $this->assertSame('Other', feature_flags::category_label('other'));
    }

    /**
     * A category that already had a label keeps it (no regression).
     */
    public function test_existing_labels_are_unchanged(): void {
        $this->resetAfterTest();
        $this->assertSame('AI & Automation', feature_flags::category_label('ai'));
        $this->assertSame('Sentientia Platform', feature_flags::category_label('sentientia'));
    }

    /**
     * The label is a plain string. get_string(..., lazyload: true) returns a
     * lang_string object, and handing that to the template is what made the
     * old fallback dead.
     */
    public function test_label_is_a_plain_string(): void {
        $this->resetAfterTest();
        $this->assertIsString(feature_flags::category_label('live'));
        $this->assertIsString(feature_flags::category_label('no_such_category'));
    }

    /**
     * A category with no lang string falls back to ucfirst(), never to the
     * [[placeholder]] the string manager prints for a missing identifier.
     */
    public function test_unknown_category_falls_back_to_ucfirst(): void {
        $this->resetAfterTest();
        $label = feature_flags::category_label('zzz_nolabel');
        $this->assertSame('Zzz_nolabel', $label);
        $this->assertStringNotContainsString('[[', $label);
    }

    /**
     * Every category present in the live registry has an English label.
     */
    public function test_every_registered_category_has_an_english_label(): void {
        $this->resetAfterTest();
        $en = $this->load_pack('en');
        $categories = $this->registered_categories();
        $this->assertNotEmpty($categories, 'The flag registry is empty - nothing to check.');
        foreach ($categories as $category) {
            $this->assertArrayHasKey('flag_category_' . $category, $en,
                "Flag category '$category' has no English label (flag_category_$category). "
                . 'The Switchboard would show a raw placeholder or the ucfirst() fallback.');
        }
    }

    /**
     * Every category present in the live registry has a Hindi label
     * (CLAUDE.md: 100% Hindi parity).
     */
    public function test_every_registered_category_has_a_hindi_label(): void {
        $this->resetAfterTest();
        $hi = $this->load_pack('hi');
        foreach ($this->registered_categories() as $category) {
            $this->assertArrayHasKey('flag_category_' . $category, $hi,
                "Flag category '$category' has no Hindi label (flag_category_$category).");
            $this->assertNotSame('', trim($hi['flag_category_' . $category]));
        }
    }

    /**
     * The two packs declare the same set of category labels, so a label added
     * to one language cannot be forgotten in the other.
     */
    public function test_category_label_keys_match_between_english_and_hindi(): void {
        $this->resetAfterTest();
        $enkeys = array_filter(array_keys($this->load_pack('en')),
            fn($k) => strpos($k, 'flag_category_') === 0);
        $hikeys = array_filter(array_keys($this->load_pack('hi')),
            fn($k) => strpos($k, 'flag_category_') === 0);
        sort($enkeys);
        sort($hikeys);
        $this->assertSame(array_values($enkeys), array_values($hikeys));
    }

    /**
     * The page must go through the helper. If somebody reverts to the inline
     * `get_string(..., null, true) ?: ucfirst()` lookup, the fallback is dead
     * again, so guard the source of the page as well as the helper.
     */
    public function test_switchboard_page_uses_the_helper(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/local/sentientia_platform/admin/switchboard.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('feature_flags::category_label(', $source);
        $this->assertStringNotContainsString("'flag_category_' . \$cat", $source,
            'switchboard.php must not build the flag_category_* identifier itself.');
    }
}
