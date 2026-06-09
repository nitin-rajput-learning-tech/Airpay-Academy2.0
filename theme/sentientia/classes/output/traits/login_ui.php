<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\output\traits;

defined('MOODLE_INTERNAL') || die();

/**
 * Login-page UI helpers: slider, welcome/caption text, public-tenant
 * statistics (active users, courses, certificates issued).
 *
 * Extracted from `core_renderer.php` in Phase 9.5 engineering item 12
 * (decomposition pass 2). These methods are pure-output helpers that
 * read theme settings and Public-tenant counters; no shared state with
 * sibling renderer methods.
 *
 * Methods provided:
 *   loginslider():             string  — image slider for the login page
 *   welcometext():             string  — welcome banner copy (truncated)
 *   captiontext():             string  — logo caption (truncated)
 *   login_stat_users():        string  — Public-tenant active user count
 *   login_stat_courses():      string  — Public-tenant visible course count
 *   login_stat_certs():        string  — Public-tenant certificates issued
 *   login_stat_completion():   string  — alias of login_stat_certs() (deprecated)
 *   get_public_tenant_path():  string  — protected — Public tenant LIKE prefix
 *
 * All methods assume `$this->page`, `$this->image_url()`, and
 * `$this->render_from_template()` are available (consuming class must
 * extend `\core_renderer`).
 *
 * @package theme_sentientia
 */
trait login_ui {

    /**
     * Image slider for the login page. Returns '' (not false) when the
     * viewer is already logged in — the consuming template renders
     * `<?= $loginslider ?>` directly, so the false return value would
     * print the literal string "false".
     *
     * Phase 9.5 micro-fix: original method returned `false` for logged-
     * in users. Trait returns '' for the same case, matching how the
     * template consumes the value.
     */
    public function loginslider(): string {
        global $CFG;
        if (isloggedin()) {
            return '';
        }

        $loginslider = '<script>'
            . ' function loginpopup(test) {'
            . '   $("#div_loginpopup_"+test).toggleClass("open");'
            . ' }'
            . ' function closeonclick(test) {'
            . '   $("#div_loginpopup_"+test).toggleClass("open");'
            . ' }'
            . '</script>';

        $slider_context = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = $this->page->theme->setting_file_url("slider{$i}", "slider{$i}");
            if (empty($url)) {
                $url = $this->image_url("slides/slide{$i}", 'theme_sentientia');
            }
            $slider_context["img{$i}_url"] = $url;
        }
        $loginslider .= $this->render_from_template(
            'theme_sentientia/slider', $slider_context);
        return $loginslider;
    }

    /**
     * Welcome-text banner from theme settings.
     *
     * Note: the original method truncated to 15 chars which is
     * aggressively short. Trait preserves that behaviour verbatim;
     * if a wider truncation is wanted, change the constant.
     */
    public function welcometext(): string {
        $welcometext = $this->page->theme->settings->welcometext ?? ' ';
        if (empty($welcometext)) {
            $welcometext = ' ';
        }
        if (strlen($welcometext) > 15) {
            $welcometext = substr($welcometext, 0, 15) . ' ';
        }
        return $welcometext;
    }

    /**
     * Logo caption from theme settings. Truncated at 80 chars.
     */
    public function captiontext(): string {
        $captiontext = $this->page->theme->settings->logocaption ?? '';
        if (empty($captiontext)) {
            return '';
        }
        if (strlen($captiontext) > 80) {
            $captiontext = substr($captiontext, 0, 80) . '...';
        }
        return $captiontext;
    }

    /**
     * Get the Public tenant path prefix for login/homepage stat queries.
     *
     * `protected` so subclasses can override (e.g. to point at a
     * different tenant during a marketing campaign) without exposing
     * to the public API.
     */
    protected function get_public_tenant_path(): string {
        $tid = (int) get_config('local_sentientia_pages', 'public_tenant_id');
        if (!$tid) {
            $tid = 77;
        }
        return '/' . $tid . '%';
    }

    /**
     * Live active-user count for the login hero — Public tenant only.
     * Returns '' on error rather than throwing (login page must render
     * even when the stat query fails).
     */
    public function login_stat_users(): string {
        global $DB;
        try {
            $count = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {user}
                  WHERE deleted   = 0
                    AND suspended = 0
                    AND id        > 1
                    AND open_path LIKE :p",
                ['p' => $this->get_public_tenant_path()]);
            return $count > 0 ? $count . '+' : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Live course count for the login hero — Public tenant only.
     */
    public function login_stat_courses(): string {
        global $DB;
        try {
            $count = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {course}
                  WHERE visible   = 1
                    AND id        > 1
                    AND open_path LIKE :p",
                ['p' => $this->get_public_tenant_path()]);
            return $count > 0 ? $count . '+' : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Certificate count for the login hero — Public tenant only.
     * Same query as login_stat_completion(); login_stat_certs() is
     * the preferred name.
     */
    public function login_stat_certs(): string {
        return $this->login_stat_completion();
    }

    /**
     * @deprecated Use login_stat_certs() — kept for backward compat
     * with templates that already reference this name.
     */
    public function login_stat_completion(): string {
        global $DB;
        try {
            if (!$DB->get_manager()->table_exists('tool_certificate_issues')) {
                return '';
            }
            $count = $DB->count_records_sql(
                "SELECT COUNT(ci.id) FROM {tool_certificate_issues} ci
                   JOIN {user} u ON u.id = ci.userid
                  WHERE u.open_path LIKE :p AND ci.archived = 0",
                ['p' => $this->get_public_tenant_path()]);
            return $count > 0 ? $count . '+' : '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
