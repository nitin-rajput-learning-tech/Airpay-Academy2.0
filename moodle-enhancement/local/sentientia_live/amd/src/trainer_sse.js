// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Trainer SSE client — Phase E.3.c (ES6 source).
 *
 * Subscribes to stream.php with role=trainer and updates the live
 * runner DOM in place. textContent only — no innerHTML.
 *
 * @module local_sentientia_live/trainer_sse
 */

const init = (opts = {}) => {
    const sessionid = opts.sessionid;
    const streamUrl = opts.streamUrl || '/local/sentientia_live/stream.php';

    if (!sessionid || typeof window.EventSource === 'undefined') {
        return;
    }

    const url = `${streamUrl}?sessionid=${encodeURIComponent(sessionid)}&role=trainer`;

    let es;
    try {
        es = new EventSource(url, {withCredentials: true});
    } catch (e) {
        return;
    }

    const updateAudienceCount = (count) => {
        const el = document.getElementById('sentientia-audience-count');
        if (el) {
            el.textContent = String(count);
        }
    };

    es.addEventListener('error', () => {});

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
