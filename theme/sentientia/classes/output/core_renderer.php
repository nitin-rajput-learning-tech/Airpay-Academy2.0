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

namespace theme_sentientia\output;

use moodle_url;
use html_writer;
use get_string;
use context_system;
use core_component;
use context_course;
use core_completion\progress;
use coding_exception;
use tabobject;
use tabtree;
use custom_menu_item;
use custom_menu;
use block_contents;
use navigation_node;
use action_link;
use stdClass;
use preferences_groups;
use action_menu;
use help_icon;
use single_button;
use pix_icon;

use paging_bar;
use context_user;
use context_coursecat;
use action_menu_filler;
use action_menu_link_secondary;
use core_text;
use user_picture;
use theme_config;
defined('MOODLE_INTERNAL') || die;

/**
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_sentientia
 * @copyright  2012 Bas Brands, www.basbrands.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \core_renderer {

    // Phase 9.5 — decomposition started. Each trait is a self-contained
    // group of renderer methods extracted from this previously 2,339-line
    // monolithic class. See docs/SUPP-A row A3 for the rationale.
    use \theme_sentientia\output\traits\branding_buttons;
    use \theme_sentientia\output\traits\login_ui;
    use \theme_sentientia\output\traits\branding_assets;
    use \theme_sentientia\output\traits\login_render;
    use \theme_sentientia\output\traits\context_header;
    use \theme_sentientia\output\traits\course_view;
    use \theme_sentientia\output\traits\user_menu;
    use \theme_sentientia\output\traits\page_helpers;

    /**
     * Override standard_head_html to inject tenant favicon, custom CSS,
     * and brand colour overrides. Each per-tenant setting is pulled from
     * local_sentientia_org\tenant_settings — falls back gracefully if the
     * org plugin or row doesn't exist yet.
     *
     * Added 2026-05-11 for Phase 1G (per-tenant settings).
     */
    public function standard_head_html() {
        $output = parent::standard_head_html();

        // ── 2026-05-23 — Workstream 0 (per-customer branding) ─────────
        // ADR-008 customer_brand resolver was shipped in commit
        // 1e4c9c1ea (#143) but never consumed by the theme. Wire it now
        // so Sentientia is genuinely white-labelable for Customer 2
        // tomorrow (Enterprise N) — when a new row lands in
        // local_sentientia_customer_brand with different theme_color +
        // bg_color, the whole stack re-tints without any code change.
        //
        // For Phase 0/1 (Airpay-only), the bundle's theme_color +
        // bg_color match the SCSS defaults, so this is a visual no-op.
        // The structural value is the wiring, not the immediate effect.
        //
        // Cascade order:
        //   1. SCSS defaults (compiled stylesheet)
        //   2. Customer brand CSS vars (this block — ALL users of customer)
        //   3. Tenant brand CSS vars (next block below — tenant within customer)
        //   4. Per-page inline overrides (rare)
        // Later-in-document wins on equal specificity, so customer
        // injects FIRST and tenant can override per-tenant.
        if (class_exists('\\local_sentientia_platform\\customer')) {
            try {
                $brand = \local_sentientia_platform\customer::branding();
                $themecolor = $brand['theme_color'] ?? '';
                $bgcolor    = $brand['bg_color']    ?? '';
                $css = '';
                if ($themecolor !== '' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $themecolor)) {
                    // Wire customer primary into the theme's brand
                    // primary token. SCSS rules already consume the
                    // variable form `var(--ap-color-primary, #0066A7)`
                    // throughout _surface-profile.scss et al.
                    $css .= ":root { --ap-color-primary: {$themecolor}; }\n";
                }
                if ($bgcolor !== '' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $bgcolor)) {
                    $css .= ":root { --ap-color-bg: {$bgcolor}; }\n";
                }
                if ($css !== '') {
                    $output .= "\n<style id=\"sentientia-customer-brand\">\n"
                        . $css . "</style>\n";
                }
                // Customer favicon (icon_192_url) — only inject if no
                // tenant-favicon override is about to kick in below.
                // Sniff via the same class_exists guard.
                if (!class_exists('\\local_sentientia_org\\tenant_settings')
                        || \local_sentientia_org\tenant_settings::favicon_url() === '') {
                    $icon = $brand['icon_192_url'] ?? '';
                    if ($icon !== '' && filter_var($icon, FILTER_VALIDATE_URL)) {
                        $tag = '<link rel="icon" href="' . s($icon) . '">';
                        if (preg_match('#<link[^>]+rel=["\']icon["\'][^>]*>#i', $output)) {
                            $output = preg_replace('#<link[^>]+rel=["\']icon["\'][^>]*>#i',
                                $tag, $output, 1);
                        } else {
                            $output .= "\n" . $tag . "\n";
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Customer-brand failure must not break page rendering —
                // the SCSS fallback values cover the gap.
                debugging('standard_head_html: customer_brand wiring failed: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if (class_exists('\\local_sentientia_org\\tenant_settings')) {
            $brand_css  = \local_sentientia_org\tenant_settings::brand_color_overrides();
            $custom_css = \local_sentientia_org\tenant_settings::custom_css();
            $inline = trim($brand_css . "\n" . $custom_css);
            if ($inline !== '') {
                $output .= "\n<style id=\"airpay-tenant-css\">\n" . $inline . "\n</style>\n";
            }

            $favicon_url = \local_sentientia_org\tenant_settings::favicon_url();
            if ($favicon_url !== '') {
                $tag = '<link rel="icon" href="' . s($favicon_url) . '">';
                if (preg_match('#<link[^>]+rel=["\']icon["\'][^>]*>#i', $output)) {
                    $output = preg_replace('#<link[^>]+rel=["\']icon["\'][^>]*>#i',
                        $tag, $output, 1);
                } else {
                    $output .= "\n" . $tag . "\n";
                }
            }
        }

        // ── 2026-05-20 — wire ux.darkMode.enabled feature flag (universal) ──
        // Lives in standard_head_html() because it runs on EVERY page
        // regardless of which layout the page uses. The earlier attempt
        // in airpay_shell_end() only fired for layouts that called that
        // method (columns2 etc.), missing /my/ (dashboard layout) and
        // others. <head> emission means the CSS hits before body renders
        // (button never visually appears) and the JS forces light theme
        // before any paint.
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            $darkmodeenabled = true;
            try {
                $darkmodeenabled = \local_sentientia_platform\feature_flags::is_enabled('ux.darkMode.enabled');
            } catch (\Throwable $e) {
                debugging('standard_head_html: ux.darkMode.enabled lookup failed: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
            if (!$darkmodeenabled) {
                $output .= "\n"
                    . '<style id="airpay-darkmode-killswitch">'
                    .   '#ap-dark-toggle, .ap-dark-toggle, [data-action="dark-toggle"]'
                    .   ' { display: none !important; }'
                    . '</style>'
                    . "\n"
                    . '<script id="airpay-darkmode-killswitch-js">'
                    .   '(function(){'
                    .     'var html=document.documentElement;'
                    .     'html.setAttribute("data-theme","light");'
                    .     'html.classList.remove("dark-mode");'
                    .     'document.addEventListener("DOMContentLoaded",function(){'
                    .       'if(document.body){document.body.classList.remove("dark-mode");}'
                    .     '});'
                    .     'try{localStorage.setItem("airpay-theme","light");}catch(e){}'
                    .   '})();'
                    . '</script>'
                    . "\n";
            }
        }

        return $output;
    }

    /**
     * Override standard_footer_html to:
     *   1. Append tenant-specific footer text (legacy behaviour)
     *   2. Strip the "Moodle Docs for this page" helplink — Sentientia
     *      LMS is product-branded; the docs link reveals the underlying
     *      Moodle deployment to end users.
     *   3. Rewrite any remaining "Moodle" mentions to "Sentientia LMS"
     *      so the footer reads cleanly when admin debug/performance
     *      blocks render.
     *
     * 2026-05-22 — added the Moodle-string scrub. Per ADR-001, the
     * product is Sentientia LMS — visible mentions of "Moodle" leak
     * the underlying engine which the customer (Airpay) hasn't asked
     * to surface. GPL §5 attribution is satisfied by the persistent
     * "Licensed under GPL v3" badge in the airpay-footer__product-
     * attribution band (footer.mustache:52-59).
     */
    public function standard_footer_html() {
        $tenant_footer = '';
        if (class_exists('\\local_sentientia_org\\tenant_settings')) {
            $tenant_footer = \local_sentientia_org\tenant_settings::footer_html();
        }
        $standard = parent::standard_footer_html();

        // Drop the "Moodle Docs for this page" link entirely (the
        // helplink class wraps a <a href="moodle.org/...">).
        $standard = preg_replace(
            '#<div class="helplink">.*?</div>#s', '', $standard) ?? $standard;

        // Rewrite remaining "Moodle" → "Sentientia LMS" for any
        // performance / debug blocks Moodle injects.
        $standard = str_replace(
            ['Moodle '],
            ['Sentientia LMS '],
            $standard);

        if ($tenant_footer !== '') {
            return '<div class="airpay-tenant-footer container py-2">' . $tenant_footer
                . '</div>' . $standard;
        }
        return $standard;
    }

    /**
     * Render the sidebar shell opening: sidebar + topbar + content area open tag.
     *
     * Call this from ANY layout template via {{{output.airpay_shell_start}}}.
     * Pair with airpay_shell_end(). The sidebar HTML is defined ONCE here —
     * no duplication across templates.
     *
     * @return string HTML
     */
    public function airpay_shell_start(): string {
        global $USER, $CFG;

        // Only show sidebar for logged-in non-guest users.
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        $sidebarnav = new \theme_sentientia\sidebar_navigation($this->page);
        $context = $sidebarnav->get_context();

        // Role switcher — surface the BizLMS multi-role switch on EVERY app-shell
        // page, not just the dashboard. The user_menu trait builds the same
        // options the dashboard sidebar uses; single-/no-extra-role users get
        // hasoptions=false so the block renders nothing (sidebar unchanged).
        $context['roleswitch'] = $this->get_role_switch_options();

        // Render the sidebar partial.
        $sidebarhtml = $this->render_from_template('theme_sentientia/sidebar', $context);

        // Build the topbar.
        // Audit fix 2026-05-15 — context-aware search. The form action +
        // placeholder default to Moodle global search; the JS in
        // airpay_shell_end() rewires both when the current page exposes a
        // scoped search affordance (.airpay-catalog__search-form OR
        // [data-airpay-table]). The form/input carry id + data-default-*
        // attributes the JS needs to find and reset them.
        $searchurl = $CFG->wwwroot . '/search/index.php';
        $defaultplaceholder = 'Search courses, people, content...';
        $topbar = '<header class="ap-topbar" id="ap-topbar">'
                . '<div class="ap-topbar__left">'
                . '<button class="ap-topbar__hamburger d-lg-none" id="ap-sidebar-mobile-toggle" aria-label="Open menu"><i class="fa fa-bars"></i></button>'
                . '</div>'
                . '<div class="ap-topbar__center">'
                . '<form action="' . s($searchurl) . '" method="get"'
                . ' class="ap-topbar__search" id="ap-topbar-search-form"'
                . ' data-default-action="' . s($searchurl) . '"'
                . ' data-default-placeholder="' . s($defaultplaceholder) . '">'
                . '<i class="fa fa-search ap-topbar__search-icon"></i>'
                . '<input type="text" name="q" class="ap-topbar__search-input"'
                . ' id="ap-topbar-search-input"'
                . ' placeholder="' . s($defaultplaceholder) . '" autocomplete="off">'
                . '</form>'
                . '</div>'
                . '<div class="ap-topbar__right"></div>'
                . '</header>';

        // A11Y-8: inject a visually-hidden <h1> with the current page title
        // so every page has a top-level heading for screen readers, regardless
        // of whether the plugin template renders an h1 or h2 for the visible
        // title. The .sr-only class hides it visually but keeps it in the
        // accessibility tree.
        $page_title = $this->page->title ?: format_string($this->page->heading);
        $h1 = '<h1 class="sr-only">' . s($page_title) . '</h1>';

        // A11Y-9: keep ONE main landmark. The outer ap-shell__content is
        // <main> — that's the single document main. Moodle's inner
        // <section id="region-main" aria-label="content"> is a region
        // landmark (sectioning content with aria-label), not a main, so
        // there's no duplicate. The `landmark-no-duplicate-main` finding
        // from the prior audit was about an older Moodle build that emitted
        // role="main" on region-main; current Moodle 5.1 emits <section>.
        // Audit fix 2026-05-15 — admin tabs CSS shim.
        //
        // Two distinct bugs on Moodle 5.x tabbed admin pages
        // (/admin/search.php, the plugins-check page, BizLMS user profile
        // tabs, etc.) — both fixed here so the shell takes care of them
        // once for every layout:
        //
        // 1) The served theme CSS hides every `.tab-pane` with display:none
        //    but never restores display:block for the active one — clicks
        //    moved the tablist underline (via the JS shim in
        //    airpay_shell_end()) but the actual content never switched.
        //
        // 2) A legacy `.nav-tabs { position:fixed; width:calc(100% - 35px);
        //    background:#fff; box-shadow:... }` rule (inherited from the
        //    BizLMS profile-tabs styling) escaped its parent scope and
        //    applied to every `.nav-tabs` on the page. That positioned the
        //    site-admin tablist outside the visible viewport and gave it
        //    a white background that fights dark mode. We reset to
        //    `position:static; background:transparent` so the tablist
        //    flows inline with the heading above it.
        //
        // !important is intentional defence-in-depth, not careless
        // specificity escalation — the goal is for these rules to survive
        // late-loading overrides like compiled sentientia SCSS bundles.
        $tabcss = '<style>'
                . '.tab-content > .tab-pane{display:none !important}'
                . '.tab-content > .tab-pane.active{display:block !important}'
                . '.nav-tabs{position:static !important;width:auto !important;'
                . 'background-color:transparent !important;'
                . 'box-shadow:none !important;padding-top:0 !important}'
                . '</style>';

        return '<div class="ap-shell" id="ap-shell">'
             . $sidebarhtml
             . '<div class="ap-shell__overlay" id="ap-shell-overlay"></div>'
             . '<div class="ap-shell__main">'
             . $topbar
             . $tabcss
             . '<main class="ap-shell__content" id="ap-shell-content">'
             . $h1;
    }

    /**
     * Render the sidebar shell closing: close content + close shell + JS.
     *
     * @return string HTML
     */
    public function airpay_shell_end(): string {
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        // ── 2026-05-20 — wire ux.darkMode.enabled feature flag ──
        // Phase A0 registered the flag but no consumer read it, so toggling
        // it from the Switchboard had no effect. Now: when the flag is OFF
        // we (a) hide every dark-mode toggle button via CSS, (b) force the
        // light theme on page load, (c) clear any persisted dark preference
        // from localStorage, (d) skip wiring the toggle JS handler entirely.
        // Resolves the orphan-flag bug. See Switchboard "Sentientia Platform"
        // category (eventually) — for now the flag lives in the "UX" group.
        $darkmodeenabled = true;
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            try {
                $darkmodeenabled = \local_sentientia_platform\feature_flags::is_enabled('ux.darkMode.enabled');
            } catch (\Throwable $e) {
                // Defensive: never break page render if the resolver hiccups.
                debugging('airpay_shell_end: ux.darkMode.enabled lookup failed: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $darkmodekillswitch = '';
        if (!$darkmodeenabled) {
            $darkmodekillswitch = '<style>'
                . '#ap-dark-toggle, .ap-dark-toggle, [data-action="dark-toggle"] '
                . '{ display: none !important; }'
                . '</style>'
                . '<script>'
                . '(function(){'
                .   'var html=document.documentElement;'
                .   'html.setAttribute("data-theme","light");'
                .   'html.classList.remove("dark-mode");'
                .   'if(document.body){document.body.classList.remove("dark-mode");}'
                .   'try{localStorage.setItem("airpay-theme","light");}catch(e){}'
                . '})();'
                . '</script>';
        }

        $js = <<<'JS'
<script>
(function() {
    var sidebar = document.getElementById('ap-sidebar');
    var toggle = document.getElementById('ap-sidebar-toggle');
    var mobileToggle = document.getElementById('ap-sidebar-mobile-toggle');
    var overlay = document.getElementById('ap-shell-overlay');
    var darkToggle = document.getElementById('ap-dark-toggle');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            var collapsed = sidebar.getAttribute('data-collapsed') === 'true';
            sidebar.setAttribute('data-collapsed', collapsed ? 'false' : 'true');
            toggle.querySelector('.ap-sidebar__toggle-icon').style.transform = collapsed ? '' : 'rotate(180deg)';
            if (typeof require !== 'undefined') {
                require(['core_user/repository'], function(UserRepo) {
                    UserRepo.setUserPreference('theme_sentientia_sidebar_collapsed', collapsed ? '0' : '1');
                });
            }
        });
    }
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() { sidebar.classList.toggle('ap-sidebar--mobile-open'); if (overlay) overlay.classList.toggle('ap-shell__overlay--visible'); });
    }
    if (overlay) { overlay.addEventListener('click', function() { if (sidebar) sidebar.classList.remove('ap-sidebar--mobile-open'); overlay.classList.remove('ap-shell__overlay--visible'); }); }
    if (darkToggle) {
        var html = document.documentElement;
        var isDark = html.getAttribute('data-theme') === 'dark' || html.classList.contains('dark-mode');
        darkToggle.querySelector('i').className = isDark ? 'fa fa-sun' : 'fa fa-moon';
        var label = darkToggle.querySelector('.ap-sidebar__theme-label');
        if (label) label.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        darkToggle.addEventListener('click', function() {
            var nowDark = html.getAttribute('data-theme') === 'dark' || html.classList.contains('dark-mode');
            html.setAttribute('data-theme', nowDark ? 'light' : 'dark');
            html.classList.toggle('dark-mode', !nowDark);
            document.body.classList.toggle('dark-mode', !nowDark);
            localStorage.setItem('airpay-theme', nowDark ? 'light' : 'dark');
            darkToggle.querySelector('i').className = nowDark ? 'fa fa-moon' : 'fa fa-sun';
            if (label) label.textContent = nowDark ? 'Dark Mode' : 'Light Mode';
        });
    }

    // ── Audit fix 2026-05-15 — Context-aware topbar search ──
    // Many pages (catalog, every admin list table) ALSO render their own
    // search bar, producing two visually identical search boxes that
    // serve different purposes. This block detects what kind of scoped
    // search the current page exposes and rewires the topbar to drive it
    // — then hides the redundant scoped UI so only one input is visible.
    //
    // Detection order:
    //   1. Catalog form-based search (.airpay-catalog__search-form). The
    //      topbar form action is repointed to the catalog URL so submit
    //      navigates with ?q=... and the catalog index reads it.
    //   2. Datatable JS-driven search ([data-airpay-table] root). The
    //      topbar drives the datatable's internal input via input events,
    //      reusing the existing debounce + AJAX path in datatable.js
    //      unchanged. The datatable's own search box is hidden once it
    //      renders (MutationObserver — the datatable JS may not have run
    //      when this script first executes).
    //   3. Nothing scoped — default behaviour (Moodle global search).
    var topbarForm = document.getElementById('ap-topbar-search-form');
    var topbarInput = document.getElementById('ap-topbar-search-input');
    if (topbarForm && topbarInput) {
        var catalogForm = document.querySelector('.airpay-catalog__search-form');
        var tableRoot = document.querySelector('[data-airpay-table]');
        if (catalogForm) {
            var action = catalogForm.getAttribute('action');
            if (action) { topbarForm.setAttribute('action', action); }
            var catalogInput = catalogForm.querySelector('input[name="q"]');
            if (catalogInput) {
                if (catalogInput.getAttribute('placeholder')) {
                    topbarInput.setAttribute('placeholder', catalogInput.getAttribute('placeholder'));
                }
                if (catalogInput.value) { topbarInput.value = catalogInput.value; }
            }
            var scopedSection = document.querySelector('.airpay-catalog__search-section');
            if (scopedSection) { scopedSection.style.display = 'none'; }
        } else if (tableRoot) {
            var customPlaceholder = tableRoot.dataset.searchPlaceholder;
            if (customPlaceholder) { topbarInput.setAttribute('placeholder', customPlaceholder); }
            var driveTableSearch = function(value) {
                var s = tableRoot.querySelector('[data-airpay-table-search]');
                if (s) { s.value = value; s.dispatchEvent(new Event('input', { bubbles: true })); }
            };
            var hideScopedSearchBox = function() {
                var s = tableRoot.querySelector('[data-airpay-table-search]');
                if (s) {
                    var wrapper = s.closest('.airpay-datatable__search');
                    if (wrapper) { wrapper.style.display = 'none'; }
                    return true;
                }
                return false;
            };
            if (!hideScopedSearchBox()) {
                var mo = new MutationObserver(function() { if (hideScopedSearchBox()) { mo.disconnect(); } });
                mo.observe(tableRoot, { childList: true, subtree: true });
            }
            topbarInput.addEventListener('input', function() { driveTableSearch(topbarInput.value); });
            topbarForm.addEventListener('submit', function(e) { e.preventDefault(); driveTableSearch(topbarInput.value); });
        }
        // Pattern 3 (default Moodle global search): no rewire.
    }

    // ── Audit fix 2026-05-15 — Bootstrap tab-pane shim ──
    // Moodle 5.x admin pages (e.g. /admin/search.php) emit Bootstrap 5
    // tab markup: <a href="#linkXXX" data-bs-toggle="tab"> + sibling
    // <div class="tab-pane">. Our standalone sentientia theme ships its
    // own Bootstrap 4.6 fork which only recognises BS4's `data-toggle`
    // attribute (no `bs` prefix), so BS5 tabs only partially work: the
    // active-state underline updates on click, but the tab-pane content
    // never switches — every pane stays visible at once.
    //
    // This vanilla-JS shim wires up `[data-bs-toggle="tab"]` links to
    // do what Bootstrap 5's Tab.show() would: toggle .active + .show on
    // the target pane and clear those classes from siblings. Idempotent
    // — if a real BS5 ever ships and beats us to the click handler, we
    // simply do the same DOM mutation a second time.
    document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(function(link) {
        if (link.dataset.airpayTabShim === '1') { return; }
        link.dataset.airpayTabShim = '1';
        link.addEventListener('click', function(e) {
            var href = this.getAttribute('href') || '';
            if (href.charAt(0) !== '#' || href.length < 2) { return; }
            var target = document.querySelector(href);
            if (!target || !target.classList.contains('tab-pane')) { return; }
            // Hide every sibling pane.
            var container = target.parentElement;
            if (container) {
                container.querySelectorAll(':scope > .tab-pane').forEach(function(p) {
                    p.classList.remove('active', 'show');
                });
            }
            // Show the target.
            target.classList.add('active', 'show');
            // Update active state on the tablist links.
            var tablist = this.closest('[role="tablist"]') || (this.parentElement && this.parentElement.parentElement);
            if (tablist) {
                tablist.querySelectorAll('a[data-bs-toggle="tab"]').forEach(function(t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
            }
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            e.preventDefault();
        });
    });
    // On initial page load, honour the URL hash if it matches a tab.
    if (window.location.hash) {
        var initial = document.querySelector('a[data-bs-toggle="tab"][href="' + window.location.hash + '"]');
        if (initial) { initial.click(); }
    }
})();
</script>
JS;

        return '</div>'  // close .ap-shell__content (was </main>; A11Y-9)
             . '</div>'   // close .ap-shell__main
             . '</div>'   // close .ap-shell
             . $darkmodekillswitch  // empty string when flag ON; CSS+JS killswitch when OFF
             . $js;
    }

    private $enable_edit_switch = true;
    /**
     * Returns HTML to display a "Turn editing on/off" button in a form.
     *
     * @param moodle_url $url The URL + params to send through when clicking the button
     * @param string $method
     * @return string HTML the button
     */
    // edit_button(), seteditswtich_display(), edit_switch(), navbar(),
    // is_admin_or_manager(), is_siteadmin_only() and loggedin_username()
    // moved to \theme_sentientia\output\traits\page_helpers in Engineering 34.

    public function custom_language_menu(){
        $langs = get_string_manager()->get_list_of_translations();
        if(count($langs) > 1){
            $select = (new \core\output\language_menu($this->page))->export_for_single_select($this);
            $select->hasparams = count($_GET);
            $action_url = $select->action."?";
            if(!empty($select->params)){
                foreach($select->params as $key=>$param){
                   if($param['name']=='lang'){
                     continue;
                   }
                   $action_url.=($key==0?"":"&").$param['name'].'='.$param['value']; 
                }
            }
            $select->actionurl = $action_url;
            return $this->render_from_template('theme_sentientia/language_menu_dropdown', $select);
        }
    }
    // context_header() and render_context_header() moved to
    // \theme_sentientia\output\traits\context_header in Engineering 30.

    /**
     * See if this is the first view of the current cm in the session if it has fake blocks.
     *
     * (We track up to 100 cms so as not to overflow the session.)
     * This is done for drawer regions containing fake blocks so we can show blocks automatically.
     *
     * @return boolean true if the page has fakeblocks and this is the first visit.
     */
    public function firstview_fakeblocks(): bool {
        global $SESSION;

        $firstview = false;
        if ($this->page->cm) {
            if (!$this->page->blocks->region_has_fakeblocks('side-pre')) {
                return false;
            }
            if (!property_exists($SESSION, 'firstview_fakeblocks')) {
                $SESSION->firstview_fakeblocks = [];
            }
            if (array_key_exists($this->page->cm->id, $SESSION->firstview_fakeblocks)) {
                $firstview = false;
            } else {
                $SESSION->firstview_fakeblocks[$this->page->cm->id] = true;
                $firstview = true;
                if (count($SESSION->firstview_fakeblocks) > 100) {
                    array_shift($SESSION->firstview_fakeblocks);
                }
            }
        }
        return $firstview;
    }

    /**
     * Displays Leftmenu links added from respective plugins using the function in lib.php as "plugintype_pluginname_leftmenunode()
     * The links are injected in the left menu.
     *
     * @return HTML
     */
    public function left_navigation_quick_links(){
        global $DB, $CFG, $USER, $PAGE;
        $systemcontext = context_system::instance();
        $core_component = new core_component();
        $block_content = '';
        $local_pluginlist = $core_component::get_plugin_list('local');
        $block_pluginlist = $core_component::get_plugin_list('block');

        $block_content .= html_writer::start_tag('ul', array('class'=>'pull-left row-fluid user_navigation_ul'));
            //======= Dasboard link ========//
            $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard'));
                $button1 = html_writer::link($CFG->wwwroot.'/my/dashboard.php', '<i class="fa fa-home" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('leftmenu_dashboard', 'theme_sentientia').'</span>', array('class'=>'user_navigation_link'));
                $block_content .= $button1;
            $block_content .= html_writer::end_tag('li');

            //=======Leader Dasboard link ========//
//             $gamificationb_plugin_exist = $core_component::get_plugin_directory('block', 'gamification');
//             $gamificationl_plugin_exist = $core_component::get_plugin_directory('local', 'gamification');
//             if($gamificationl_plugin_exist && $gamificationb_plugin_exist && (has_capability('local/gamification:view
// ',$systemcontext) || is_siteadmin() )){
//                 $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_gamification_leaderboard', 'class'=>'pull-left user_nav_div notifications'));
//                 $gamification_url = new moodle_url('/blocks/gamification/dashboard.php');
//                 $gamification = html_writer::link($gamification_url, '<i class="fa fa-trophy"></i><span class="user_navigation_link_text">'.get_string('leftmenu_gmleaderboard','theme_sentientia').'</span>',array('class'=>'user_navigation_link'));
//                 $block_content .= $gamification;
//                 $block_content .= html_writer::end_tag('li');
//             }

            $pluginnavs = array();
            foreach($local_pluginlist as $key => $local_pluginname){
                if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
                    $functionname = 'local_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                       // $data = $functionname();
                        $data = (array)$functionname();
                         foreach($data as $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }
            // ksort($pluginnavs);
            // foreach($pluginnavs as $pluginnav){
            //     foreach($pluginnav  as $key => $value){
            //             $data = $value;
            //             $block_content .= $data;
            //     }
            // }

            foreach($block_pluginlist as $key => $local_pluginname){
                 if(file_exists($CFG->dirroot.'/blocks/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/blocks/'.$key.'/lib.php');
                    $functionname = 'block_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                    // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard row-fluid '));
                        $data = $functionname();
                        foreach($data as $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    // $block_content .= html_writer::end_tag('li');
                    }
                }
            }

            $tool_certificate = $core_component::get_plugin_directory('tool', 'certificate');
            if($tool_certificate){
                if(file_exists($CFG->dirroot.'/admin/tool/certificate/lib.php')){
                    require_once($CFG->dirroot.'/admin/tool/certificate/lib.php');
                    $functionname = 'tool_certificate_leftmenunode';
                    if(function_exists($functionname)){
                        $data = $functionname();
                        foreach($data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }

            ksort($pluginnavs);
            foreach($pluginnavs as $pluginnav){
                foreach($pluginnav  as $key => $value){
                        $data = $value;
                        $block_content .= $data;
                }
            }
            /*Site Administration Link*/
            if(is_siteadmin()){
                $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_adminstration', 'class'=>'pull-left user_nav_div adminstration'));
                    $admin_url = new moodle_url('/admin/search.php');
                    $admin = html_writer::link($admin_url, '<i class="fa fa-cogs"></i><span class="user_navigation_link_text">'.get_string('leftmenu_adminstration','theme_sentientia').'</span>',array('class'=>'user_navigation_link'));
                    $block_content .= $admin;
                $block_content .= html_writer::end_tag('li');
            }
        $block_content .= html_writer::end_tag('ul');

        return $block_content;
    }
    /**
     * returns the link of the costcenter scheme css file to load in header of every layout
     * MAY BE CHANGED IN THE COMING VERSIONS
     *
     * @return URL
     */
    function get_costcenter_scheme_css(){
        return \local_sentientia_org\branding_manager::get_org_theme_scheme();
    }
     /**
         * returns the scheme names for theme and costcenter
         *
         * @return string
         */
        function get_my_scheme(){
        global $PAGE;

        $return = '';
        $theme_schemename = $PAGE->theme->settings->theme_scheme ?? '';
        if(!empty($theme_schemename)){
            $return .= ' theme_'.$theme_schemename;
        }
        $orgclass = \local_sentientia_org\branding_manager::get_body_scheme_class();
        if (!empty($orgclass)) {
            $return .= ' ' . $orgclass;
        }

        return $return;
    }
    /**
     * Whether we should display the logo in the navbar.
     *
     * We will when there are no main logos, and we have compact logo.
     *
     * @return bool
     */
    // should_display_navbar_logo(), get_custom_logo(), carousellogo(),
    // loginlogo(), logintext(), loginordering() — moved to
    // \theme_sentientia\output\traits\branding_assets in Phase 9.5
    // engineering item 14 (decomposition pass 3).
    /*
     * returns the images slider for the login page.
     * @author Raghuvaran Komati.
     *
     * @return URL
    */
    // loginslider(), welcometext(), captiontext(), login_stat_users(),
    // login_stat_courses(), login_stat_certs(), login_stat_completion()
    // and get_public_tenant_path() — moved to
    // \theme_sentientia\output\traits\login_ui in Phase 9.5 engineering
    // item 12 (decomposition pass 2). Behaviour preserved.
    //
    // is_admin_or_manager() and is_siteadmin_only() remain inline here
    // because they belong with the user-menu trait that will be
    // extracted in pass 3 (per traits/README.md).
    // (is_admin_or_manager() and is_siteadmin_only() — see page_helpers trait.)
    /**
     * Returns the Help button text of the given helpdesc in theme settings.
     *
     * @return HTML
     */
    // helpbtn(), aboutbtn(), contactbtn(), get_copyright_text(),
    // secure_login_info() and footer_social_icons() — moved to
    // \theme_sentientia\output\traits\branding_buttons in Phase 9.5
    // (engineering item 3, core_renderer decomposition pass 1).
    // Behaviour preserved verbatim; the trait adds null-coalescing for
    // theme settings that aren't yet populated.

    /*
     * returns the Navigtion links for the quick information.
     * @author Raghuvaran Komati
     *
     * @return URL
    */
    public function get_quickLinks() {
        $quickinfo1 = $quickinfo2 = $quickinfo3 = $quickinfo4 = $quickinfo5 = '';
        $quickinfo1 = (empty($this->page->theme->settings->quickinfo1)) ? false : $this->page->theme->settings->quickinfo1;
        $quickinfo2 = (empty($this->page->theme->settings->quickinfo2)) ? false : $this->page->theme->settings->quickinfo2;
        $quickinfo3 = (empty($this->page->theme->settings->quickinfo3)) ? false : $this->page->theme->settings->quickinfo3;
        $quickinfo4 = (empty($this->page->theme->settings->quickinfo4)) ? false : $this->page->theme->settings->quickinfo4;
        $quickinfo5 = (empty($this->page->theme->settings->quickinfo5)) ? false : $this->page->theme->settings->quickinfo5;
        $quickInfo = [
            'quicklinksEnable' => ($this->page->theme->settings->quickinfo == 'no') ? false : true,
            'hasquicklinks' => ($quickinfo1 || $quickinfo2
                 || $quickinfo3  || $quickinfo4 ||  $quickinfo4
                 ) ? true : false,
            'quicklinks' => array(
                'quickinfo1' => $quickinfo1,
                'quickinfo2' => $quickinfo2,
                'quickinfo3' => $quickinfo3,
                'quickinfo4' => $quickinfo4,
                'quickinfo5' => $quickinfo5,
            )
        ];
        // print_object($this->page->theme->settings->quickinfo);
        return $this->render_from_template('theme_sentientia/quickinfo', $quickInfo);
    }
    // render_login() and render_otplogin() moved to
    // \theme_sentientia\output\traits\login_render in Engineering 28.

    /**
     * Wrapper for header elements.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {

        global $USER,$COURSE,$DB, $CFG;

        $data = $this->custom_secured_redirection();
        $pagetype = $this->page->pagetype;
        $homepage = get_home_page();
        $homepagetype = null;
        $context = $this->page->context;
        $courseid = $this->page->course->id;
        // Add a special case since /my/courses is a part of the /my subsystem.
        if ($homepage == HOMEPAGE_MY || $homepage == HOMEPAGE_MYCOURSES) {
            $homepagetype = 'my-index';
        } else if ($homepage == HOMEPAGE_SITE) {
            $homepagetype = 'site-index';
        }
        if ($this->page->include_region_main_settings_in_header_actions() &&
                !$this->page->blocks->is_block_present('settings')) {
            // Only include the region main settings if the page has requested it and it doesn't already have
            // the settings block on it. The region main settings are included in the settings block and
            // duplicating the content causes behat failures.
            $this->page->add_header_action(html_writer::div(
                $this->region_main_settings_menu(),
                'd-print-none',
                ['id' => 'region-main-settings-menu']
            ));
        }
        $show_course_header = false;

        $header=new stdClass();

        if (($context->contextlevel == CONTEXT_COURSE) && $courseid > 1 && $this->courseviewmenu_hidden()){

            $course_extended_menu = $this->course_context_header_settings_menu();

            $show_course_header = true;

            $progress_pct = \local_sentientia_courses\course_manager::get_progress_percentage($courseid, $USER->id);
            $usercourseprogress = ['progress' => $progress_pct];
            // Ratings: prefer Airpay plugin, fall back to BizLMS.
            $display_ratings = null;
            if (class_exists('\local_sentientia_ratings\rating_manager')) {
                $display_ratings = \local_sentientia_ratings\rating_manager::render($courseid, 'local_sentientia_courses');
            } else {
                $ratings_lib = $CFG->dirroot . '/local/ratings/lib.php';
                if (file_exists($ratings_lib)) {
                    require_once($ratings_lib);
                    if (function_exists('display_rating')) {
                        $display_ratings = display_rating($courseid, 'local_sentientia_courses');
                    }
                }
            }
            $header=(object)array_merge((array)$header,$usercourseprogress);
            $header->display_ratings=$display_ratings;
            if(!is_siteadmin()){
                if(isset($COURSE->open_coursecompletiondays) && $COURSE->open_coursecompletiondays != 0)
                {
                    $today = date('Y-m-d');
                    $userenroldate = $DB->get_field_sql("SELECT max(ue.timecreated) as enrolldate 
                            FROM {course} course
                            JOIN {enrol} e ON e.courseid = course.id 
                            JOIN {user_enrolments} ue ON ue.enrolid = e.id
                            JOIN {user} u ON u.id = $USER->id AND course.id = $COURSE->id ");
                      
                    if(!empty($userenroldate)){
                        $userenroldate = date('Y-m-d',$userenroldate);
                        //$userenroldate = '2023-04-12';
                        $difference = strtotime($userenroldate) - strtotime($today);
                        $days = abs($difference/(60 * 60)/24);                       
                       
                        if($days != 0 && $days < $COURSE->open_coursecompletiondays){
                            $duedays = 'Due In : <strong>' .($COURSE->open_coursecompletiondays-$days). ' days </strong>';
                        }else if($days != 0 && $days > $COURSE->open_coursecompletiondays){
                            $duedays = 'Overdue by : ' .abs($COURSE->open_coursecompletiondays-$days). ' days';
                        }
                        if($duedays !=0 ){
                            $display_duedays =' <div class="col-md-3 user_enrollment d-flex align-items-center ">
                                                    <i class="fa fa-calendar"></i>                                                  
                                                    <div class="enroll_details d-flex">
                                                        <span class="details_content text-nowrap"> </span>
                                                        <span class="enroll_number">'.$duedays.'</span>
                                                    </div>
                                                </div> ';
                        }                         
                      
                    }
                 }
            }
        }else{
            $course_extended_menu = $this->context_header_settings_menu();
        }
        $header->settingsmenu = $course_extended_menu;

        // if(!$data->hideheader)
        $header->contextheader = $this->context_header();
        $header->course_summary_data = $this->course_summary_data();
        $header->hasnavbar = empty($this->page->layout_options['nonavbar']);
        $header->navbar = $this->navbar();
        $header->coursebannerimage = $this->course_bannerimage();
        $header->pageheadingbutton = $this->page_heading_button();
        $header->courseheader = $this->course_header();
        $header->display_duedays = !empty($display_duedays) ? $display_duedays : '';        
        $header->headeractions = $this->page->get_header_actions();
        if (!empty($pagetype) && !empty($homepagetype) && $pagetype == $homepagetype) {
            $header->welcomemessage = \core_user::welcome_message();
        }
        $header->courseid = $COURSE->id;
        $header->activityurl =$this->activityurl_get_course();
        return $this->render_from_template($show_course_header? 'theme_sentientia/course_full_header' : 'theme_sentientia/full_header', $header);
    }
        /**
     * return custom course page header buttons to show only on course pages
     *
     * @return HTML
     */
    public function course_context_header_settings_menu(){
        global $PAGE, $COURSE, $DB, $USER;

        $courseid = $COURSE->id;
        $sesskey = sesskey();
        if($courseid < 2){
            return '';
        }

        $return = '';

        $systemcontext = \context_course::instance($courseid);

        $categorycontext = context_coursecat::instance($COURSE->category);

        $admin_default_menu = $is_courseedit_icon = $course_reports = $course_complition = $coursebackup = false;
        $allow_editing = false;
        $editing_url = "";
         if(has_capability('moodle/course:create', $systemcontext) || is_siteadmin()) {
            $admin_default_menu = true;
            $manage = true;
        }
        $useredit = '';
        if ($PAGE->user_is_editing() && $PAGE->user_allowed_editing()) {
            $useredit = 'off';
        }else{
            $useredit = 'on';
        }
        if($this->page->pagetype!='local-catalog-courseinfo') {
            if(!(is_siteadmin() || has_any_capability(['moodle/course:view'], $systemcontext))){
                $manage = false;
                $USER->editing = 0;
            }
            if ($PAGE->user_allowed_editing() && $manage){

                $allow_editing = true;
                $editing_url = new moodle_url('/course/view.php', array('id' => $courseid, 'sesskey'=> $sesskey, 'edit'=>$useredit));
            }
            if((has_capability('moodle/course:create',$systemcontext) ||
                \local_sentientia_courses\course_manager::can_enrol($systemcontext)) && $manage) {
                $is_courseedit_icon = true;
                $course_reports =  true;
                $course_complition = true;
            }

            if((has_capability('moodle/backup:backupcourse',$systemcontext) || is_siteadmin()) && $manage) {
                $coursebackup = true;
            }
            $maincheckcontext = \local_sentientia_org\accesslib::get_module_context();
            if(\local_sentientia_courses\course_manager::can_manage($maincheckcontext)
                && \local_sentientia_courses\course_manager::can_enrol($maincheckcontext)) {
                $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $courseid ,'enrol' => 'manual'));
                $userenrollment = true;
            }
        }
        // if($this->page->pagetype === 'blocks-gamification-index'){
        //     $gamificationpage = true;
        // }else{
        //     $gamificationpage = false;
        // }
        $challenge_element = false;
        $challenge_dir = \core_component::get_plugin_directory('local', 'sentientia_challenge')
                      ?: \core_component::get_plugin_directory('local', 'challenge');
        if (!empty($challenge_dir) && (int)get_config('', 'local_challenge_enable_challenge')) {
            try {
                $render_class = $PAGE->get_renderer('local_sentientia_challenge');
                $challenge_element = $render_class->render_challenge_object('local_sentientia_courses', $courseid);
            } catch (\Throwable $e) {
                $challenge_element = false;
            }
        }
        $gamification_plugin_exist = \core_component::get_plugin_directory('block', 'gamification');
        $gamification_element = false;
        if(!empty($gamification_plugin_exist)){
            $gamification_element = true;
        }


        $course_context = [
            "courseid" => $courseid,
            "admin_default_menu" => $admin_default_menu,
            "default_menu" => $this->context_header_settings_menu(),
            "allow_editing" => $allow_editing,
            "editing_url" => $editing_url,
            "useredit" => $useredit,
            "is_courseedit_icon" => $is_courseedit_icon,
            "course_reports" => $course_reports,
            "course_complition" => $course_complition,
            "coursebackup" => $coursebackup,
            "enrolid" => $enrolid??0,
            "userenrollment" => $userenrollment??false,
            "categorycontextid" =>$categorycontext->id,
            // "gamificationpage" => $gamificationpage,
            "challenge_element" => $challenge_element,
            "gamification_element" => $gamification_element,
            "manage" => $manage,
            'isenrolled' => is_enrolled(context_course::instance($COURSE->id)),
        ];

        if(!is_siteadmin()){
            $switchedrole = isset($USER->access['rsw']['/1'])?$USER->access['rsw']['/1']:"";
            if($switchedrole){
                $userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
            }else{
                $userrole = null;
            }

//            if(is_null($userrole) || $userrole == 'user'){
             if(is_null($userrole) || $userrole == 'employee'){
                $core_component = new core_component();
                $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
                if($certificate_plugin_exist){
                    if(!empty($COURSE->open_certificateid)){
                        $course_context['certificate_exists'] = true;
                        $sql = "SELECT id
                                FROM {course_completions}
                                WHERE course = :courseid AND userid = :userid
                                AND timecompleted IS NOT NULL ";

                        $completed = $DB->record_exists_sql($sql, array('courseid'=>$COURSE->id, 'userid'=>$USER->id));
                        if($completed){

                $certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$COURSE->id,'userid'=>$USER->id,'moduletype'=>'course'));
                            if($certcode == 0){
                                $course_context['certificate_exists'] = false;
                            }
                            $course_context['certificate_download'] = true;
                            $course_context['certificateid'] = $certcode; //$COURSE->open_certificateid;
                            $course_context['moduletype'] = 'course';
                            $course_context['moduleid'] = $COURSE->id;
                        }else{
                            $course_context['certificate_download'] = false;
                        }
                    }
                }
            }
        }

        return $this->render_from_template('theme_sentientia/course_context_header', $course_context);
    }
    function theme_sentientia_user_get_user_navigation_info($user, $page, $options = array()) {
        global $OUTPUT, $DB, $SESSION, $CFG;

        $returnobject = new stdClass();
        $returnobject->navitems = array();
        $returnobject->metadata = array();

        $course = $page->course;

        // Query the environment.
        $context = context_course::instance($course->id);

        // Get basic user metadata.
        // $returnobject->metadata['userid'] = $user->id;
        // $returnobject->metadata['userfullname'] = fullname($user, true);
        // $returnobject->metadata['userprofileurl'] = new moodle_url('/local/user/profile.php', array(
        //     'id' => $user->id
        // ));

        $avataroptions = array('link' => false, 'visibletoscreenreaders' => false);
        if (!empty($options['avatarsize'])) {
            $avataroptions['size'] = $options['avatarsize'];
        }
        $returnobject->metadata['useravatar'] = $OUTPUT->user_picture (
            $user, $avataroptions
        );
        // Build a list of items for a regular user.

        // Query MNet status.
        if ($returnobject->metadata['asmnetuser'] = is_mnet_remote_user($user)) {
            $mnetidprovider = $DB->get_record('mnet_host', array('id' => $user->mnethostid));
            $returnobject->metadata['mnetidprovidername'] = $mnetidprovider->name;
            $returnobject->metadata['mnetidproviderwwwroot'] = $mnetidprovider->wwwroot;
        }

        // Did the user just log in?
        if (isset($SESSION->justloggedin)) {
            // Don't unset this flag as login_info still needs it.
            if (!empty($CFG->displayloginfailures)) {
                // Don't reset the count either, as login_info() still needs it too.
                if ($count = user_count_login_failures($user, false)) {

                    // Get login failures string.
                    $a = new stdClass();
                    $a->attempts = html_writer::tag('span', $count, array('class' => 'value'));
                    $returnobject->metadata['userloginfail'] =
                        get_string('failedloginattempts', '', $a);

                }
            }
        }

        // Links: Dashboard.
        $myhome = new stdClass();
        $myhome->itemtype = 'link';
        $myhome->url = new moodle_url('/my/dashboard.php');
        $myhome->title = get_string('mymoodle', 'admin');
        $myhome->titleidentifier = 'mymoodle,admin';
        // $myhome->pix = "i/dashboard";
        $returnobject->navitems[] = $myhome;

        // Links: My Profile.
        $myprofile = new stdClass();
        $myprofile->itemtype = 'link';
        $myprofile->url = new moodle_url('/local/sentientia_users/profile.php', array('id' => $user->id));
        $myprofile->title = get_string('profile');
        $myprofile->titleidentifier = 'profile,moodle';
        // $myprofile->pix = "i/user";
        $returnobject->navitems[] = $myprofile;

        // Links: My Privacy & Data (DPDP Act 2023 compliance).
        if (file_exists($CFG->dirroot . '/local/sentientia_privacy/version.php')) {
            $myprivacy = new stdClass();
            $myprivacy->itemtype = 'link';
            $myprivacy->url = new moodle_url('/local/sentientia_privacy/index.php');
            $myprivacy->title = get_string('myprivacy', 'local_sentientia_privacy');
            $myprivacy->titleidentifier = 'myprivacy,local_sentientia_privacy';
            $returnobject->navitems[] = $myprivacy;
        }

        $returnobject->metadata['asotherrole'] = false;

        // Before we add the last items (usually a logout + switch role link), add any
        // custom-defined items.
        $customitems = user_convert_text_to_menu_items($CFG->customusermenuitems, $page);
        foreach ($customitems as $item) {
            $returnobject->navitems[] = $item;
        }


        if ($returnobject->metadata['asotheruser'] = \core\session\manager::is_loggedinas()) {
            $realuser = \core\session\manager::get_realuser();

            // Save values for the real user, as $user will be full of data for the
            // user the user is disguised as.
            $returnobject->metadata['realuserid'] = $realuser->id;
            $returnobject->metadata['realuserfullname'] = fullname($realuser, true);
            $returnobject->metadata['realuserprofileurl'] = new moodle_url('/user/profile.php', array(
                'id' => $realuser->id
            ));
            $returnobject->metadata['realuseravatar'] = $OUTPUT->user_picture($realuser, $avataroptions);

            // Build a user-revert link.
            $userrevert = new stdClass();
            $userrevert->itemtype = 'link';
            $userrevert->url = new moodle_url('/course/loginas.php', array(
                'id' => $course->id,
                'sesskey' => sesskey()
            ));
            // $userrevert->pix = "a/logout";
            $userrevert->title = get_string('logout');
            $userrevert->titleidentifier = 'logout,moodle';
            $returnobject->navitems[] = $userrevert;

        } else {

            // Build a logout link.
            $logout = new stdClass();
            $logout->itemtype = 'link';
            $logout->url = new moodle_url('/login/logout.php', array('sesskey' => sesskey()));
            // $logout->pix = "a/logout";
            $logout->title = get_string('logout');
            $logout->titleidentifier = 'logout,moodle';
            $returnobject->navitems[] = $logout;
        }

        // Removed the default switch back to default role as we have custom level role switch.

        return $returnobject;
    }
    // user_menu() moved to theme_sentientia.output.traits.user_menu in Engineering 32.

    /**
     * SA-04 (QA Walk 2026-05-29): may the current user reach Moodle's NATIVE
     * course/category management hub? The theme redirects /course/management.php
     * and /course/index.php to the LXP catalog; before this gate the redirect
     * was UNCONDITIONAL, so siteadmins / category managers lost native course +
     * category management, bulk ops and restore-to-category (only deep-links
     * like /course/edit.php survived). True for siteadmins and holders of
     * moodle/category:manage or moodle/course:create at system context.
     */
    private function can_reach_native_course_admin(): bool {
        $ctx = \context_system::instance();
        return is_siteadmin()
            || has_capability('moodle/category:manage', $ctx)
            || has_capability('moodle/course:create', $ctx);
    }

    public function custom_secured_redirection(){
        global $USER, $CFG, $DB, $COURSE;
        $return = new stdClass();
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $pageurl = "https";
        else
            $pageurl = "http";
        $pageurl .= "://";
        $pageurl .= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $string = strpos($pageurl, '?');
        if($string)
            $newpageurl = substr($pageurl,0 , $string);
        else
            $newpageurl = $pageurl;

        if($newpageurl == $CFG->wwwroot.'/enrol/index.php' || $newpageurl == $CFG->wwwroot.'/enrol/'){
            redirect($CFG->wwwroot.'/my');
        }
        if($newpageurl == $CFG->wwwroot.'/course/management.php'){
            // SA-04 (QA Walk 2026-05-29): gate the catalog redirect by capability
            // so course/category managers reach Moodle's native management hub
            // (bulk ops, restore-to-category). Learners/guests still get the LXP
            // catalog. Mirrors the capability-gated trainer redirect below.
            if (!$this->can_reach_native_course_admin()) {
                redirect($CFG->wwwroot.'/local/sentientia_catalog/index.php');//Category page redirection
            }
        }
        if($newpageurl == $CFG->wwwroot.'/user/view.php' || $newpageurl == $CFG->wwwroot.'/user/profile.php'){
            $id = optional_param('id', $USER->id, PARAM_INT);
            redirect($CFG->wwwroot."/local/sentientia_users/profile.php?id=$id");
        }
        if($newpageurl == $CFG->wwwroot.'/course/index.php' || $newpageurl == $CFG->wwwroot.'/course'){
            // SA-04: same capability gate as /course/management.php above.
            if (!$this->can_reach_native_course_admin()) {
                redirect($CFG->wwwroot."/local/sentientia_catalog/index.php");
            }
        }
        $systemcontext = \context_system::instance();
        if (
            !is_siteadmin()
            && !\local_sentientia_org\accesslib::can_manage_multi($systemcontext)
            && !\local_sentientia_org\accesslib::can_view($systemcontext)
            && !\local_sentientia_org\accesslib::can_manage($systemcontext)
            && !\local_sentientia_org\accesslib::can_manage_classroom($systemcontext)
            && (has_capability('block/sentientia_trainer:viewtrainerslist', $systemcontext)
                // F3 (2026-06-18): guard the legacy block/trainerdashboard cap so an
                // unregistered (renamed-away) cap no longer emits a debug notice per render.
                || \local_sentientia_org\accesslib::legacy_cap('block/trainerdashboard:viewtrainerslist', $systemcontext))
            && $newpageurl == $CFG->wwwroot . '/my/dashboard.php'
        ) {
            redirect($CFG->wwwroot . '/blocks/sentientia_trainer/dashboard.php');
        }
        if(!(\local_sentientia_org\accesslib::can_manage_multi($systemcontext))){
            $is_oh = \local_sentientia_org\accesslib::is_org_head($systemcontext);
            $is_dh = \local_sentientia_org\accesslib::is_dept_head($systemcontext);

            // Derive the user's costcenter and department from open_path
            // (open_costcenterid / open_departmentid columns do not exist
            // on production — open_path '/<cc>/<dept>/...' is canonical).
            // ADR-018 Wave 2: cc/dept via the Sentientia seam (was an inline
            // explode). root_for_user / department_for_user accept any object
            // carrying open_path, so the same calls serve the user and entities.
            $user_cc   = \local_sentientia_core\tenant_identity::root_for_user($USER);
            $user_dept = \local_sentientia_core\tenant_identity::department_for_user($USER);

            $derive_cc_dept = function ($obj) {
                if (!$obj) {
                    return [0, 0];
                }
                return [
                    \local_sentientia_core\tenant_identity::root_for_user($obj),
                    \local_sentientia_core\tenant_identity::department_for_user($obj),
                ];
            };

            if($newpageurl == $CFG->wwwroot.'/course/completion.php' || $newpageurl == $CFG->wwwroot.'/backup/backup.php'){/*for course completion settings and backup page*/
                $courseid = required_param('id',  PARAM_INT);
                $course = get_course($courseid);
                [$course_cc, $course_dept] = $derive_cc_dept($course);
                if($is_oh && $user_cc != $course_cc){
                    redirect($CFG->wwwroot.'/local/sentientia_catalog/index.php');
                }else if($is_dh && $user_dept != $course_dept){
                    redirect($CFG->wwwroot.'/local/sentientia_catalog/index.php');
                }
            }else if($newpageurl == $CFG->wwwroot.'/mod/quiz/edit.php' || $newpageurl == $CFG->wwwroot.'/mod/quiz/report.php'){/*for edit quiz page and quiz default report page*/
                if($COURSE->id == 1){
                    $cmid = ($newpageurl == $CFG->wwwroot.'/mod/quiz/edit.php')
                        ? optional_param('cmid', 0, PARAM_INT)
                        : optional_param('id', 0, PARAM_INT);

                    $onlinetest = $cmid ? \local_sentientia_exams\exam_manager::get_by_course_module($cmid) : false;
                    if($onlinetest){
                        $return->hideheader = TRUE;
                        [$ot_cc, $ot_dept] = $derive_cc_dept($onlinetest);
                        if($is_oh && $user_cc != $ot_cc){
                            redirect($CFG->wwwroot.'/local/sentientia_exams/index.php');
                        }else if($is_dh && $user_dept != $ot_dept){
                            redirect($CFG->wwwroot.'/local/sentientia_exams/index.php');
                        }
                    }else{
                        $return->hideheader = FALSE;
                    }
                }
            }else if($newpageurl == $CFG->wwwroot.'/mod/quiz/review.php' /*|| $newpageurl == $CFG->wwwroot.'/mod/quiz/attempt.php'*/){/*for quiz reviewpage and quiz attempt page*/
                if($COURSE->id == 1){
                    $attempt = optional_param('attempt', 0, PARAM_INT);
                    $onlinetest = $attempt ? \local_sentientia_exams\exam_manager::get_by_attempt($attempt) : false;
                    if($onlinetest){
                        $return->hideheader = TRUE;
                        [$ot_cc, $ot_dept] = $derive_cc_dept($onlinetest);
                        if($is_oh && $user_cc != $ot_cc){
                            redirect($CFG->wwwroot.'/local/sentientia_exams/index.php');
                        }else if($is_dh && $user_dept != $ot_dept){
                            redirect($CFG->wwwroot.'/local/sentientia_exams/index.php');
                        }
                    }else{
                        $return->hideheader = FALSE;
                    }
                }
            }
        }
        return $return;
    }
    /**
     * Number of role switch based on user roles
     *
     * @param int $courseid A course object.
     * @param stdClass $context usually site context.
     * @return string HTML.
     */
    function role_switch_basedon_userroles($roleid, $purge, $contextid = 1, $applyrsw = true){
        global $DB, $CFG, $USER;

        if(is_siteadmin($USER->id) || ($roleid <= 0) || $purge){
            return false;
        }

        $role = $DB->get_record('role', array('id' => $roleid));
        if(!$role){
            throw new moodle_exception('nopermission');
        }
        $context = \context::instance_by_id($contextid);
        $roles = get_user_roles($context, $USER->id);
        // $userroles = array();

        // foreach($roles as $r){
        //     $userroles[$r->roleid] = $r->shortname;
        // }

        $accessdata = get_empty_accessdata();
        if($this->roleswitch($roleid, $context, $accessdata, $applyrsw)){
            return true;
        }else{
            return false;
        }
    }
    /**
     * sitelevel roleswitch as buttons.
     *
     * @param int $courseid A course object.
     * @param stdClass $context usually site context.
     * @return string HTML.
     */
    function roleswitch($roleid, $context, &$accessdata, $applyrsw = true){

        global $DB, $ACCESSLIB_PRIVATE, $USER;
        // WF-025b (2026-06-15) — $applyrsw=false establishes the role-VIEW scoping
        // context ($USER->useraccess['currentroleinfo'], consumed by the org-scoped
        // reports/blocks) WITHOUT writing $USER->access['rsw'] (the array core
        // has_capability() consults to REPLACE effective caps). The first-visit
        // auto-call (user_menu.php) now passes false, so merely navigating to the
        // dashboard never silently reduces a multi-role user's capabilities — a real
        // capability switch only ever happens on an explicit user action. Report
        // org-scoping is unchanged by construction (currentroleinfo is still set
        // below regardless of $applyrsw). See WF-025 + WORKFLOW-TEST-MATRIX A5.
        // if($context->path == '/1'){
        //     $USER->access['rsw'] = [];
        // }else{
            if($applyrsw){
                $USER->access['rsw'][$context->path] = $roleid;
            }
        // }


        $costcenterpath = \local_sentientia_org\accesslib::get_costcenterpath_context($context);

        $USER->useraccess['currentroleinfo']['roleid'] = $roleid;
        $categorypath = \local_sentientia_org\accesslib::get_category_info($context->instanceid, 'path');
        $categoryids = array_values(array_filter((explode('/', $categorypath))));
        $USER->useraccess['currentroleinfo']['orgcatid'] = $categoryids[0];
        $USER->useraccess['currentroleinfo']['depth'] = $context->depth;
        $USER->useraccess['currentroleinfo']['contextinfo'] = [];
        $USER->useraccess['currentroleinfo']['contextinfo'][] = ['context' => $context,'costcenterpath' => $costcenterpath];
       /* Get the relevant rolecaps into rdef
        * - relevant role caps
        *   - at ctx and above
        *   - below this ctx
        */
        if (empty($context->path)) {
            // weird, this should not happen
            return;
        }
        //Fetching the category contexts where the role is assigned ans switching as user to those for achieving system level role switch starts.
        if($context->id == SYSCONTEXTID){
            $userroleid = $DB->get_field('role', 'id', array('archetype' => 'student'));
        }else{
            $userroleid = $DB->get_field('role', 'id', array('archetype' => 'user'));
        }
        // $assignedcontexts = array_map(function($cxtpath){
        //     return end(explode('/', $cxtpath));
        // }, array_unique(array_keys($USER->access['ra'])));
        $assignedroles = \local_sentientia_org\accesslib::get_user_roles_in_catgeorycontexts($USER->id);
        $contextdepth = $context->__get('depth');
        foreach($assignedroles AS $assignedrole){
            if($assignedrole->contextid != $context->id && $assignedrole->contextid != 1){
                $othercontext = \context::instance_by_id($assignedrole->contextid);
                // considering only category level role switches.
                if($othercontext->__get('contextlevel') == CONTEXT_COURSECAT){
                    $othercategorypath = \local_sentientia_org\accesslib::get_category_info($othercontext->instanceid, 'path');
                    $othercategoryids = array_values(array_filter((explode('/', $othercategorypath))));

                    if($contextdepth == $othercontext->__get('depth') && $othercategoryids[0] == $USER->useraccess['currentroleinfo']['orgcatid'] && $roleid == $assignedrole->roleid){//in_array($roleid, $USER->access['ra'][$othercontext->path])


                        // strpos(haystack, needle)
                        if($this->role_capability_assignments($roleid, $othercontext, $accessdata)){
                            if($applyrsw){
                                $USER->access['rsw'][$othercontext->path] = $roleid;
                            }
                            $othercostcenterpath = \local_sentientia_org\accesslib::get_costcenterpath_context($othercontext);
                            $USER->useraccess['currentroleinfo']['contextinfo'][] = ['context' => $othercontext,'costcenterpath' => $othercostcenterpath];
                        }
                    }else {//if($context->path != '/1')if user is assigned at system context we unset the rsw variable.

                        if($applyrsw){
                            if(strpos($othercontext->path.'/', $context->path.'/') === 0 && $context->path != '/1'){
                                unset($USER->access['rsw'][$othercontext->path]);
                            }else{
                                if($this->role_capability_assignments($userroleid, $othercontext, $accessdata))
                                    $USER->access['rsw'][$othercontext->path] = $userroleid;
                            }
                        }
                    }
                }
            }
        }
        //Fetching the category contexts where the role is assigned ans switching as user to those for achieving system level role switch ends.
        $this->role_capability_assignments($roleid, $context, $accessdata);

        return true;
    }
    private function role_capability_assignments($roleid, $context, &$accessdata){
        global $DB;
        list($parentsaself, $params) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'pc_');
        $params['roleid'] = $roleid;
        $params['childpath'] = $context->path.'/%';

        $sql = "SELECT ctx.path, rc.capability, rc.permission
                  FROM {role_capabilities} rc
                  JOIN {context} ctx ON (rc.contextid = ctx.id)
                 WHERE rc.roleid = :roleid AND (ctx.id $parentsaself OR ctx.path LIKE :childpath)
              ORDER BY rc.capability"; // fixed capability order is necessary for rdef dedupe
        $rs = $DB->get_recordset_sql($sql, $params);

        $newrdefs = array();
        foreach ($rs as $rd) {
            $k = $rd->path.':'.$roleid;
            if (isset($accessdata['rdef'][$k])) {
                continue;
            }
            $newrdefs[$k][$rd->capability] = (int)$rd->permission;
        }
        $rs->close();

        // share new role definitions
        // foreach ($newrdefs as $k=>$unused) {
        //     if (!isset($ACCESSLIB_PRIVATE->rolepermissions[$k])) {

        //         $ACCESSLIB_PRIVATE->rolepermissions[$k] = $newrdefs[$k];

        //     }
        //     $accessdata['rdef'][$k] =& $ACCESSLIB_PRIVATE->rolepermissions[$k];
        // }
        return true;
    }
    public function quickaccess_links() {
        global $DB, $CFG, $USER, $PAGE;
        $systemcontext = context_system::instance();
        $core_component = new core_component();
        $block_content = '';
        $local_pluginlist = $core_component::get_plugin_list('local');
        $block_pluginlist = $core_component::get_plugin_list('block');

        $block_content .= html_writer::start_tag('ul', array('class'=>'quickpop_over_ul'));
            //======= Dasboard link ========//
            // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard'));
            //     $button1 = html_writer::link($CFG->wwwroot, '<i class="fa fa-home" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('leftmenu_dashboard', 'theme_sentientia').'</span>', array('class'=>'user_navigation_link'));
            //     $block_content .= $button1;
            // $block_content .= html_writer::end_tag('li');

            //=======Leader Dasboard link ========//
//             $gamificationb_plugin_exist = $core_component::get_plugin_directory('block', 'gamification');
//             $gamificationl_plugin_exist = $core_component::get_plugin_directory('local', 'gamification');
//             if($gamificationl_plugin_exist && $gamificationb_plugin_exist && (has_capability('local/gamification:view
// ',$systemcontext) || is_siteadmin() )){
//                 $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_gamification_leaderboard', 'class'=>'pull-left user_nav_div notifications'));
//                 $gamification_url = new moodle_url('/blocks/gamification/dashboard.php');
//                 $gamification = html_writer::link($gamification_url, '<i class="fa fa-trophy"></i><span class="user_navigation_link_text">'.get_string('leftmenu_gmleaderboard','theme_sentientia').'</span>',array('class'=>'user_navigation_link'));
//                 $block_content .= $gamification;
//                 $block_content .= html_writer::end_tag('li');
//             }
            $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_home', 'class'=>'pull-left user_nav_div adminstration'));
                    $admin_url = new moodle_url('/my/dashboard.php');
                    $admin = html_writer::link($admin_url, '<i class="fa fa-home" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('home','theme_sentientia').'</span>',array('class'=>'user_navigation_link'));
                    $block_content .= $admin;
                $block_content .= html_writer::end_tag('li');
            $pluginnavs = array();
            foreach($local_pluginlist as $key => $local_pluginname){
                if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
                    $functionname = 'local_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                        $data = $functionname();
                        foreach((array) $data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }
            // ksort($pluginnavs);
            // foreach($pluginnavs as $pluginnav){
            //     foreach($pluginnav  as $key => $value){
            //             $data = $value;
            //             $block_content .= $data;
            //     }
            // }

            foreach($block_pluginlist as $key => $local_pluginname){
                 if(file_exists($CFG->dirroot.'/blocks/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/blocks/'.$key.'/lib.php');
                    $functionname = 'block_'.$key.'_leftmenunode';
                    if(function_exists($functionname)){
                    // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard row-fluid '));
                        $data = $functionname();
                        foreach($data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    // $block_content .= html_writer::end_tag('li');
                    }
                }
            }
            if(file_exists($CFG->dirroot.'/admin/tool/certificate/lib.php')){
                require_once($CFG->dirroot.'/admin/tool/certificate/lib.php');
                $functionname = 'tool_certificate_leftmenunode';
                if(function_exists($functionname)){
                // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_dashboard', 'class'=>'pull-left user_nav_div dashboard row-fluid '));
                    $data = $functionname();
                    foreach($data as  $key => $val){
                        $pluginnavs[$key][] = $val;
                    }
                // $block_content .= html_writer::end_tag('li');
                }
            }

            ksort($pluginnavs);
            foreach($pluginnavs as $pluginnav){
                foreach($pluginnav  as $key => $value){
                        $data = $value;
                        $block_content .= $data;
                }
            }
            /*Site Administration Link*/
            if(is_siteadmin()){
                // $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_adminstration', 'class'=>'pull-left user_nav_div adminstration'));
                //     $admin_url = new moodle_url('/repository/customfiles/file.php');
                //     $admin = html_writer::link($admin_url, '<span class="image_repository_icon dypatil_cmn_icon icon"></span><span class="user_navigation_link_text">'.get_string('repositoryfiles','theme_sentientia').'</span>',array('class'=>'user_navigation_link'));
                //     $block_content .= $admin;
                // $block_content .= html_writer::end_tag('li');
                $block_content .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_adminstration', 'class'=>'pull-left user_nav_div adminstration'));
                    $admin_url = new moodle_url('/admin/search.php');
                    $admin = html_writer::link($admin_url, '<i class="fa fa-cogs" aria-hidden="true"></i></span><span class="user_navigation_link_text">'.get_string('leftmenu_adminstration','theme_sentientia').'</span>',array('class'=>'user_navigation_link'));
                    $block_content .= $admin;
                $block_content .= html_writer::end_tag('li');

            }
        $block_content .= html_writer::end_tag('ul');

        return $block_content;
    }
    // get_primarycolor / get_secondarycolor / get_hovercolor /
    // getsitecolors_link — moved to traits/branding_assets in
    // Phase 9.5 engineering item 14.
    public function courseformat_drawer_content(){

        global $DB,$COURSE,$CFG,$USER;


        if (!$this->courseviewmenu_hidden()) {

        $course = $DB->get_record('course',array('id' => $COURSE->id));
        $completion = new \completion_info($course);

            // First, let's make sure completion is enabled.
            if ($completion->is_enabled()) {
                
                $percentage = progress::get_course_progress_percentage($course, $USER->id);
            }
        $display_ratings = null;
        if (class_exists('\local_sentientia_ratings\rating_manager')) {
            $display_ratings = \local_sentientia_ratings\rating_manager::render($COURSE->id, 'local_sentientia_courses');
        } else {
            $ratings_lib = $CFG->dirroot . '/local/ratings/lib.php';
            if (file_exists($ratings_lib)) {
                require_once($ratings_lib);
                if (function_exists('display_rating')) {
                    $display_ratings = display_rating($COURSE->id, 'local_sentientia_courses');
                }
            }
        }
        if(empty($percentage)){
            $percentage=0;}
            $coursedata=array();
            $coursedata['coursename']=$COURSE->fullname;
            $coursedata['display_ratings']=$display_ratings;
            $coursedata['percentage']=$percentage;
            $coursedata['coursebannerimage']=$this->course_bannerimage();
            //print
            return $this->render_from_template('theme_sentientia/core_courseformat/local/courseindex/course_drawer_header', $coursedata);
        }
    }

    // courseviewmenu_hidden(), course_bannerimage(), course_summary_data(),
    // hasrmaincontenthidden(), activityurl_get_course() moved to
    // \theme_sentientia\output\traits\course_view in Engineering 31.
    // (loggedin_username() — see page_helpers trait.)

}
