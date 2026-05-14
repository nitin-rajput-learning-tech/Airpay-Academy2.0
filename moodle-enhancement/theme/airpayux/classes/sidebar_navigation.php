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

namespace theme_airpayux;

defined('MOODLE_INTERNAL') || die();

/**
 * Sidebar navigation builder — role-aware, tenant-aware.
 *
 * Generates the navigation context for sidebar.mustache.
 * Each role tier sees different nav items. No hardcoded tenant data.
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sidebar_navigation {

    /** @var \moodle_page */
    private $page;

    /** @var bool */
    private $issiteadmin;
    private $isldadmin;
    private $ismanager;

    public function __construct(\moodle_page $page) {
        $this->page = $page;
        $this->detect_roles();
    }

    /**
     * Build the full sidebar context for Mustache.
     *
     * @return array Template context
     */
    public function get_context(): array {
        global $USER, $CFG, $OUTPUT;

        $collapsed = get_user_preferences('theme_airpayux_sidebar_collapsed', false);

        return [
            'wwwroot'       => $CFG->wwwroot,
            'collapsed'     => (bool) $collapsed,
            'logourl'       => $this->get_logo_url(),
            'sitename'      => format_string(get_config('core', 'shortname') ?: 'airpay academy'),
            'navitems'      => $this->get_nav_items(),
            'issiteadmin'   => $this->issiteadmin,
            'isldadmin'     => $this->isldadmin,
            'ismanager'     => $this->ismanager,
            'userfullname'  => fullname($USER),
            'useravatar'    => $OUTPUT->user_picture($USER, ['size' => 36, 'link' => false]),
            'userinitials'  => $this->get_initials($USER),
            'profileurl'    => (new \moodle_url('/local/airpay_users/profile.php'))->out(false),
            'logouturl'     => (new \moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
        ];
    }

    /**
     * Get navigation items based on user role.
     *
     * @return array
     */
    private function get_nav_items(): array {
        global $CFG;

        $items = [];
        $currenturl = $this->page->url->out(false);

        // ═══════════════════════════════════════════════════
        // SITEADMIN — platform operator, NOT a learner
        // Order: high-frequency admin tasks first
        // ═══════════════════════════════════════════════════
        if ($this->issiteadmin) {
            // ── Overview ──
            $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);

            // ── People & Content ──
            $items[] = $this->divider();
            $items[] = $this->item('Manage Users', 'fa-users', '/local/airpay_users/index.php', $currenturl);
            $items[] = $this->item('Manage Courses', 'fa-book', '/local/airpay_courses/index.php', $currenturl);
            // Sprint D nav entry — Airpay admins see pending course-share
            // requests from other tenants here. The page itself enforces
            // the local/airpay_courses:approve_request cap (siteadmin only).
            //
            // Phase A0 (2026-05-14): also gated by the Switchboard flag
            // `commerce.crossTenantRequest.enabled`. When off, this link
            // disappears AND /manage_requests.php returns a friendly
            // "feature disabled" page.
            if (\local_airpay_core\feature_flags::is_enabled('commerce.crossTenantRequest.enabled')) {
                $items[] = $this->item('Course-share Requests', 'fa-inbox',
                    '/local/airpay_courses/manage_requests.php', $currenturl);
            }
            $items[] = $this->item('Online Exams', 'fa-edit', '/local/airpay_exams/index.php', $currenturl);
            $items[] = $this->item('Classrooms', 'fa-calendar', '/local/airpay_classroom/index.php', $currenturl);
            $items[] = $this->item('Learning Paths', 'fa-map-signs', '/local/airpay_learningpath/index.php', $currenturl);
            $items[] = $this->item('Programs', 'fa-trophy', '/local/airpay_programs/index.php', $currenturl);

            // ── Insights ──
            $items[] = $this->divider();
            $items[] = $this->item('Reports', 'fa-chart-bar', '/local/airpay_reports/index.php', $currenturl);
            $items[] = $this->item('Analytics', 'fa-chart-line', '/local/airpay_analytics/index.php', $currenturl);
            $items[] = $this->item('Compliance', 'fa-shield', '/local/airpay_compliance_report/index.php', $currenturl);

            // ── Platform ──
            $items[] = $this->divider();
            $items[] = $this->item('Organisation', 'fa-sitemap', '/local/airpay_org/admin.php', $currenturl);
            $items[] = $this->item('Skills', 'fa-bullseye', '/local/airpay_skills/admin.php', $currenturl);
            $items[] = $this->item('Notifications', 'fa-bell', '/local/airpay_notifications/index.php', $currenturl);
            $items[] = $this->item('Evaluations', 'fa-clipboard', '/local/airpay_evaluation/index.php', $currenturl);
            $items[] = $this->item('Certificates', 'fa-certificate', '/admin/tool/certificate/manage_templates.php', $currenturl);
            $items[] = $this->item('Emails', 'fa-envelope', '/local/airpay_emails/manage.php', $currenturl);
            $items[] = $this->item('Privacy', 'fa-lock', '/local/airpay_privacy/index.php', $currenturl);
            $items[] = $this->item('Site Admin', 'fa-cog', '/admin/search.php', $currenturl);
            return $items;
        }

        // ═══════════════════════════════════════════════════
        // L&D ADMIN — manages courses and users, NOT a learner
        // Order: course/user management, then reporting
        // ═══════════════════════════════════════════════════
        if ($this->isldadmin) {
            $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);

            // ── Content ──
            $items[] = $this->divider();
            $items[] = $this->item('Manage Users', 'fa-users', '/local/airpay_users/index.php', $currenturl);
            $items[] = $this->item('Manage Courses', 'fa-book', '/local/airpay_courses/index.php', $currenturl);
            // Sprint D — non-Airpay L&D admins (Public/ZEEA) get the
            // Browse Airpay Library link to request specific courses
            // from the Airpay tenant's library.
            //
            // Phase A0 (2026-05-14): also gated by the Switchboard flag
            // `commerce.crossTenantRequest.enabled`.
            if ($this->is_non_airpay_tenant_user()
                    && \local_airpay_core\feature_flags::is_enabled('commerce.crossTenantRequest.enabled')) {
                $items[] = $this->item('Browse Airpay Library', 'fa-handshake-o',
                    '/local/airpay_courses/browse_airpay.php', $currenturl);
            }
            $items[] = $this->item('Online Exams', 'fa-pencil-square-o', '/local/airpay_exams/index.php', $currenturl);
            $items[] = $this->item('Classrooms', 'fa-calendar', '/local/airpay_classroom/index.php', $currenturl);
            $items[] = $this->item('Learning Paths', 'fa-road', '/local/airpay_learningpath/index.php', $currenturl);

            // ── Insights ──
            $items[] = $this->divider();
            $items[] = $this->item('Reports', 'fa-bar-chart', '/local/airpay_reports/index.php', $currenturl);
            $items[] = $this->item('Analytics', 'fa-line-chart', '/local/airpay_analytics/index.php', $currenturl);
            $items[] = $this->item('Compliance', 'fa-shield', '/local/airpay_compliance_report/index.php', $currenturl);
            return $items;
        }

        // ═══════════════════════════════════════════════════
        // MANAGER — team lead, also a learner
        // Order: team management first, then own learning
        // ═══════════════════════════════════════════════════
        if ($this->ismanager) {
            $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);
            $items[] = $this->item('My Team', 'fa-users', '/local/airpay_manager/index.php', $currenturl);
            $items[] = $this->item('Compliance', 'fa-shield-alt', '/local/airpay_compliance_report/index.php', $currenturl);
            $items[] = $this->divider();
            $items[] = $this->item('My Courses', 'fa-book', '/local/airpay_catalog/mycourses.php', $currenturl);
            $items[] = $this->item('Catalog', 'fa-compass', '/local/airpay_catalog/public.php', $currenturl);
            // Sprint D — managers in non-Airpay tenants (Public/ZEEA)
            // get a "Browse Airpay Library" link so they can request
            // specific Airpay courses for their tenant's catalogue.
            // Airpay-tenant managers don't see this — they already own
            // every Airpay course by tree membership.
            // Paired with "My Requests" outbox so they can track the
            // status of every request they've filed.
            //
            // Phase A0 (2026-05-14): also gated by the Switchboard flag
            // `commerce.crossTenantRequest.enabled`.
            if ($this->is_non_airpay_tenant_user()
                    && \local_airpay_core\feature_flags::is_enabled('commerce.crossTenantRequest.enabled')) {
                $items[] = $this->item('Browse Airpay Library', 'fa-handshake-o',
                    '/local/airpay_courses/browse_airpay.php', $currenturl);
                $items[] = $this->item('My Requests', 'fa-clipboard-list',
                    '/local/airpay_courses/my_requests.php', $currenturl);
            }
            // Cart for managers in cart-enabled tenants.
            if ($this->is_cart_enabled_for_current_user()) {
                $items[] = $this->item('My Cart', 'fa-shopping-cart',
                    '/local/airpay_cart/index.php', $currenturl);
            }
            $items[] = $this->item('Certificates', 'fa-certificate', '/local/airpay_pages/certificates.php', $currenturl);
            $items[] = $this->item('Profile', 'fa-user', '/local/airpay_users/profile.php', $currenturl);
            return $items;
        }

        // ═══════════════════════════════════════════════════
        // LEARNER (employee / external) — default
        // Order: learning activities first
        // ═══════════════════════════════════════════════════
        $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);
        $items[] = $this->item('My Courses', 'fa-book', '/local/airpay_catalog/mycourses.php', $currenturl);
        $items[] = $this->item('Catalog', 'fa-compass', '/local/airpay_catalog/public.php', $currenturl);

        // ── Cart (only for tenants where cart is enabled — Phase 1G) ──
        // Public/ZEEA tenants get a cart link; Airpay tenant employees
        // get their training free and don't see this link.
        if ($this->is_cart_enabled_for_current_user()) {
            $items[] = $this->item('My Cart', 'fa-shopping-cart',
                '/local/airpay_cart/index.php', $currenturl);
        }

        $items[] = $this->item('Certificates', 'fa-certificate', '/local/airpay_pages/certificates.php', $currenturl);
        $items[] = $this->item('Profile', 'fa-user', '/local/airpay_users/profile.php', $currenturl);

        return $items;
    }

    /**
     * Is the airpay_cart plugin enabled for the current user's tenant?
     * Safe-fails if the plugin isn't installed.
     */
    private function is_cart_enabled_for_current_user(): bool {
        global $USER;
        if (!class_exists('\\local_airpay_cart\\cart_manager')) {
            return false;
        }
        try {
            return \local_airpay_cart\cart_manager::is_enabled_for_user($USER);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Sprint D — true when the current user belongs to a tenant other
     * than Airpay (i.e. Public/77, ZEEA/177, or any other receiving
     * tenant). Those users get the "Browse Airpay Library" sidebar
     * link to file share-requests. Airpay users (open_path under /1)
     * don't see it — they already own Airpay's catalog by definition.
     */
    private function is_non_airpay_tenant_user(): bool {
        global $USER;
        $path = $USER->open_path ?? '';
        if ($path === '') {
            return false;
        }
        $parts = explode('/', trim($path, '/'));
        $root = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        // Airpay tenant root = 1 (per project convention). Anything
        // else with a valid root is a "receiving" tenant.
        return $root > 0 && $root !== 1;
    }

    /**
     * Build a single nav item.
     */
    private function item(string $label, string $icon, string $path, string $currenturl,
                          ?string $badge = null, array $pathmatches = []): array {
        global $CFG;

        $url = (new \moodle_url($path))->out(false);
        $active = ($url === $currenturl);

        // Also check partial path match for sub-pages.
        if (!$active && !empty($pathmatches)) {
            foreach ($pathmatches as $match) {
                if (strpos($currenturl, $match) !== false) {
                    $active = true;
                    break;
                }
            }
        }

        // Fallback: match by path segment.
        if (!$active) {
            $urlpath = parse_url($url, PHP_URL_PATH);
            $curpath = parse_url($currenturl, PHP_URL_PATH);
            if ($urlpath && $curpath && $urlpath !== '/' && strpos($curpath, $urlpath) === 0) {
                $active = true;
            }
        }

        return [
            'type'   => 'item',
            'label'  => $label,
            'icon'   => $icon,
            'url'    => $url,
            'active' => $active,
            'badge'  => $badge,
        ];
    }

    /**
     * Build a divider.
     */
    private function divider(): array {
        // Sentinel — template checks 'isdivider' to add visual separator
        return ['isdivider' => true, 'label' => '', 'icon' => '', 'url' => '', 'active' => false];
    }

    /**
     * Detect user role tier.
     */
    private function detect_roles(): void {
        global $USER, $DB;

        $this->issiteadmin = is_siteadmin();

        // L&D Admin: has course manage capability.
        $this->isldadmin = false;
        if (!$this->issiteadmin) {
            $syscontext = \context_system::instance();
            $this->isldadmin = has_capability('local/airpay_courses:manage', $syscontext)
                            || has_capability('local/courses:manage', $syscontext);
        }

        // Manager: has direct reports.
        $this->ismanager = false;
        if (!$this->issiteadmin && !$this->isldadmin) {
            $this->ismanager = $DB->record_exists('user', [
                'open_supervisorid' => $USER->id,
                'deleted' => 0,
            ]);
        }
    }

    /**
     * Get logo URL (tenant-branded or default).
     */
    private function get_logo_url(): string {
        global $CFG;

        // Try tenant-specific logo from branding_manager.
        if (class_exists('\local_airpay_org\branding_manager')) {
            $logo = \local_airpay_org\branding_manager::get_tenant_logo();
            if (!empty($logo)) {
                return $logo;
            }
        }

        // Default logo.
        return $CFG->wwwroot . '/theme/airpayux/pix/default_logo.png';
    }

    /**
     * Get user initials for avatar fallback.
     */
    private function get_initials(object $user): string {
        $first = mb_substr($user->firstname ?? '', 0, 1);
        $last = mb_substr($user->lastname ?? '', 0, 1);
        return mb_strtoupper($first . $last);
    }
}
