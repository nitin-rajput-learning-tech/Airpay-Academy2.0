<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_airpayux\output\traits;

defined('MOODLE_INTERNAL') || die();

/**
 * Branding-asset accessors: logos, carousel image, login text, login
 * ordering preference, and the four colour palette helpers.
 *
 * Extracted from `core_renderer.php` in Phase 9.5 engineering item 14
 * (decomposition pass 3). All methods consult either
 * `\local_airpay_org\branding_manager` (the canonical per-tenant
 * branding source) or the theme's own settings.
 *
 * Methods provided:
 *   should_display_navbar_logo(): bool     — does the navbar render a logo?
 *   get_custom_logo():            string   — logo URL (tenant-specific)
 *   carousellogo():               string   — login carousel image URL
 *   loginlogo():                  string   — login page logo URL
 *   logintext():                  string   — login description copy
 *   loginordering():              bool     — login layout order preference
 *   get_primarycolor():           string   — brand colour (#hex)
 *   get_secondarycolor():         string   — button colour (#hex)
 *   get_hovercolor():             string   — hover colour (#hex)
 *   getsitecolors_link():         string   — legacy epsilon-style link
 *                                            (returns '' post-fork; kept
 *                                            for template compatibility)
 *
 * @package theme_airpayux
 */
trait branding_assets {

    /**
     * True when the navbar should render a logo (i.e. one is configured).
     */
    public function should_display_navbar_logo(): bool {
        $logopath = \local_airpay_org\branding_manager::get_tenant_logo();
        if (empty($logopath)) {
            $logopath = $this->get_compact_logo_url();
            if (empty($logopath)) {
                $logopath = $this->image_url('default_logo', 'theme_airpayux');
            }
        }
        return !empty($logopath);
    }

    /**
     * Tenant-specific logo URL with a three-step fallback:
     *   1. local_airpay_org branding_manager
     *   2. Moodle compact-logo setting
     *   3. theme_airpayux default_logo image
     */
    public function get_custom_logo(): string {
        $logopath = \local_airpay_org\branding_manager::get_tenant_logo();
        if (empty($logopath)) {
            $logopath = $this->get_compact_logo_url();
            if (empty($logopath)) {
                $logopath = $this->image_url('default_logo', 'theme_airpayux');
            }
        }
        return $logopath;
    }

    /**
     * Carousel logo URL (login page carousel header).
     */
    public function carousellogo(): string {
        $carousellogo = $this->page->theme->setting_file_url(
            'carousellogo', 'carousellogo');
        if (empty($carousellogo)) {
            $carousellogo = $this->image_url('carousel_logo', 'theme_airpayux');
        }
        return $carousellogo;
    }

    /**
     * Login page logo URL.
     */
    public function loginlogo(): string {
        $loginlogo = $this->page->theme->setting_file_url(
            'loginlogo', 'loginlogo');
        if (empty($loginlogo)) {
            $loginlogo = $this->image_url('login_logo', 'theme_airpayux');
        }
        return $loginlogo;
    }

    /**
     * Login description copy from theme settings (truncated at 600 chars).
     */
    public function logintext(): string {
        $logintext = $this->page->theme->settings->logindesc ?? '';
        if (empty($logintext)) {
            return '';
        }
        if (strlen($logintext) > 600) {
            $logintext = substr($logintext, 0, 600) . '...';
        }
        return $logintext;
    }

    /**
     * Login layout-order preference (admin-configurable boolean).
     * Originally accepted an unused `$value` parameter; trait drops it.
     */
    public function loginordering(): bool {
        $order = get_config('theme_airpayux', 'loginorder');
        return $order != 0;
    }

    /**
     * Primary brand colour (#hex). Sourced from tenant-specific
     * `\local_airpay_org\branding_manager::get_brand_colors()` so the
     * Public tenant gets purple, Airpay tenant gets blue, etc.
     */
    public function get_primarycolor(): string {
        $colors = \local_airpay_org\branding_manager::get_brand_colors();
        return $colors->brand_color;
    }

    /**
     * Secondary / button colour.
     */
    public function get_secondarycolor(): string {
        $colors = \local_airpay_org\branding_manager::get_brand_colors();
        return $colors->button_color;
    }

    /**
     * Hover state colour.
     */
    public function get_hovercolor(): string {
        $colors = \local_airpay_org\branding_manager::get_brand_colors();
        return $colors->hover_color;
    }

    /**
     * Legacy compatibility — Epsilon theme rendered a `<link rel>`
     * pointing at a per-site colour stylesheet. The fork (Phase 6B)
     * dropped that mechanism in favour of inline tenant CSS injected
     * by `standard_head_html()`. This method remains for templates
     * that still reference it and always returns ''.
     */
    public function getsitecolors_link(): string {
        return '';
    }
}
