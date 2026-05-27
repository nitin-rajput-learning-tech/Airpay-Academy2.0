// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Word-cloud renderer loader — Phase E.5 (ES6 source for grunt).
 *
 * Why this module exists
 * ----------------------
 * Mirrors the airpayux/chart_loader.js pattern (chip-N, audit finding
 * F-14): one dedicated AMD module owns the "render word cloud into a
 * DOM container" dependency, so callers can rely on a stable API
 * regardless of which underlying library does the layout.
 *
 * Why we DON'T vendor d3-cloud
 * ----------------------------
 * d3-cloud is ~12 KB minified + a 200 KB d3 dependency. For a CSS-
 * driven tag cloud with five size buckets (cloud-size-1 … cloud-size-5,
 * declared in result_panel.mustache), the physics-based layout
 * d3-cloud adds is overkill and would balloon the audience page weight
 * on mobile. The default renderer here uses pure DOM + CSS classes,
 * which:
 *
 *   1. Stays under 4 KB total module weight.
 *   2. Works without any external library — no CSP audit needed.
 *   3. Updates in place via textContent + className mutations — XSS-
 *      safe even with hostile server payloads.
 *
 * If a future customer needs d3-cloud's force-directed layout, they
 * can swap the implementation here without changing every caller; the
 * exposed render() / computeSize() API is the stable surface.
 *
 * Server contract
 * ---------------
 * Pages that need word-cloud rendering should:
 *   - Call `init()` from PHP once per page:
 *       $PAGE->requires->js_call_amd(
 *           'local_sentientia_live/wordcloud_loader', 'init');
 *   - Then `wordcloud_updater.js` (loaded separately) calls
 *     `render()` on each `response_added` SSE event.
 *
 * Public API
 * ----------
 *   init()                  — Module entry. No side effects beyond
 *                              exposing window.SentientiaWordCloud.
 *   computeSize(count, max) — Map a raw count → size bucket
 *                              ('cloud-size-1' … 'cloud-size-5').
 *   render(panel, tally)    — Mutate the panel's word DOM to reflect
 *                              `tally` (a { word: count } map).
 *
 * @module local_sentientia_live/wordcloud_loader
 */

const SIZE_BUCKETS = [
    'cloud-size-1',
    'cloud-size-2',
    'cloud-size-3',
    'cloud-size-4',
    'cloud-size-5',
];

/**
 * Compute the CSS bucket class for one word given its raw count and
 * the maximum count in the current tally. Five buckets, ceil-rounded
 * so even rare words land in bucket 1 rather than disappearing.
 *
 * @param {number} count Raw response count for this word.
 * @param {number} max   Maximum count across the whole tally.
 * @returns {string} CSS class name (one of SIZE_BUCKETS).
 */
const computeSize = (count, max) => {
    if (typeof count !== 'number' || count <= 0) {
        return SIZE_BUCKETS[0];
    }
    if (typeof max !== 'number' || max <= 0) {
        return SIZE_BUCKETS[0];
    }
    const ratio = count / max;
    let bucket = Math.ceil(ratio * SIZE_BUCKETS.length);
    if (bucket < 1) { bucket = 1; }
    if (bucket > SIZE_BUCKETS.length) { bucket = SIZE_BUCKETS.length; }
    return SIZE_BUCKETS[bucket - 1];
};

/**
 * Render the cloud into a panel container.
 *
 * tally shape: { word: count, ... } already sorted desc on the server.
 *
 * The function only mutates textContent + className + dataset on
 * existing nodes, and uses document.createElement + textContent for
 * new nodes — NEVER innerHTML. This keeps the renderer XSS-safe
 * even if the SSE payload is tampered with.
 *
 * @param {HTMLElement} panel  Top-level results panel for this slide.
 * @param {Object<string,number>} tally Word → count map.
 */
const render = (panel, tally) => {
    if (!panel || !tally || typeof tally !== 'object') {
        return;
    }
    const container = panel.querySelector('.sentientia-wordcloud');
    if (!container) {
        return;
    }

    // Take top 50 by count so the cloud stays readable on small screens.
    const entries = Object.keys(tally)
        .map(w => ({word: String(w), count: Number(tally[w]) || 0}))
        .sort((a, b) => b.count - a.count)
        .slice(0, 50);

    if (entries.length === 0) {
        return;
    }
    const max = entries[0].count > 0 ? entries[0].count : 1;

    // Build a map of existing nodes keyed by word so we can update in
    // place (preserves CSS transitions). New words get appended;
    // dropped words get removed.
    const existing = new Map();
    container.querySelectorAll('.sentientia-wordcloud-word').forEach(node => {
        const w = node.dataset.word || node.textContent.trim();
        if (w) {
            existing.set(w, node);
        }
    });

    const seen = new Set();
    entries.forEach(({word, count}) => {
        const sizeClass = computeSize(count, max);
        seen.add(word);
        let node = existing.get(word);
        if (!node) {
            node = document.createElement('span');
            node.className = 'sentientia-wordcloud-word ' + sizeClass;
            // textContent — never innerHTML — to keep this XSS-safe.
            node.textContent = word;
            node.dataset.word = word;
            container.appendChild(node);
        } else {
            // Remove any previous bucket class before applying the new.
            SIZE_BUCKETS.forEach(b => node.classList.remove(b));
            node.classList.add(sizeClass);
        }
        node.dataset.count = String(count);
        node.setAttribute('title', String(count));
    });

    // Remove nodes for words that fell out of the top-50.
    existing.forEach((node, word) => {
        if (!seen.has(word)) {
            node.remove();
        }
    });
};

const init = () => {
    // Expose the API on the window so non-AMD callers (e.g. an inline
    // page script in trainer/run.php) can reach it. The module return
    // value is the canonical handle; window is the convenience handle.
    if (typeof window !== 'undefined') {
        window.SentientiaWordCloud = {render, computeSize};
    }
};

export {init, render, computeSize};
