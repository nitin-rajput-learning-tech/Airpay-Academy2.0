// Manager-side actions: decide-request modal + create-allocation modal +
// delete-allocation confirm + filter wiring on requests/allocations pages.
// @module     local_airpay_manager/manager_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openDecideForm = (requestid, decision, returnFocus) => {
    const verb = decision === 'rejected' ? 'Reject' : 'Approve';
    const modalForm = new ModalForm({
        formClass: 'local_airpay_manager\\form\\decide_request_dynamic_form',
        args: {requestid: requestid, decision: decision},
        modalConfig: {title: verb + ' enrolment request'},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const detail = event.detail || {};
        const warn = detail.enrolwarning || '';
        Notification.addNotification({
            message: warn
                ? `Decision saved. Note: ${warn}`
                : `Request ${decision}.`,
            type: warn ? 'warning' : 'success',
        });
        window.location.reload();
    });
    modalForm.show();
};

const openCreateAllocationForm = (returnFocus) => {
    const modalForm = new ModalForm({
        formClass: 'local_airpay_manager\\form\\create_allocation_dynamic_form',
        args: {},
        modalConfig: {title: 'Allocate course to direct report', large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        Notification.addNotification({
            message: 'Course allocated.',
            type: 'success',
        });
        window.location.reload();
    });
    modalForm.show();
};

const openBulkAllocateForm = (returnFocus) => {
    const modalForm = new ModalForm({
        formClass: 'local_airpay_manager\\form\\bulk_allocate_dynamic_form',
        args: {},
        modalConfig: {title: 'Bulk-assign course to direct reports', large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const detail = event.detail || {};
        const succ = detail.succeeded_count ?? 0;
        const skip = detail.skipped_count ?? 0;
        const fail = detail.failed_count ?? 0;
        let msg = `Allocated to ${succ} user(s).`;
        if (skip > 0) msg += ` ${skip} skipped (already had).`;
        if (fail > 0) msg += ` ${fail} failed.`;
        Notification.addNotification({
            message: msg,
            type: fail > 0 ? 'warning' : 'success',
        });
        window.location.reload();
    });
    modalForm.show();
};

const confirmDeleteAllocation = (allocid, name, returnFocus) => {
    Notification.deleteCancelPromise(
        'Cancel allocation',
        `Cancel the allocation of "${name}"? The user's enrolment is not removed.`,
        'Cancel allocation', returnFocus).then(() => {
        Ajax.call([{
            methodname: 'local_airpay_manager_delete_allocation',
            args: {id: allocid},
        }])[0].then(() => {
            Notification.addNotification({
                message: 'Allocation cancelled.',
                type: 'success',
            });
            window.location.reload();
            return null;
        }).catch(Notification.exception);
        return true;
    }, () => null);
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const requestid = parseInt(trigger.dataset.requestid || '0', 10);
    const decision = trigger.dataset.decision || 'approved';
    const allocid = parseInt(trigger.dataset.allocid || '0', 10);
    const name = trigger.dataset.name || 'this allocation';
    switch (action) {
        case 'decide-request':
            event.preventDefault();
            openDecideForm(requestid, decision, trigger);
            break;
        case 'create-allocation':
            event.preventDefault();
            openCreateAllocationForm(trigger);
            break;
        case 'bulk-allocate':
            event.preventDefault();
            openBulkAllocateForm(trigger);
            break;
        case 'delete-allocation':
            event.preventDefault();
            confirmDeleteAllocation(allocid, name, trigger);
            break;
    }
};

const reloadTable = (mountId, extraArgs) => {
    const mount = document.getElementById(mountId);
    if (!mount) return;
    mount.dataset.extraArgs = JSON.stringify(extraArgs);
    mount.dispatchEvent(new CustomEvent('airpay-datatable:reload', {bubbles: true}));
};

const wireRequestsFilter = () => {
    const sel = document.getElementById('ap-mgr-status');
    if (!sel) return;
    sel.addEventListener('change', () => {
        reloadTable('ap-mgr-requests-table', {status: sel.value || 'pending'});
    });
};

const wireAllocFilter = () => {
    const sel = document.getElementById('ap-mgr-alloc-status');
    if (!sel) return;
    sel.addEventListener('change', () => {
        reloadTable('ap-mgr-alloc-table', {status: sel.value || 'all'});
    });
};

export const init = (config = {}) => {
    document.addEventListener('click', handleClick);
    if (config.page === 'requests') wireRequestsFilter();
    if (config.page === 'allocations') wireAllocFilter();
};
