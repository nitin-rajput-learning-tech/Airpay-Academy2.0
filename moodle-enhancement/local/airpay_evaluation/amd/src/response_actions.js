// Learner-facing evaluation response submission.
// @module     local_airpay_evaluation/response_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

/**
 * Swap a button's contents safely (no innerHTML, no XSS surface).
 * Same pattern as P1 #26's `setButtonContent` in skill_actions.js —
 * security hook flagged innerHTML there; refactored here at the same
 * time to keep the codebase consistent.
 */
const setButtonContent = (btn, iconClass, text) => {
    while (btn.firstChild) btn.removeChild(btn.firstChild);
    if (iconClass) {
        const i = document.createElement('i');
        i.className = iconClass;
        i.setAttribute('aria-hidden', 'true');
        btn.appendChild(i);
        btn.appendChild(document.createTextNode(' '));
    }
    btn.appendChild(document.createTextNode(text));
};

/**
 * Read one question card's current answer. Returns null when unanswered.
 * Extracted from collectAnswers() so the visibility computation can use
 * the same parsing logic — keeps "what is the user's answer" defined in
 * exactly one place.
 */
const readCardValue = (card) => {
    const qtype = card.dataset.questiontype;

    if (qtype === 'text') {
        const ta = card.querySelector('textarea');
        const v = ta ? ta.value.trim() : '';
        return v === '' ? null : v;
    }
    if (qtype === 'numeric') {
        // P1 #18 — integer input. Empty string → null. Otherwise pass
        // raw string; server-side enforces bounds.
        const input = card.querySelector('input[type="number"]');
        return (input && input.value.trim() !== '') ? input.value.trim() : null;
    }
    if (qtype === 'multichoice_multi') {
        // P1 #18 — collect checked checkbox values into an array.
        // Wire format is JSON-string (the PHP side json_decode()s it
        // back inside validate_answer for multichoice_multi).
        const checked = card.querySelectorAll('input[type="checkbox"]:checked');
        const picks = Array.from(checked).map((cb) => cb.value);
        return picks.length > 0 ? JSON.stringify(picks) : null;
    }
    // Radio-based: rating, nps, yesno, multichoice.
    const checked = card.querySelector('input[type="radio"]:checked');
    return checked ? checked.value : null;
};

/**
 * P1 #31 (2026-05-20) — compute which question cards are visible given
 * the current answers on the page. Mirrors PHP's
 * `evaluation_manager::compute_visibility_map()` so the two stay in
 * lockstep — if you change one, change the other.
 *
 * Returns a Map<qid, bool>.
 *
 * Match rules (must mirror the PHP side):
 *   • No `data-depends-on-qid` → always visible.
 *   • Parent not visible → child not visible (cascading hide).
 *   • Parent answer null/empty → child not visible.
 *   • `data-depends-on-value` empty → any non-empty parent answer triggers.
 *   • Parent is multichoice_multi (array, JSON-encoded) → match if any
 *     selected option equals the depends_on_value.
 *   • Otherwise → strict string equality after trim.
 */
const computeVisibility = (root) => {
    const cards = Array.from(root.querySelectorAll('.question-card'));
    // First pass: build qid → answer map using readCardValue for every
    // card regardless of current visibility (we re-evaluate from scratch).
    const answers = new Map();
    for (const card of cards) {
        const qid = parseInt(card.dataset.questionid || '0', 10);
        if (!qid) continue;
        answers.set(qid, readCardValue(card));
    }

    const visible = new Map();
    // Walk in DOM order — same as get_questions()'s sortorder ASC. Parent
    // visibility is decided before child because forms render parents first.
    for (const card of cards) {
        const qid = parseInt(card.dataset.questionid || '0', 10);
        if (!qid) continue;
        const parentQid = parseInt(card.dataset.dependsOnQid || '0', 10);
        if (parentQid <= 0) {
            visible.set(qid, true);
            continue;
        }
        if (!visible.get(parentQid)) {
            visible.set(qid, false);
            continue;
        }
        const parentAnswer = answers.get(parentQid);
        if (parentAnswer === null || parentAnswer === '') {
            visible.set(qid, false);
            continue;
        }
        const needed = card.dataset.dependsOnValue || '';
        if (needed === '') {
            // "Any non-empty parent answer triggers."
            visible.set(qid, true);
            continue;
        }
        // multichoice_multi parents pass a JSON-encoded array — decode
        // and check membership.
        const parentCard = root.querySelector(
            '.question-card[data-questionid="' + parentQid + '"]');
        const parentType = parentCard ? parentCard.dataset.questiontype : '';
        if (parentType === 'multichoice_multi') {
            let arr;
            try {
                arr = JSON.parse(parentAnswer);
            } catch (e) {
                arr = [];
            }
            visible.set(qid, Array.isArray(arr)
                && arr.map(String).includes(needed));
        } else {
            visible.set(qid, String(parentAnswer).trim() === needed.trim());
        }
    }
    return visible;
};

