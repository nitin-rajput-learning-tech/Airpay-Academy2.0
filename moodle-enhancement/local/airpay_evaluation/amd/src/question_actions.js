// Question builder actions for evaluations.
// @module     local_airpay_evaluation/question_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openQuestionForm = async (questionid, evaluationid, returnFocus) => {
    const titleKey = (questionid === 0) ? 'addquestion' : 'editquestion';
    const title = await getString(titleKey, 'local_airpay_evaluation');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_evaluation\\form\\edit_question',
        args: {questionid: questionid, evaluationid: evaluationid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = (event.detail && event.detail.message) || 'Saved.';
        Notification.addNotification({message: message, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const confirmDelete = async (questionid, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deletequestion', 'local_airpay_evaluation'),
        getString('confirmdeletequestion', 'local_airpay_evaluation'),
        getString('delete', 'core'),
        getString('questiondeleted', 'local_airpay_evaluation'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_evaluation_delete_question',
                    args: {questionid: questionid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

/**
 * Persist current visual order to backend.
 */
const saveOrder = async (root) => {
    const evaluationid = parseInt(root.dataset.evaluationid || '0', 10);
    const list = root.querySelector('#airpay-question-list');
    if (!list) return;

    const ids = Array.from(list.querySelectorAll('.question-item'))
        .map(el => parseInt(el.dataset.questionid, 10))
        .filter(id => !isNaN(id) && id > 0);

    if (ids.length === 0) return;

    try {
        await Ajax.call([{
            methodname: 'local_airpay_evaluation_reorder_questions',
            args: {evaluationid: evaluationid, questionids: ids},
        }])[0];
        const success = await getString('orderupdated', 'local_airpay_evaluation');
        Notification.addNotification({message: success, type: 'success'});
        // Update visual position numbers without reload.
        list.querySelectorAll('.question-item').forEach((el, idx) => {
            const pos = el.querySelector('.question-position');
            if (pos) pos.textContent = idx + 1;
        });
    } catch (err) {
        Notification.exception(err);
    }
};

/**
 * Wire HTML5 drag-drop on question items.
 */
const wireDragDrop = (root) => {
    const list = root.querySelector('#airpay-question-list');
    if (!list) return;

    let draggedEl = null;

    list.addEventListener('dragstart', (e) => {
        const item = e.target.closest('.question-item');
        if (!item) return;
        draggedEl = item;
        item.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.dataset.questionid);
    });

    list.addEventListener('dragend', (e) => {
        const item = e.target.closest('.question-item');
        if (item) item.classList.remove('dragging');
        list.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        draggedEl = null;
    });

    list.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const target = e.target.closest('.question-item');
        if (!target || target === draggedEl) return;
        list.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        target.classList.add('drag-over');
    });

    list.addEventListener('drop', (e) => {
        e.preventDefault();
        const target = e.target.closest('.question-item');
        if (!target || !draggedEl || target === draggedEl) return;

        const targetRect = target.getBoundingClientRect();
        const insertAfter = (e.clientY - targetRect.top) > (targetRect.height / 2);
        if (insertAfter) {
            target.parentNode.insertBefore(draggedEl, target.nextSibling);
        } else {
            target.parentNode.insertBefore(draggedEl, target);
        }
        target.classList.remove('drag-over');

        // Persist new order.
        saveOrder(root);
    });
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const root = document.querySelector('[data-region="airpay-eval-questions"]');
    const evaluationid = parseInt(root?.dataset?.evaluationid || '0', 10);
    const questionid = parseInt(trigger.dataset.questionid || '0', 10);

    switch (action) {
        case 'add-question':
            event.preventDefault();
            openQuestionForm(0, evaluationid, trigger);
            break;
        case 'edit-question':
            event.preventDefault();
            openQuestionForm(questionid, evaluationid, trigger);
            break;
        case 'delete-question':
            event.preventDefault();
            confirmDelete(questionid, trigger);
            break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-eval-questions"]');
    if (!root || root.dataset.airpayQuestionsInit === '1') return;
    root.dataset.airpayQuestionsInit = '1';

    root.addEventListener('click', handleClick);
    wireDragDrop(root);
};
