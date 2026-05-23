// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Wire up "Request access" buttons in the catalog.
 *
 * Pattern: any element with data-action="request-course" and
 * data-courseid="N" opens a modal where the user types a reason.
 *
 * @module local_airpay_request/request_button
 *
 * @todo Phase B.4 cutover (2026-05-23): Moodle 5.2 removed
 * `core/modal_factory` (MDL-79182). At cutover-day swap to
 * `core/modal` per docs/5.2-merge/PHASE-B4-LIB-ADMIN-CONFLICTS.md.
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

export const init = () => {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="request-course"]');
        if (!btn) return;
        e.preventDefault();
        const courseid   = parseInt(btn.dataset.courseid, 10);
        const coursename = btn.dataset.coursename || 'this course';
        if (!courseid) return;

        const modal = await ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: 'Request enrolment',
            body: `
                <p>Requesting access to <strong>${coursename}</strong>.</p>
                <p class="text-muted small">
                    Your request will be routed to your manager (if assigned)
                    or course owner. SLA: 48 hours.
                </p>
                <div class="mb-2">
                    <label class="form-label">Reason (min 20 chars)</label>
                    <textarea id="request_reason" class="form-control" rows="4"
                              placeholder="Why do you need this course?"></textarea>
                    <small class="text-muted">e.g. "Required for new role in operations team."</small>
                </div>`,
        });

        modal.getRoot().on(ModalEvents.save, () => {
            const reason = document.getElementById('request_reason').value || '';
            if (reason.trim().length < 20) {
                Notification.alert('Reason too short',
                    'Please give at least 20 characters explaining why.');
                return;
            }
            Ajax.call([{
                methodname: 'local_airpay_request_submit',
                args: { courseid: courseid, reason: reason.trim() }
            }])[0].then(() => {
                btn.disabled = true;
                btn.textContent = 'Requested';
                Notification.addNotification({
                    message: 'Request submitted successfully.',
                    type: 'success',
                });
            }).catch(Notification.exception);
        });

        modal.show();
    });
};
