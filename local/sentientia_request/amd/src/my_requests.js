// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Handle cancel-request clicks on the "My requests" datatable.
 *
 * @module local_sentientia_request/my_requests
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="cancel-request"]');
        if (!btn) return;
        e.preventDefault();
        const requestid = parseInt(btn.dataset.requestid, 10);
        if (!requestid) return;
        if (!confirm('Cancel this request?')) return;

        Ajax.call([{
            methodname: 'local_sentientia_request_cancel',
            args: { requestid: requestid }
        }])[0].then(() => window.location.reload())
              .catch(Notification.exception);
    });
};
