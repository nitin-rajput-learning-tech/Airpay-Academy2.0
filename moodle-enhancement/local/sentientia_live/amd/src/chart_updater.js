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
        if (slideType === 'quiz') {
            // Phase E.6 — also update the "X of Y got it right" summary
            // numbers in place. Leaderboard refresh is page-reload-driven
            // (we don't build DOM nodes with user display names here to
            // stay XSS-safe; mustache renders them at page load).
            updateQuizSummary(panel, tally, countNow);
        }
    } else if (slideType === 'rating') {
        updateRatingChart(panel, tally);
    }
};

/**
 * Phase E.6 — quiz summary numbers. The DOM has data-correct-index on
 * the row that's marked correct (.sentientia-bar-row.border-success);
 * we walk tally and find the count for that index. textContent only —
 * no innerHTML, no shape change.
 */
const updateQuizSummary = (panel, tally, countNow) => {
    const summary = panel.querySelector('.sentientia-quiz-summary');
    if (!summary) {
        return;  // not a quiz, or summary not rendered (no responses yet)
    }
    // The correct option is the row with bg-success class — find it.
    const correctRow = panel.querySelector(
        '.sentientia-bar-row .badge.bg-success');
    let correctIdx = -1;
    if (correctRow) {
        const row = correctRow.closest('.sentientia-bar-row');
        if (row && row.dataset.optionIndex !== undefined) {
            correctIdx = parseInt(row.dataset.optionIndex, 10);
        }
    }
    if (correctIdx < 0) {
        return;
    }
    const correctCount = (typeof tally[correctIdx] === 'number')
        ? tally[correctIdx]
        : (typeof tally[String(correctIdx)] === 'number')
        ? tally[String(correctIdx)] : 0;
    const total = (typeof countNow === 'number') ? countNow : 0;
    const percent = total > 0 ? Math.round((correctCount / total) * 100) : 0;

    const countEl = summary.querySelector('.sentientia-quiz-correct-count');
    if (countEl) { countEl.textContent = String(correctCount); }
    const totalEl = summary.querySelector('.sentientia-quiz-total');
    if (totalEl) { totalEl.textContent = String(total); }
    const pctEl = summary.querySelector('.sentientia-quiz-percent-correct');
    if (pctEl) { pctEl.textContent = String(percent); }
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
