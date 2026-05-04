// Exam CRUD actions.
// @module     local_airpay_exams/exam_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openExamForm = async (examid, returnFocus) => {
    const titleKey = (examid === 0) ? 'addexam' : 'editexam';
    const title = await getString(titleKey, 'local_airpay_exams');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_exams\\form\\edit_exam',
        args: {examid: examid},
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

const confirmStatus = async (examid, name, makeActive, returnFocus) => {
    const titleKey = makeActive ? 'activateexam' : 'deactivateexam';
    const msgKey = makeActive ? 'confirmactivate' : 'confirmdeactivate';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_airpay_exams'),
        getString(msgKey, 'local_airpay_exams', name),
        getString('examstatuschanged', 'local_airpay_exams'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_airpay_exams_toggle_status',
                    args: {examid: examid, active: makeActive}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (examid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deleteexam', 'local_airpay_exams'),
        getString('confirmdelete', 'local_airpay_exams', name),
        getString('delete', 'core'),
        getString('examdeleted', 'local_airpay_exams'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_exams_delete_exam',
                    args: {examid: examid}}])[0]
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
    const examid = parseInt(trigger.dataset.examid || '0', 10);
    const name = trigger.dataset.name || 'this exam';
    switch (action) {
        case 'create-exam':     event.preventDefault(); openExamForm(0, trigger); break;
        case 'edit-exam':       event.preventDefault(); openExamForm(examid, trigger); break;
        case 'activate-exam':   event.preventDefault(); confirmStatus(examid, name, true, trigger); break;
        case 'deactivate-exam': event.preventDefault(); confirmStatus(examid, name, false, trigger); break;
        case 'delete-exam':     event.preventDefault(); confirmDelete(examid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-exams"]') || document.body;
    if (root.dataset.airpayExamsInit === '1') return;
    root.dataset.airpayExamsInit = '1';
    root.addEventListener('click', handleClick);
};
