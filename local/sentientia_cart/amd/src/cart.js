// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * AMD module for the cart page — handles remove-from-cart clicks.
 *
 * @module local_sentientia_cart/cart
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="remove-from-cart"]');
        if (!btn) return;
        e.preventDefault();
        const courseid = parseInt(btn.dataset.courseid, 10);
        if (!courseid) return;

        Ajax.call([{
            methodname: 'local_sentientia_cart_remove_item',
            args: { courseid: courseid }
        }])[0].then(() => {
            // Simple reload — datatable refresh would be nicer but for
            // small carts (typically <10 items) reload is fine.
            window.location.reload();
        }).catch(Notification.exception);
    });
};
