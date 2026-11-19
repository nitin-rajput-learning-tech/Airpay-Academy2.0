// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The integrity report on the quiz attempt review page, for the quiz's teachers (B2, ADR-038 D3 and D6, 2026-11-20).
 *
 * Loaded by the quiz access rule (quizaccess_sentientia_proctoring\rule::setup_review_page) when a teacher opens a
 * learner's attempt that ran in secure mode. It fetches the report (local_sentientia_proctoring_integrity_report,
 * which checks mod/quiz:viewreports and the session's tenant again) and shows it under the attempt summary. Nothing
 * is shown when the report is not available to this viewer; the review page itself is never changed otherwise.
 *
 * @module local_sentientia_proctoring/integrity_report
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';

const METHOD = 'local_sentientia_proctoring_integrity_report';
const TEMPLATE = 'local_sentientia_proctoring/integrity_report';

let started = false;

/**
 * Fetch and show the report.
 *
 * @param {Object} config from the access rule
 * @param {number} config.attemptid the attempt shown on the page
 */
export const init = (config) => {
    const attemptid = config ? parseInt(config.attemptid, 10) : 0;
    if (started || !(attemptid > 0)) {
        return;
    }
    started = true;
    Ajax.call([{methodname: METHOD, args: {attemptid: attemptid}}])[0].then((data) => {
        if (!data || !data.available) {
            return null;
        }
        return Templates.renderForPromise(TEMPLATE, data);
    }).then((rendered) => {
        if (rendered) {
            place(rendered.html, rendered.js);
        }
        return rendered;
    }).catch(Notification.exception);
};

/**
 * Put the report after the attempt summary table, or at the top of the main region.
 *
 * @param {string} html
 * @param {string} js
 */
const place = (html, js) => {
    const summary = document.querySelector('.quizreviewsummary');
    if (summary && summary.parentNode) {
        const holder = document.createElement('div');
        holder.className = 'mt-3';
        summary.parentNode.insertBefore(holder, summary.nextSibling);
        Templates.appendNodeContents(holder, html, js);
        return;
    }
    const main = document.querySelector('#region-main [role="main"]') || document.querySelector('[role="main"]');
    if (main) {
        Templates.prependNodeContents(main, html, js);
    }
};
