<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Every full-page shell layout prints the footer partial once, and nothing else prints the end-of-body output.
 *
 * Surface walk 2026-11-18 (checklist V4): the admin-layout pages (drawers.mustache) had no footer, while the
 * columns2 and course pages had one. The footer partial prints standard_footer_html, standard_end_of_body_html and
 * debug_footer_html, so a layout that includes it must not print standard_end_of_body_html a second time, or the
 * page JavaScript loads twice.
 *
 * These tests pin the template sources. The rendered pages are checked by the visual pass.
 *
 * @package    theme_sentientia
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class layout_footer_template_test extends \advanced_testcase {

    /** @var string The footer partial include, as the layouts write it. */
    private const FOOTER_PARTIAL = '{{> theme_sentientia/footer }}';

    /**
     * A theme template's source without its Mustache comments, with LF line endings.
     *
     * @param string $name template name under templates/, without .mustache
     * @return string
     */
    private function template(string $name): string {
        $path = dirname(__DIR__) . '/templates/' . $name . '.mustache';
        $this->assertFileExists($path);
        $source = str_replace("\r\n", "\n", (string) file_get_contents($path));
        // Comments end at the first closing pair of braces, as Mustache itself reads them.
        return (string) preg_replace('/\{\{!.*?\}\}/s', '', $source);
    }

    /**
     * The full-page shell layouts that must carry the footer.
     *
     * @return array
     */
    public static function layout_provider(): array {
        return [
            'drawers (admin layout)' => ['drawers'],
            'columns2 (standard layout)' => ['columns2'],
            'course' => ['course'],
            'course_editing' => ['course_editing'],
        ];
    }

    /**
     * Each layout includes the footer partial exactly once.
     *
     * @dataProvider layout_provider
     * @param string $name template name
     */
    public function test_layout_includes_the_footer_partial_once(string $name): void {
        $this->assertSame(1, substr_count($this->template($name), self::FOOTER_PARTIAL),
            "$name.mustache must include the footer partial exactly once");
    }

    /**
     * No layout prints standard_end_of_body_html itself: the footer partial does, and twice loads the page JS twice.
     *
     * @dataProvider layout_provider
     * @param string $name template name
     */
    public function test_layout_does_not_print_the_end_of_body_output_itself(string $name): void {
        $this->assertStringNotContainsString('standard_end_of_body_html', $this->template($name),
            "$name.mustache must leave standard_end_of_body_html to the footer partial");
    }

    /**
     * The footer partial is the one that prints the end-of-body output, so the layouts above still load their JS.
     */
    public function test_footer_partial_prints_the_end_of_body_output(): void {
        $footer = $this->template('footer');
        $this->assertSame(1, substr_count($footer, '{{{ output.standard_end_of_body_html }}}'));
        $this->assertSame(1, substr_count($footer, '{{{ output.standard_footer_html }}}'));
    }

    /**
     * In drawers, the footer is the last element of the content scroller: it comes before the shell end.
     */
    public function test_drawers_footer_comes_before_the_shell_end(): void {
        $template = $this->template('drawers');
        $footer = strpos($template, self::FOOTER_PARTIAL);
        $shellend = strpos($template, '{{{ output.airpay_shell_end }}}');
        $this->assertNotFalse($footer);
        $this->assertNotFalse($shellend);
        $this->assertLessThan($shellend, $footer, 'the footer must be printed inside the shell, before airpay_shell_end');
    }
}
