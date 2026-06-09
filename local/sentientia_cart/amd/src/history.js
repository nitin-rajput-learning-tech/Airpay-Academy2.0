// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Post-process rows returned by list_orders into datatable-friendly cells.
 * Datatable component renders the raw WS shape; we transform after fetch.
 *
 * @module local_sentientia_cart/history
 */
export const init = () => {
    document.addEventListener('airpay-datatable:rows-rendered', (e) => {
        const table = e.detail?.tableEl;
        if (!table) return;
        table.querySelectorAll('tr[data-row-id]').forEach((tr) => {
            const id = tr.dataset.rowId;
            const orderid = tr.dataset.orderid;
            if (!orderid) return;
            // Add link to order detail in the first cell.
            const first = tr.querySelector('td:first-child');
            if (first && !first.querySelector('a')) {
                first.innerHTML = `<a href="${M.cfg.wwwroot}/local/sentientia_cart/return.php?orderid=${orderid}">#${orderid}</a>`;
            }
        });
    });
};
