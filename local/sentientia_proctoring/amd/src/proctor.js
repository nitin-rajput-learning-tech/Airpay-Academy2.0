// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * In-attempt proctoring monitor (B1, ADR-038 D2, 2026-11-20).
 *
 * Loaded by the quiz access rule (quizaccess_sentientia_proctoring\rule::setup_attempt_page) on EVERY page of an
 * open proctored attempt, the attempt pages and the summary page, so it restarts on each page. Before B1 it was
 * started once, from the consent dialog, and stopped working after the first page.
 *
 * It records, on the session the server chose for the attempt:
 *  - page_shown       once per page (gaps in the timeline show pages where the monitor did not run);
 *  - tab_switch       the page was hidden (another tab, minimised window);
 *  - window_blur      the window lost focus to another window or application, and window_focus when it came back;
 *  - fullscreen_exit  the page left fullscreen;
 *  - clipboard_paste  text was pasted into the page, including into the rich-text editor.
 * The server stores its own time, sets the severity and refuses anything else (external\report_event).
 *
 * The page's own frames (B1 review, 2026-11-20). Moodle's editor (TinyMCE) puts essay answers in a same-origin
 * iframe. A paste there fires in the frame's document and never reaches this one, and while the cursor is in the
 * editor a switch to another application blurs the frame's window, not this one. So the monitor also listens inside
 * every same-origin frame of the page: those present at start, those added later (a MutationObserver), each again
 * after its document loads, and any frame that takes the focus. Frames from another origin (an embedded video)
 * cannot be read; a window switch made from one is still caught by a once-a-second check of document.hasFocus(),
 * which is true while this tab's window has the focus, wherever inside the page it is.
 *
 * B1 does not use the camera, the microphone or the screen (the webcam arrives with B4 behind
 * sentientia.proctoring.recording.enabled). It does not block anything (B2) and it does not end the session: the
 * server closes it when the attempt is submitted or abandoned. Moving between quiz pages is not a tab switch: page
 * unloads are ignored. Focus moving between the page and an editor inside it is not a window switch: window_blur is
 * recorded once when the window loses the focus and window_focus once when it gets it back.
 *
 * @module local_sentientia_proctoring/proctor
 */
import Ajax from 'core/ajax';
import Config from 'core/config';

const FLUSH_INTERVAL_MS = 5000;
const FOCUS_POLL_MS = 1000;
const MAX_BATCH = 20;
const MAX_TRIES = 3;
const REPORT_METHOD = 'local_sentientia_proctoring_report_event';

let sessionId = 0;
let quizPage = 0;
let queue = [];
let flushTimer = null;
let focusTimer = null;
let frameObserver = null;
let away = false;
let inFlight = false;
let leaving = false;
let stopped = false;
let started = false;

/**
 * Start the monitor for this page.
 *
 * @param {Object} config from the access rule
 * @param {number} config.sessionid the session the server found for the attempt
 * @param {number} config.attemptid the attempt
 * @param {number} config.page the quiz page shown (0 on the summary page)
 * @param {string} config.notice the banner text, already in the learner's language
 */
export const start = (config) => {
    if (started || !config) {
        return;
    }
    sessionId = parseInt(config.sessionid, 10) || 0;
    if (!sessionId) {
        return;
    }
    started = true;
    quizPage = parseInt(config.page, 10) || 0;

    showNotice(config.notice);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && !leaving) {
            record('tab_switch');
        }
    });
    window.addEventListener('blur', onBlur);
    window.addEventListener('focus', onFocus);
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && !leaving) {
            record('fullscreen_exit');
        }
    });
    document.addEventListener('paste', onPaste, true);

    // The page's own frames: the rich-text editor is one, and TinyMCE creates it after the page has loaded.
    document.querySelectorAll('iframe').forEach((frame) => watchFrame(frame));
    if (window.MutationObserver && document.body) {
        frameObserver = new window.MutationObserver(watchAddedFrames);
        frameObserver.observe(document.body, {childList: true, subtree: true});
    }

    // Navigating to the next quiz page hides this one: that is not a tab switch.
    window.addEventListener('beforeunload', () => {
        leaving = true;
        // If the navigation is cancelled the page stays: listen again after a moment.
        window.setTimeout(() => {
            leaving = false;
        }, 3000);
    });
    window.addEventListener('pagehide', () => {
        leaving = true;
        sendRemaining();
    });
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            // Back from the browser's page cache.
            leaving = false;
        }
    });

    flushTimer = window.setInterval(flush, FLUSH_INTERVAL_MS);
    focusTimer = window.setInterval(checkFocus, FOCUS_POLL_MS);
    record('page_shown');
    flush();
};

/**
 * Stop the monitor on this page. Kept for callers of the pre-B1 module: it sends what is queued and stops listening
 * for the rest of the page. It does not end the session; the server does that when the attempt ends.
 */
export const stop = () => {
    sendRemaining();
    halt();
};

/**
 * A paste, in this page or in one of its frames.
 */
const onPaste = () => record('clipboard_paste');

/**
 * This window, or the window of one of the page's frames, lost the focus. Read the focus after the browser has moved
 * it: focus going from the page into one of its frames (the text editor), or back, leaves document.hasFocus() true and
 * is not a switch. A frame that has just taken the focus is watched from now on.
 */
const onBlur = () => {
    window.setTimeout(() => {
        const active = document.activeElement;
        if (active && active.tagName === 'IFRAME') {
            watchFrame(active);
        }
        if (!document.hasFocus()) {
            markAway();
        }
    }, 0);
};

