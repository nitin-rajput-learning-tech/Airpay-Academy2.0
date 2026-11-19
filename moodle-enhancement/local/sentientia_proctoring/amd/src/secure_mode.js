// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Secure assessment mode on the quiz attempt pages (B2, ADR-038 D3 and D6, post-demo plan B2, 2026-11-20).
 *
 * Loaded by the quiz access rule (quizaccess_sentientia_proctoring\rule::setup_secure_page) on every page of an open
 * attempt of a quiz that runs in secure mode, behind sentientia.assessment.secure_mode.enabled. On this page it:
 *  - hides the questions until the page is in fullscreen (attempt pages only; the summary page shows no question),
 *    with a button that enters fullscreen, and hides them again whenever the page leaves fullscreen;
 *  - blocks copy, cut, paste, right-click, drag and drop, printing and common shortcuts (also inside the page's own
 *    frames, such as the rich-text editor), and lists the attempts on the server without counting them;
 *  - shows the learner's name and attempt number faintly across the page;
 *  - lists a second display where the browser says so (Chromium's screen.isExtended), without counting it;
 *  - reports to local_sentientia_proctoring_secure_report (core/ajax, so with the session key): leaving fullscreen,
 *    the page being hidden and the window losing the focus at once, everything else with the next heartbeat, every
 *    HEARTBEAT seconds. The server stores its own time, decides what counts as a violation, and may submit the
 *    attempt (hiring assessments at their limit); this page only shows the count it gets back.
 *  - B2 review (2026-11-20): the server does not count a lost focus in the first seconds after it sent the page
 *    (permission prompts blur the window), but only when it ends within that grace. So the focus coming back is
 *    reported at once too, and a lost focus that began in the grace and is still lost when it ends is reported again
 *    (window_still_away); the server then counts it.
 *
 * What it cannot do: see a second device, another person or a screenshot taken with the computer's own tools, and
 * stop a learner who switches this script off. The server records a heartbeat gap when the page stops reporting.
 * Moving between the quiz's own pages is not a violation: page unloads are ignored, as in the B1 monitor.
 *
 * @module local_sentientia_proctoring/secure_mode
 */
import Ajax from 'core/ajax';
import Config from 'core/config';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';

const METHOD = 'local_sentientia_proctoring_secure_report';
const FOCUS_POLL_MS = 1000;
const BLOCKED_REPORT_MS = 10000;
const BLOCKED_HINT_MS = 4000;
const FILE_DIALOG_MS = 60000;
const MAX_BATCH = 20;
const MAX_QUEUE = 200;
const VIOLATIONS = ['tab_switch', 'window_blur', 'fullscreen_exit'];
const STILL_AWAY = 'window_still_away';
// Sent at once, so the server's time is the moment they happened; everything else waits for the next heartbeat.
const IMMEDIATE = ['page_shown', 'window_focus', STILL_AWAY].concat(VIOLATIONS);
// Seconds past the grace before a lost focus is reported as still lost: the server counts in whole seconds.
const GRACE_MARGIN_S = 2;
const COMPONENT = 'local_sentientia_proctoring';

let cfg = null;
let started = false;
let stopped = false;
let leaving = false;
let away = false;
let inFlight = false;
let queue = [];
let timers = [];
let frameObserver = null;
let gate = null;
let banner = null;
let violations = 0;
let limit = 0;
let fileDialogUntil = 0;
let lastHint = 0;
let done = false;
let startedAt = 0;
let awayEpisode = 0;
let graceTimer = null;
const lastBlocked = {};

/**
 * Start secure mode on this page.
 *
 * @param {Object} config from the access rule
 * @param {number} config.attemptid the open attempt
 * @param {number} config.page the quiz page (-1 on the summary page)
 * @param {boolean} config.gate hide the questions outside fullscreen (attempt pages)
 * @param {string} config.watermark the learner's name and attempt number, as text
 * @param {number} config.violations violations counted so far
 * @param {number} config.limit violations at which the attempt is submitted, 0 = never
 * @param {number} config.heartbeat seconds between two reports
 * @param {number} config.grace seconds after the server sent the page in which a short lost focus is not counted
 * @param {string} config.viewurl the quiz's page, where the learner goes once the attempt is submitted
 */
export const start = (config) => {
    if (started || !config || !(parseInt(config.attemptid, 10) > 0)) {
        return;
    }
    started = true;
    // The server sent the page before this runs, so its grace ends no later than this page's.
    startedAt = Date.now();
    const grace = parseInt(config.grace, 10);
    cfg = {
        attemptid: parseInt(config.attemptid, 10),
        page: parseInt(config.page, 10) || 0,
        gate: !!config.gate,
        watermark: String(config.watermark || ''),
        heartbeat: Math.max(5, parseInt(config.heartbeat, 10) || 15),
        grace: grace >= 0 ? grace : 10,
        viewurl: String(config.viewurl || ''),
    };
    violations = parseInt(config.violations, 10) || 0;
    limit = parseInt(config.limit, 10) || 0;

    // The access rule already put both classes on the page, so the questions were hidden from the first paint.
    document.body.classList.add('sentientia-secure-active');
    const gated = cfg.gate && fullscreenSupported();
    if (gated) {
        hideQuestions();
    } else {
        revealQuestions();
    }

    protect(window, document);
    document.querySelectorAll('iframe').forEach((frame) => watchFrame(frame));
    if (window.MutationObserver && document.body) {
        frameObserver = new window.MutationObserver(watchAddedFrames);
        frameObserver.observe(document.body, {childList: true, subtree: true});
    }
    watermark(cfg.watermark);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && !leaving) {
            record('tab_switch');
        }
    });
    window.addEventListener('blur', onBlur);
    window.addEventListener('focus', onFocus);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    window.addEventListener('beforeprint', () => blocked('print_blocked'));
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
            leaving = false;
        }
    });

    record('page_shown');
    if (window.screen && window.screen.isExtended === true) {
        // A second display (the browser says so in Chromium only; elsewhere this is never known).
        record('extended_display');
    }
    if (cfg.gate && !gated) {
        // A phone browser without fullscreen: the questions stay visible, and the reviewer sees why.
        record('fullscreen_unavailable');
    }
    showBanner();
    if (gated) {
        showGate('secure_gate_body');
    }
    timers.push(window.setInterval(heartbeat, cfg.heartbeat * 1000));
    timers.push(window.setInterval(checkFocus, FOCUS_POLL_MS));
};

