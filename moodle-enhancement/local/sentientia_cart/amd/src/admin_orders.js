// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin orders — refund modal trigger + action handler.
 *
 * @module local_sentientia_cart/admin_orders
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
        const btn = e.target.closest('[data-action="refund-order"]');
        if (!btn) return;
        e.preventDefault();
        const historyid = parseInt(btn.dataset.historyid, 10);
        const total     = btn.dataset.total;
        const orderid   = btn.dataset.orderid;
        if (!historyid) return;

        const modal = await createSaveCancelModal({
            title: `Refund order #${orderid}`,
            body: `
                <p>Order total: <strong>${total}</strong></p>
                <div class="mb-2">
                    <label class="form-label">Refund amount</label>
                    <input type="number" id="refund_amount" class="form-control"
                           min="0.01" step="0.01" placeholder="Leave blank for full refund"/>
                    <small class="text-muted">Leave blank for full refund.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label">Reason</label>
                    <textarea id="refund_reason" class="form-control" rows="3"
                              placeholder="Customer request / duplicate / dispute / etc."></textarea>
                </div>`,
        });

        modal.getRoot().on(ModalEvents.save, () => {
            const amount = parseFloat(document.getElementById('refund_amount').value || 0);
            const reason = document.getElementById('refund_reason').value || '';
            Ajax.call([{
                methodname: 'local_sentientia_cart_refund',
                args: { historyid: historyid, amount: amount, reason: reason }
            }])[0].then(() => {
                window.location.reload();
            }).catch(Notification.exception);
        });

        modal.show();
    });
};
