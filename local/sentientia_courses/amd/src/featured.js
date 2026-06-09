// Admin curation actions for the featured-courses widget.
//
// @module     local_sentientia_courses/featured
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const handleAddSubmit = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const courseid = parseInt(
        form.querySelector('#ap-featured-course').value || '0', 10);
    const tenantid = parseInt(
        form.querySelector('#ap-featured-tenant').value || '0', 10);
    const label = (form.querySelector('#ap-featured-label').value || '').trim();
    if (!courseid) {
        Notification.addNotification({
            message: 'Please pick a course.',
            type: 'warning',
        });
        return;
    }
    Ajax.call([{
        methodname: 'local_sentientia_courses_add_featured',
        args: {courseid, costcenterid: tenantid, label},
    }])[0].then(() => {
        Notification.addNotification({
            message: 'Pinned to featured.',
            type: 'success',
        });
        window.location.reload();
        return null;
    }).catch(Notification.exception);
};

const confirmRemove = (rowid, courseName, returnFocus) => {
    Notification.deleteCancelPromise(
        'Unpin from featured',
        `Remove "${courseName}" from the featured-courses widget?`,
        'Remove', returnFocus).then(() => {
        Ajax.call([{
            methodname: 'local_sentientia_courses_remove_featured',
            args: {id: rowid},
        }])[0].then(() => {
            Notification.addNotification({
                message: 'Unpinned.',
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
    if (action === 'remove-featured') {
        event.preventDefault();
        const rowid = parseInt(trigger.dataset.rowid || '0', 10);
        const name = trigger.dataset.course || 'this course';
        confirmRemove(rowid, name, trigger);
    }
};

export const init = () => {
    const form = document.getElementById('ap-featured-add');
    if (form && form.dataset.airpayInit !== '1') {
        form.dataset.airpayInit = '1';
        form.addEventListener('submit', handleAddSubmit);
    }
    const root = document.querySelector('[data-region="ap-featured-admin"]');
    if (root && root.dataset.airpayClickInit !== '1') {
        root.dataset.airpayClickInit = '1';
        root.addEventListener('click', handleClick);
    }
};
