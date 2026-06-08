<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant settings accessor — single read-through cache for all per-tenant
 * branding / email / hero / CSS fields.
 *
 * Use this instead of poking columns of local_sentientia_org directly.
 *
 * @package local_sentientia_org
 */
class tenant_settings {

    /** Cached per-tenant rows for the current request. */
    private static array $cache = [];

    /**
     * Get the settings row for the user's current tenant (or for a specific
     * tenant if $tenantid is provided).
     */
    public static function for_current_user(?\stdClass $user = null): \stdClass {
        $tenantid = tenant_manager::get_tenant_id($user);
        return self::for_tenant((int) $tenantid);
    }

    public static function for_tenant(int $tenantid): \stdClass {
        if ($tenantid <= 0) {
            return self::defaults();
        }
        if (isset(self::$cache[$tenantid])) {
            return self::$cache[$tenantid];
        }
        global $DB;
        $row = $DB->get_record('local_sentientia_org', ['id' => $tenantid]);
        if (!$row) {
            return self::defaults();
        }
        $merged = self::merge_with_defaults($row);
        self::$cache[$tenantid] = $merged;
        return $merged;
    }

    /** Logo URL — uses existing branding_manager logic. */
    public static function logo_url(?\stdClass $user = null): string {
        return branding_manager::get_tenant_logo($user);
    }

    /** Favicon URL (similar pattern). */
    public static function favicon_url(?\stdClass $user = null): string {
        global $CFG;
        $tenantid = tenant_manager::get_tenant_id($user);
        if ($tenantid <= 0) return '';
        $settings = self::for_tenant($tenantid);
        if (empty($settings->favicon)) return '';

        global $DB;
        $filerec = $DB->get_record_sql(
            "SELECT filename FROM {files}
              WHERE itemid = :iid AND filename != '.'
                AND component = 'local_sentientia_org'
                AND filearea = 'favicon' AND filesize > 0 LIMIT 1",
            ['iid' => $settings->favicon]);
        if (!$filerec) return '';
        $syscontextid = \context_system::instance()->id;
        return $CFG->wwwroot . '/pluginfile.php/' . $syscontextid
             . '/local_sentientia_org/favicon/' . $settings->favicon
             . '/' . rawurlencode($filerec->filename);
    }

    /**
     * Footer HTML for the user's tenant. Falls back to site-wide footer.
     */
    public static function footer_html(?\stdClass $user = null): string {
        $settings = self::for_current_user($user);
        $text = trim($settings->footer_text ?? '');
        if ($text !== '') {
            return format_text($text, FORMAT_HTML, ['noclean' => true]);
        }
        return '';
    }

    /** Email-from name + address for outgoing messages. */
    public static function email_from(?\stdClass $user = null): array {
        $s = self::for_current_user($user);
        return [
            'name' => $s->email_from_name ?: '',
            'addr' => $s->email_from_addr ?: '',
        ];
    }

    public static function support_email(?\stdClass $user = null): string {
        return self::for_current_user($user)->support_email ?: '';
    }

    public static function help_url(?\stdClass $user = null): string {
        return self::for_current_user($user)->help_url ?: '';
    }

    /** Hero strings for login page (Public tenant typically customises). */
    public static function hero(?\stdClass $user = null): array {
        $s = self::for_current_user($user);
        return [
            'title'    => $s->hero_title ?: '',
            'subtitle' => $s->hero_subtitle ?: '',
        ];
    }

    /** Custom CSS for current user's tenant — injected into <head>. */
    public static function custom_css(?\stdClass $user = null): string {
        $css = self::for_current_user($user)->custom_css ?? '';
        return trim($css);
    }

    /** Apply brand colours as CSS variable overrides (added to <head>). */
    public static function brand_color_overrides(?\stdClass $user = null): string {
        $s = self::for_current_user($user);
        $brand  = $s->brand_color  ?? '';
        $button = $s->button_color ?? '';
        $hover  = $s->hover_color  ?? '';
        if ($brand === '' && $button === '' && $hover === '') {
            return '';
        }
        $css = ':root {';
        if ($brand !== '')  $css .= "--ap-color-primary: {$brand};";
        if ($button !== '') $css .= "--ap-color-button: {$button};";
        if ($hover !== '')  $css .= "--ap-color-primary-dark: {$hover};";
        $css .= '}';
        return $css;
    }

    /**
     * Defaults when no tenant row found.
     */
    private static function defaults(): \stdClass {
        return (object) [
            'org_logo'        => 0,
            'favicon'         => 0,
            'brand_color'     => '#0066A7',
            'button_color'    => '#0066A7',
            'hover_color'     => '#004d80',
            'theme_scheme'    => '',
            'email_from_name' => '',
            'email_from_addr' => '',
            'support_email'   => '',
            'help_url'        => '',
            'footer_text'     => '',
            'hero_title'      => '',
            'hero_subtitle'   => '',
            'custom_css'      => '',
        ];
    }

    private static function merge_with_defaults(\stdClass $row): \stdClass {
        $defaults = self::defaults();
        foreach (get_object_vars($defaults) as $k => $v) {
            if (!isset($row->$k) || $row->$k === null || $row->$k === '') {
                $row->$k = $v;
            }
        }
        return $row;
    }
}
