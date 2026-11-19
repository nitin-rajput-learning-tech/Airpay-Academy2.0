<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * V19-ORG-CARD (Phase C, 2026-11-18): the tenant card on the organisation page is one row.
 *
 * The kebab menu used to sit inside the tenant row's collapse link. Its menu items are links, and a link inside a
 * link makes the HTML parser close the outer one early, so the units, users and status row dropped under the
 * tenant name. The menu is now the link's next sibling. The rows also no longer rely on the Bootstrap 5 gap-*
 * utilities, which the Bootstrap 4.6 theme does not have.
 *
 * @package    local_sentientia_org
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class manage_template_test extends \advanced_testcase {

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/local/sentientia_org/admin.php');
    }

    /**
     * One node in the shape admin.php builds.
     *
     * @param int $id
     * @param int $depth
     * @param array $children
     * @return array
     */
    private function node(int $id, int $depth, array $children = []): array {
        return [
            'id' => $id,
            'fullname' => 'Unit ' . $id,
            'shortname' => 'U' . $id,
            'description' => '',
            'path' => '/' . $id,
            'depth' => $depth,
            'is_tenant' => $depth === 1,
            'visible' => true,
            'visible_int' => 1,
            'visiblelabel' => 'Active',
            'visiblecss' => 'badge-success',
            'brand_color' => '',
            'has_brand' => false,
            'children' => $children,
            'has_children' => !empty($children),
            'child_count' => count($children),
            'user_count' => 5,
            'can_manage' => true,
        ];
    }

    /**
     * Renders the page template with one tenant that has one department.
     *
     * @return string
     */
    private function render_page(): string {
        global $OUTPUT;
        $tenant = $this->node(9101, 1, [$this->node(9102, 2)]);
        return $OUTPUT->render_from_template('local_sentientia_org/manage', [
            'kpi_tiles' => [],
            'has_kpi_tiles' => false,
            'tenants' => [$tenant],
            'has_tenants' => true,
            'can_manage' => true,
        ]);
    }

    public function test_tenant_row_link_contains_no_other_link(): void {
        $html = $this->render_page();

        $this->assertSame(1, preg_match('~<a class="tenant-row[^"]*"[^>]*>(.*?)</a>~s', $html, $m),
            'The tenant row link is rendered.');
        $inner = $m[1];
        $this->assertStringNotContainsString('<a', $inner, 'No link may sit inside the collapse link.');
        $this->assertStringNotContainsString('dropdown', $inner, 'The kebab menu sits outside the collapse link.');
        // The meta row and the chevron stay inside the link, so the row still toggles and the chevron still turns.
        $this->assertStringContainsString('class="org-actions"', $inner);
        $this->assertStringContainsString('toggle-arrow', $inner);
    }

    public function test_kebab_menu_is_the_link_sibling_and_still_wired(): void {
        $html = $this->render_page();

        $head = strpos($html, 'class="tenant-head"');
        $linkend = strpos($html, '</a>', strpos($html, 'class="tenant-row'));
        $menu = strpos($html, 'class="dropdown"');
        $collapse = strpos($html, 'id="org-9101-children"');
        $this->assertNotFalse($head);
        $this->assertNotFalse($menu);
        $this->assertNotFalse($collapse);
        $this->assertGreaterThan($linkend, $menu, 'The menu follows the collapse link.');
        $this->assertLessThan($collapse, $menu, 'The menu belongs to the tenant head, before the departments.');

        // org_actions.js delegates on [data-region="airpay-org"] and reads these attributes.
        $this->assertStringContainsString('data-action="edit-org" data-orgid="9101"', $html);
        $this->assertStringContainsString('data-action="add-child-org" data-parentid="9101"', $html);
        // The menu no longer needs to stop the click from reaching the collapse link.
        $this->assertStringNotContainsString('stopPropagation', $html);
        // Bootstrap 4.6 data API only (FX-20).
        $this->assertStringNotContainsString('data-bs-toggle', $html);
    }

    public function test_rows_do_not_rely_on_bootstrap5_gap_utilities(): void {
        $html = $this->render_page();

        $this->assertDoesNotMatchRegularExpression('~class="[^"]*\bgap-\d~', $html);
        // The tenant card and the department row both use the spaced actions container.
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="org-actions"'));
    }
}
