// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * User CRUD actions — wires the Add User button, edit/suspend/delete row actions.
 *
 * Uses Moodle 5's core_form/modalform for create+edit, and core/notification
 * for delete/suspend confirmations.
 *
 * @module     local_airpay_users/user_actions
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

/**
 * Initialise event handlers on the user management page.
 */
export const init = () => {
    const root = document.querySelector('[data-region="airpay-users"]') || document.body;
    if (root.dataset.airpayUsersInit === '1') {
        return; // Already wired (avoid double-binding on AJAX page reloads).
    }
    root.dataset.airpayUsersInit = '1';

    root.addEventListener('click', handleClick);
};

/**
 * Single click handler — delegates based on data-action attribute.
 */
const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) {
        return;
    }

    const action = trigger.dataset.action;
    const userid = parseInt(trigger.dataset.userid || '0', 10);
    const username = trigger.dataset.username || 'this user';

    switch (action) {
        case 'create-user':
            event.preventDefault();
            openUserForm(0, trigger);
            break;
        case 'edit-user':
            event.preventDefault();
            openUserForm(userid, trigger);
            break;
        case 'suspend-user':
            event.preventDefault();
            confirmSuspend(userid, username, true, trigger);
            break;
        case 'activate-user':
            event.preventDefault();
            confirmSuspend(userid, username, false, trigger);
            break;
        case 'delete-user':
            event.preventDefault();
            confirmDelete(userid, username, trigger);
            break;
    }
};

/**
 * Open the create/edit user modal form.
 */
const openUserForm = async (userid, returnFocus) => {
    const isCreate = (userid === 0);
    const titleKey = isCreate ? 'adduser' : 'edituser';
    const title = await getString(titleKey, 'local_airpay_users');

    const modalForm = new ModalForm({
        formClass: 'local_airpay_users\\form\\edit_user',
        args: {userid: userid},
        modalConfig: {title: title},
        returnFocus: returnFocus,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = event.detail?.message || 'Saved.';
        Notification.addNotification({message: message, type: 'success'});
        // Reload to show the new/updated row.
        window.location.reload();
    });

    modalForm.show();
};

/**
 * Suspend or activate a user — shows confirm dialog then calls web service.
 */
const confirmSuspend = async (userid, username, suspend, returnFocus) => {
    const titleKey = suspend ? 'suspenduser' : 'activateuser';
    const msgKey = suspend ? 'confirmsuspend' : 'confirmactivate';
    const successKey = suspend ? 'usersuspended' : 'useractivated';

    const [title, message, successMsg] = await Promise.all([
        getString(titleKey, 'local_airpay_users'),
        getString(msgKey, 'local_airpay_users', username),
        getString(successKey, 'local_airpay_users'),
    ]);

    Notification.confirm(
        title,
        message,
        title,
        null,
        async () => {
            try {
                await Ajax.call([{
                    methodname: 'local_airpay_users_suspend_user',
                    args: {userid: userid, suspended: suspend},
                }])[0];
                Notification.addNotification({message: successMsg, type: 'success'});
                window.location.reload();
            } catch (err) {
                Notification.exception(err);
            }
        }
    );

    if (returnFocus) {
        setTimeout(() => returnFocus.focus(), 100);
    }
};

/**
 * Delete a user — shows danger confirm dialog then calls web service.
 */
const confirmDelete = async (userid, username, returnFocus) => {
    const [title, message, deleteLabel, successMsg] = await Promise.all([
        getString('deleteuser', 'local_airpay_users'),
        getString('confirmdelete', 'local_airpay_users', username),
        getString('delete', 'core'),
        getString('userdeleted', 'local_airpay_users'),
    ]);

    Notification.deleteCancelPromise(title, message, deleteLabel, returnFocus)
        .then(async () => {
            try {
                await Ajax.call([{
                    methodname: 'local_airpay_users_delete_user',
                    args: {userid: userid},
                }])[0];
                Notification.addNotification({message: successMsg, type: 'success'});
                window.location.reload();
            } catch (err) {
                Notification.exception(err);
            }
            return true;
        }, () => null);
};