/**
 * Can this browser show the page in fullscreen?
 *
 * @returns {boolean}
 */
const fullscreenSupported = () => !!(document.fullscreenEnabled || document.webkitFullscreenEnabled);

/**
 * Is the page in fullscreen now?
 *
 * @returns {boolean}
 */
const isFullscreen = () => !!(document.fullscreenElement || document.webkitFullscreenElement);

/**
 * Ask the browser for fullscreen. Must run inside the learner's click.
 *
 * @returns {Promise}
 */
const requestFullscreen = () => {
    const root = document.documentElement;
    try {
        if (root.requestFullscreen) {
            return Promise.resolve(root.requestFullscreen({navigationUI: 'hide'}));
        }
        if (root.webkitRequestFullscreen) {
            root.webkitRequestFullscreen();
            return new Promise((resolve, reject) => {
                window.setTimeout(() => (isFullscreen() ? resolve() : reject(new Error('fullscreen'))), 500);
            });
        }
    } catch (e) {
        return Promise.reject(e);
    }
    return Promise.reject(new Error('fullscreen'));
};

/**
 * The page entered or left fullscreen.
 */
const onFullscreenChange = () => {
    if (done) {
        return;
    }
    if (isFullscreen()) {
        revealQuestions();
        return;
    }
    if (leaving) {
        return;
    }
    record('fullscreen_exit');
    if (cfg.gate) {
        showGate('secure_gate_returned');
    }
};

/**
 * The quiz's question form.
 *
 * @returns {HTMLElement|null}
 */
const questionForm = () => document.getElementById('responseform');

/**
 * Hide the questions: out of sight (styles.css) and out of reach of the keyboard and screen readers.
 */
const hideQuestions = () => {
    document.body.classList.add('sentientia-secure-gated');
    const form = questionForm();
    if (form) {
        form.setAttribute('aria-hidden', 'true');
        form.inert = true;
    }
};

