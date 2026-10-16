<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * UI fix 2026-10-15 (visual pass 2026-10-14, finding 9): the org tree printed "Issuance &amp; Loyalty".
 *
 * admin.php handed format_string() / s() output to org_node.mustache and manage.mustache, whose {{ }} escape it a
 * second time. org_manager::plain() is the text form admin.php now uses for every value it gives those templates.
 *
 * @package    local_sentientia_org
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_org\org_manager::plain
 */
final class names_escaped_once_test extends \advanced_testcase {

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        // format_string() filters in a page context.
        $PAGE->set_context(\context_system::instance());
    }

    public function test_plain_is_text_whether_or_not_the_site_strips_tags(): void {
        global $CFG;

        // The Moodle default: tags are stripped, so format_string() with escape off is already text.
        $CFG->formatstringstriptags = 1;
        $this->assertSame('Issuance & Loyalty on', org_manager::plain('Issuance & Loyalty on'));

        // Setting OFF: format_string() ends in clean_text(), which returns HTML. plain() still returns text.
        $CFG->formatstringstriptags = 0;
        $this->assertSame('Issuance & Loyalty off', org_manager::plain('Issuance & Loyalty off'));
        $this->assertSame('Issuance & Loyalty bold end', org_manager::plain('Issuance & Loyalty <b>bold</b> end'));
    }

    public function test_plain_is_text_not_html(): void {
        $this->assertSame('Issuance & Loyalty', org_manager::plain('Issuance & Loyalty'));
        $this->assertSame('', org_manager::plain(null));
        $this->assertSame('', org_manager::plain(''));
        // The HTML form is what format_string() has always returned: one escape.
        $this->assertSame('Issuance &amp; Loyalty', format_string('Issuance & Loyalty'));
    }

    public function test_the_org_row_prints_the_name_once_escaped(): void {
        global $OUTPUT;

        // The shape admin.php builds for a node.
        $node = [
            'id' => 8801,
            'fullname' => org_manager::plain('Issuance & Loyalty'),
            'shortname' => 'R&D',
            'description' => org_manager::plain(''),
            'path' => '/8801',
            'depth' => 2,
            'is_tenant' => false,
            'visible' => true,
            'visible_int' => 1,
            'visiblelabel' => 'Active',
            'visiblecss' => 'badge-success',
            'brand_color' => '',
            'has_brand' => false,
            'children' => [],
            'has_children' => false,
            'child_count' => 0,
            'user_count' => 3,
            'can_manage' => true,
        ];
        $html = $OUTPUT->render_from_template('local_sentientia_org/org_node', $node);

        // The visible name, the menu's data-name (read by org_actions.js) and the short name: one escape each.
        $this->assertStringContainsString('<span class="fw-semibold">Issuance &amp; Loyalty</span>', $html);
        $this->assertStringContainsString('data-name="Issuance &amp; Loyalty"', $html);
        $this->assertStringContainsString('R&amp;D', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
