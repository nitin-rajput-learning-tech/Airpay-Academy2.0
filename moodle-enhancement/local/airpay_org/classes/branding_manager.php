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

namespace local_airpay_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Branding manager — org logos, colour schemes, theme per-tenant.
 *
 * Replaces the costcenter class methods and costcenter_logo() function
 * from BizLMS local/costcenter/lib.php.
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class branding_manager {

    /**
     * Get logo URL for an org by its logo item ID.
     *
     * Replaces: costcenter_logo($logoid) from local/costcenter/lib.php
     *
     * Checks files with component='local_airpay_org' first, then falls
     * back to 'local_costcenter' for transition compatibility.
     *
     * @param int $itemid  The file item ID (stored in org_logo / costcenter_logo field)
     * @return string  Logo URL or empty string
     */
    public static function get_logo_url(int $itemid): string {
        global $DB;

        if (empty($itemid)) {
            return '';
        }

        // Try local_airpay_org component first.
        $url = self::resolve_logo_file($itemid, 'local_airpay_org', 'org_logo');
        if (!empty($url)) {
            return $url;
        }

        // Fallback: BizLMS component (logos uploaded before fork).
        $url = self::resolve_logo_file($itemid, 'local_costcenter', 'costcenter_logo');
        if (!empty($url)) {
            return $url;
        }

        return '';
    }

    /**
     * Get logo URL for the current user's tenant.
     *
     * Replaces the duplicated logo-fetching logic in core_renderer.php
     * (should_display_navbar_logo + get_custom_logo methods).
     *
     * @param object|null $user
     * @return string  Logo URL or empty string
     */
    public static function get_tenant_logo(?object $user = null): string {
        $tenantid = tenant_manager::get_tenant_id($user);
        if ($tenantid <= 0) {
            return '';
        }

        $org = org_manager::get($tenantid);
        if (!$org) {
            return '';
        }

        // Try org_logo field (new) then costcenter_logo (legacy).
        $logoid = $org->org_logo ?? $org->costcenter_logo ?? 0;
        if (empty($logoid)) {
            return '';
        }

        return self::get_logo_url((int) $logoid);
    }

    /**
     * Get the colour scheme CSS URL for the current user's org.
     *
     * Replaces: core_renderer->get_costcenter_scheme_css()
     * And: new costcenter()->get_costcenter_theme()
     *
     * Returns the theme scheme identifier (not a CSS URL — the renderer
     * uses this as a class name on the body element).
     *
     * @return string|false  Scheme name or false
     */
    public static function get_org_theme_scheme() {
        global $USER;

        $tenantid = tenant_manager::get_tenant_id();
        if ($tenantid <= 0) {
            return false;
        }

        $org = org_manager::get($tenantid);
        if (!$org) {
            return false;
        }

        return $org->theme_scheme ?? false;
    }

    /**
     * Get brand colours for a tenant.
     *
     * @param int|null $orgid  (null = current user's tenant)
     * @return object  {brand_color, button_color, hover_color}
     */
    public static function get_brand_colors(?int $orgid = null): object {
        if ($orgid === null) {
            $orgid = tenant_manager::get_tenant_id();
        }

        $defaults = (object) [
            'brand_color'  => '#0066A7',
            'button_color' => '#0066A7',
            'hover_color'  => '#004d80',
        ];

        if ($orgid <= 0) {
            return $defaults;
        }

        $org = org_manager::get($orgid);
        if (!$org) {
            return $defaults;
        }

        return (object) [
            'brand_color'  => $org->brand_color ?: $defaults->brand_color,
            'button_color' => $org->button_color ?: $defaults->button_color,
            'hover_color'  => $org->hover_color ?: $defaults->hover_color,
        ];
    }

    /**
     * Build CSS class string for body element based on org scheme.
     *
     * Replaces: core_renderer->get_my_scheme()
     *
     * @return string  e.g. "organization_airpay" or empty
     */
    public static function get_body_scheme_class(): string {
        $scheme = self::get_org_theme_scheme();
        if (empty($scheme)) {
            return '';
        }
        return 'organization_' . $scheme;
    }

    /**
     * Resolve a logo file to a pluginfile URL.
     *
     * @param int    $itemid
     * @param string $component  e.g. 'local_airpay_org' or 'local_costcenter'
     * @param string $filearea   e.g. 'org_logo' or 'costcenter_logo'
     * @return string  URL or empty string
     */
    private static function resolve_logo_file(int $itemid, string $component, string $filearea): string {
        global $DB, $CFG;

        $filerec = $DB->get_record_sql(
            "SELECT id, contenthash, filename, contextid
               FROM {files}
              WHERE itemid = :iid
                AND filename != '.'
                AND component = :comp
                AND filearea = :area
                AND filesize > 0
              LIMIT 1",
            ['iid' => $itemid, 'comp' => $component, 'area' => $filearea]
        );

        if (!$filerec) {
            return '';
        }

        // Verify physical file exists on disk.
        $h = $filerec->contenthash;
        $physpath = $CFG->dataroot . '/filedir/'
                  . substr($h, 0, 2) . '/' . substr($h, 2, 2) . '/' . $h;

        if (!file_exists($physpath)) {
            return '';
        }

        // Build pluginfile URL.
        $syscontextid = \context_system::instance()->id;
        return $CFG->wwwroot . '/pluginfile.php/' . $syscontextid
             . '/' . $component . '/' . $filearea . '/' . $itemid
             . '/' . rawurlencode($filerec->filename);
    }
}
