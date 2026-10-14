// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Approve / reject modal for pending course requests.
 *
 * @module local_sentientia_request/decide
 *
 * WF-024 (2026-06-15) + Moodle 5.3 compat FX-06 (2026-10-08): the dialog is a core/modal_save_cancel
 * modal. Moodle 5.2 removed the legacy modal factory AMD module (MDL-79182), and a modalType option
 * on Modal.create is NOT a core API: it silently built a BASE modal with an empty footer (no
 * Save/Cancel buttons), so a decision could never be submitted. core/modal_save_cancel exists
 * unchanged on 5.1, 5.2 and 5.3, so the former runtime fallback to the factory is gone.
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';

/**
 * Escape a dataset-sourced string for interpolation into modal HTML.
 * Requester/course names are user-controlled data (WF-019 hardening).
 * @param {string} s
 * @return {string}
 */
const esc = (s) => String(s).replace(/[&<>"']/g,
    (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

export const init = () => {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="decide-request"]');
        if (!btn) return;
        e.preventDefault();
        const requestid = parseInt(btn.dataset.requestid, 10);
        const decision  = btn.dataset.decision;  // 'approved' | 'rejected'
        const requester = esc(btn.dataset.requester || 'this user');
        const course    = esc(btn.dataset.course || 'this course');
        if (!requestid || !['approved', 'rejected'].includes(decision)) return;

        const isApprove = decision === 'approved';
        const title = isApprove
            ? `Approve request from ${requester}`
            : `Reject request from ${requester}`;
        const body = `
            <p>${isApprove ? 'Approving' : 'Rejecting'} the request for <strong>${course}</strong>.</p>
            <div class="mb-2">
                <label class="form-label">${isApprove ? 'Note (optional)' : 'Reason (required)'}</label>
                <textarea id="decision_note" class="form-control" rows="3"
                          placeholder="${isApprove ? 'e.g. relevant to your role' : 'Tell the requester why'}"></textarea>
            </div>`;

        // removeOnClose: a cancelled dialog must not stay in the DOM, or the next one would read its
        // note from the stale hidden textarea with the same id.
        const modal = await ModalSaveCancel.create({
            removeOnClose: true,
            title: title,
            body: body,
        });

        modal.getRoot().on(ModalEvents.save, () => {
            const note = document.getElementById('decision_note').value || '';
            if (!isApprove && note.trim() === '') {
                Notification.alert('Reason required', 'Please give the requester a reason for rejection.');
                return;
            }
            Ajax.call([{
                methodname: 'local_sentientia_request_decide',
                args: { requestid: requestid, decision: decision, note: note }
            }])[0].then(() => window.location.reload())
                  .catch(Notification.exception);
        });

        modal.show();
    });
};
