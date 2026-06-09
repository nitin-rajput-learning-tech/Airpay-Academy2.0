// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Save course price via WS.
 *
 * @module local_sentientia_cart/set_price
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="save-price"]');
        if (!btn) return;
        e.preventDefault();
        const row = btn.closest('tr[data-courseid]');
        if (!row) return;
        const courseid = parseInt(row.dataset.courseid, 10);
        const input = row.querySelector('.price-input');
        const price = parseFloat(input.value || 0);

        btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        Ajax.call([{
            methodname: 'local_sentientia_cart_set_price',
            args: { courseid: courseid, price: price, currency: 'INR' }
        }])[0].then(() => {
            btn.innerHTML = '<i class="fa fa-check"></i>';
            setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; }, 1500);
        }).catch((err) => {
            btn.disabled = false;
            btn.innerHTML = orig;
            Notification.exception(err);
        });
    });
};
