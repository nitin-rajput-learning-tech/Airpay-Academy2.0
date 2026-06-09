// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Word-cloud updater — Phase E.5 (ES6 source for grunt).
 *
 * Listens for the `sentientia-live:response_added` CustomEvent
 * dispatched by audience_sse.js / trainer_sse.js. When the event
 * carries a wordcloud-typed slide, the cloud is re-rendered in place
 * via wordcloud_loader.render(). No window.location.reload is needed,
 * so trainer + audience screens update without flicker.
 *
 * Why this is split from chart_updater.js
 * ---------------------------------------
 * chart_updater.js owns numeric-bar visualisations (multichoice / quiz /
 * rating). Mixing wordcloud DOM mutation in there bloats the module
 * and tangles two unrelated tally shapes. Each renderer owns its
 * surface; both subscribe to the same SSE-driven CustomEvent.
 *
 * On the same page both modules attach a listener — both run for every
 * response_added event but each early-returns when the slide_type
 * isn't its own. Cheap; no race conditions because both do textContent /
 * className mutations only.
 *
 * @module local_sentientia_live/wordcloud_updater
 */

import * as Loader from './wordcloud_loader.js';

const init = () => {
    window.addEventListener(
        'sentientia-live:response_added', handleResponseAdded);
};

const handleResponseAdded = (ev) => {
    if (!ev.detail) {
        return;
    }
    const {slide_id: slideId, slide_type: slideType, tally,
        count_now: countNow} = ev.detail;
    if (slideType !== 'wordcloud') {
        return;   // Not our concern — chart_updater handles others.
    }

    const panel = document.querySelector(
        `.sentientia-results-panel[data-slideid="${slideId}"]`);
    if (!panel) {
        return;
    }

    // Update the visible total counter (mirrors chart_updater's
    // behaviour — done here too because chart_updater early-returns
    // for wordcloud and we want the total to stay in sync).
    const totalEl = panel.querySelector('.sentientia-results-total');
    if (totalEl && typeof countNow === 'number') {
        totalEl.textContent = String(countNow);
    }

    // P0 #8 — sr-only tally summary for screen readers, same protocol
    // as chart_updater (data-a11y-tally-suffix is set server-side).
    const summaryEl = panel.querySelector('[data-live-tally-summary]');
    if (summaryEl && typeof countNow === 'number') {
        const suffix = panel.dataset.a11yTallySuffix || '';
        summaryEl.textContent = String(countNow)
            + (suffix ? ' ' + suffix : '');
    }

    if (!tally || typeof tally !== 'object') {
        return;
    }

    // The .sentientia-wordcloud container is rendered server-side only
    // inside the template's {{#has_responses}} block — so at the 0→1
    // transition it doesn't exist yet. In that case fall back to a full
    // reload (the same pattern chart_updater uses for types it can't
    // update inline): the reload re-renders the panel with the cloud
    // present, and every subsequent response updates in place. Without
    // this, the very first word on a fresh wordcloud slide would never
    // appear until a manual refresh.
    const container = panel.querySelector('.sentientia-wordcloud');
    if (!container) {
        setTimeout(() => window.location.reload(), 500);
        return;
    }

    // Delegate the actual DOM mutation to the loader. This keeps
    // "what's a word cloud?" in one module — swap renderers without
    // changing the SSE subscription wiring.
    Loader.render(panel, tally);
};

export {init};
