// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Chart updater — Phase E.5.b (ES6 source for grunt).
 *
 * Listens for sentientia-live:response_added CustomEvent and mutates
 * result panel DOM in place. NO innerHTML — only textContent + style.width.
 *
 * @module local_sentientia_live/chart_updater
 */

const INLINE_UPDATE_TYPES = ['multichoice', 'quiz', 'rating'];

const init = () => {
    window.addEventListener(
        'sentientia-live:response_added', handleResponseAdded);
};

const handleResponseAdded = (ev) => {
    if (!ev.detail) {
        return;
    }
    const {slide_id: slideId, slide_type: slideType,
        count_now: countNow, tally} = ev.detail;

    const totalEl = document.querySelector(
        `.sentientia-results-panel[data-slideid="${slideId}"] .sentientia-results-total`);
    if (totalEl && typeof countNow === 'number') {
        totalEl.textContent = String(countNow);
    }

    if (!INLINE_UPDATE_TYPES.includes(slideType)) {
        setTimeout(() => window.location.reload(), 500);
        return;
    }
    if (!tally) {
        return;
    }
    const panel = document.querySelector(
        `.sentientia-results-panel[data-slideid="${slideId}"]`);
    if (!panel) {
        return;
    }

    if (slideType === 'multichoice' || slideType === 'quiz') {
        updateBarChart(panel, tally);
    } else if (slideType === 'rating') {
        updateRatingChart(panel, tally);
    }
};

const updateBarChart = (panel, tally) => {
    const counts = Object.values(tally).map(v => typeof v === 'number' ? v : 0);
    const total = counts.reduce((a, b) => a + b, 0);
    const max   = counts.reduce((a, b) => Math.max(a, b), 0);

    panel.querySelectorAll('.sentientia-bar-row').forEach(row => {
        const idx = parseInt(row.dataset.optionIndex, 10);
        if (isNaN(idx)) {
            return;
        }
        const count = (typeof tally[idx] === 'number') ? tally[idx]
            : (typeof tally[String(idx)] === 'number') ? tally[String(idx)]
            : 0;
        const percent = total > 0 ? Math.round((count / total) * 100) : 0;
        const barPc = max > 0 ? Math.round((count / max) * 100) : 0;

        const bar = row.querySelector('.sentientia-bar');
        if (bar) {
            bar.style.width = `${barPc}%`;
        }
        const countEl = row.querySelector('.sentientia-bar-count');
        if (countEl) {
            countEl.textContent = String(count);
        }
        const pctEl = row.querySelector('.sentientia-bar-percent');
        if (pctEl) {
            pctEl.textContent = String(percent);
        }
    });
};

const updateRatingChart = (panel, tally) => {
    let max = 0;
    Object.keys(tally).forEach(k => {
        if (k === '_avg' || k === '_count') {
            return;
        }
        const v = tally[k];
        if (typeof v === 'number' && v > max) {
            max = v;
        }
    });

    panel.querySelectorAll('[data-rating-value]').forEach(row => {
        const v = row.dataset.ratingValue;
        const count = (typeof tally[v] === 'number') ? tally[v]
            : (typeof tally[parseInt(v, 10)] === 'number') ? tally[parseInt(v, 10)]
            : 0;
        const barPc = max > 0 ? Math.round((count / max) * 100) : 0;

        const bar = row.querySelector('.sentientia-bar');
        if (bar) {
            bar.style.width = `${barPc}%`;
        }
        const countEl = row.querySelector('.sentientia-bar-count');
        if (countEl) {
            countEl.textContent = String(count);
        }
    });

    const avgEl = panel.querySelector('.sentientia-results-avg');
    if (avgEl && tally._avg !== undefined && tally._avg !== null) {
        avgEl.textContent = String(tally._avg);
    }
    const countSummaryEl = panel.querySelector('.sentientia-results-count');
    if (countSummaryEl && typeof tally._count === 'number') {
        countSummaryEl.textContent = String(tally._count);
    }
};

export {init};
