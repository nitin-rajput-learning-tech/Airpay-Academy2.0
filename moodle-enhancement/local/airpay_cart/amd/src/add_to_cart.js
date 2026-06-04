// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hook into "Add to cart" buttons that catalog/course-detail templates
 * may render. Pattern: any element with `data-action="add-to-cart"`
 * and `data-courseid="N"` is wired up automatically.
 *
 * Templates from sentientia_catalog can opt into cart-button rendering by
 * including this module — keeps the cart plugin's footprint isolated.
 *
 * @module local_airpay_cart/add_to_cart
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="add-to-cart"]');
        if (!btn) return;
        e.preventDefault();
        const courseid = parseInt(btn.dataset.courseid, 10);
        if (!courseid) return;

        btn.disabled = true;
        const orig_html = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        Ajax.call([{
            methodname: 'local_airpay_cart_add_item',
            args: { courseid: courseid }
        }])[0].then((resp) => {
            btn.innerHTML = '<i class="fa fa-check"></i> In cart';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            // Update any visible badge of cart count.
            const badges = document.querySelectorAll('[data-region="cart-count-badge"]');
            badges.forEach(b => { b.textContent = resp.item_count; });
        }).catch((err) => {
            btn.disabled = false;
            btn.innerHTML = orig_html;
            Notification.exception(err);
        });
    });
};