/**
 * P1 #31 — toggle .question-card display + clear hidden inputs.
 *
 * Clearing inputs on hide matters: if a learner picks "Yes" → answers
 * the dependent Q → then changes parent to "No", the dependent Q's
 * answer would otherwise be carried into the submit payload. The
 * server treats it as null anyway (compute_visibility_map runs
 * server-side too) but clearing on the client keeps the UI honest.
 */
const applyVisibility = (root, vis) => {
    for (const card of root.querySelectorAll('.question-card')) {
        const qid = parseInt(card.dataset.questionid || '0', 10);
        if (!qid) continue;
        const shouldShow = vis.get(qid) !== false;
        const wasVisible = card.style.display !== 'none';
        if (shouldShow && !wasVisible) {
            card.style.display = '';
        } else if (!shouldShow && wasVisible) {
            card.style.display = 'none';
            clearCardInputs(card);
        } else if (!shouldShow) {
            // Re-clear in case the user found a way to re-set values
            // on a card that's been hidden the whole time (defensive —
            // initial inline `display:none` from the template).
            clearCardInputs(card);
        }
    }
};

/**
 * Wipe every input on a card so a hidden question doesn't carry an
 * answer the user can no longer see. Idempotent.
 */
const clearCardInputs = (card) => {
    for (const radio of card.querySelectorAll('input[type="radio"]:checked')) {
        radio.checked = false;
    }
    for (const cb of card.querySelectorAll('input[type="checkbox"]:checked')) {
        cb.checked = false;
    }
    for (const ta of card.querySelectorAll('textarea')) {
        ta.value = '';
    }
    for (const num of card.querySelectorAll('input[type="number"]')) {
        num.value = '';
    }
};

/**
 * Read all visible answers into {qid: value}. Hidden cards are
 * skipped — server-side compute_visibility_map enforces the same,
 * so this is purely a smaller-payload optimisation.
 */
const collectAnswers = (root) => {
    const cards = root.querySelectorAll('.question-card');
    const answers = {};
    let missing = null;

    for (const card of cards) {
        // P1 #31 — hidden cards don't contribute. Required-check is
        // also skipped because the server treats hidden as not-required.
        if (card.style.display === 'none') continue;

        const qid = card.dataset.questionid;
        const required = card.dataset.required === '1';
        const value = readCardValue(card);

        if (required && (value === null || value === '')) {
            missing = card;
            break;
        }

        if (value !== null && value !== '') {
            answers[qid] = value;
        }
    }

    return {answers, missing};
};

const submit = async (root) => {
    const {answers, missing} = collectAnswers(root);

    if (missing) {
        missing.scrollIntoView({behavior: 'smooth', block: 'center'});
        missing.style.outline = '2px solid #dc2626';
        missing.style.outlineOffset = '4px';
        setTimeout(() => {
            missing.style.outline = '';
            missing.style.outlineOffset = '';
        }, 2400);
        const msg = await getString('please_answer_required', 'local_airpay_evaluation');
        Notification.addNotification({message: msg, type: 'warning'});
        return;
    }

    const evaluationid = parseInt(root.dataset.evaluationid || '0', 10);
    const context = {
        courseid: parseInt(root.dataset.contextCourseid || '0', 10),
        programid: parseInt(root.dataset.contextProgramid || '0', 10),
        classroomid: parseInt(root.dataset.contextClassroomid || '0', 10),
    };

    const btn = root.querySelector('[data-action="submit-response"]');
    if (btn) {
        btn.disabled = true;
        setButtonContent(btn, 'fa fa-spinner fa-spin fa-fw', 'Submitting...');
    }

    try {
        await Ajax.call([{
            methodname: 'local_airpay_evaluation_submit_response',
            args: {
                evaluationid: evaluationid,
                answers: JSON.stringify(answers),
                context: JSON.stringify(context),
            },
        }])[0];

        const success = await getString('response_submitted', 'local_airpay_evaluation');
        Notification.addNotification({message: success, type: 'success'});

        setTimeout(() => window.location.reload(), 600);
    } catch (err) {
        Notification.exception(err);
        if (btn) {
            btn.disabled = false;
            setButtonContent(btn, 'fa fa-paper-plane fa-fw', 'Submit Response');
        }
    }
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    if (trigger.dataset.action !== 'submit-response') return;
    event.preventDefault();
    const root = document.querySelector('[data-region="airpay-eval-respond"]');
    if (root) submit(root);
};

/**
 * P1 #31 — bind visibility recompute to every input mutation on the
 * form. We listen on the root (event delegation) so dynamically-added
 * inputs (none today, but cheap) would also be picked up.
 */
const wireVisibility = (root) => {
    const recompute = () => {
        applyVisibility(root, computeVisibility(root));
    };
    root.addEventListener('change', recompute);
    root.addEventListener('input', recompute);
    // Initial pass — reveals any dependent question that's pre-selected
    // (e.g. browser auto-fill, future "resume partial response" feature).
    recompute();
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-eval-respond"]');
    if (!root || root.dataset.airpayResponseInit === '1') return;
    root.dataset.airpayResponseInit = '1';
    root.addEventListener('click', handleClick);
    wireVisibility(root);
};
