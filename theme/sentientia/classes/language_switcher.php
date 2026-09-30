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

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Language switcher for the app-shell sidebar.
 *
 * Persona-pass fix D7 (2026-09-30). The product guide promises "language
 * switching is per-user and immediate", but the shell had no language
 * control at all:
 *
 *   - core_renderer::custom_language_menu() had no caller. The layouts hand
 *     a 'langmenu' value to their templates and no template prints it.
 *   - Core's own language_menu returns nothing while $CFG->langmenu is 0,
 *     and it is 0 on the local copy.
 *   - Core's "?lang=xx" only sets $SESSION->lang, so a choice was lost at
 *     the next login.
 *
 * This class is the data + action side of the fix:
 *
 *   get_context()  data-only options for the sidebar partial. Empty unless
 *                  the flag is ON, the viewer is a real logged-in user, two
 *                  or more languages are installed and the course does not
 *                  force one.
 *   switch_to()    validates the choice, applies it to the session at once
 *                  and saves it to the user's profile language.
 *
 * What the site owner must configure: nothing in core. The visibility gate
 * is the platform flag ux.languageSwitcher.enabled (default OFF, registered
 * in local_sentientia_platform, per-customer and per-tenant overridable).
 * $CFG->langmenu is ignored on purpose, so a site that keeps core's menu
 * off still gets the switcher. Languages come from the installed language
 * packs (Site admin > Language > Language packs) and are narrowed by the
 * core "Display language menu" list ($CFG->langlist) when that is set.
 *
 * The languages list is injectable so PHPUnit does not depend on which
 * language packs the test site has installed.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class language_switcher {

    /** @var string Feature flag key (local_sentientia_platform db/feature_flags.php). Default OFF. */
    public const FLAG = 'ux.languageSwitcher.enabled';

    /** @var string Endpoint that applies and saves a choice (sesskey-checked). */
    public const ENDPOINT = '/theme/sentientia/switchlang.php';

    /**
     * Is the switcher switched on for the current user's customer and tenant?
     *
     * Fails closed: a missing platform plugin or a resolver error means OFF,
     * because this is a new feature and the default is OFF.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
        } catch (\Throwable $e) {
            debugging('language_switcher: ' . self::FLAG . ' lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Sidebar context for the language switcher.
     *
     * @param \moodle_page $page the page being rendered (for the return url and the course).
     * @param array|null $languages code => label; null reads the installed translations.
     * @return array {
     *     hasoptions:   bool   true only when there is an actual choice to make
     *     currentlabel: string label of the language in use
     *     currentcode:  string code of the language in use
     *     options:      array  list of [code, label, htmllang, active, url]
     * }
     */
    public static function get_context(\moodle_page $page, ?array $languages = null): array {
        $result = ['hasoptions' => false, 'currentlabel' => '', 'currentcode' => '', 'options' => []];

        // Guests and logged-out visitors never see the app shell sidebar; a
        // guest has no profile to save to.
        if (!isloggedin() || isguestuser()) {
            return $result;
        }
        if (!self::is_enabled()) {
            return $result;
        }

        // A course (or an activity) that forces its language wins over the
        // session and the profile (current_language() returns it), so a switch
        // there would silently do nothing. Core hides its menu in the same case.
        $course = $page->course;
        if ((int) $course->id !== SITEID && !empty($course->lang)) {
            return $result;
        }
        if (!empty($page->cm->lang)) {
            return $result;
        }

        if ($languages === null) {
            $languages = get_string_manager()->get_list_of_translations();
        }
        if (count($languages) < 2) {
            return $result;
        }

        $current = current_language();
        $returnurl = self::return_url($page);

        foreach ($languages as $code => $label) {
            $code = (string) $code;
            $active = ($code === $current);
            $result['options'][] = [
                'code'     => $code,
                'label'    => (string) $label,
                'htmllang' => get_html_lang_attribute_value($code),
                'active'   => $active,
                // The active language is a non-clickable marker, like the role switcher.
                // The parameter is 'code', not 'lang': core's lib/setup.php applies any
                // GET 'lang' to $SESSION->lang before the endpoint runs, so a link that
                // carried 'lang' would change the session even while the flag is OFF or
                // the sesskey check fails.
                'url'      => $active ? '' : (new \moodle_url(self::ENDPOINT, [
                    'code'      => $code,
                    'sesskey'   => sesskey(),
                    'returnurl' => $returnurl,
                ]))->out(false),
            ];
            if ($active) {
                $result['currentlabel'] = (string) $label;
            }
        }

        $result['currentcode'] = $current;
        if ($result['currentlabel'] === '') {
            // The language in use is outside the offered list (for example
            // narrowed away by $CFG->langlist): show its code rather than nothing.
            $result['currentlabel'] = $current;
        }
        $result['hasoptions'] = true;

        return $result;
    }

    /**
     * Apply a language choice: session at once, profile when allowed.
     *
     * The profile is left alone when the viewer is logged in as somebody
     * else (the session is not theirs to change) or when the site has taken
     * moodle/user:editownprofile away from them.
     *
     * @param string $lang language code (for example 'hi').
     * @param array|null $languages code => label; null reads the installed translations.
     * @return bool false when the code is not an offered language, true once applied.
     */
    public static function switch_to(string $lang, ?array $languages = null): bool {
        global $CFG, $SESSION, $USER;

        if ($languages === null) {
            $languages = get_string_manager()->get_list_of_translations();
        }
        if ($lang === '' || !isset($languages[$lang])) {
            return false;
        }

        // Immediate: current_language() reads $SESSION->lang before $USER->lang.
        $SESSION->lang = $lang;
        // Core does this after it sets $SESSION->lang from ?lang=xx (lib/setup.php).
        if (class_exists('\\core_courseformat\\base') && method_exists('\\core_courseformat\\base', 'session_cache_reset_all')) {
            \core_courseformat\base::session_cache_reset_all();
        }

        // Per user: save to the profile so the next login keeps the choice.
        if (isloggedin() && !isguestuser()
                && !\core\session\manager::is_loggedinas()
                && has_capability('moodle/user:editownprofile', \context_system::instance())
                && (string) ($USER->lang ?? '') !== $lang) {
            require_once($CFG->dirroot . '/user/lib.php');
            user_update_user((object) ['id' => $USER->id, 'lang' => $lang], false, true);
            $USER->lang = $lang;
        }

        return true;
    }

    /**
     * Local url of the current page for the redirect back after a switch, without
     * a lang parameter (core would re-apply it to the session on the way back) and
     * without a sesskey (returning must not replay a state-changing GET page with a
     * valid key). Empty when the page url cannot be made local.
     *
     * @param \moodle_page $page
     * @return string
     */
    private static function return_url(\moodle_page $page): string {
        try {
            $url = new \moodle_url($page->url);
            $url->remove_params(['lang', 'sesskey']);
            return $url->out_as_local_url(false);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
