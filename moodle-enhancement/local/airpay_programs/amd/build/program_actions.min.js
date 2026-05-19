// Program CRUD actions.
// @module     local_airpay_programs/program_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const STATUS = {DRAFT: 0, ACTIVE: 1, ARCHIVED: 2};

// ─── Program-level actions (existing — index.php) ─────────────────────────

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

// ─── View-page actions (G-03 — view.php levels/users tabs) ────────────────

const openLevelForm = async (programid, levelid, returnFocus) => {
    const titleKey = (levelid === 0) ? 'add_level' : 'edit_level';
    const title = await getString(titleKey, 'local_airpay_programs');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_programs\\form\\edit_level',
        args: {programid: programid, levelid: levelid},
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

const openEnrolUsersForm = async (programid, returnFocus) => {
    const title = await getString('enrol_users', 'local_airpay_programs');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_programs\\form\\enrol_program_users',
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

// P1 #14 (2026-05-16) — bulk enrol by target audience.
const openBulkEnrolAudienceForm = async (programid, returnFocus) => {
    const title = await getString('audience_modal_title',
        'local_airpay_programs');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_programs\\form\\bulk_enrol_audience_form',
        args: {programid: programid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.LOADED, async () => {
        const modalBody = document.querySelector('.modal.show .modal-body')
            || document.querySelector('.modal-body');
        if (modalBody) {
            const helper = await import(
                'local_airpay_programs/audience_form_helper');
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
        window.location.reload();
    });
    modalForm.show();
};

// Phase F.3 (2026-05-08) — mass-enrol cohort modal.
const openEnrolCohortForm = (programid, returnFocus) => {
    const modalForm = new ModalForm({
        formClass: 'local_airpay_programs\\form\\enrol_program_cohort',
        args: {programid: programid},
        modalConfig: {title: 'Mass-enrol cohort', large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = (event.detail && event.detail.message) || 'Cohort enrolled.';
        Notification.addNotification({message: message, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const confirmDeleteLevel = async (levelid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('delete_level', 'local_airpay_programs'),
        getString('confirm_delete_level', 'local_airpay_programs', name),
        getString('delete', 'core'),
        getString('leveldeleted', 'local_airpay_programs'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_programs_delete_level',
                    args: {levelid: levelid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

const confirmUnenrolUser = async (programid, userid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('unenrol_user', 'local_airpay_programs'),
        getString('confirm_unenrol_user', 'local_airpay_programs', name),
        getString('remove', 'core'),
        getString('userunenrolled', 'local_airpay_programs'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_programs_unenrol_user',
                    args: {programid: programid, userid: userid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

const handleViewClick = (programid) => (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    switch (action) {
        case 'add-level':
            event.preventDefault();
            openLevelForm(programid, 0, trigger);
            break;
        case 'edit-level': {
            event.preventDefault();
            const lid = parseInt(trigger.dataset.levelid || '0', 10);
            openLevelForm(programid, lid, trigger);
            break;
        }
        case 'delete-level': {
            event.preventDefault();
            const lid = parseInt(trigger.dataset.levelid || '0', 10);
            const name = trigger.dataset.name || 'this level';
            confirmDeleteLevel(lid, name, trigger);
            break;
        }
        case 'enrol-program-users':
            event.preventDefault();
            openEnrolUsersForm(programid, trigger);
            break;
        case 'bulk-enrol-audience':
            event.preventDefault();
            openBulkEnrolAudienceForm(programid, trigger);
            break;
        case 'enrol-program-cohort':
            event.preventDefault();
            openEnrolCohortForm(programid, trigger);
            break;
        case 'unenrol-program-user': {
            event.preventDefault();
            const uid = parseInt(trigger.dataset.userid || '0', 10);
            const name = trigger.dataset.name || 'this user';
            confirmUnenrolUser(programid, uid, name, trigger);
            break;
        }
    }
};

// ─── Level-courses sub-page actions (G-03 — levelcourses.php) ─────────────

const openAssignCoursesForm = async (levelid, returnFocus) => {
    const title = await getString('add_courses', 'local_airpay_programs');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_programs\\form\\assign_level_courses',
        args: {levelid: levelid},
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

const confirmUnassignCourse = async (levelid, courseid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('add_courses', 'local_airpay_programs'),  // header reuses
        getString('confirm_unassign_course', 'local_airpay_programs', name),
        getString('remove', 'core'),
        getString('courseunassigned', 'local_airpay_programs'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_programs_unassign_level_course',
                    args: {levelid: levelid, courseid: courseid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

const handleLevelCoursesClick = (levelid) => (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    switch (action) {
        case 'add-level-courses':
            event.preventDefault();
            openAssignCoursesForm(levelid, trigger);
            break;
        case 'unassign-level-course': {
            event.preventDefault();
            const cid = parseInt(trigger.dataset.courseid || '0', 10);
            const name = trigger.dataset.name || 'this course';
            confirmUnassignCourse(levelid, cid, name, trigger);
            break;
        }
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-programs"]') || document.body;
    if (root.dataset.airpayProgramsInit === '1') return;
    root.dataset.airpayProgramsInit = '1';
    root.addEventListener('click', handleClick);
};

export const initView = (programid) => {
    const root = document.querySelector('[data-region="airpay-programs-view"]') || document.body;
    if (root.dataset.airpayProgramsViewInit === '1') return;
    root.dataset.airpayProgramsViewInit = '1';
    root.addEventListener('click', handleViewClick(programid));
};

export const initLevelCourses = (levelid) => {
    const root = document.querySelector('[data-region="airpay-level-courses"]') || document.body;
    if (root.dataset.airpayLevelCoursesInit === '1') return;
    root.dataset.airpayLevelCoursesInit = '1';
    root.addEventListener('click', handleLevelCoursesClick(levelid));
};
