// Challenge actions: create/edit/delete via modal, join/leave via AJAX,
// filter wiring on index + leaderboard pages.
// @module     local_sentientia_challenge/challenge_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openChallengeForm = async (challengeid, returnFocus) => {
    const titleKey = challengeid === 0 ? 'btn_create' : 'btn_edit';
    const title = await getString(titleKey, 'local_sentientia_challenge');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_challenge\\form\\edit_challenge_dynamic_form',
        args: {challengeid: challengeid},
        modalConfig: {title: title, large: true},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, async () => {
        const msg = await getString(challengeid === 0 ? 'challenge_created' : 'challenge_updated',
            'local_sentientia_challenge', '');
        Notification.addNotification({message: msg, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const join = async (challengeid, returnFocus) => {
    const success = await getString('joined_challenge', 'local_sentientia_challenge');
    Ajax.call([{
        methodname: 'local_sentientia_challenge_join_challenge',
        args: {challengeid: challengeid},
    }])[0].then(() => {
        Notification.addNotification({message: success, type: 'success'});
        window.location.reload();
        return null;
    }).catch(Notification.exception);
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const leave = async (challengeid, returnFocus) => {
    const [title, message, ok, success] = await Promise.all([
        getString('btn_leave', 'local_sentientia_challenge'),
        getString('btn_leave', 'local_sentientia_challenge'),
        getString('continue', 'core'),
        getString('left_challenge', 'local_sentientia_challenge'),
    ]);
    Notification.confirm(title, message + '?', ok, null, () => {
        Ajax.call([{
            methodname: 'local_sentientia_challenge_leave_challenge',
            args: {challengeid: challengeid},
        }])[0].then(() => {
            Notification.addNotification({message: success, type: 'success'});
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const confirmDelete = async (challengeid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('btn_delete', 'local_sentientia_challenge'),
        getString('challenge_deleted', 'local_sentientia_challenge', name),
        getString('delete', 'core'),
        getString('challenge_deleted', 'local_sentientia_challenge', name),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{
            methodname: 'local_sentientia_challenge_delete_challenge',
            args: {id: challengeid},
        }])[0].then(() => {
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
    const challengeid = parseInt(trigger.dataset.challengeid || '0', 10);
    const name = trigger.dataset.name || '';
    switch (action) {
        case 'create-challenge':
            event.preventDefault();
            openChallengeForm(0, trigger);
            break;
        case 'edit-challenge':
            event.preventDefault();
            openChallengeForm(challengeid, trigger);
            break;
        case 'delete-challenge':
            event.preventDefault();
            confirmDelete(challengeid, name, trigger);
            break;
        case 'join-challenge':
            event.preventDefault();
            join(challengeid, trigger);
            break;
        case 'leave-challenge':
            event.preventDefault();
            leave(challengeid, trigger);
            break;
    }
};

const reloadTable = (mountId, extraArgs) => {
    const mount = document.getElementById(mountId);
    if (!mount) return;
    mount.dataset.extraArgs = JSON.stringify(extraArgs);
    mount.dispatchEvent(new CustomEvent('airpay-datatable:reload', {bubbles: true}));
};

const debounce = (fn, ms = 250) => {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
};

const wireIndexFilters = () => {
    const search = document.getElementById('airpay-challenge-search');
    const status = document.getElementById('airpay-challenge-status');
    if (!search || !status) return;
    const update = () => reloadTable('airpay-challenge-table', {
        search: search.value.trim(),
        status: status.value || 'active',
    });
    search.addEventListener('input', debounce(update));
    status.addEventListener('change', update);
};

const wireLeaderboardFilters = (mountId) => {
    const challenge = document.getElementById('airpay-challenge-lb-challenge');
    const tenant = document.getElementById('airpay-challenge-lb-tenant');
    const update = () => reloadTable(mountId, {
        challengeid: parseInt((challenge && challenge.value) || '0', 10),
        tenantmode: (tenant && tenant.value) || 'mine',
    });
    if (challenge) challenge.addEventListener('change', update);
    if (tenant)    tenant.addEventListener('change', update);
};

export const init = (config = {}) => {
    document.addEventListener('click', handleClick);
    if (config.page === 'index') {
        wireIndexFilters();
    } else if (config.page === 'leaderboard') {
        wireLeaderboardFilters('airpay-challenge-leaderboard-global-table');
    }
};