/**
 * This window, or the window of one of the page's frames, got the focus.
 */
const onFocus = () => {
    markBack();
};

/**
 * Once a second: does this tab's window have the focus? Catches a switch made while the focus was in a frame the
 * monitor cannot listen in (another origin), and a page shown while the learner is already in another window.
 */
const checkFocus = () => {
    if (document.hasFocus()) {
        markBack();
    } else {
        markAway();
    }
};

/**
 * The window lost the focus to another window or application: recorded once, until it comes back.
 */
const markAway = () => {
    if (leaving || away) {
        return;
    }
    away = true;
    record('window_blur');
};

/**
 * The window has the focus again after a recorded window_blur.
 */
const markBack = () => {
    if (leaving || !away) {
        return;
    }
    away = false;
    record('window_focus');
};

/**
 * A frame's document has (re)loaded: listen in the new document.
 *
 * @param {Event} e
 */
const onFrameLoad = (e) => watchFrame(e.target);

/**
 * Listen inside one of the page's frames: pastes in its document, focus moves of its window. Safe to call again for
 * the same frame (the same listener is never added twice) and needed again whenever the frame's document is replaced,
 * hence the load listener. A frame from another origin cannot be read and is skipped.
 *
 * @param {HTMLIFrameElement} frame
 */
const watchFrame = (frame) => {
    if (stopped || !frame || !frame.addEventListener) {
        return;
    }
    frame.addEventListener('load', onFrameLoad);
    let doc = null;
    try {
        doc = frame.contentDocument;
    } catch (e) {
        doc = null;
    }
    const win = doc ? doc.defaultView : null;
    if (!win) {
        return;
    }
    doc.addEventListener('paste', onPaste, true);
    win.addEventListener('blur', onBlur);
    win.addEventListener('focus', onFocus);
};

/**
 * Watch the frames among (or inside) the nodes added to the page.
 *
 * @param {MutationRecord[]} mutations
 */
const watchAddedFrames = (mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType !== 1) {
                return;
            }
            if (node.tagName === 'IFRAME') {
                watchFrame(node);
            } else if (node.querySelectorAll) {
                node.querySelectorAll('iframe').forEach((frame) => watchFrame(frame));
            }
        });
    });
};

/**
 * Queue one event.
 *
 * @param {string} type one of the event types the server accepts
 */
const record = (type) => {
    if (stopped) {
        return;
    }
    queue.push({type: type, tries: 0});
    if (type === 'clipboard_paste') {
        flush();
    }
};

/**
 * Send up to MAX_BATCH queued events in one request.
 */
const flush = () => {
    if (stopped || inFlight || queue.length === 0) {
        return;
    }
    const batch = queue.splice(0, MAX_BATCH);
    inFlight = true;
    const requests = Ajax.call(batch.map((e) => ({methodname: REPORT_METHOD, args: args(e.type)})), true, true);
    Promise.allSettled(requests).then((results) => {
        inFlight = false;
        results.forEach((result, i) => {
            if (result.status === 'fulfilled') {
                return;
            }
            const error = result.reason || {};
            if (error.errorcode === 'error_session_state') {
                // The server closed the session (the attempt was submitted elsewhere): stop recording.
                halt();
                return;
            }
            const e = batch[i];
            e.tries++;
            if (e.tries < MAX_TRIES) {
                queue.unshift(e);
            }
        });
        return results;
    }).catch(() => {
        inFlight = false;
    });
};

/**
 * Hand what is still queued to the browser as the page goes away (sendBeacon survives the unload; an Ajax call may
 * not). Same web service endpoint and session key as core/ajax.
 */
const sendRemaining = () => {
    if (stopped || queue.length === 0 || !navigator.sendBeacon) {
        return;
    }
    const batch = queue.splice(0, MAX_BATCH);
    const body = JSON.stringify(batch.map((e, i) => ({index: i, methodname: REPORT_METHOD, args: args(e.type)})));
    const url = Config.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(Config.sesskey)
        + '&info=' + REPORT_METHOD;
    navigator.sendBeacon(url, new Blob([body], {type: 'text/plain'}));
};

/**
 * The web service arguments for one event. The server ignores the severity and keeps only the page number.
 *
 * @param {string} type
 * @returns {Object}
 */
const args = (type) => ({
    sessionid: sessionId,
    // eslint-disable-next-line camelcase
    event_type: type,
    severity: 'info',
    // eslint-disable-next-line camelcase
    payload_json: JSON.stringify({page: quizPage}),
});

/**
 * Stop for good on this page.
 */
const halt = () => {
    stopped = true;
    queue = [];
    if (flushTimer) {
        window.clearInterval(flushTimer);
        flushTimer = null;
    }
    if (focusTimer) {
        window.clearInterval(focusTimer);
        focusTimer = null;
    }
    if (frameObserver) {
        frameObserver.disconnect();
        frameObserver = null;
    }
};

/**
 * Tell the learner, on every page, that the attempt is monitored. Text only, never HTML.
 *
 * @param {string} text
 */
const showNotice = (text) => {
    if (!text || document.getElementById('sentientia-proctoring-notice')) {
        return;
    }
    const notice = document.createElement('div');
    notice.id = 'sentientia-proctoring-notice';
    notice.className = 'alert alert-info';
    notice.setAttribute('role', 'status');
    notice.textContent = text;
    const host = document.querySelector('#region-main [role="main"]')
        || document.querySelector('[role="main"]')
        || document.getElementById('region-main');
    if (host) {
        host.insertBefore(notice, host.firstChild);
    }
};
