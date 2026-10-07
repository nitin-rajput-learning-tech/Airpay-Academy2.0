// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Wire up "Request access" buttons in the catalog.
 *
 * Pattern: any element with data-action="request-course" and
 * data-courseid="N" opens a modal where the user types a reason.
 *
 * @module local_sentientia_request/request_button
 *
 * WF-024 (2026-06-15) + Moodle 5.3 compat FX-06 (2026-10-08): the dialog is a core/modal_save_cancel
 * modal. Moodle 5.2 removed the legacy modal factory AMD module (MDL-79182), and a modalType option
 * on Modal.create is NOT a core API: it silently built a BASE modal with an empty footer (no
 * Save/Cancel buttons), so the request could never be submitted. core/modal_save_cancel exists
 * unchanged on 5.1, 5.2 and 5.3, so the former runtime fallback to the factory is gone.
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';

export const init = () => {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="request-course"]');
        if (!btn) return;
        e.preventDefault();
        const courseid   = parseInt(btn.dataset.courseid, 10);
        // Escape: course names are user-controlled data interpolated into
        // modal HTML (WF-019 hardening, mirrors decide.js).
        const coursename = String(btn.dataset.coursename || 'this course').replace(/[&<>"']/g,
            (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        if (!courseid) return;

        // removeOnClose: a cancelled dialog must not stay in the DOM, or the next one would read its
        // reason from the stale hidden textarea with the same id.
        const modal = await ModalSaveCancel.create({
            removeOnClose: true,
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
                methodname: 'local_sentientia_request_submit',
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
