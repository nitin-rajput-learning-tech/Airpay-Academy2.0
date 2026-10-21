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

use theme_sentientia\output\core_renderer;

/**
 * Tests for the text of the screen-reader page heading (A11Y-8).
 *
 * $PAGE->heading holds HTML: set_heading() takes format_string() output, so a skill called "Data Privacy & GDPR"
 * arrives as "Skill: Data Privacy &amp; GDPR". The shell's sr-only h1 ran format_string() and s() over it again, so
 * screen readers heard "amp" (found by the 2026-10-20 visual pass on local_sentientia_skills/view.php).
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_sentientia\output\core_renderer::sr_heading_text
 */
final class sr_heading_text_test extends \advanced_testcase {

    public function test_an_escaped_heading_becomes_text_that_s_escapes_once(): void {
        $this->resetAfterTest();
        $heading = 'Skill: ' . format_string('Data Privacy & GDPR');
        $text = core_renderer::sr_heading_text($heading);
        $this->assertSame('Skill: Data Privacy & GDPR', $text);
        $this->assertSame('Skill: Data Privacy &amp; GDPR', s($text), 'the h1 source carries the ampersand escaped once');
    }

    public function test_quotes_tags_and_empty_values(): void {
        $this->resetAfterTest();
        $this->assertSame('Risk & "Compliance"', core_renderer::sr_heading_text(s('Risk & "Compliance"')));
        $this->assertSame('Bold title', core_renderer::sr_heading_text('<strong>Bold</strong> title'));
        $this->assertSame('', core_renderer::sr_heading_text(null));
        $this->assertSame('', core_renderer::sr_heading_text('   '));
    }

    public function test_the_shell_uses_the_helper_for_the_sr_only_heading(): void {
        $source = (string) file_get_contents(__DIR__ . '/../classes/output/core_renderer.php');
        $this->assertStringContainsString(
            '$page_title = self::sr_heading_text($this->page->heading) ?: self::sr_heading_text($this->page->title);', $source);
        $this->assertStringNotContainsString('$page_title = format_string($this->page->heading)', $source,
            'format_string() + s() over an HTML heading escapes it twice');
    }
}
