// Notification rule CRUD actions.
// @module     local_sentientia_notifications/rule_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openRuleForm = async (ruleid, returnFocus) => {
    const titleKey = (ruleid === 0) ? 'addrule' : 'editrule';
    const title = await getString(titleKey, 'local_sentientia_notifications');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_notifications\\form\\edit_rule',
        args: {ruleid: ruleid},
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

const confirmToggle = async (ruleid, name, makeEnabled, returnFocus) => {
    const titleKey = makeEnabled ? 'enablerule' : 'disablerule';
    const msgKey = makeEnabled ? 'confirmenable' : 'confirmdisable';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_sentientia_notifications'),
        getString(msgKey, 'local_sentientia_notifications', name),
        getString('rulestatuschanged', 'local_sentientia_notifications'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_sentientia_notifications_toggle_rule',
                    args: {ruleid: ruleid, enabled: makeEnabled}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (ruleid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deleterule', 'local_sentientia_notifications'),
        getString('confirmdelete', 'local_sentientia_notifications', name),
        getString('delete', 'core'),
        getString('ruledeleted', 'local_sentientia_notifications'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_notifications_delete_rule',
                    args: {ruleid: ruleid}}])[0]
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
    const ruleid = parseInt(trigger.dataset.ruleid || '0', 10);
    const name = trigger.dataset.name || 'this rule';
    switch (action) {
        case 'create-rule':  event.preventDefault(); openRuleForm(0, trigger); break;
        case 'edit-rule':    event.preventDefault(); openRuleForm(ruleid, trigger); break;
        case 'enable-rule':  event.preventDefault(); confirmToggle(ruleid, name, true, trigger); break;
        case 'disable-rule': event.preventDefault(); confirmToggle(ruleid, name, false, trigger); break;
        case 'delete-rule':  event.preventDefault(); confirmDelete(ruleid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-notifications"]') || document.body;
    if (root.dataset.airpayNotificationsInit === '1') return;
    root.dataset.airpayNotificationsInit = '1';
    root.addEventListener('click', handleClick);
};
