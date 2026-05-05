// Org hierarchy CRUD actions.
// @module     local_airpay_org/org_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openOrgForm = async (orgid, parentid, returnFocus) => {
    const titleKey = (orgid === 0) ? 'addorg' : 'editorg';
    const title = await getString(titleKey, 'local_airpay_org');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_org\\form\\edit_org',
        args: {orgid: orgid, parentid: parentid},
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

const confirmToggle = async (orgid, name, isVisible, returnFocus) => {
    const titleKey = isVisible ? 'hideorg' : 'showorg';
    const msgKey = isVisible ? 'confirmhide' : 'confirmshow';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_airpay_org'),
        getString(msgKey, 'local_airpay_org', name),
        getString('orgvisibilitychanged', 'local_airpay_org'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_airpay_org_toggle_visibility',
                    args: {orgid: orgid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (orgid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deleteorg', 'local_airpay_org'),
        getString('confirmdelete', 'local_airpay_org', name),
        getString('delete', 'core'),
        getString('orgdeleted', 'local_airpay_org'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_org_delete_org',
                    args: {orgid: orgid}}])[0]
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
    const orgid = parseInt(trigger.dataset.orgid || '0', 10);
    const parentid = parseInt(trigger.dataset.parentid || '0', 10);
    const name = trigger.dataset.name || 'this organisation';
    const isVisible = trigger.dataset.visible === '1';
    switch (action) {
        case 'create-org':       event.preventDefault(); openOrgForm(0, 0, trigger); break;
        case 'add-child-org':    event.preventDefault(); openOrgForm(0, parentid, trigger); break;
        case 'edit-org':         event.preventDefault(); openOrgForm(orgid, 0, trigger); break;
        case 'toggle-org':       event.preventDefault(); confirmToggle(orgid, name, isVisible, trigger); break;
        case 'delete-org':       event.preventDefault(); confirmDelete(orgid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-org"]') || document.body;
    if (root.dataset.airpayOrgInit === '1') return;
    root.dataset.airpayOrgInit = '1';
    root.addEventListener('click', handleClick);
};
