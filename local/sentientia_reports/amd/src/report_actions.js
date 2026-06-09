// Report definition CRUD actions.
// @module     local_sentientia_reports/report_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openReportForm = async (reportid, returnFocus) => {
    const titleKey = (reportid === 0) ? 'addreport' : 'editreport';
    const title = await getString(titleKey, 'local_sentientia_reports');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_reports\\form\\edit_report',
        args: {reportid: reportid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = (event.detail && event.detail.message) || 'Saved.';
        Notification.addNotification({message: message, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const confirmToggle = async (reportid, name, isActive, returnFocus) => {
    const titleKey = isActive ? 'archivereport' : 'activatereport';
    const msgKey = isActive ? 'confirmarchive' : 'confirmactivate';
    const [title, message, success] = await Promise.all([
        getString(titleKey, 'local_sentientia_reports'),
        getString(msgKey, 'local_sentientia_reports', name),
        getString('reportstatuschanged', 'local_sentientia_reports'),
    ]);
    Notification.confirm(title, message, title, null, () => {
        Ajax.call([{methodname: 'local_sentientia_reports_toggle_status',
                    args: {reportid: reportid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (reportid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deletereport', 'local_sentientia_reports'),
        getString('confirmdelete', 'local_sentientia_reports', name),
        getString('delete', 'core'),
        getString('reportdeleted', 'local_sentientia_reports'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_reports_delete_report',
                    args: {reportid: reportid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
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
    const reportid = parseInt(trigger.dataset.reportid || '0', 10);
    const name = trigger.dataset.name || 'this report';
    const isActive = trigger.dataset.active === '1';
    switch (action) {
        case 'create-report':   event.preventDefault(); openReportForm(0, trigger); break;
        case 'edit-report':     event.preventDefault(); openReportForm(reportid, trigger); break;
        case 'toggle-report':   event.preventDefault(); confirmToggle(reportid, name, isActive, trigger); break;
        case 'delete-report':   event.preventDefault(); confirmDelete(reportid, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-reports"]') || document.body;
    if (root.dataset.airpayReportsInit === '1') return;
    root.dataset.airpayReportsInit = '1';
    root.addEventListener('click', handleClick);
};