/**
 * Show the questions again (in fullscreen).
 */
const revealQuestions = () => {
    document.body.classList.remove('sentientia-secure-gated');
    const form = questionForm();
    if (form) {
        form.removeAttribute('aria-hidden');
        form.inert = false;
    }
    if (gate) {
        gate.hidden = true;
    }
};

/**
 * Show the fullscreen gate over the page.
 *
 * @param {string} bodykey the string that explains why
 */
const showGate = (bodykey) => {
    hideQuestions();
    if (!gate) {
        gate = document.createElement('div');
        gate.className = 'sentientia-secure-gate';
        gate.setAttribute('role', 'dialog');
        gate.setAttribute('aria-modal', 'true');
        gate.setAttribute('aria-labelledby', 'sentientia-secure-gate-title');
        const card = document.createElement('div');
        card.className = 'sentientia-secure-gate__card';
        const title = document.createElement('h2');
        title.id = 'sentientia-secure-gate-title';
        title.className = 'sentientia-secure-gate__title';
        const body = document.createElement('p');
        body.className = 'sentientia-secure-gate__body';
        const warning = document.createElement('p');
        warning.className = 'sentientia-secure-gate__warning';
        warning.setAttribute('role', 'alert');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary';
        button.addEventListener('click', onGateButton);
        card.append(title, body, warning, button);
        gate.appendChild(card);
        document.body.appendChild(gate);
        setText(title, 'secure_gate_title');
        setText(button, 'secure_gate_button');
    }
    setText(gate.querySelector('.sentientia-secure-gate__body'), bodykey);
    updateGateWarning();
    gate.hidden = false;
    const button = gate.querySelector('button');
    if (button) {
        button.focus();
    }
};

/**
 * The learner asked for fullscreen.
 */
const onGateButton = () => {
    requestFullscreen().then(() => {
        revealQuestions();
        record('fullscreen_enter');
        return null;
    }).catch(() => {
        record('fullscreen_denied');
        showGate('secure_gate_denied');
    });
};

/**
 * The count shown in the gate after a violation.
 */
const updateGateWarning = () => {
    if (!gate || done) {
        return;
    }
    const warning = gate.querySelector('.sentientia-secure-gate__warning');
    if (violations <= 0) {
        warning.textContent = '';
        return;
    }
    setText(warning, limit > 0 ? 'secure_warning_limit' : 'secure_warning_flag',
        limit > 0 ? {count: violations, limit: limit} : violations);
};

/**
 * The secure-mode banner at the top of the page, with the count.
 */
const showBanner = () => {
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'sentientia-secure-banner';
        banner.className = 'sentientia-secure-banner alert alert-info';
        banner.setAttribute('role', 'status');
        const host = document.querySelector('#region-main [role="main"]')
            || document.querySelector('[role="main"]')
            || document.getElementById('region-main');
        if (!host) {
            return;
        }
        host.insertBefore(banner, host.firstChild);
    }
    const parts = [getString('secure_banner', COMPONENT)];
    if (violations > 0) {
        parts.push(limit > 0
            ? getString('secure_banner_limit', COMPONENT, {count: violations, limit: limit})
            : getString('secure_banner_count', COMPONENT, violations));
    }
    Promise.all(parts).then((texts) => {
        banner.textContent = texts.join(' ');
        banner.classList.toggle('alert-info', violations <= 0);
        banner.classList.toggle('alert-warning', violations > 0);
        return texts;
    }).catch(() => null);
};

/**
 * A violation was counted: tell the learner, with the count.
 */
const warn = () => {
    showBanner();
    updateGateWarning();
    const message = limit > 0
        ? getString('secure_warning_limit', COMPONENT, {count: violations, limit: limit})
        : getString('secure_warning_flag', COMPONENT, violations);
    message.then((text) => addToast(text, {type: 'warning', autohide: false, closeButton: true})).catch(() => null);
};

/**
 * The server submitted the attempt: say so, then go to the quiz page.
 *
 * @param {string} url
 */
