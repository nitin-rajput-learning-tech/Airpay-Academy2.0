<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * The .ics file of one classroom session carries the session's notes as plain text.
 *
 * An imported session's notes are the BizLMS session description, which is HTML. A calendar description is plain
 * text, so a note written with markup showed its tags in Outlook and Google Calendar. The note is converted with
 * Moodle's html_to_text() when it holds a tag; a note typed as plain text is left alone. The lines of the
 * description are separated by the iCal line break, not by the two characters backslash and n.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_classroom\ics_builder
 * @group local_sentientia_classroom
 */
final class ics_builder_test extends \advanced_testcase {

    /**
     * The DESCRIPTION of the file built for one session, with the RFC 5545 line folding removed.
     *
     * @param string $notes
     * @return string
     */
    private function description_for(string $notes): string {
        $classroom = (object) ['id' => 7, 'name' => 'Annual AML', 'location' => 'Mumbai HQ'];
        $session = (object) ['id' => 11, 'title' => 'Day 1', 'starttime' => 1900000000, 'endtime' => 1900003600,
            'location' => '', 'notes' => $notes];
        $ics = ics_builder::build_session($session, $classroom, 'support@example.test');
        $unfolded = preg_replace("/\r\n[ \t]/", '', $ics);
        foreach (explode("\r\n", $unfolded) as $line) {
            if (str_starts_with($line, 'DESCRIPTION:')) {
                return substr($line, strlen('DESCRIPTION:'));
            }
        }
        $this->fail('the file has no DESCRIPTION');
    }

    public function test_html_notes_become_plain_text_in_the_description(): void {
        $description = $this->description_for(
            '<p>Bring your <b>laptop</b> &amp; charger.</p><ul><li>Room 4</li><li>Ask for <a href="https://example.test/x">Priya</a></li></ul>');

        // Case is not asserted: an HTML to text converter may upper-case bold text.
        $this->assertStringContainsStringIgnoringCase('Bring your laptop & charger.', $description,
            'the markup is converted and the entity is decoded');
        $this->assertStringContainsString('Room 4', $description);
        $this->assertStringContainsString('Priya', $description);
        $this->assertStringNotContainsString('<', $description, 'no tag reaches the calendar');
        $this->assertStringNotContainsString('&amp;', $description);
        $this->assertStringNotContainsString('example.test/x', $description, 'no link table is appended to the note');
        $this->assertStringStartsWith('Classroom: Annual AML', $description);
    }

    public function test_plain_text_notes_are_left_alone_and_still_escaped(): void {
        $description = $this->description_for('Arrive early; bring ID, and a pen. Seats < 20.');

        // ; and , are escaped for iCal, and a literal "<" that opens no tag is not read as markup.
        $this->assertStringContainsString('Arrive early\\; bring ID\\, and a pen. Seats < 20.', $description);
    }

    public function test_the_description_lines_are_separated_by_the_ical_line_break(): void {
        $description = $this->description_for('Bring ID');

        // One backslash and an n: what RFC 5545 calls a line break in a text value. Never two backslashes.
        $this->assertStringContainsString('Classroom: Annual AML\\nBring ID\\nView in Moodle: ', $description);
        $this->assertStringNotContainsString('\\\\n', $description);
    }

    public function test_a_note_with_only_markup_adds_nothing(): void {
        $description = $this->description_for('<p> </p><br>');

        $this->assertStringStartsWith('Classroom: Annual AML\\nView in Moodle: ', $description,
            'no empty line stands in for a note with no text');
    }

    public function test_the_notes_helper_trims_and_only_converts_when_there_is_a_tag(): void {
        $this->assertSame('plain', ics_builder::plain_notes("  plain \n"));
        $this->assertSame('a < b', ics_builder::plain_notes('a < b'));
        $this->assertSame('', ics_builder::plain_notes(''));
        $this->assertSame('hello', strtolower(ics_builder::plain_notes('<div>Hello</div>')));
    }
}
