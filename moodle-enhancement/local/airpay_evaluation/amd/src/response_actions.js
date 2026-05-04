// Learner-facing evaluation response submission.
// @module     local_airpay_evaluation/response_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

/**
 * Read all answers from the form into a {questionid: answer} map.
 * Returns null if a required question is missing an answer.
 */
const collectAnswers = (root) => {
    const cards = root.querySelectorAll('.question-card');
    const answers = {};
    let missing = null;

    for (const card of cards) {
        const qid = card.dataset.questionid;
        const qtype = card.dataset.questiontype;
        const required = card.dataset.required === '1';
        let value = null;

        if (qtype === 'text') {
            const ta = card.querySelector('textarea');
            value = ta ? ta.value.trim() : '';
        } else {
            // Radio-based: rating, nps, yesno, multichoice
            const checked = card.querySelector('input[type="radio"]:checked');
            value = checked ? checked.value : null;
        }

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
        // Scroll to and highlight the missing question.
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

    // Disable submit button while submitting.
    const btn = root.querySelector('[data-action="submit-response"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin fa-fw"></i> Submitting...';
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

        // Reload to show the "thank you" state.
        setTimeout(() => window.location.reload(), 600);
    } catch (err) {
        Notification.exception(err);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane fa-fw"></i> Submit Response';
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

export const init = () => {
    const root = document.querySelector('[data-region="airpay-eval-respond"]');
    if (!root || root.dataset.airpayResponseInit === '1') return;
    root.dataset.airpayResponseInit = '1';
    root.addEventListener('click', handleClick);
};
