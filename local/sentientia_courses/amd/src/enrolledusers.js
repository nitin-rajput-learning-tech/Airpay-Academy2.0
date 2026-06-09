// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Native enrol modal + per-row unenrol handler for the enrolledusers page.
 *
 * @module local_sentientia_courses/enrolledusers
 *
 * Phase B.4 dual-target (2026-05-24): Moodle 5.2 removed `core/modal_factory`
 * (MDL-79182). This module no longer statically imports `core/modal_factory`
 * — that would break module-load on 5.2. Instead the version-specific module
 * is loaded via AMD's runtime `require()` inside `createSaveCancelModal()`,
 * with `core/modal`'s new static `.create({modalType, ...})` method preferred
 * (5.2) and `ModalFactory.create({type: ModalFactory.types.X, ...})` as
 * fallback (5.1).
 *
 * See docs/5.2-merge/PHASE-B4-LIB-ADMIN-CONFLICTS.md.
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';

let COURSE_ID = 0;
let COURSE_NAME = '';

export const init = (courseid, coursename) => {
    COURSE_ID = parseInt(courseid, 10);
    COURSE_NAME = coursename;

    document.addEventListener('click', async (e) => {
        // Enrol modal trigger.
        const enrolBtn = e.target.closest('[data-action="enrol-user-modal"]');
        if (enrolBtn) {
            e.preventDefault();
            await openEnrolModal();
            return;
        }
        // Per-row unenrol.
        const unenrolBtn = e.target.closest('[data-action="unenrol-user"]');
        if (unenrolBtn) {
            e.preventDefault();
            const userid = parseInt(unenrolBtn.dataset.userid, 10);
            const username = unenrolBtn.dataset.username || 'this user';
            if (!confirm(`Unenrol ${username} from "${COURSE_NAME}"?`)) return;

            Ajax.call([{
                methodname: 'local_sentientia_courses_unenrol_single',
                args: { courseid: COURSE_ID, userid: userid }
            }])[0].then(() => window.location.reload())
                  .catch(Notification.exception);
        }
    });
};

/**
 * Dual-target SAVE_CANCEL modal factory.
 *
 * 5.2: require('core/modal') -> Modal.create({modalType: 'SAVE_CANCEL', ...})
 * 5.1: require('core/modal_factory') -> ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL, ...})
 *
 * @param {{title: string, body: string}} spec
 * @return {Promise<object>} resolves to a Moodle modal instance
 */
const createSaveCancelModal = (spec) => new Promise((resolve, reject) => {
    require(['core/modal'], (Modal) => {
        if (Modal && typeof Modal.create === 'function') {
            // Moodle 5.2 — new API.
            Modal.create({
                modalType: 'SAVE_CANCEL',
                title: spec.title,
                body: spec.body,
            }).then(resolve).catch(reject);
            return;
        }
        // Moodle 5.1 — Modal class exists but no static create(); use factory.
        require(['core/modal_factory'], (ModalFactory) => {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: spec.title,
                body: spec.body,
            }).then(resolve).catch(reject);
        }, reject);
    }, () => {
        // core/modal failed to load — try factory directly.
        require(['core/modal_factory'], (ModalFactory) => {
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: spec.title,
                body: spec.body,
            }).then(resolve).catch(reject);
        }, reject);
    });
});

const openEnrolModal = async () => {
    const modal = await createSaveCancelModal({
        title: `Enrol user in "${COURSE_NAME}"`,
        body: `
            <p>Find user by <strong>email</strong>, <strong>employee ID</strong>, or <strong>username</strong>.</p>
            <div class="mb-3">
                <label class="form-label">Identifier</label>
                <input type="text" id="airpay-enrol-identifier" class="form-control"
                       placeholder="user@airpay.co.in OR EMP-1234 OR username" autofocus>
            </div>
            <div id="airpay-enrol-status" class="small"></div>`,
    });

    modal.getRoot().on(ModalEvents.save, () => {
        const identifier = document.getElementById('airpay-enrol-identifier').value.trim();
        const status = document.getElementById('airpay-enrol-status');
        if (!identifier) {
            status.innerHTML = '<div class="text-danger">Identifier is required.</div>';
            return;
        }
        status.innerHTML = '<div class="text-muted">Enrolling...</div>';

        Ajax.call([{
            methodname: 'local_sentientia_courses_enrol_single',
            args: { courseid: COURSE_ID, identifier: identifier }
        }])[0].then((r) => {
            if (r.success && r.enrolled) {
                status.innerHTML = '<div class="text-success">Enrolled (user ID ' + r.userid + ')</div>';
                setTimeout(() => window.location.reload(), 1000);
            } else if (r.success && !r.enrolled) {
                status.innerHTML = '<div class="text-warning">' + r.reason + '</div>';
            } else {
                status.innerHTML = '<div class="text-danger">' + r.reason + '</div>';
            }
        }).catch(Notification.exception);
    });

    modal.show();
};
