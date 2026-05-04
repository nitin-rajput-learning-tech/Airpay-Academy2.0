// Evaluation form CRUD actions.
// @module     local_airpay_evaluation/evaluation_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const STATUS = {DRAFT: 0, ACTIVE: 1, ARCHIVED: 2};

const openEvalForm = async (evaluationid, returnFocus) => {
    const titleKey = (evaluationid === 0) ? 'addevaluation' : 'editevaluation';
    const title = await getString(titleKey, 'local_airpay_evaluation');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_evaluation\\form\\edit_evaluation',
        args: {evaluationid: evaluationid},
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

const confirmStatus = async (evaluationid, name, newStatus, msgKey, returnFocus) => {
    const titleKey = msgKey === 'confirmpublish' ? 'publishevaluation'
                   : msgKey === 'confirmarchive' ? 'archiveevaluation'
                   : 'draftevaluation';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_airpay_evaluation'),
        getString(msgKey, 'local_airpay_evaluation', name),
        getString('evaluationstatuschanged', 'local_airpay_evaluation'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_airpay_evaluation_change_status',
                    args: {evaluationid: evaluationid, status: newStatus}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (evaluationid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deleteevaluation', 'local_airpay_evaluation'),
        getString('confirmdelete', 'local_airpay_evaluation', name),
        getString('delete', 'core'),
        getString('evaluationdeleted', 'local_airpay_evaluation'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_evaluation_delete_evaluation',
                    args: {evaluationid: evaluationid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const evaluationid = parseInt(trigger.dataset.evaluationid || '0', 10);
    const name = trigger.dataset.name || 'this evaluation';
    switch (action) {
        case 'create-evaluation':  event.preventDefault(); openEvalForm(0, trigger); break;
        case 'edit-evaluation':    event.preventDefault(); openEvalForm(evaluationid, trigger); break;
        case 'publish-evaluation': event.preventDefault(); confirmStatus(evaluationid, name, STATUS.ACTIVE, 'confirmpublish', trigger); break;
        case 'archive-evaluation': event.preventDefault(); confirmStatus(evaluationid, name, STATUS.ARCHIVED, 'confirmarchive', trigger); break;
        case 'draft-evaluation':   event.preventDefault(); confirmStatus(evaluationid, name, STATUS.DRAFT, 'confirmdraft', trigger); break;
        case 'delete-evaluation':  event.preventDefault(); confirmDelete(evaluationid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-evaluation"]') || document.body;
    if (root.dataset.airpayEvaluationInit === '1') return;
    root.dataset.airpayEvaluationInit = '1';
    root.addEventListener('click', handleClick);
};
