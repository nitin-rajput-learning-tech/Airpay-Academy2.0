<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-module navigation URL resolver — P0 borrow #9 from Moodle 5.2.
 *
 * Moodle 5.2 ships a public `cm_info::get_navigation_url()` method that lets
 * a module override its activity-link target — so SCORM can jump straight
 * to the player with an attempt id, URL activities can link to the
 * external URL, and our `mod_sentientia_evaluation` can land each learner on
 * their own attempt page rather than the generic /view.php.
 *
 * This class is the 5.1 backport. The API surface is identical to the
 * 5.2 method: pass in a {@see \cm_info}, get back a {@see \moodle_url}
 * (or null when the module doesn't have a launchable URL — e.g. labels).
 *
 * Migration plan on the 5.2 wholesale upgrade
 * -------------------------------------------
 * Replace every call site
 *
 *     \local_sentientia_platform\cm_navigation::resolve_url($cm)
 *
 * with the 5.2-native
 *
 *     $cm->get_navigation_url()
 *
 * and delete this class. Nothing else changes.
 *
 * How a module opts into a custom URL
 * -----------------------------------
 * Define a callback in your module's lib.php:
 *
 *     function mod_yourname_get_navigation_url(\cm_info $cm): ?\moodle_url {
 *         return new \moodle_url('/mod/yourname/play.php', ['cmid' => $cm->id]);
 *     }
 *
 * The resolver picks this up via `component_callback()`. Return null
 * to signal "no launchable URL" — the resolver then falls back to
 * `$cm->url` (Moodle's default).
 *
 * Tenant safety
 * -------------
 * This class does NOT scope by tenant. The cm_info object already
 * carries the course context, which is tenant-scoped upstream. Adding a
 * tenant filter here would double-filter and break legitimate cross-tenant
 * admin views. (See ADR-009 §3 on detection consistency.)
 *
 * @package local_sentientia_platform
 */
class cm_navigation {

    /**
     * Resolve the navigation URL for a course module.
     *
     * Resolution order:
     *   1. Module-defined `mod_<modname>_get_navigation_url($cm)` callback
     *      via `component_callback()`. Module returns null to fall through.
     *   2. The cm_info's default `->url` (what Moodle uses today).
     *   3. null — module has no launchable URL (e.g. label).
     *
     * @param \cm_info $cm Course module to resolve.
     * @return \moodle_url|null URL to navigate to, or null if none applies.
     */
    public static function resolve_url(\cm_info $cm): ?\moodle_url {
        // Step 1 — ask the module if it has a custom URL.
        $component = 'mod_' . $cm->modname;
        try {
            $custom = component_callback($component, 'get_navigation_url', [$cm], null);
        } catch (\Throwable $e) {
            // A broken module callback must NOT take down the whole page.
            // Fall through to the default. Log the exception so we notice.
            debugging(
                'cm_navigation: callback ' . $component . '_get_navigation_url '
                . 'threw ' . get_class($e) . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            $custom = null;
        }

        if ($custom instanceof \moodle_url) {
            return $custom;
        }

        // Step 2 — Moodle default. cm_info::$url is null for label-type
        // activities and a moodle_url for everything else.
        if ($cm->url instanceof \moodle_url) {
            return $cm->url;
        }

        // Step 3 — no launchable URL at all.
        return null;
    }

    /**
     * Convenience wrapper — returns a string URL or '' if none.
     *
     * Useful in renderers and templates that want a string-or-empty contract
     * (e.g. Mustache `{{url}}` where '' suppresses the link).
     *
     * @param \cm_info $cm
     * @return string Absolute URL or empty string.
     */
    public static function resolve_url_string(\cm_info $cm): string {
        $url = self::resolve_url($cm);
        return $url instanceof \moodle_url ? $url->out(false) : '';
    }
}
