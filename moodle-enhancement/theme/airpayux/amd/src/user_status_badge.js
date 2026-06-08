// This file is part of Airpay Academy / Sentientia LMS.
//
// theme_airpayux/user_status_badge — Moodle 5.2 borrow #10.
//
// Decorates user-name links across report-like pages with an inline
// "Suspended" / "Deleted" pill, sourced from a server-rendered JSON
// blob embedded in the page body.
//
// Why an AMD pass and not a server-side renderer override?
//   - Gradebook + report tables render user names through `fullname()`
//     directly, not through `user_picture()`. Hooking every tabular
//     report renderer would mean touching ~12 plugin renderers.
//   - The server already knows which userids are suspended (one DB
//     query in theme_airpayux_before_standard_top_of_body_html). The
//     AMD just paints what the server already computed.
//
// Server contract: a single `<script id="airpay-user-status-data"
// type="application/json">` element containing a `{ "userid": "status" }`
// map, where status ∈ {"suspended", "deleted"}.
//
// Selectors: any `<a>` whose href matches `/user/profile.php?id=N` —
// this catches the participants page, gradebook, log reports, activity
// completion, search-results profile links, etc.
//
// Idempotent: re-running `init()` skips already-decorated links (each
// gets a `data-airpay-status-painted` attribute on first paint).
//
// @module     theme_airpayux/user_status_badge
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Read and parse the embedded suspended-user JSON blob.
 *
 * @returns {Object<string, string>|null}
 *   `{userid: status}` map, or null when the blob is absent or invalid.
 */
const readStatusMap = () => {
    const el = document.getElementById('airpay-user-status-data');
    if (!el) {
        return null;
    }
    try {
        const txt = el.textContent || '';
        if (!txt.trim()) {
            return null;
        }
        const parsed = JSON.parse(txt);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return parsed;
        }
        return null;
    } catch (e) {
        // Bad JSON — log once, don't crash the page.
        if (window.console && window.console.warn) {
            window.console.warn('user_status_badge: failed to parse status blob', e);
        }
        return null;
    }
};

/**
 * Extract the numeric userid from a `/user/profile.php?id=N&...` URL.
 *
 * @param {string} href
 * @returns {number|null}
 */
const extractUserId = (href) => {
    if (!href) {
        return null;
    }
    // Match both absolute and relative forms.
    const m = href.match(/\/user\/profile\.php\?(?:.*?&)?id=(\d+)/);
    return m ? parseInt(m[1], 10) : null;
};

/**
 * Build the badge element for a status. Returns null for unknown statuses
 * (defensive — server should only ever send "suspended" or "deleted").
 *
 * The text content + aria-label + title all carry the same translated
 * string, served by Moodle's lang pack so it follows the user's locale.
 *
 * @param {string} status   "suspended" or "deleted"
 * @param {Object} labels   { suspended, deleted, aria } translated strings
 * @returns {HTMLSpanElement|null}
 */
const buildBadge = (status, labels) => {
    if (status !== 'suspended' && status !== 'deleted') {
        return null;
    }
    const label = status === 'suspended' ? labels.suspended : labels.deleted;
    const span = document.createElement('span');
    span.className = `airpay-user-status-badge airpay-user-status-badge--${status}`;
    span.textContent = label;
    span.setAttribute('title', label);
    // Aria-label gives screen readers the full "Account status: X"
    // phrasing instead of just the bare word.
    span.setAttribute('aria-label', labels.aria.replace('{$a}', label));
    span.setAttribute('role', 'img');
    return span;
};

/**
 * Decorate every visible profile link with a status badge.
 *
 * @param {Object<string,string>} statusMap   server-rendered userid→status
 * @param {Object<string,string>} labels      translated badge strings
 */
const paintBadges = (statusMap, labels) => {
    const links = document.querySelectorAll('a[href*="/user/profile.php"]');
    links.forEach((a) => {
        if (a.hasAttribute('data-airpay-status-painted')) {
            return; // already done
        }
        const uid = extractUserId(a.getAttribute('href'));
        if (uid === null) {
            return;
        }
        const status = statusMap[String(uid)];
        if (!status) {
            a.setAttribute('data-airpay-status-painted', '1');
            return; // active user — no badge, but mark so we skip on rerun
        }
        const badge = buildBadge(status, labels);
        if (!badge) {
            a.setAttribute('data-airpay-status-painted', '1');
            return;
        }
        // Insert the badge AFTER the link so screen readers read the
        // name first ("Jane Doe, Suspended") not the other way around.
        if (a.parentNode) {
            a.parentNode.insertBefore(badge, a.nextSibling);
        }
        a.setAttribute('data-airpay-status-painted', '1');
    });
};

/**
 * Re-run decoration when the DOM mutates — gradebook and many reports
 * use AJAX paginators that swap table rows in without a full reload.
 *
 * @param {Object<string,string>} statusMap
 * @param {Object<string,string>} labels
 */
const watchForMutations = (statusMap, labels) => {
    if (typeof MutationObserver !== 'function') {
        return; // very old browser — initial paint is good enough
    }
    let scheduled = false;
    const observer = new MutationObserver(() => {
        if (scheduled) {
            return;
        }
        scheduled = true;
        // requestIdleCallback if available — never block the render loop.
        const run = () => {
            scheduled = false;
            paintBadges(statusMap, labels);
        };
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(run, {timeout: 500});
        } else {
            window.setTimeout(run, 16); // ~one frame
        }
    });
    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

/**
 * Module entry point. Called once per page from PHP via
 * `$PAGE->requires->js_call_amd('theme_airpayux/user_status_badge', 'init')`.
 *
 * @returns {Promise<void>}  resolves once initial paint completes
 */
export const init = async () => {
    const statusMap = readStatusMap();
    if (!statusMap || Object.keys(statusMap).length === 0) {
        return; // nothing to decorate
    }

    // Pull translated labels. Falls back to the English text if the
    // strings haven't been preloaded — happens occasionally on dev.
    let labels;
    try {
        const Str = await import('core/str');
        const [suspended, deleted, aria] = await Str.get_strings([
            {key: 'userstatus_suspended', component: 'local_sentientia_platform'},
            {key: 'userstatus_deleted', component: 'local_sentientia_platform'},
            {key: 'userstatus_badge_aria', component: 'local_sentientia_platform'},
        ]);
        labels = {suspended, deleted, aria};
    } catch (e) {
        labels = {
            suspended: 'Suspended',
            deleted: 'Deleted',
            aria: 'Account status: {$a}',
        };
    }

    paintBadges(statusMap, labels);
    watchForMutations(statusMap, labels);
};

export default {init};