const finished = (url) => {
    done = true;
    leaving = true;
    hideQuestions();
    if (isFullscreen() && document.exitFullscreen) {
        document.exitFullscreen().catch(() => null);
    }
    if (!gate) {
        showGate('secure_autosubmitted_body');
    }
    gate.hidden = false;
    setText(gate.querySelector('.sentientia-secure-gate__title'), 'secure_autosubmitted_title');
    setText(gate.querySelector('.sentientia-secure-gate__body'), 'secure_autosubmitted_body', violations);
    gate.querySelector('.sentientia-secure-gate__warning').textContent = '';
    const button = gate.querySelector('button');
    button.removeEventListener('click', onGateButton);
    setText(button, 'secure_continue');
    button.addEventListener('click', () => {
        window.location.href = url || cfg.viewurl;
    });
    button.focus();
};

/**
 * Put a string into an element, as text.
 *
 * @param {HTMLElement|null} element
 * @param {string} key
 * @param {*} param
 */
const setText = (element, key, param) => {
    if (!element) {
        return;
    }
    getString(key, COMPONENT, param).then((text) => {
        element.textContent = text;
        return text;
    }).catch(() => null);
};

/**
 * Block what a page can block, in this page or one of its frames.
 *
 * @param {Window} win
 * @param {Document} doc
 */
const protect = (win, doc) => {
    doc.addEventListener('copy', onClipboard, true);
    doc.addEventListener('cut', onClipboard, true);
    doc.addEventListener('paste', onClipboard, true);
    doc.addEventListener('contextmenu', onContextMenu, true);
    doc.addEventListener('dragstart', onDragStart, true);
    doc.addEventListener('drop', onDrop, true);
    doc.addEventListener('keydown', onKeyDown, true);
    doc.addEventListener('keyup', onKeyUp, true);
    doc.addEventListener('selectstart', onSelectStart, true);
    doc.addEventListener('click', onClick, true);
    if (win !== window) {
        win.addEventListener('blur', onBlur);
        win.addEventListener('focus', onFocus);
    }
};

/**
 * Copy, cut or paste.
 *
 * @param {ClipboardEvent} e
 */
const onClipboard = (e) => {
    e.preventDefault();
    e.stopPropagation();
    blocked(e.type + '_blocked');
};

/**
 * Right-click menu.
 *
 * @param {MouseEvent} e
 */
const onContextMenu = (e) => {
    e.preventDefault();
    blocked('contextmenu_blocked');
};

/**
 * Dragging text out of the page.
 *
 * @param {DragEvent} e
 */
const onDragStart = (e) => {
    e.preventDefault();
};

/**
 * Dropping text into the page (a paste by another name).
 *
 * @param {DragEvent} e
 */
const onDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    blocked('drop_blocked');
};

/**
 * Is this element one the learner types an answer into?
 *
 * @param {EventTarget} target
 * @returns {boolean}
 */
const isEditable = (target) => {
    if (!target || target.nodeType !== 1) {
        return false;
    }
    const tag = target.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || !!target.isContentEditable;
};

/**
 * Selecting question text (answers can still be selected).
 *
 * @param {Event} e
 */
const onSelectStart = (e) => {
    let target = e.target || null;
    if (target && target.nodeType !== 1) {
        // A text node: judge by the element it is in.
        target = target.parentElement;
    }
    if (!isEditable(target) && !(target && target.closest && target.closest('[contenteditable="true"]'))) {
        e.preventDefault();
    }
};

/**
 * Keyboard shortcuts: copy, cut, paste, print, save, view source and the developer tools.
 *
 * @param {KeyboardEvent} e
 */
const onKeyDown = (e) => {
    const key = (e.key || '').toLowerCase();
    const mod = e.ctrlKey || e.metaKey;
    let type = '';
    if (mod && !e.altKey && ['c', 'x', 'v', 'p', 's', 'u'].includes(key)) {
        type = key === 'p' ? 'print_blocked' : 'shortcut_blocked';
    } else if (mod && e.shiftKey && ['i', 'j', 'c', 'k'].includes(key)) {
        type = 'shortcut_blocked';
    } else if (e.metaKey && e.altKey && ['i', 'j', 'c', 'u'].includes(key)) {
        type = 'shortcut_blocked';
    } else if (key === 'f12' || (key === 'insert' && (e.shiftKey || mod))) {
        type = 'shortcut_blocked';
    }
    if (type) {
        e.preventDefault();
        e.stopPropagation();
        blocked(type);
    }
};

