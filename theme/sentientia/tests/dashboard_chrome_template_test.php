<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * The dashboard's own shell prints the same chrome as the shell on every other page.
 *
 * Surface walk 2026-11-18 (checklist V4): /my/ had no Notifications bell, no footer and no mobile
 * bottom nav, while the columns2 pages had all three. The dashboard paints its own shell in
 * templates/dashboard.mustache instead of calling core_renderer::airpay_shell_start() and
 * airpay_shell_end(), and:
 *
 *   - its topbar's right-hand side was empty (the shell topbar prints the bell and Messages link);
 *   - it never included the footer partial, and a stray closing div closed main early;
 *   - core prints the before_footer_html_generation hook output (the mobile bottom nav of
 *     local_sentientia_platform, the assistant button of local_sentientia_assistant) straight after
 *     the page's main content, which this layout keeps in the hidden #page-content.
 *
 * These tests pin the template source. The rendered page is checked by the visual pass.
 *
 * @package    theme_sentientia
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard_chrome_template_test extends \advanced_testcase {

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
     * The dashboard template's shell branch: from {{#use_shell}} to the matching {{/use_shell}}.
     *
     * @return string
     */
    private function shell_branch(): string {
        $template = $this->template('dashboard');
        $start = strpos($template, '{{#use_shell}}');
        $this->assertNotFalse($start);
        $end = strpos($template, '{{/use_shell}}', $start);
        $this->assertNotFalse($end);
        return substr($template, $start, $end - $start);
    }

    /**
     * The inside of the dashboard's main element (.ap-shell__content).
     *
     * @return string
     */
    private function main_element(): string {
        $branch = $this->shell_branch();
        $start = strpos($branch, '<main class="ap-shell__content"');
        $this->assertNotFalse($start);
        $end = strpos($branch, '</main>', $start);
        $this->assertNotFalse($end);
        return substr($branch, $start, $end - $start);
    }

    /**
     * The topbar carries the Notifications bell and the Messages link, once, as the shell topbar does.
     *
     * Surface walk V20 (2026-11-18): the bell is the renderer's own (core_renderer::topbar_notifications_link(),
     * with the unread-count badge), not a hard-coded link without a count.
     */
    public function test_topbar_has_the_notifications_bell_and_the_messages_link(): void {
        $branch = $this->shell_branch();
        $start = strpos($branch, 'class="ap-topbar__right"');
        $this->assertNotFalse($start);
        $end = strpos($branch, '</header>', $start);
        $this->assertNotFalse($end);
        $right = substr($branch, $start, $end - $start);

        $this->assertStringContainsString('{{{ topbar.notificationhtml }}}', $right);
        $this->assertStringContainsString('{{{ topbar.messageslink }}}', $right);
        // One bell on the page: the dashboard does not print the shell topbar as well.
        $this->assertSame(1, substr_count($branch, 'topbar.notificationhtml'));
        $this->assertSame(1, substr_count($branch, 'topbar.messageslink'));
        // The hard-coded bell (no unread count) is gone from the template.
        $template = $this->template('dashboard');
        $this->assertStringNotContainsString('fa fa-bell', $template);
        $this->assertStringNotContainsString('/message/output/popup/notifications.php', $template);

        // The same destination as the shell topbar on every other page: the renderer builds the bell.
        $renderer = (string) file_get_contents(dirname(__DIR__) . '/classes/output/core_renderer.php');
        $this->assertStringContainsString("'/message/output/popup/notifications.php'", $renderer);
    }

    /**
     * The layout hands the template the shell's own (gated) Messages link and Notifications bell.
     */
    public function test_layout_passes_the_gated_messages_link(): void {
        $layout = (string) file_get_contents(dirname(__DIR__) . '/layout/dashboard.php');
        $this->assertMatchesRegularExpression("/'messageslink'\\s*=>/", $layout);
        $this->assertStringContainsString('core_renderer::topbar_messages_link()', $layout);
        $this->assertMatchesRegularExpression("/'notificationhtml'\\s*=>/", $layout);
        $this->assertStringContainsString('core_renderer::topbar_notifications_link()', $layout);
        // The key is no longer hard-wired to an empty string.
        $this->assertDoesNotMatchRegularExpression("/'notificationhtml'\\s*=>\\s*''/", $layout);
    }

    /**
     * The footer partial is the last element of main, as in columns2, and is included once.
     */
    public function test_footer_is_the_last_element_of_main(): void {
        $main = $this->main_element();
        $this->assertSame(1, substr_count($main, '{{> theme_sentientia/footer }}'));
        $this->assertSame('{{> theme_sentientia/footer }}', trim(substr($main, (int) strrpos($main, '{{>'))));
        $this->assertSame(1, substr_count($this->shell_branch(), 'theme_sentientia/footer'));
    }

    /**
     * Every div opened inside main is closed inside it, and none closes an element outside it.
     */
    public function test_main_has_no_stray_closing_div(): void {
        preg_match_all('#<div\b|</div>#', $this->main_element(), $matches);
        $depth = 0;
        foreach ($matches[0] as $tag) {
            $depth += ($tag === '</div>') ? -1 : 1;
            $this->assertGreaterThanOrEqual(0, $depth, 'a closing div inside main closes an element outside it');
        }
        $this->assertSame(0, $depth);
    }

    /**
     * The end-of-body output (the AMD loader and every js_call_amd) comes from the footer partial only.
     *
     * Core replaces every copy of the token, so a second copy in the dashboard template would load the
     * page JavaScript twice; none at all leaves require undefined.
     */
    public function test_end_of_body_html_is_printed_once_through_the_footer(): void {
        // output.: the sidebar script's own JavaScript comments name the token in prose.
        $this->assertSame(0, substr_count($this->shell_branch(), 'output.standard_end_of_body_html'));
        $this->assertSame(1, substr_count($this->template('footer'), 'output.standard_end_of_body_html'));
    }

    /**
     * The hook-printed chrome is moved out of the hidden #page-content.
     */
    public function test_hook_chrome_is_moved_out_of_the_hidden_content(): void {
        $branch = $this->shell_branch();

        // What the script relies on: core's main content, and so the hook output printed right after it,
        // sits in the hidden #page-content.
        $this->assertMatchesRegularExpression(
            '#<div id="page-content"[^>]*display:\s*none[^>]*>\s*\{\{\{ output\.main_content \}\}\}#', $branch);

        $this->assertStringContainsString("document.getElementById('page-content')", $branch);
        $this->assertStringContainsString("querySelectorAll('.airpay-mobile-nav, #airpay-assistant')", $branch);
        $this->assertStringContainsString('insertBefore', $branch);

        // The selectors match what the hook callbacks print.
        $this->assertStringContainsString('<nav class="airpay-mobile-nav"', $this->template('mobile_bottom_nav'));
        global $CFG;
        $assistant = $CFG->dirroot . '/local/sentientia_assistant/templates/chat_bubble.mustache';
        if (file_exists($assistant)) {
            $this->assertStringContainsString('id="airpay-assistant"', (string) file_get_contents($assistant));
        }
    }
}
