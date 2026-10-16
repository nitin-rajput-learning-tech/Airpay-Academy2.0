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
 * Tests for the edit mode switch template (templates/core/editswitch.mustache).
 *
 * The Moodle 5.2 core markup (form-check form-switch, ms-2, me-2, input-group) is Bootstrap 5, which this
 * Bootstrap 4.6 theme does not style, so the switch showed as a bare browser checkbox. The theme override
 * uses Bootstrap 4's custom-control custom-switch, which the theme compiles and already styles in the
 * navbar. These tests pin the markup, and everything the script and the no-JavaScript fallback rely on.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_switch_template_test extends \advanced_testcase {

    /**
     * The template source without its Mustache comments.
     *
     * @return string
     */
    private function template_without_comments(): string {
        $path = dirname(__DIR__) . '/templates/core/editswitch.mustache';
        $this->assertFileExists($path);
        // Comments end at the first closing pair of braces, as Mustache itself reads them.
        return (string) preg_replace('/\{\{!.*?\}\}/s', '', (string) file_get_contents($path));
    }

    /**
     * The markup is Bootstrap 4 (custom-control custom-switch), not the Bootstrap 5 classes.
     */
    public function test_markup_is_the_bootstrap_4_custom_switch(): void {
        $template = $this->template_without_comments();

        $this->assertStringContainsString('custom-control custom-switch', $template);
        $this->assertStringContainsString('class="custom-control-input"', $template);
        $this->assertStringContainsString('custom-control-label', $template);

        foreach (['form-check', 'form-switch', 'input-group', 'ms-2', 'me-2'] as $bs5class) {
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*\b' . preg_quote($bs5class, '/') . '\b[^"]*"/',
                $template,
                $bs5class . ' is Bootstrap 5 and is not styled by this theme'
            );
        }
    }

    /**
     * The label text sits in its own span, so below the md breakpoint only the words are visually
     * hidden: hiding the whole label would clip the switch drawn by its ::before and ::after.
     */
    public function test_label_text_is_hideable_without_hiding_the_switch(): void {
        $template = $this->template_without_comments();
        $this->assertStringContainsString('editmode-switch-form__text', $template);

        $scss = (string) file_get_contents(dirname(__DIR__) . '/scss/moodle/primarynavigation.scss');
        $this->assertStringContainsString('.editmode-switch-form .editmode-switch-form__text', $scss);
        $this->assertStringNotContainsString('.editmode-switch-form label {', $scss);
    }

    /**
     * What core/edit_switch and the no-JavaScript fallback rely on is unchanged.
     */
    public function test_script_and_noscript_contract_is_unchanged(): void {
        $template = $this->template_without_comments();

        // A real form: "<formaction=" (no space) once built no form at all, so the fallback was dead.
        $this->assertStringContainsString('<form action="{{{legacyseturl}}}" method="post"', $template);
        $this->assertStringContainsString('name="setmode"', $template);
        $this->assertStringContainsString('id="{{uniqid}}-editingswitch"', $template);
        $this->assertStringContainsString('data-context="{{{pagecontextid}}}"', $template);
        $this->assertStringContainsString('data-pageurl="{{{pageurl}}}"', $template);
        $this->assertStringContainsString('<input type="hidden" name="sesskey" value="{{{sesskey}}}">', $template);
        $this->assertStringContainsString('<input type="hidden" name="pageurl" value="{{{pageurl}}}">', $template);
        $this->assertStringContainsString('<input type="hidden" name="context" value="{{{pagecontextid}}}">', $template);
        $this->assertStringContainsString('<noscript>', $template);
        $this->assertStringContainsString("editSwitch.init('{{uniqid}}-editingswitch');", $template);
    }

    /**
     * Rendered through the theme, the switch is the Bootstrap 4 custom switch, checked when editing is on.
     */
    public function test_rendered_switch(): void {
        global $PAGE;
        $this->resetAfterTest();

        if (!is_dir(dirname(__DIR__) . '/templates/core')) {
            $this->markTestSkipped('theme_sentientia templates are not available');
        }
        $PAGE->force_theme('sentientia');
        $renderer = $PAGE->get_renderer('core');

        $off = $renderer->render_from_template('core/editswitch', [
            'legacyseturl' => '/editmode.php',
            'pagecontextid' => 42,
            'pageurl' => 'http://example.com/course/view.php?id=2',
            'sesskey' => 'abc123',
        ]);
        $this->assertStringContainsString('custom-control custom-switch', $off);
        $this->assertStringNotContainsString('form-switch', $off);
        $this->assertStringContainsString('<form action="/editmode.php" method="post"', $off);
        $this->assertStringNotContainsString(' checked', $off);
        $this->assertStringNotContainsString('text-primary', $off);

        $on = $renderer->render_from_template('core/editswitch', [
            'legacyseturl' => '/editmode.php',
            'pagecontextid' => 42,
            'pageurl' => 'http://example.com/course/view.php?id=2',
            'sesskey' => 'abc123',
            'checked' => true,
        ]);
        $this->assertStringContainsString(' checked', $on);
        $this->assertStringContainsString('custom-control-label text-primary', $on);
        $this->assertStringContainsString('data-context="42"', $on);
    }
}
