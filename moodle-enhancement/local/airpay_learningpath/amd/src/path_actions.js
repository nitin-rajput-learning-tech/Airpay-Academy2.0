// Learning Path CRUD actions.
// @module     local_airpay_learningpath/path_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openPathForm = async (pathid, returnFocus) => {
    const titleKey = (pathid === 0) ? 'addpath' : 'editpath';
    const title = await getString(titleKey, 'local_airpay_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_learningpath\\form\\edit_path',
        args: {pathid: pathid},
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

const confirmStatus = async (pathid, name, makeActive, returnFocus) => {
    const titleKey = makeActive ? 'activatepath' : 'archivepath';
    const msgKey = makeActive ? 'confirmactivate' : 'confirmarchive';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_airpay_learningpath'),
        getString(msgKey, 'local_airpay_learningpath', name),
        getString('pathstatuschanged', 'local_airpay_learningpath'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_airpay_learningpath_toggle_status',
                    args: {pathid: pathid, active: makeActive}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (pathid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deletepath', 'local_airpay_learningpath'),
        getString('confirmdelete', 'local_airpay_learningpath', name),
        getString('delete', 'core'),
        getString('pathdeleted', 'local_airpay_learningpath'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_learningpath_delete_path',
                    args: {pathid: pathid}}])[0]
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
    const pathid = parseInt(trigger.dataset.pathid || '0', 10);
    const name = trigger.dataset.name || 'this path';
    switch (action) {
        case 'create-path':   event.preventDefault(); openPathForm(0, trigger); break;
        case 'edit-path':     event.preventDefault(); openPathForm(pathid, trigger); break;
        case 'archive-path':  event.preventDefault(); confirmStatus(pathid, name, false, trigger); break;
        case 'activate-path': event.preventDefault(); confirmStatus(pathid, name, true, trigger); break;
        case 'delete-path':   event.preventDefault(); confirmDelete(pathid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-learningpath"]') || document.body;
    if (root.dataset.airpayLearningpathInit === '1') return;
    root.dataset.airpayLearningpathInit = '1';
    root.addEventListener('click', handleClick);
};
