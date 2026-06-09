<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_m365;

defined('MOODLE_INTERNAL') || die();

/**
 * Microsoft Graph API client for Sentientia LMS — Phase C.1 scaffold.
 *
 * Every public method in Phase C.1 throws
 * \moodle_exception('confirm_required') BEFORE any HTTP call so the
 * higher-level features cannot accidentally hit graph.microsoft.com
 * without an explicit unlock. The guard runs as the first statement of
 * each method so even a misconfigured feature flag cannot bypass it.
 *
 * Phase C.2 will:
 *   - Replace the guard with a call to a new
 *     sentientia_m365_live_api flag.
 *   - Add a `[CONFIRM]` UI gate analogous to the one in
 *     local_sentientia_aiquiz.
 *   - Wire each method to graph.microsoft.com via cURL using the
 *     access token loaded from msal_client::load_tokens() and
 *     decrypted with msal_client::decrypt_token().
 *
 * The method signatures are committed now so Phase C.2 can drop in the
 * real bodies without touching callers.
 *
 * Per .claude/rules/api.md: NEVER log access tokens; NEVER swallow
 * exceptions silently; ALWAYS set a request timeout; ALWAYS validate
 * the response shape before use.
 *
 * @package local_sentientia_m365
 */
class graph_client {

    /** Graph API root. */
    public const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    /** Default HTTP timeout in seconds when Phase C.2 wires real calls. */
    public const HTTP_TIMEOUT = 30;

    /**
     * Retrieve the authenticated user's Microsoft 365 profile.
     *
     * In Phase C.2 this will GET /me with the Bearer access token.
     *
     * Required scope: User.Read.
     *
     * @param int $userid     Moodle user ID for which we hold tokens
     * @param int $customerid Customer scope
     * @return array          Decoded JSON body — never reached in C.1
     * @throws \moodle_exception Always in Phase C.1 (`confirm_required`)
     */
    public static function get_me(int $userid, int $customerid = 1): array {
        self::guard_no_live_calls();
        // Phase C.2: load + decrypt token, then:
        //   return self::call_graph('/me', $access_token);
        return [];
    }

    /**
     * List SharePoint sites the authenticated user can see.
     *
     * In Phase C.2 this will GET /sites?search=* with paging.
     *
     * Required scope: Sites.Read.All.
     *
     * @param int    $userid
     * @param int    $customerid
     * @param string $search        Optional filter
     * @return array                List of site objects — never reached in C.1
     * @throws \moodle_exception    Always in Phase C.1
     */
    public static function list_sharepoint_sites(int $userid, int $customerid = 1, string $search = ''): array {
        self::guard_no_live_calls();
        // Phase C.2: load + decrypt token, then call /sites with pagination.
        return [];
    }

    /**
     * Retrieve the authenticated user's Outlook calendar events for a
     * date range.
     *
     * In Phase C.2 this will GET /me/calendar/calendarView with
     * startDateTime + endDateTime query params.
     *
     * Required scope: Calendars.Read.
     *
     * @param int $userid
     * @param int $customerid
     * @param int $start_unix       Inclusive start timestamp
     * @param int $end_unix         Exclusive end timestamp
     * @return array                List of event objects — never reached in C.1
     * @throws \moodle_exception    Always in Phase C.1
     */
    public static function get_user_calendar(
        int $userid,
        int $customerid = 1,
        int $start_unix = 0,
        int $end_unix = 0
    ): array {
        self::guard_no_live_calls();
        // Phase C.2: load + decrypt token, then call /me/calendar/calendarView.
        return [];
    }

    /**
     * Phase C.1 safety guard — every public method funnels through this.
     *
     * Throws `confirm_required` regardless of feature-flag state. The
     * guard intentionally ignores the flag in C.1 because the LIVE-API
     * flag does not exist yet; flipping `sentientia_m365_enabled` ON
     * unlocks the OAuth flow but explicitly NOT the Graph traffic.
     *
     * Phase C.2 replaces this with:
     *   if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia_m365_live_api')) {
     *       throw new \moodle_exception('feature_off', 'local_sentientia_m365');
     *   }
     *
     * @throws \moodle_exception
     */
    private static function guard_no_live_calls(): void {
        throw new \moodle_exception('confirm_required', 'local_sentientia_m365');
    }
}
