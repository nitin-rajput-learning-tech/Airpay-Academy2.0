// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Audience SSE client — Phase E.3.b (ES6 source for grunt).
 *
 * Hand-bundled ES5 version in amd/build/audience_sse.min.js.
 *
 * Listens for slide_changed / session_ended (full reload) and
 * response_added (dispatched as a DOM CustomEvent for E.4 to handle).
 * Never sets innerHTML — page-level refreshes only.
 *
 * @module local_sentientia_live/audience_sse
 */

const POLL_FALLBACK_MS = 10000;

const init = (opts = {}) => {
    const sessionid = opts.sessionid;
    const token     = opts.token || '';
    // Default to M.cfg.wwwroot-prefixed URL so this works in any
    // Moodle deploy location (subfolder install vs root install).
    // M is a bare global in Moodle (not window.M), hence typeof guard.
    const wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
    const streamUrl = opts.streamUrl || (wwwroot + '/local/sentientia_live/stream.php');

    if (!sessionid) {
        setTimeout(() => window.location.reload(), POLL_FALLBACK_MS);
        return;
    }
    if (typeof window.EventSource === 'undefined') {
        setTimeout(() => window.location.reload(), POLL_FALLBACK_MS);
        return;
    }

    const qs = new URLSearchParams({sessionid: String(sessionid)});
    if (token) {
        qs.set('token', token);
    }
    const url = `${streamUrl}?${qs.toString()}`;

    let es;
    try {
        es = new EventSource(url, {withCredentials: false});
    } catch (e) {
        setTimeout(() => window.location.reload(), POLL_FALLBACK_MS);
        return;
    }

    es.addEventListener('error', () => {
        if (es.readyState === EventSource.CLOSED) {
            setTimeout(() => window.location.reload(), POLL_FALLBACK_MS);
        }
    });

    es.addEventListener('sync', (ev) => {
        try {
            const data = JSON.parse(ev.data);
            const domSlideId = parseInt(
                document.body.dataset.currentSlideId || '0', 10);
            if (data.current_slide && data.current_slide !== domSlideId) {
                window.location.reload();
            }
        } catch {}
    });

    es.addEventListener('slide_changed', () => window.location.reload());
    es.addEventListener('session_ended', () => window.location.reload());

    es.addEventListener('response_added', (ev) => {
        try {
            const data = JSON.parse(ev.data);
            window.dispatchEvent(new CustomEvent(
                'sentientia-live:response_added',
                {detail: data}
            ));
        } catch {}
    });

    window.sentientiaLiveSSE = es;
};

export {init};
