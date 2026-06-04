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
    /**
     * Goal A audit Bug #11 (2026-05-22) — Compliance Officer / HR / Trainer
     * tier. Hold `moodle/site:viewreports` system cap without being a higher-
     * tier role. Joseph Mandapati (Compliance Officer) hit this — he could
     * load /local/airpay_compliance_report/ by URL but the sidebar didn't
     * surface it. The page-layer auth already accepts viewreports, so the
     * sidebar should reflect that.
     */
    private $iscomplianceuser;

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

        // ── ADR-017 / C1.5 (2026-05-28) ─────────────────────────────────
        // Expose user_type axis to the sidebar template. Role-axis
        // filters above already exclude admin items from non-admin
        // sidebars, so consumers naturally see the learner shape. This
        // exposure lets the template add type-specific accent styling
        // (e.g. consumer badge in header) and gives a safety net for
        // future surfaces.
        $user_type = 'employee';
        $user_type_label = '';
        if (class_exists('\\local_airpay_core\\user_type_factory')) {
            try {
                $type_provider = \local_airpay_core\user_type_factory::for_user((int) $USER->id);
                $user_type = $type_provider::type_id();
                $user_type_label = $type_provider::label();
            } catch (\Throwable $e) {
                // Defensive: leave defaults.
            }
        }

        return [
            'wwwroot'       => $CFG->wwwroot,
            'collapsed'     => (bool) $collapsed,
            'logourl'       => $this->get_logo_url(),
            'sitename'      => format_string(get_config('core', 'shortname') ?: 'airpay academy'),
            'navitems'      => $this->get_nav_items(),
            'issiteadmin'   => $this->issiteadmin,
            'isldadmin'     => $this->isldadmin,
            'ismanager'     => $this->ismanager,
            'user_type'         => $user_type,
            'user_type_label'   => $user_type_label,
            'is_employee'         => ($user_type === 'employee'),
            'is_consumer'         => ($user_type === 'consumer'),
            'is_partner_employee' => ($user_type === 'partner_employee'),
            'is_operator'         => ($user_type === 'operator'),
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
            $items[] = $this->item('Analytics', 'fa-chart-line', '/local/sentientia_analytics/index.php', $currenturl);
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
            // OA-GRAN fix (2026-05-29 QA walk, P1): gate each admin link by the
            // SAME system-context capability its target page enforces
            // (require_capability('local/airpay_*:view', context_system) in each
            // index.php). role_detector grants the L&D shell to anyone with the
            // `administrator` role at a CATEGORY context (e.g. compliance
            // officers — see role_detector docblock / Bug #11), but those users
            // may not hold every system cap. Before this gate, qa_compliance saw
            // 5/8 dead links (Manage Users/Courses/Exams/Classrooms/Reports) that
            // 403 on click. Siteadmins are unaffected (capability bypass); full
            // L&D admins keep every link (they hold the caps). Compliance +
            // Analytics stay ungated — Compliance must remain reachable for
            // compliance officers (its page accepts moodle/site:viewreports).
            $sys = \context_system::instance();
            $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);

            // ── Content ──
            $items[] = $this->divider();
            if (has_capability('local/airpay_users:view', $sys)) {
                $items[] = $this->item('Manage Users', 'fa-users', '/local/airpay_users/index.php', $currenturl);
            }
            if (has_capability('local/airpay_courses:view', $sys)) {
                $items[] = $this->item('Manage Courses', 'fa-book', '/local/airpay_courses/index.php', $currenturl);
            }
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
            if (has_capability('local/airpay_exams:view', $sys)) {
                $items[] = $this->item('Online Exams', 'fa-pencil-square-o', '/local/airpay_exams/index.php', $currenturl);
            }
            if (has_capability('local/airpay_classroom:view', $sys)) {
                $items[] = $this->item('Classrooms', 'fa-calendar', '/local/airpay_classroom/index.php', $currenturl);
            }
            if (has_capability('local/airpay_learningpath:view', $sys)) {
                $items[] = $this->item('Learning Paths', 'fa-road', '/local/airpay_learningpath/index.php', $currenturl);
            }

            // ── Insights ──
            $items[] = $this->divider();
            if (has_capability('local/airpay_reports:view', $sys)) {
                $items[] = $this->item('Reports', 'fa-bar-chart', '/local/airpay_reports/index.php', $currenturl);
            }
            $items[] = $this->item('Analytics', 'fa-line-chart', '/local/sentientia_analytics/index.php', $currenturl);
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
            // T-02 (QA Walk 2026-05-29): Sentientia Live trainer dashboard.
            // Gated by can_create_live_session() so it only shows when the
            // live.enabled flag is on AND the user holds live:create (true for
            // the BizLMS trainer role after the T-01 access.php fix).
            if ($this->can_create_live_session()) {
                $items[] = $this->item('Live Sessions', 'fa-bolt',
                    '/local/sentientia_live/trainer/index.php', $currenturl);
            }
            $items[] = $this->divider();
            $items[] = $this->item('My Courses', 'fa-book', '/local/sentientia_catalog/mycourses.php', $currenturl);
            $items[] = $this->item('Catalog', 'fa-compass', '/local/sentientia_catalog/public.php', $currenturl);
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
            // P-01 (QA Walk 2026-05-29): point at the catalog's SESSION cart
            // (commerce::add_to_cart, viewed at /local/sentientia_catalog/cart.php) —
            // the cart the catalog "Add to Cart" buttons actually fill. The DB
            // cart at /local/airpay_cart/index.php is fed only by the add_item WS,
            // never the catalog buttons, so it always rendered empty here.
            if ($this->is_cart_enabled_for_current_user()) {
                $items[] = $this->item('My Cart', 'fa-shopping-cart',
                    '/local/sentientia_catalog/cart.php', $currenturl);
            }
            // E-02 (QA Walk 2026-05-29): managers are learners too — give
            // them their own Skills dashboard, same as the Learner shell.
            if ($this->can_view_own_skills()) {
                $items[] = $this->item('My Skills', 'fa-bullseye',
                    '/local/airpay_skills/index.php', $currenturl);
            }
            $items[] = $this->item('Certificates', 'fa-certificate', '/local/sentientia_pages/certificates.php', $currenturl);
            $items[] = $this->item('Profile', 'fa-user', '/local/airpay_users/profile.php', $currenturl);
            return $items;
        }

        // ═══════════════════════════════════════════════════
        // LEARNER (employee / external) — default
        // Order: learning activities first
        // ═══════════════════════════════════════════════════
        $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);
        $items[] = $this->item('My Courses', 'fa-book', '/local/sentientia_catalog/mycourses.php', $currenturl);
        $items[] = $this->item('Catalog', 'fa-compass', '/local/sentientia_catalog/public.php', $currenturl);

        // ── Cart (only for tenants where cart is enabled — Phase 1G) ──
        // Public/ZEEA tenants get a cart link; Airpay tenant employees
        // get their training free and don't see this link.
        // P-01 (QA Walk 2026-05-29): point at the catalog's SESSION cart
        // (/local/sentientia_catalog/cart.php) — where the catalog "Add to Cart"
        // buttons put items — not the DB cart /local/airpay_cart/index.php
        // (fed only by the add_item WS), which always rendered empty here.
        if ($this->is_cart_enabled_for_current_user()) {
            $items[] = $this->item('My Cart', 'fa-shopping-cart',
                '/local/sentientia_catalog/cart.php', $currenturl);
        }

        // T-02 (QA Walk 2026-05-29): surface Sentientia Live for trainer-role
        // users who land in the Learner shell (no viewreports cap, no direct
        // reports, so role_detector returns islearner). Gated by the create
        // cap — same defensive pattern as the iscomplianceuser link below.
        if ($this->can_create_live_session()) {
            $items[] = $this->item('Live Sessions', 'fa-bolt',
                '/local/sentientia_live/trainer/index.php', $currenturl);
        }

        // Goal A audit Bug #11 (2026-05-22) — Compliance Officer / HR /
        // Trainer can hold `moodle/site:viewreports` without being Site
        // Admin / L&D Admin / Manager. The page-layer auth already
        // accepts this cap (see local/airpay_compliance_report/index.php
        // line ~34); the sidebar should mirror that so the link is
        // reachable without typing a URL. Inserted before Certificates
        // so it sits with the high-value workflow links.
        if ($this->iscomplianceuser) {
            $items[] = $this->item('Compliance', 'fa-shield',
                '/local/airpay_compliance_report/index.php', $currenturl);
        }

        // E-02 (QA Walk 2026-05-29): surface the learner's own Skills
        // dashboard (gap analysis + radar + self-rate). No userid param →
        // index.php defaults to $USER. Cap-gated, no feature flag — same
        // pattern as the iscomplianceuser Compliance link above.
        if ($this->can_view_own_skills()) {
            $items[] = $this->item('My Skills', 'fa-bullseye',
                '/local/airpay_skills/index.php', $currenturl);
        }

        $items[] = $this->item('Certificates', 'fa-certificate', '/local/sentientia_pages/certificates.php', $currenturl);
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
     * Can the current user create / run a Sentientia Live session?
     *
     * Gates the "Live Sessions" sidebar link so it only appears for users who
     * can actually enter /local/sentientia_live/trainer/index.php (which
     * enforces the same capability). True for the BizLMS `trainer` role (after
     * the T-01 access.php fix), editingteachers, the manager archetype and
     * siteadmins.
     *
     * Two gates, matching the conventions already in this file:
     *   1. feature_flags::is_enabled('live.enabled') — the Live master flag
     *      (same flag the trainer pages check; off = no feature, no link).
     *   2. has_capability('local/sentientia_live:create', system).
     *
     * Safe-fails to false (no link, no crash) when the plugin, its capability
     * or the flag resolver isn't installed — e.g. a future Sentientia customer
     * who didn't license Live. Mirrors is_cart_enabled_for_current_user().
     */
    private function can_create_live_session(): bool {
        try {
            if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
                return false;
            }
            return has_capability('local/sentientia_live:create', \context_system::instance());
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Can the current user reach their own Skills dashboard?
     *
     * E-02 (QA Walk 2026-05-29): the learner-facing skills dashboard
     * (/local/airpay_skills/index.php — gap analysis, radar chart,
     * recommended courses, self-rate) existed but had NO sidebar entry, so
     * learners couldn't discover it (the siteadmin shell only links the
     * admin page admin.php). Gate the "My Skills" link by the same
     * capability the skills surface declares — local/airpay_skills:view,
     * granted to the student archetype — so it shows for learners and
     * disappears if an admin revokes the cap or the plugin isn't installed
     * (a future Sentientia customer who didn't license Skills). No new
     * feature flag: this mirrors the cap-only iscomplianceuser Compliance
     * link (Bug #11) — a discoverability fix for an existing, live surface,
     * not a new feature. Safe-fails to false.
     */
    private function can_view_own_skills(): bool {
        try {
            return has_capability('local/airpay_skills:view', \context_system::instance());
        } catch (\Throwable $e) {
            return false;
        }
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
     * Detect user role tier. Delegates to the shared role_detector.
     *
     * Refactor (2026-05-22 — follow-up to Bug #11): role detection used
     * to be duplicated between this class and layout/dashboard.php. They
     * drifted, causing Bug #11 (Joseph Mandapati saw L&D Admin dashboard
     * with Learner sidebar). The shared `\theme_airpayux\role_detector`
     * is now the single source of truth — both callers consume it.
     *
     * `iscomplianceuser` stays as a sidebar-only concept: a learner-tier
     * user who still has report-view cap and should see a Compliance
     * link inserted in their otherwise-Learner sidebar. The page-layer
     * (auth at /local/airpay_compliance_report/index.php) doesn't need
     * this distinction; only the sidebar does.
     */
    private function detect_roles(): void {
        $roles = role_detector::detect();
        $this->issiteadmin = $roles['issiteadmin'];
        $this->isldadmin   = $roles['isldadmin'];
        $this->ismanager   = $roles['ismanager'];

        // Sidebar-only "Compliance officer in a Learner shell" detection:
        // any non-admin/manager user with report-view cap gets a Compliance
        // sidebar link before Certificates.
        $this->iscomplianceuser = false;
        if (!$this->issiteadmin && !$this->isldadmin && !$this->ismanager) {
            $this->iscomplianceuser = has_capability(
                'moodle/site:viewreports', \context_system::instance());
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