/**
 * The Print Screen key cannot be blocked; it is listed for the reviewer.
 *
 * @param {KeyboardEvent} e
 */
const onKeyUp = (e) => {
    if (e.key === 'PrintScreen') {
        blocked('screenshot_key', false);
    }
};

/**
 * A click on a file field opens the computer's file chooser, which takes the focus: that is not a switch.
 *
 * @param {MouseEvent} e
 */
const onClick = (e) => {
    const target = e.target;
    if (target && target.tagName === 'INPUT' && String(target.type).toLowerCase() === 'file') {
        fileDialogUntil = Date.now() + FILE_DIALOG_MS;
    }
};

/**
 * A blocked action: listed on the server at most once per type every BLOCKED_REPORT_MS, never counted.
 *
 * @param {string} type
 * @param {boolean} hint show the learner why nothing happened
 */
const blocked = (type, hint = true) => {
    const now = Date.now();
    if (!lastBlocked[type] || now - lastBlocked[type] >= BLOCKED_REPORT_MS) {
        lastBlocked[type] = now;
        record(type);
    }
    if (hint && now - lastHint >= BLOCKED_HINT_MS) {
        lastHint = now;
        getString('secure_blocked', COMPONENT).then((text) => addToast(text, {type: 'info'})).catch(() => null);
    }
};

/**
 * This window, or the window of one of the page's frames, lost the focus. As in the B1 monitor: read the focus
 * after the browser has moved it, so focus moving into the page's own editor is not a switch.
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
 * This window, or one of its frames, got the focus.
 */
const onFocus = () => {
    fileDialogUntil = 0;
    markBack();
};

/**
 * Once a second: does this tab's window have the focus?
 */
const checkFocus = () => {
    if (document.hasFocus()) {
        markBack();
    } else {
        markAway();
    }
};

/**
 * The window lost the focus: recorded once, until it comes back.
 */
const markAway = () => {
    if (leaving || away || stopped) {
        return;
    }
    away = true;
    awayEpisode++;
    if (Date.now() < fileDialogUntil) {
        record('file_dialog');
        return;
    }
    record('window_blur');
    watchGrace(awayEpisode);
};

/**
 * A lost focus that began in the grace: if it has not ended when the grace is over, say so, so that the server counts
 * it (it does not count a lost focus that is still within the grace, B2 review).
 *
 * @param {number} episode the lost focus this is about
 */
const watchGrace = (episode) => {
    const elapsed = Date.now() - startedAt;
    if (elapsed >= cfg.grace * 1000) {
        // Past the grace on this page's clock, so past it on the server's too: the server has counted it.
        return;
    }
    window.clearTimeout(graceTimer);
    graceTimer = window.setTimeout(() => {
        graceTimer = null;
        if (away && episode === awayEpisode && !leaving && !stopped) {
            record(STILL_AWAY);
        }
    }, (cfg.grace + GRACE_MARGIN_S) * 1000 - elapsed);
};

/**
 * The window has the focus again. Reported at once: a lost focus that began in the grace counts only when it ended
 * after the grace, and the server takes the time of this report.
 */
const markBack = () => {
    if (leaving || !away || stopped) {
        return;
    }
    away = false;
    window.clearTimeout(graceTimer);
    graceTimer = null;
    record('window_focus');
};

/**
 * A frame's document has (re)loaded: protect the new document.
 *
 * @param {Event} e
 */
const onFrameLoad = (e) => watchFrame(e.target);

/**
 * Protect one of the page's own frames (the rich-text editor). A frame from another origin cannot be read and is
 * skipped; a window switch from inside it is still caught by checkFocus().
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
    if (!win || doc.sentientiaSecure) {
        return;
    }
    doc.sentientiaSecure = true;
    protect(win, doc);
};

/**
 * Protect the frames among (or inside) the nodes added to the page.
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
 * Draw the learner's name and attempt number across the page, faintly, on a layer that never takes a click.
 *
 * @param {string} text
 */
