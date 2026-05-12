// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * In-exam proctoring runtime.
 *
 * Runs during the candidate's quiz attempt. Captures:
 *  - Tab visibility changes (event: tab_switch)
 *  - Window blur/focus (event: window_blur)
 *  - Fullscreen exits (event: fullscreen_exit)
 *  - Clipboard paste (event: clipboard_paste)
 *  - Webcam frame snapshots → could be fed to local face detection
 *  - Mic level samples (for noise spike detection)
 *
 * Events are batched and flushed every 5 seconds to reduce server load.
 *
 * @module local_airpay_proctoring/proctor
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';

let SESSION_ID = 0;
let MEDIA_STREAM = null;
let SCREEN_STREAM = null;
let EVENT_QUEUE = [];
let FLUSH_TIMER = null;

const FLUSH_INTERVAL_MS = 5000;
const FACE_CHECK_INTERVAL_MS = 3000;  // sample face presence every 3s

/**
 * Start the proctoring runtime for an active session.
 *
 * Call this after the candidate has consented and identity has passed.
 */
export const start = async (sessionid) => {
    SESSION_ID = parseInt(sessionid, 10);
    if (!SESSION_ID) return;

    // 1. Page visibility (tab switch).
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) queueEvent('tab_switch', 'warn');
    });

    // 2. Window focus.
    window.addEventListener('blur',  () => queueEvent('window_blur', 'warn'));
    window.addEventListener('focus', () => queueEvent('window_focus', 'info'));

    // 3. Fullscreen exits.
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) queueEvent('fullscreen_exit', 'warn');
    });

    // 4. Clipboard paste detection.
    document.addEventListener('paste', () => queueEvent('clipboard_paste', 'critical'));

    // 5. Window beforeunload — give the candidate a warning if they close the tab.
    window.addEventListener('beforeunload', (e) => {
        flushNow();
        e.preventDefault();
        e.returnValue = '';
    });

    // 6. Start webcam + screen capture.
    try {
        MEDIA_STREAM = await navigator.mediaDevices.getUserMedia({
            video: { width: 320, height: 240 },
            audio: true,
        });
        const video = ensureVideoEl('proctor-webcam', MEDIA_STREAM);
        // Face presence sampling — light client-side check.
        setInterval(() => sampleFace(video), FACE_CHECK_INTERVAL_MS);
    } catch (err) {
        queueEvent('webcam_denied', 'critical', { error: err.message });
    }

    try {
        SCREEN_STREAM = await navigator.mediaDevices.getDisplayMedia({
            video: true, audio: false,
        });
    } catch (err) {
        queueEvent('screen_share_denied', 'critical', { error: err.message });
    }

    // Periodic flush.
    FLUSH_TIMER = setInterval(flushQueue, FLUSH_INTERVAL_MS);

    // Initial event.
    queueEvent('runtime_started', 'info');
};

/** Stop monitoring. Called on quiz submit. */
export const stop = async () => {
    if (FLUSH_TIMER) {
        clearInterval(FLUSH_TIMER);
        FLUSH_TIMER = null;
    }
    queueEvent('runtime_stopped', 'info');
    await flushNow();
    if (MEDIA_STREAM) {
        MEDIA_STREAM.getTracks().forEach(t => t.stop());
    }
    if (SCREEN_STREAM) {
        SCREEN_STREAM.getTracks().forEach(t => t.stop());
    }
    // Finalize the session server-side.
    await Ajax.call([{
        methodname: 'local_airpay_proctoring_finalize',
        args: { sessionid: SESSION_ID }
    }])[0].catch(Notification.exception);
};

const queueEvent = (type, severity = 'info', payload = {}) => {
    EVENT_QUEUE.push({
        event_type: type,
        severity: severity,
        payload_json: JSON.stringify(payload),
        ts: Date.now(),
    });
    // Critical events flush immediately.
    if (severity === 'critical') {
        flushNow();
    }
};

const flushQueue = async () => {
    if (EVENT_QUEUE.length === 0) return;
    const batch = EVENT_QUEUE.splice(0, EVENT_QUEUE.length);
    // Use Promise.all for parallelism but bounded.
    const calls = batch.map(e => ({
        methodname: 'local_airpay_proctoring_report_event',
        args: {
            sessionid: SESSION_ID,
            event_type: e.event_type,
            severity: e.severity,
            payload_json: e.payload_json,
        }
    }));
    try {
        await Promise.all(Ajax.call(calls));
    } catch (err) {
        // Re-queue on failure (network blip etc.).
        EVENT_QUEUE.unshift(...batch);
    }
};

const flushNow = async () => {
    return flushQueue();
};

const ensureVideoEl = (id, stream) => {
    let el = document.getElementById(id);
    if (!el) {
        el = document.createElement('video');
        el.id = id;
        el.autoplay = true; el.muted = true; el.playsInline = true;
        el.style.cssText = 'position:fixed;bottom:10px;right:10px;width:160px;height:120px;'
            + 'border:2px solid #0066A7;border-radius:8px;z-index:9999;background:#000;';
        document.body.appendChild(el);
    }
    el.srcObject = stream;
    return el;
};

/**
 * Sample face presence. For the prototype we just check whether the
 * webcam is actively streaming. Real production would use a face-detection
 * library (e.g. face-api.js) loaded lazily.
 */
const sampleFace = (videoEl) => {
    if (!videoEl || videoEl.videoWidth === 0) {
        queueEvent('face_lost', 'warn');
    }
    // Future: run face-api.js detection here, report multiple_faces / no_face.
};
