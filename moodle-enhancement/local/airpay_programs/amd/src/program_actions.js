// Program CRUD actions.
// @module     local_airpay_programs/program_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const STATUS = {DRAFT: 0, ACTIVE: 1, ARCHIVED: 2};

const openProgramForm = async (programid, returnFocus) => {
    const titleKey = (programid === 0) ? 'addprogram' : 'editprogram';
    const title = await getString(titleKey, 'local_airpay_programs');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_programs\\form\\edit_program',
        args: {programid: programid},
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

const confirmStatus = async (programid, name, newStatus, msgKey, returnFocus) => {
    const titleKey = msgKey === 'confirmpublish' ? 'publishprogram'
                   : msgKey === 'confirmarchive' ? 'archiveprogram'
                   : 'draftprogram';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_airpay_programs'),
        getString(msgKey, 'local_airpay_programs', name),
        getString('programstatuschanged', 'local_airpay_programs'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_airpay_programs_change_status',
                    args: {programid: programid, status: newStatus}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (programid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deleteprogram', 'local_airpay_programs'),
        getString('confirmdelete', 'local_airpay_programs', name),
        getString('delete', 'core'),
        getString('programdeleted', 'local_airpay_programs'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_programs_delete_program',
                    args: {programid: programid}}])[0]
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
    const programid = parseInt(trigger.dataset.programid || '0', 10);
    const name = trigger.dataset.name || 'this program';
    switch (action) {
        case 'create-program':  event.preventDefault(); openProgramForm(0, trigger); break;
        case 'edit-program':    event.preventDefault(); openProgramForm(programid, trigger); break;
        case 'publish-program': event.preventDefault(); confirmStatus(programid, name, STATUS.ACTIVE, 'confirmpublish', trigger); break;
        case 'archive-program': event.preventDefault(); confirmStatus(programid, name, STATUS.ARCHIVED, 'confirmarchive', trigger); break;
        case 'draft-program':   event.preventDefault(); confirmStatus(programid, name, STATUS.DRAFT, 'confirmdraft', trigger); break;
        case 'delete-program':  event.preventDefault(); confirmDelete(programid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-programs"]') || document.body;
    if (root.dataset.airpayProgramsInit === '1') return;
    root.dataset.airpayProgramsInit = '1';
    root.addEventListener('click', handleClick);
};
