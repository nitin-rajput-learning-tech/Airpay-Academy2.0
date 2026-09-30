<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pages;

defined('MOODLE_INTERNAL') || die();

/**
 * The certificate template browser must open for the users its gate admits.
 *
 * Persona pass 2026-09-30, D6: certificate_templates.php admitted holders of
 * tool/certificate:manage and then called admin_externalpage_setup() for an
 * external page that is registered only for moodle/site:config holders, so core
 * threw `accessdenied` after the gate had said yes.
 *
 * @package    local_sentientia_pages
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_pages\cert_templates_access
 */
final class cert_templates_access_test extends \advanced_testcase {

    /**
     * A user whose only system-level role allows exactly $caps.
     *
     * @param string[] $caps
     * @return \stdClass
     */
    private function user_with(array $caps): \stdClass {
        $syscontext = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        role_assign($roleid, $user->id, $syscontext->id);
        return $user;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!get_capability_info('tool/certificate:manage')) {
            $this->markTestSkipped('tool_certificate is not installed in this environment.');
        }
    }

    /**
     * D6: the tenant-admin case. The gate admits a tool/certificate:manage
     * holder, but the admin tree does not contain the page for them.
     */
    public function test_certificate_manager_may_view_without_the_admin_tree(): void {
        $user = $this->user_with(['tool/certificate:manage']);
        $this->setUser($user);
        $context = \context_system::instance();

        $this->assertTrue(cert_templates_access::can_view($context));
        $this->assertFalse(cert_templates_access::uses_admin_tree($context),
            'the external page is registered for moodle/site:config only');
    }

    public function test_user_with_neither_capability_may_not_view(): void {
        $user = $this->user_with(['moodle/site:viewreports']);
        $this->setUser($user);
        $context = \context_system::instance();

        $this->assertFalse(cert_templates_access::can_view($context));
        $this->assertFalse(cert_templates_access::uses_admin_tree($context));
    }

    public function test_site_admin_keeps_the_admin_tree_page(): void {
        $this->setAdminUser();
        $context = \context_system::instance();

        $this->assertTrue(cert_templates_access::can_view($context));
        $this->assertTrue(cert_templates_access::uses_admin_tree($context));
    }

    public function test_site_config_holder_who_is_not_a_site_admin_keeps_the_admin_tree_page(): void {
        $user = $this->user_with(['moodle/site:config']);
        $this->setUser($user);
        $context = \context_system::instance();

        $this->assertTrue(cert_templates_access::can_view($context));
        $this->assertTrue(cert_templates_access::uses_admin_tree($context));
    }

    /**
     * Pins the defect itself: core really does refuse this user, which is why
     * the page must not call admin_externalpage_setup() for them.
     */
    public function test_core_refuses_the_admin_externalpage_for_a_certificate_manager(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $user = $this->user_with(['tool/certificate:manage']);
        $this->setUser($user);

        try {
            admin_externalpage_setup(cert_templates_access::EXTERNALPAGE, '', null, '',
                ['pagelayout' => 'admin']);
            $this->fail('core accepted the external page for a user without moodle/site:config');
        } catch (\moodle_exception $e) {
            $this->assertSame('accessdenied', $e->errorcode);
        }
    }

    /**
     * The fix: the ordinary page setup succeeds for that same user and leaves
     * an admin-layout system-context page with the title as heading and
     * breadcrumb.
     */
    public function test_setup_page_builds_an_ordinary_page_for_a_certificate_manager(): void {
        global $PAGE;

        $user = $this->user_with(['tool/certificate:manage']);
        $this->setUser($user);
        $context = \context_system::instance();
        $url = new \moodle_url('/local/sentientia_pages/certificate_templates.php');

        cert_templates_access::setup_page($context, $url, 'Certificate templates');

        $this->assertSame('admin', $PAGE->pagelayout);
        $this->assertSame((int) $context->id, (int) $PAGE->context->id);
        $this->assertSame($url->out_omit_querystring(), $PAGE->url->out_omit_querystring());
        $this->assertSame('Certificate templates', $PAGE->heading);
        $this->assertStringContainsString('Certificate templates', $PAGE->title);
    }
}
