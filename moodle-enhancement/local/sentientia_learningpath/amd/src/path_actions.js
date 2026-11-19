// Learning Path CRUD actions.
//
// Two init paths:
//   - init()       — for the index/list page (Add Path, Edit Path, Status, Delete)
//   - initView(id) — for the detail page (Add Courses, Remove Course, Enrol Users, Unenrol User,
//                    and why an imported BizLMS course row cannot be removed)
//
// @module     local_sentientia_learningpath/path_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

// Path, course and user names come from data-name attributes as plain text; every dialog body below is HTML, so
// escape them (2026-11-19, plan 2.4: a name with "<" or "&" broke or injected into the dialog).
const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

// ════════════════════════════════════════════════════════════════════
// Path-level actions (index page)
// ════════════════════════════════════════════════════════════════════

const openPathForm = async (pathid, returnFocus) => {
    const titleKey = (pathid === 0) ? 'addpath' : 'editpath';
    const title = await getString(titleKey, 'local_sentientia_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_learningpath\\form\\edit_path',
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
        getString(titleKey, 'local_sentientia_learningpath'),
        getString(msgKey, 'local_sentientia_learningpath', escapeHtml(name)),
        getString('pathstatuschanged', 'local_sentientia_learningpath'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_sentientia_learningpath_toggle_status',
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
        getString('deletepath', 'local_sentientia_learningpath'),
        getString('confirmdelete', 'local_sentientia_learningpath', escapeHtml(name)),
        getString('delete', 'core'),
        getString('pathdeleted', 'local_sentientia_learningpath'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_learningpath_delete_path',
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
    const title = await getString('add_courses', 'local_sentientia_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_learningpath\\form\\assign_courses_form',
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
    const title = await getString('enrol_users', 'local_sentientia_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_learningpath\\form\\enrol_users_form',
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

// P1 #11 (2026-05-16) — bulk enrol by target audience.
const openBulkEnrolAudienceForm = async (pathid, returnFocus) => {
    const title = await getString('audience_modal_title',
        'local_sentientia_learningpath');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_learningpath\\form\\bulk_enrol_audience_form',
        args: {pathid: pathid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    // Once the modal renders, wire up the audience filter helper:
    // populates dropdowns + fires live preview on every filter change.
    modalForm.addEventListener(modalForm.events.LOADED, async () => {
        // The audience_form_helper module discovers the modal body
        // automatically via the data-airpay-audience-filter attribute.
        const modalBody = document.querySelector('.modal.show .modal-body')
            || document.querySelector('.modal-body');
        if (modalBody) {
            const helper = await import(
                'local_sentientia_learningpath/audience_form_helper');
            helper.init(modalBody);
        }
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const detail = event.detail || {};
        const enrolled = detail.enrolled || 0;
        const matched = detail.matched || 0;
        const message = enrolled === matched
            ? `Enrolled all ${enrolled} matching user${enrolled === 1 ? '' : 's'}.`
            : `${enrolled} of ${matched} matching users enrolled (rest were already enrolled).`;
        Notification.addNotification({message: message, type: 'success'});
        refreshTable();
    });
    modalForm.show();
};

// 2026-11-19 (plan 2.4): the two dialogs below were titled "Add Courses" and "Enrol Users", their button said
// "Delete" and their success notices were hard-coded English. Each now names its own action (en + hi strings).
const confirmUnassignCourse = async (pathid, courseid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('unassign_course_title', 'local_sentientia_learningpath'),
        getString('confirm_unassign_course', 'local_sentientia_learningpath', escapeHtml(name)),
        getString('unassign_course_button', 'local_sentientia_learningpath'),
        getString('course_unassigned', 'local_sentientia_learningpath'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_learningpath_unassign_course',
                    args: {pathid: pathid, courseid: courseid}}])[0]
            .then(() => {
                Notification.addNotification({
                    message: success,
                    type: 'success'
                });
                refreshTable();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

// An imported BizLMS course row cannot be removed (ADR-032); its lock button says why (2026-11-19, plan 2.4).
const explainImportedCourse = async (name) => {
    const [title, message, close] = await Promise.all([
        getString('imported_course_title', 'local_sentientia_learningpath'),
        getString('imported_course_explain', 'local_sentientia_learningpath', escapeHtml(name)),
        getString('closebuttontitle', 'core'),
    ]);
    return Notification.alert(title, message, close);
};

const confirmUnenrolUser = async (pathid, userid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('unenrol_user_title', 'local_sentientia_learningpath'),
        getString('confirm_unenrol_user', 'local_sentientia_learningpath', escapeHtml(name)),
        getString('unenrol_user_button', 'local_sentientia_learningpath'),
        getString('user_unenrolled', 'local_sentientia_learningpath'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_learningpath_unenrol_user',
                    args: {pathid: pathid, userid: userid}}])[0]
            .then((result) => {
                Notification.addNotification({
                    message: success,
                    type: 'success'
                });
                // LRN-10: an enrolment the BizLMS import converted leaves course enrolments behind; the server
                // says which (ready-made, escaped HTML), so the admin removes them knowingly.
                if (result && result.notice) {
                    Notification.addNotification({
                        message: result.notice,
                        type: 'warning'
                    });
                }
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
        case 'bulk-enrol-audience':
            event.preventDefault();
            openBulkEnrolAudienceForm(pathid, trigger);
            break;
        case 'unassign-course':
            event.preventDefault();
            confirmUnassignCourse(pathid, courseid, name, trigger);
            break;
        case 'imported-course-info':
            event.preventDefault();
            explainImportedCourse(name).catch(Notification.exception);
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
