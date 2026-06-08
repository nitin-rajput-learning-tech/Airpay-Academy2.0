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
 * Course CRUD actions — modal create/edit, hide/show, delete.
 *
 * @module     local_sentientia_courses/course_actions
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openCourseForm = async (courseid, returnFocus) => {
    const titleKey = (courseid === 0) ? 'addcourse' : 'editcourse';
    const title = await getString(titleKey, 'local_sentientia_courses');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_courses\\form\\edit_course',
        args: {courseid: courseid},
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

const confirmVisibility = async (courseid, coursename, makeVisible, returnFocus) => {
    const titleKey = makeVisible ? 'showcourse' : 'hidecourse';
    const msgKey = makeVisible ? 'confirmshow' : 'confirmhide';
    const successKey = makeVisible ? 'courseshown' : 'coursehidden';
    const [title, message, successMsg] = await Promise.all([
        getString(titleKey, 'local_sentientia_courses'),
        getString(msgKey, 'local_sentientia_courses', coursename),
        getString(successKey, 'local_sentientia_courses'),
    ]);
    Notification.confirm(title, message, title, null, async () => {
        try {
            await Ajax.call([{
                methodname: 'local_sentientia_courses_toggle_visibility',
                args: {courseid: courseid, visible: makeVisible},
            }])[0];
            Notification.addNotification({message: successMsg, type: 'success'});
            window.location.reload();
        } catch (err) {
            Notification.exception(err);
        }
    });
    if (returnFocus) {
        setTimeout(() => returnFocus.focus(), 100);
    }
};

const confirmDelete = async (courseid, coursename, returnFocus) => {
    const [title, message, deleteLabel, successMsg] = await Promise.all([
        getString('deletecourse', 'local_sentientia_courses'),
        getString('confirmdelete', 'local_sentientia_courses', coursename),
        getString('delete', 'core'),
        getString('coursedeleted', 'local_sentientia_courses'),
    ]);
    Notification.deleteCancelPromise(title, message, deleteLabel, returnFocus)
        .then(async () => {
            try {
                await Ajax.call([{
                    methodname: 'local_sentientia_courses_delete_course',
                    args: {courseid: courseid},
                }])[0];
                Notification.addNotification({message: successMsg, type: 'success'});
                window.location.reload();
            } catch (err) {
                Notification.exception(err);
            }
            return true;
        }, () => null);
};

// Phase F.5 (2026-05-08) — native enrol modal (replaces deep-link).
const openEnrolUsersModal = (courseid, coursename, returnFocus) => {
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_courses\\form\\enrol_users_modal',
        args: {courseid: courseid},
        modalConfig: {title: 'Enrol users — ' + coursename, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = (event.detail && event.detail.message) || 'Enrolled.';
        Notification.addNotification({message, type: 'success'});
        // No reload — the courses list doesn't show enrol counts inline.
    });
    modalForm.show();
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const courseid = parseInt(trigger.dataset.courseid || '0', 10);
    const coursename = trigger.dataset.coursename
        || trigger.dataset.name
        || 'this course';
    switch (action) {
        case 'create-course':
            event.preventDefault();
            openCourseForm(0, trigger);
            break;
        case 'edit-course':
            event.preventDefault();
            openCourseForm(courseid, trigger);
            break;
        case 'hide-course':
            event.preventDefault();
            confirmVisibility(courseid, coursename, false, trigger);
            break;
        case 'show-course':
            event.preventDefault();
            confirmVisibility(courseid, coursename, true, trigger);
            break;
        case 'delete-course':
            event.preventDefault();
            confirmDelete(courseid, coursename, trigger);
            break;
        case 'enrol-users-modal':
            event.preventDefault();
            openEnrolUsersModal(courseid, coursename, trigger);
            break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-courses"]') || document.body;
    if (root.dataset.airpayCoursesInit === '1') return;
    root.dataset.airpayCoursesInit = '1';
    root.addEventListener('click', handleClick);
};
