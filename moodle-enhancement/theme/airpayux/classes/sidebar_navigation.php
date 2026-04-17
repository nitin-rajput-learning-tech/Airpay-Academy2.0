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

        // ── Learner items (everyone sees these) ──────────
        $items[] = $this->item('Dashboard', 'fa-home', '/my/', $currenturl, null, ['/my/index.php']);
        $items[] = $this->item('My Courses', 'fa-book', '/local/airpay_catalog/mycourses.php', $currenturl);
        $items[] = $this->item('Catalog', 'fa-compass', '/local/airpay_catalog/public.php', $currenturl,
            null, ['airpay_catalog']);
        $items[] = $this->item('Certificates', 'fa-certificate', '/local/airpay_pages/certificates.php', $currenturl);
        $items[] = $this->item('Profile', 'fa-user', '/local/airpay_users/profile.php', $currenturl);

        // ── Manager items ────────────────────────────────
        if ($this->ismanager) {
            $items[] = $this->divider();
            $items[] = $this->item('My Team', 'fa-users', '/local/airpay_manager/index.php', $currenturl);
        }

        // ── L&D Admin items ──────────────────────────────
        if ($this->isldadmin || $this->issiteadmin) {
            $items[] = $this->divider();
            $items[] = $this->item('Manage Users', 'fa-user-cog', '/local/airpay_users/index.php', $currenturl);
            $items[] = $this->item('Manage Courses', 'fa-folder-open', '/local/airpay_catalog/index.php', $currenturl);
            $items[] = $this->item('Reports', 'fa-chart-bar', '/blocks/learnerscript/managereport.php', $currenturl);
            $items[] = $this->item('Compliance', 'fa-shield-alt', '/local/airpay_compliance_report/index.php', $currenturl);
            $items[] = $this->item('Analytics', 'fa-chart-line', '/local/airpay_analytics/index.php', $currenturl);
        }

        // ── Siteadmin items ──────────────────────────────
        if ($this->issiteadmin) {
            $items[] = $this->divider();
            $items[] = $this->item('Emails', 'fa-envelope', '/local/airpay_emails/manage.php', $currenturl);
            $items[] = $this->item('Privacy', 'fa-lock', '/local/airpay_privacy/index.php', $currenturl);
            $items[] = $this->item('Site Admin', 'fa-cog', '/admin/search.php', $currenturl);
        }

        return $items;
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
        return ['type' => 'divider'];
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