const watermark = (text) => {
    if (!text || document.getElementById('sentientia-secure-watermark')) {
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 420;
    canvas.height = 220;
    const ctx = canvas.getContext ? canvas.getContext('2d') : null;
    if (!ctx) {
        return;
    }
    let size = 16;
    ctx.font = '600 ' + size + 'px Montserrat, Arial, sans-serif';
    while (size > 9 && ctx.measureText(text).width > 380) {
        size--;
        ctx.font = '600 ' + size + 'px Montserrat, Arial, sans-serif';
    }
    ctx.fillStyle = window.getComputedStyle(document.body).color || '#1a1a2e';
    ctx.globalAlpha = 0.12;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(-Math.PI / 9);
    ctx.fillText(text, 0, 0);
    const layer = document.createElement('div');
    layer.id = 'sentientia-secure-watermark';
    layer.className = 'sentientia-secure-watermark';
    layer.setAttribute('aria-hidden', 'true');
    layer.style.backgroundImage = 'url("' + canvas.toDataURL('image/png') + '")';
    document.body.appendChild(layer);
};

/**
 * Queue one event. Violations, the focus coming back, a lost focus still lost after the grace and the page opening go
 * at once (IMMEDIATE), so the server's time is the moment they happened; everything else waits for the next heartbeat.
 *
 * @param {string} type
 */
const record = (type) => {
    if (stopped) {
        return;
    }
    queue.push({type: type, page: cfg.page});
    if (queue.length > MAX_QUEUE) {
        queue = queue.slice(queue.length - MAX_QUEUE);
    }
    if (IMMEDIATE.includes(type)) {
        send();
    }
};

/**
 * The heartbeat: a report, with whatever is queued (possibly nothing).
 */
const heartbeat = () => {
    send(true);
};

/**
 * Send up to MAX_BATCH queued events (or, for the heartbeat, none) and act on the server's answer.
 *
 * @param {boolean} beat send even with nothing queued
 */
const send = (beat = false) => {
    if (stopped || inFlight || (!beat && queue.length === 0)) {
        return;
    }
    const batch = queue.splice(0, MAX_BATCH);
    inFlight = true;
    Ajax.call([{methodname: METHOD, args: {attemptid: cfg.attemptid, events: batch}}])[0].then((result) => {
        inFlight = false;
        handle(result);
        if (!stopped && queue.some((e) => IMMEDIATE.includes(e.type))) {
            send();
        }
        return result;
    }).catch((error) => {
        inFlight = false;
        if (error && error.errorcode === 'error_session_state') {
            halt();
            return;
        }
        // Network trouble: keep the events for the next heartbeat.
        queue = batch.concat(queue).slice(0, MAX_QUEUE);
    });
};

/**
 * Act on the server's answer.
 *
 * @param {Object} result
 */
const handle = (result) => {
    if (!result) {
        return;
    }
    violations = parseInt(result.violations, 10) || 0;
    limit = parseInt(result.limit, 10) || 0;
    if (result.autosubmitted) {
        finished(result.redirecturl);
        halt();
        return;
    }
    if (result.closed) {
        halt();
        return;
    }
    if (result.counted > 0) {
        warn();
    }
};

/**
 * Hand what is still queued to the browser as the page goes away (sendBeacon survives the unload). Same endpoint
 * and session key as core/ajax.
 */
const sendRemaining = () => {
    if (stopped || queue.length === 0 || !navigator.sendBeacon) {
        return;
    }
    const batch = queue.splice(0, MAX_BATCH);
    const body = JSON.stringify([{index: 0, methodname: METHOD, args: {attemptid: cfg.attemptid, events: batch}}]);
    const url = Config.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(Config.sesskey)
        + '&info=' + METHOD;
    navigator.sendBeacon(url, new Blob([body], {type: 'text/plain'}));
};

/**
 * Stop reporting on this page (the attempt is closed, or its session no longer takes reports). The blocking and the
 * fullscreen gate stay until the page is left; only the reports and the heartbeat stop.
 */
const halt = () => {
    stopped = true;
    queue = [];
    timers.forEach((timer) => window.clearInterval(timer));
    timers = [];
    window.clearTimeout(graceTimer);
    graceTimer = null;
    if (frameObserver) {
        frameObserver.disconnect();
        frameObserver = null;
    }
};
