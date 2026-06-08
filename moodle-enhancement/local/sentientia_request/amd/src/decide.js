// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Approve / reject modal for pending course requests.
 *
 * @module local_sentientia_request/decide
 *
 * Phase B.4 dual-target (2026-05-24): see createSaveCancelModal() below.
 * Moodle 5.2 removed core/modal_factory (MDL-79182).
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';

/**
 * Dual-target SAVE_CANCEL modal factory.
 * 5.2: require('core/modal') -> Modal.create({modalType: 'SAVE_CANCEL', ...})
 * 5.1: require('core/modal_factory') -> ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL, ...})
 * @param {{title: string, body: string}} spec
 * @return {Promise<object>}
 */
const createSaveCancelModal = (spec) => new Promise((resolve, reject) => {
    require(['core/modal'], (Modal) => {
        if (Modal && typeof Modal.create === 'function') {
            Modal.create({modalType: 'SAVE_CANCEL', title: spec.title, body: spec.body})
                .then(resolve).catch(reject);
            return;
        }
        require(['core/modal_factory'], (ModalFactory) => {
            ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL, title: spec.title, body: spec.body})
                .then(resolve).catch(reject);
        }, reject);
    }, () => {
        require(['core/modal_factory'], (ModalFactory) => {
            ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL, title: spec.title, body: spec.body})
                .then(resolve).catch(reject);
        }, reject);
    });
});

export const init = () => {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="decide-request"]');
        if (!btn) return;
        e.preventDefault();
        const requestid = parseInt(btn.dataset.requestid, 10);
        const decision  = btn.dataset.decision;  // 'approved' | 'rejected'
        const requester = btn.dataset.requester || 'this user';
        const course    = btn.dataset.course || 'this course';
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

        const modal = await createSaveCancelModal({
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
