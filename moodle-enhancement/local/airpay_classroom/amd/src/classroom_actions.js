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
 * Classroom CRUD actions.
 *
 * @module     local_airpay_classroom/classroom_actions
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const STATUS = {CANCELLED: 0, ACTIVE: 1, COMPLETED: 2};

const openClassroomForm = async (classroomid, returnFocus) => {
    const titleKey = (classroomid === 0) ? 'addclassroom' : 'editclassroom';
    const title = await getString(titleKey, 'local_airpay_classroom');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_classroom\\form\\edit_classroom',
        args: {classroomid: classroomid},
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

const confirmStatusChange = async (classroomid, name, newStatus, msgKey, returnFocus) => {
    const successMsg = await getString('classroomstatuschanged', 'local_airpay_classroom');
    const [title, message] = await Promise.all([
        getString(msgKey === 'confirmcancel' ? 'cancelclassroom' :
                  msgKey === 'confirmcomplete' ? 'completeclassroom' :
                  'reopenclassroom', 'local_airpay_classroom'),
        getString(msgKey, 'local_airpay_classroom', name),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{
            methodname: 'local_airpay_classroom_change_status',
            args: {classroomid: classroomid, status: newStatus},
        }])[0].then(() => {
            Notification.addNotification({message: successMsg, type: 'success'});
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    });
    if (returnFocus) {
        setTimeout(() => returnFocus.focus(), 100);
    }
};

const confirmDelete = async (classroomid, name, returnFocus) => {
    const [title, message, deleteLabel, successMsg] = await Promise.all([
        getString('deleteclassroom', 'local_airpay_classroom'),
        getString('confirmdelete', 'local_airpay_classroom', name),
        getString('delete', 'core'),
        getString('classroomdeleted', 'local_airpay_classroom'),
    ]);
    Notification.deleteCancelPromise(title, message, deleteLabel, returnFocus).then(() => {
        Ajax.call([{
            methodname: 'local_airpay_classroom_delete_classroom',
            args: {classroomid: classroomid},
        }])[0].then(() => {
            Notification.addNotification({message: successMsg, type: 'success'});
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
    const classroomid = parseInt(trigger.dataset.classroomid || '0', 10);
    const name = trigger.dataset.name || 'this classroom';
    switch (action) {
        case 'create-classroom':
            event.preventDefault();
            openClassroomForm(0, trigger);
            break;
        case 'edit-classroom':
            event.preventDefault();
            openClassroomForm(classroomid, trigger);
            break;
        case 'cancel-classroom':
            event.preventDefault();
            confirmStatusChange(classroomid, name, STATUS.CANCELLED, 'confirmcancel', trigger);
            break;
        case 'complete-classroom':
            event.preventDefault();
            confirmStatusChange(classroomid, name, STATUS.COMPLETED, 'confirmcomplete', trigger);
            break;
        case 'reopen-classroom':
            event.preventDefault();
            confirmStatusChange(classroomid, name, STATUS.ACTIVE, 'confirmreopen', trigger);
            break;
        case 'delete-classroom':
            event.preventDefault();
            confirmDelete(classroomid, name, trigger);
            break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-classroom"]') || document.body;
    if (root.dataset.airpayClassroomInit === '1') return;
    root.dataset.airpayClassroomInit = '1';
    root.addEventListener('click', handleClick);
};
