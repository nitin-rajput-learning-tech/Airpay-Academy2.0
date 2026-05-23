// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Native enrol modal + per-row unenrol handler for the enrolledusers page.
 *
 * @module local_airpay_courses/enrolledusers
 *
 * @todo Phase B.4 cutover (2026-05-23): Moodle 5.2 removed `core/modal_factory`
 * and `core/modal_registry` (MDL-79182). At cutover-day:
 *   1. Replace `import ModalFactory from 'core/modal_factory'` with
 *      `import Modal from 'core/modal'`.
 *   2. Replace `ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL, ...})`
 *      with `Modal.create({modalType: 'SAVE_CANCEL', ...})`.
 *   3. Smoke test the enrol modal on /local/airpay_courses/enrolledusers.php.
 * See docs/5.2-merge/PHASE-B4-LIB-ADMIN-CONFLICTS.md.
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
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
                methodname: 'local_airpay_courses_unenrol_single',
                args: { courseid: COURSE_ID, userid: userid }
            }])[0].then(() => window.location.reload())
                  .catch(Notification.exception);
        }
    });
};

const openEnrolModal = async () => {
    const modal = await ModalFactory.create({
        type: ModalFactory.types.SAVE_CANCEL,
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
            methodname: 'local_airpay_courses_enrol_single',
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
