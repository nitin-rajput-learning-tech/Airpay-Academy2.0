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

namespace theme_sentientia;

/**
 * Tests for the course page in editing mode (course author, editingteacher).
 *
 * Visual pass 2026-10-14, finding 2 and 3. For a course author edit mode did not survive a page load and
 * the course editor threw two console errors with no editing controls. Three causes, all in this theme:
 *
 * 1. core_renderer::course_context_header_settings_menu() set $USER->editing = 0 for everybody without
 *    moodle/course:view (a manager capability, an editingteacher does not hold it). It ran in the middle of
 *    the request: the layout and the body class were already built in editing mode, the course content
 *    that follows the header rendered in viewing mode, and the next load found editing off.
 * 2. layout/course.php rendered the edit switch for every course page, but the editing template does not
 *    print it (its navbar partial renders its own), so core/edit_switch was initialised on an id that is not in
 *    the page: "Cannot read properties of null (reading 'addEventListener')".
 * 3. course_full_header.mustache dropped the header actions. On a course page in editing mode core adds the
 *    Bulk edit button there and queues its script: "Reactive components needs a main DOM element".
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_editing_mode_test extends \advanced_testcase {

    /**
     * Read a file of the theme.
     *
     * @param string $relative path below theme/sentientia
     * @return string the file content
     */
    private function theme_file(string $relative): string {
        $path = dirname(__DIR__) . '/' . $relative;
        $this->assertFileExists($path, $relative . ' is missing');
        return (string) file_get_contents($path);
    }

    /**
     * The source of a method without its comment lines.
     *
     * @param string $class
     * @param string $method
     * @return string
     */
    private function method_code(string $class, string $method): string {
        $reflection = new \ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());
        $body = array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1);
        $code = array_filter($body, static function (string $line): bool {
            return strpos(ltrim($line), '//') !== 0;
        });
        return implode('', $code);
    }

    /**
     * The course header menu must not change the edit mode of the user.
     */
    public function test_course_header_menu_does_not_clear_edit_mode(): void {
        $code = $this->method_code(\theme_sentientia\output\core_renderer::class, 'course_context_header_settings_menu');
        $this->assertDoesNotMatchRegularExpression(
            '/\$USER->editing\s*=[^=]/',
            $code,
            'course_context_header_settings_menu() must not assign $USER->editing: it runs after the layout was built'
        );
    }

    /**
     * An editing teacher keeps edit mode through the course header render.
     */
    public function test_editing_teacher_keeps_edit_mode(): void {
        global $PAGE, $USER, $COURSE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $USER->editing = 1;

        $COURSE = $course;
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_course($course);
        $PAGE->force_theme('sentientia');
        $renderer = $PAGE->get_renderer('core');
        if (!($renderer instanceof \theme_sentientia\output\core_renderer)) {
            $this->markTestSkipped('The theme core_renderer is not in use');
        }

        // Precondition: an editingteacher does not hold the capability the old code asked for.
        $this->assertFalse(has_capability('moodle/course:view', \context_course::instance($course->id)));
        $this->assertTrue($PAGE->user_is_editing());

        try {
            $renderer->course_context_header_settings_menu();
        } catch (\Throwable $e) {
            // The tail of the method needs the BizLMS tenant plugins of the deployment.
            $this->markTestSkipped('Not runnable here: ' . $e->getMessage());
        }

        $this->assertEquals(1, $USER->editing);
        $this->assertTrue($PAGE->user_is_editing());
    }

    /**
     * The editing layout renders the edit switch once, through its navbar. The layout variable is for the
     * viewing template only: rendering it for the editing template queued an init for a missing element.
     */
    public function test_editing_layout_does_not_render_an_unprinted_edit_switch(): void {
        $layout = $this->theme_file('layout/course.php');
        $this->assertStringContainsString(
            "if (!\$apcourseediting && method_exists(\$OUTPUT, 'edit_switch')) {",
            $layout
        );

        // The editing template never prints the layout variable, the viewing template does.
        $editing = (string) preg_replace('/\{\{!.*?\}\}/s', '', $this->theme_file('templates/course_editing.mustache'));
        $viewing = (string) preg_replace('/\{\{!.*?\}\}/s', '', $this->theme_file('templates/course.mustache'));
        $this->assertDoesNotMatchRegularExpression('/\{\{[#{]?\s*editswitch\s*\}\}/', $editing);
        $this->assertMatchesRegularExpression('/\{\{\{\s*editswitch\s*\}\}\}/', $viewing);

        // The editing template gets its switch from the navbar partial.
        $this->assertStringContainsString('{{> theme_sentientia/navbar }}', $editing);
        $navbar = $this->theme_file('templates/navbar.mustache');
        $this->assertStringContainsString('{{{ output.edit_switch }}}', $navbar);
    }

    /**
     * The course page header prints the header actions (the Bulk edit button of editing mode).
     */
    public function test_course_header_prints_header_actions(): void {
        $template = (string) preg_replace('/\{\{!.*?\}\}/s', '', $this->theme_file('templates/course_full_header.mustache'));
        $this->assertMatchesRegularExpression(
            '/data-region="header-actions-container">\s*\{\{#headeractions\}\}\s*<div class="header-action[^"]*">\{\{\{\.\}\}\}<\/div>\s*\{\{\/headeractions\}\}/',
            $template
        );

        // The renderer passes them for the course header as well as for the plain one.
        $code = $this->method_code(\theme_sentientia\output\core_renderer::class, 'full_header');
        $this->assertStringContainsString('$header->headeractions = $this->page->get_header_actions();', $code);
        $this->assertStringContainsString("'theme_sentientia/course_full_header'", $code);
    }
}
