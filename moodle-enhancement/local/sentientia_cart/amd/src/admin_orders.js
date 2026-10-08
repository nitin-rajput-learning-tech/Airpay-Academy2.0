// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin orders — refund modal trigger + action handler.
 *
 * @module local_sentientia_cart/admin_orders
 *
 * Moodle 5.3 compat FX-06 (2026-10-08): the Refund dialog is a core/modal_save_cancel modal.
 * The legacy modal factory AMD module is gone in Moodle 5.2 (MDL-79182) and a modalType option on
 * Modal.create is not a core API: it built a BASE modal with an empty footer, so the Save button
 * never existed and a refund could not be submitted. core/modal_save_cancel exists unchanged on
 * 5.1, 5.2 and 5.3 (same fix as local_sentientia_request/decide, WF-024).
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';

export const init = () => {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="refund-order"]');
        if (!btn) return;
        e.preventDefault();
        const historyid = parseInt(btn.dataset.historyid, 10);
        const total     = btn.dataset.total;
        const orderid   = btn.dataset.orderid;
        if (!historyid) return;

        const modal = await ModalSaveCancel.create({
            removeOnClose: true,
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
