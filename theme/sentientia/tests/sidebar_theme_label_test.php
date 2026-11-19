<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * The sidebar dark-mode toggle label follows the user's language.
 *
 * Phase B verification 2026-11-18 (PB-5): under ?lang=hi the sidebar toggle on every shell page read
 * "Dark Mode". sidebar.mustache printed the localised string, but the shell JS in
 * core_renderer::airpay_shell_end() overwrote it on load with English literals ('Dark Mode' /
 * 'Light Mode'). The template now carries both localised labels in data-label-dark and
 * data-label-light, and the JS swaps between those.
 *
 * @package    theme_sentientia
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sidebar_theme_label_test extends \advanced_testcase {

    /**
     * A theme file's source with LF line endings.
     *
     * @param string $relpath path under the theme directory
     * @return string
     */
    private function source(string $relpath): string {
        $path = dirname(__DIR__) . '/' . $relpath;
        $this->assertFileExists($path);
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /**
     * The shell JS no longer writes English labels and reads the localised data attributes instead.
     */
    public function test_shell_js_has_no_english_labels(): void {
        $code = $this->source('classes/output/core_renderer.php');
        $this->assertStringNotContainsString("'Light Mode'", $code);
        $this->assertStringNotContainsString("'Dark Mode'", $code);
        $this->assertStringContainsString("darkToggle.getAttribute('data-label-dark')", $code);
        $this->assertStringContainsString("darkToggle.getAttribute('data-label-light')", $code);
    }

    /**
     * The sidebar toggle carries both labels as theme strings.
     */
    public function test_sidebar_template_carries_both_labels(): void {
        $template = $this->source('templates/sidebar.mustache');
        // Comments end at the first closing pair of braces, as Mustache itself reads them.
        $template = (string) preg_replace('/\{\{!.*?\}\}/s', '', $template);
        $this->assertStringContainsString(
            'data-label-dark="{{#cleanstr}}dash_dark_mode_label, theme_sentientia{{/cleanstr}}"', $template);
        $this->assertStringContainsString(
            'data-label-light="{{#cleanstr}}dash_light_mode_label, theme_sentientia{{/cleanstr}}"', $template);
    }

    /**
     * Rendered, the toggle's attributes and visible label hold the strings of the current language.
     */
    public function test_rendered_sidebar_labels(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->force_theme('sentientia');
        $renderer = $PAGE->get_renderer('core');

        $html = $renderer->render_from_template('theme_sentientia/sidebar', [
            'wwwroot' => 'https://example.com',
            'collapsed' => false,
            'sitename' => 'Academy',
            'navitems' => [],
            'userfullname' => 'Test User',
            'profileurl' => '#',
            'logouturl' => '#',
        ]);

        $dark = get_string('dash_dark_mode_label', 'theme_sentientia');
        $light = get_string('dash_light_mode_label', 'theme_sentientia');
        $this->assertStringContainsString('data-label-dark="' . s($dark) . '"', $html);
        $this->assertStringContainsString('data-label-light="' . s($light) . '"', $html);
        $this->assertStringContainsString('<span class="ap-sidebar__theme-label">' . $dark . '</span>', $html);
    }

    /**
     * Both labels exist in English and Hindi, and the Hindi ones are translated.
     */
    public function test_labels_have_hindi_values(): void {
        $en = $this->strings('en');
        $hi = $this->strings('hi');
        foreach (['dash_dark_mode_label', 'dash_light_mode_label'] as $key) {
            $this->assertArrayHasKey($key, $en, "lang/en lacks {$key}");
            $this->assertArrayHasKey($key, $hi, "lang/hi lacks {$key}");
            $this->assertNotSame($en[$key], $hi[$key], "lang/hi {$key} is still English");
        }
    }

    /**
     * The theme's string array for one language, read from its lang file.
     *
     * @param string $lang language code
     * @return array<string, string>
     */
    private function strings(string $lang): array {
        $path = dirname(__DIR__) . '/lang/' . $lang . '/theme_sentientia.php';
        $this->assertFileExists($path);
        $string = [];
        include($path);
        return $string;
    }
}
