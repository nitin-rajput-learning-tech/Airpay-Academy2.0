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
 * Source checks for the modal form label column (QW-MODAL-FORMS, 2026-11-19).
 *
 * forms.scss carries core's `.mform fieldset .fcontainer.collapseable .col-form-label { padding-left: 2.5rem }`.
 * Core puts .col-form-label on the label column; this theme's core_form/element-template override puts it on an
 * inline <label>, so the padding indented only the first line of the label, and in a ModalForm dialogue (Edit course,
 * Add classroom, Edit program) the label wrapped as "    Course full / name" with the icons after "name".
 * _modal-forms.scss undoes that for modals only. These tests keep the partial wired in and keep it scoped to modals.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class modal_forms_scss_test extends \basic_testcase {

    /** @var string Theme scss/moodle directory. */
    private const SCSSDIR = __DIR__ . '/../scss/moodle';

    /**
     * Read a theme file.
     *
     * @param string $relative Path under scss/moodle.
     * @return string
     */
    private function read(string $relative): string {
        $path = self::SCSSDIR . '/' . $relative;
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    public function test_the_partial_is_imported_after_the_core_form_rules(): void {
        $orchestrator = $this->read('custom_changes.scss');
        $this->assertStringContainsString('@import "partials/modal-forms";', $orchestrator);
        // moodle.scss imports forms.scss before custom_changes.scss, so the modal rules come later in the CSS.
        $moodle = (string) file_get_contents(__DIR__ . '/../scss/moodle.scss');
        $forms = strpos($moodle, '@import "moodle/forms";');
        $custom = strpos($moodle, '@import "moodle/custom_changes";');
        $this->assertNotFalse($forms);
        $this->assertNotFalse($custom);
        $this->assertLessThan($custom, $forms);
    }

    public function test_the_first_line_indent_is_undone_in_modals_only(): void {
        $partial = $this->read('partials/_modal-forms.scss');
        $this->assertMatchesRegularExpression(
            '/\.modal:not\(\.openLMStransition\) \.mform fieldset \.fcontainer\.collapseable \.col-form-label \{\s*padding-left: 0;/',
            $partial);
        // The core rule stays for page forms.
        $this->assertMatchesRegularExpression(
            '/\.mform fieldset \.fcontainer\.collapseable \.col-form-label \{\s*padding-left: \(\$spacer \* 2\.5\);/',
            $this->read('forms.scss'));
        // Every selector in the partial is scoped to a modal (no rule reaches page forms).
        $code = preg_replace('~//[^\n]*~', '', $partial);
        preg_match_all('/^([^{}@\n][^{}\n]*)\{/m', $code, $m);
        $this->assertNotEmpty($m[1]);
        $toplevel = array_filter(array_map('trim', $m[1]), function(string $sel): bool {
            return strpos($sel, '>') !== 0;
        });
        foreach ($toplevel as $selector) {
            foreach (array_map('trim', explode(',', $selector)) as $one) {
                $this->assertStringStartsWith('.modal:not(.openLMStransition) ', $one, "unscoped selector: {$one}");
            }
        }
    }

    public function test_the_label_column_layout_rules_are_present(): void {
        $partial = $this->read('partials/_modal-forms.scss');
        // Scoped like 3a/3b: a .full-width-labels form keeps core's layout (its empty label columns stay hidden).
        // Large dialogues only: in a default-size (500px) dialogue the 25% label column is about 95px, and as a
        // flex row it broke one-word labels mid-word ("Permiss / ion", Roles > Edit capability, 2026-11-19).
        $this->assertStringContainsString(
            '.modal:not(.openLMStransition) .modal-lg .mform:not(.full-width-labels) .fitem.row > .col-md-3:first-child',
            $partial);
        $this->assertStringContainsString(
            '.modal:not(.openLMStransition) .modal-xl .mform:not(.full-width-labels) .fitem.row > .col-md-3:first-child',
            $partial);
        $this->assertStringNotContainsString(
            '.modal:not(.openLMStransition) .mform:not(.full-width-labels) .fitem.row > .col-md-3:first-child', $partial);
        $this->assertStringNotContainsString('.mform .fitem.row > .col-md-3:first-child', $partial);
        $this->assertStringContainsString('margin-left: auto;', $partial);
        // Between md and lg the label column is full width, so the icons follow the label text instead.
        $this->assertMatchesRegularExpression(
            '/> \.col-md-3:first-child > \.text-nowrap \{\s*margin-left: 0;/', $partial);
        $this->assertStringContainsString('padding-top: add($input-padding-y, $input-border-width);', $partial);
        $this->assertStringContainsString('.modal-lg .mform:not(.full-width-labels) .fitem.row', $partial);
        $this->assertStringContainsString('flex: 0 0 30%;', $partial);
        $this->assertStringContainsString('flex: 0 0 70%;', $partial);
        $this->assertStringContainsString('@include media-breakpoint-only(md)', $partial);
    }

    public function test_the_theme_template_still_puts_col_form_label_on_an_inline_label(): void {
        // The partial is written for this markup. If the override is ever brought in line with core 5.x
        // (col-form-label on the column div, .form-label-addon), revisit _modal-forms.scss.
        $template = (string) file_get_contents(__DIR__ . '/../templates/core_form/element-template.mustache');
        $this->assertStringContainsString('<label class="col-form-label d-inline', $template);
        $this->assertStringContainsString('<span class="float-sm-right text-nowrap">', $template);
        $this->assertStringContainsString('<div class="col-md-3">', $template);
    }
}
