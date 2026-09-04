// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Trainer SSE client — Phase E.3.c (ES6 source).
 *
 * Subscribes to stream.php with role=trainer and updates the live
 * runner DOM in place. textContent only — no innerHTML.
 *
 * H4 remediation (2026-09-04): stream.php can now respond with a plain
 * HTTP 503 (Retry-After) when the server-side SSE concurrency cap is
 * reached (see classes/sse_connection_registry.php). EventSource treats
 * a non-2xx response as a fatal error — readyState goes CLOSED and the
 * browser does NOT retry on its own. Previously this module's 'error'
 * handler was a no-op, so a trainer whose connection hit that cap (most
 * plausible exactly during the volumetric-flood scenario H4 mitigates)
 * silently lost all realtime updates for the rest of the session. Mirror
 * audience_sse.js's fallback: on a fatal close, reload the page after a
 * short delay so the trainer re-attempts the stream from a fresh request.
 *
 * @module local_sentientia_live/trainer_sse
 */

const RETRY_RELOAD_MS = 10000;

const init = (opts = {}) => {
    const sessionid = opts.sessionid;
    // Default to M.cfg.wwwroot-prefixed URL so this works in any
    // Moodle deploy location (subfolder install vs root install).
    // M is a bare global in Moodle (not window.M), hence typeof guard.
    const wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
    const streamUrl = opts.streamUrl || (wwwroot + '/local/sentientia_live/stream.php');

    if (!sessionid || typeof window.EventSource === 'undefined') {
        return;
    }

    const url = `${streamUrl}?sessionid=${encodeURIComponent(sessionid)}&role=trainer`;

    let es;
    try {
        // withCredentials triggers CORS-credentials mode in Chrome
        // even same-origin. Same-origin cookies are sent by default;
        // omitting the option avoids the spurious CORS rejection.
        es = new EventSource(url);
    } catch (e) {
        return;
    }

    const updateAudienceCount = (count) => {
        const el = document.getElementById('sentientia-audience-count');
        if (el) {
            el.textContent = String(count);
        }
    };

    es.addEventListener('error', () => {
        // Non-fatal errors (a normal network hiccup, or our own
        // intentional wall-clock rotation closing the connection)
        // leave the browser in CONNECTING state and it retries on its
        // own — nothing to do here. Only a fatal close (CLOSED — e.g.
        // the H4 503 concurrency-cap response) needs a manual retry.
        if (es.readyState === EventSource.CLOSED) {
            setTimeout(() => window.location.reload(), RETRY_RELOAD_MS);
        }
    });

    es.addEventListener('participant_joined', (ev) => {
        try {
            const data = JSON.parse(ev.data);
            if (typeof data.count_now === 'number') {
                updateAudienceCount(data.count_now);
            }
        } catch {}
    });

    es.addEventListener('participant_left', (ev) => {
        try {
            const data = JSON.parse(ev.data);
            if (typeof data.count_now === 'number') {
                updateAudienceCount(data.count_now);
            }
        } catch {}
    });

    es.addEventListener('response_added', (ev) => {
        try {
            const data = JSON.parse(ev.data);
            const counter = document.getElementById('sentientia-response-count');
            if (counter && typeof data.count_now === 'number') {
                counter.textContent = String(data.count_now);
            }
            window.dispatchEvent(new CustomEvent(
                'sentientia-live:response_added',
                {detail: data}
            ));
        } catch {}
    });

    es.addEventListener('slide_changed', () => window.location.reload());
    es.addEventListener('session_ended', () => window.location.reload());

    window.sentientiaLiveTrainerSSE = es;
};

export {init};
