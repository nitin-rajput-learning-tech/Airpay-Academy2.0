// Learning Path CRUD actions.
//
// Two init paths:
//   - init()       — for the index/list page (Add Path, Edit Path, Status, Delete)
//   - initView(id) — for the detail page (Add Courses, Remove Course, Enrol Users, Unenrol User)
//
// @module     local_airpay_learningpath/path_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

// ════════════════════════════════════════════════════════════════════
// Path-level actions (index page)
// ════════════════════════════════════════════════════════════════════

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

// ════════════════════════════════════════════════════════════════════
// Detail-page actions (G-04) — assign courses + enrol users
// ════════════════════════════════════════════════════════════════════

const refreshTable = (rootEl) => {
    // Bypass module re-import — the shared datatable is already on `window`
    // via its own `init` call from view.mustache. Easiest reliable refresh:
    // dispatch a fake change so the existing event handler runs, OR just reload.
    // The reload is cheaper than wiring up the instance-getter ladder here, and
    // avoids subtle staleness if multiple datatables are on the page.
    window.location.reload();
};

const openAssignCoursesForm = async (pathid, returnFocus) => {
    const title = await getString('add_courses', 'local_airpay_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_learningpath\\form\\assign_courses_form',
        args: {pathid: pathid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const detail = event.detail || {};
        const inserted = detail.inserted || 0;
        const message = `${inserted} course${inserted === 1 ? '' : 's'} added.`;
        Notification.addNotification({message: message, type: 'success'});
        refreshTable();
    });
    modalForm.show();
};

const openEnrolUsersForm = async (pathid, returnFocus) => {
    const title = await getString('enrol_users', 'local_airpay_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_learningpath\\form\\enrol_users_form',
        args: {pathid: pathid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const detail = event.detail || {};
        const enrolled = detail.enrolled || 0;
        const message = `${enrolled} user${enrolled === 1 ? '' : 's'} enrolled.`;
        Notification.addNotification({message: message, type: 'success'});
        refreshTable();
    });
    modalForm.show();
};

const confirmUnassignCourse = async (pathid, courseid, name, returnFocus) => {
    const [title, message, label] = await Promise.all([
        getString('add_courses', 'local_airpay_learningpath'),
        getString('confirm_unassign_course', 'local_airpay_learningpath', name),
        getString('delete', 'core'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_learningpath_unassign_course',
                    args: {pathid: pathid, courseid: courseid}}])[0]
            .then(() => {
                Notification.addNotification({
                    message: 'Course removed from path.',
                    type: 'success'
                });
                refreshTable();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

const confirmUnenrolUser = async (pathid, userid, name, returnFocus) => {
    const [title, message, label] = await Promise.all([
        getString('enrol_users', 'local_airpay_learningpath'),
        getString('confirm_unenrol_user', 'local_airpay_learningpath', name),
        getString('delete', 'core'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_learningpath_unenrol_user',
                    args: {pathid: pathid, userid: userid}}])[0]
            .then(() => {
                Notification.addNotification({
                    message: 'User unenrolled.',
                    type: 'success'
                });
                refreshTable();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

// ════════════════════════════════════════════════════════════════════
// Click delegators
// ════════════════════════════════════════════════════════════════════

const handleIndexClick = (event) => {
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

const handleViewClick = (pathid) => (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const courseid = parseInt(trigger.dataset.courseid || '0', 10);
    const userid = parseInt(trigger.dataset.userid || '0', 10);
    const name = trigger.dataset.name || 'this item';
    switch (action) {
        case 'add-courses':
            event.preventDefault();
            openAssignCoursesForm(pathid, trigger);
            break;
        case 'enrol-users':
            event.preventDefault();
            openEnrolUsersForm(pathid, trigger);
            break;
        case 'unassign-course':
            event.preventDefault();
            confirmUnassignCourse(pathid, courseid, name, trigger);
            break;
        case 'unenrol-user':
            event.preventDefault();
            confirmUnenrolUser(pathid, userid, name, trigger);
            break;
    }
};

// ════════════════════════════════════════════════════════════════════
// Public init functions
// ════════════════════════════════════════════════════════════════════

export const init = () => {
    const root = document.querySelector('[data-region="airpay-learningpath"]') || document.body;
    if (root.dataset.airpayLearningpathInit === '1') return;
    root.dataset.airpayLearningpathInit = '1';
    root.addEventListener('click', handleIndexClick);
};

export const initView = (pathid) => {
    const root = document.querySelector('[data-region="airpay-path-view"]') || document.body;
    if (root.dataset.airpayPathviewInit === '1') return;
    root.dataset.airpayPathviewInit = '1';
    root.addEventListener('click', handleViewClick(pathid));
};
