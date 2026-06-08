// Role-management actions: open edit-cap modal, reset cap to inherit,
// and wire filter forms on index/view/audit pages.
// @module     local_sentientia_roles/role_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

// ─── Capability mutations ────────────────────────────────────────────────

const openEditCapForm = async (roleid, capability, current, returnFocus) => {
    const title = await getString('form_edit_cap', 'local_sentientia_roles');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_roles\\form\\edit_capability_dynamic_form',
        args: {roleid: roleid, capability: capability, current: current},
        modalConfig: {title: title, large: false},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, async () => {
        const msg = await getString('cap_updated_success', 'local_sentientia_roles', capability);
        Notification.addNotification({message: msg, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const resetCap = async (roleid, capability, returnFocus) => {
    const [title, message, ok, success] = await Promise.all([
        getString('form_edit_cap', 'local_sentientia_roles'),
        getString('cap_perm_inherit', 'local_sentientia_roles'),
        getString('continue', 'core'),
        getString('cap_updated_success', 'local_sentientia_roles', capability),
    ]);
    Notification.confirm(title,
        `${capability} → ${message}?`, ok, null, () => {
        Ajax.call([{
            methodname: 'local_sentientia_roles_update_capability',
            args: {roleid: roleid, capability: capability, permission: 'inherit', reason: ''},
        }])[0].then(() => {
            Notification.addNotification({message: success, type: 'success'});
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    });
    if (returnFocus) setTimeout(() => returnFocus.focus(), 100);
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const roleid = parseInt(trigger.dataset.roleid || '0', 10);
    const capability = trigger.dataset.capability || '';
    const current = parseInt(trigger.dataset.current || '0', 10);
    switch (action) {
        case 'edit-cap':
            event.preventDefault();
            openEditCapForm(roleid, capability, current, trigger);
            break;
        case 'reset-cap':
            event.preventDefault();
            resetCap(roleid, capability, trigger);
            break;
    }
};

// ─── Filter wiring ───────────────────────────────────────────────────────

// Helper: re-encode the data-extra-args JSON on a datatable mount and
// trigger the shared datatable to reload. Falls back to a CustomEvent
// the shared component listens for.
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
    const search = document.getElementById('airpay-roles-search');
    const archetype = document.getElementById('airpay-roles-archetype');
    if (!search || !archetype) return;
    const update = () => reloadTable('airpay-roles-table', {
        search: search.value.trim(),
        archetype: archetype.value || 'all',
    });
    search.addEventListener('input', debounce(update));
    archetype.addEventListener('change', update);
};

const wireCapsFilters = (roleid) => {
    const search = document.getElementById('airpay-roles-cap-search');
    const perm = document.getElementById('airpay-roles-cap-perm');
    if (!search || !perm) return;
    const update = () => reloadTable('airpay-roles-caps-table', {
        roleid: roleid,
        search: search.value.trim(),
        perm: perm.value || 'all',
    });
    search.addEventListener('input', debounce(update));
    perm.addEventListener('change', update);
};

const wireAuditFilters = (mountId, defaultRoleId) => {
    const role = document.getElementById('airpay-roles-audit-role');
    const action = document.getElementById('airpay-roles-audit-action');
    const update = () => reloadTable(mountId, {
        roleid: parseInt((role && role.value) || defaultRoleId || 0, 10),
        action: (action && action.value) || '',
    });
    if (role)   role.addEventListener('change', update);
    if (action) action.addEventListener('change', update);
};

// ─── Public init ─────────────────────────────────────────────────────────

export const init = (config = {}) => {
    document.addEventListener('click', handleClick);

    if (config.page === 'index') {
        wireIndexFilters();
    } else if (config.page === 'view') {
        if (config.tab === 'capabilities') {
            wireCapsFilters(parseInt(config.roleid || 0, 10));
        } else if (config.tab === 'audit') {
            wireAuditFilters('airpay-roles-audit-table', parseInt(config.roleid || 0, 10));
        }
    } else if (config.page === 'audit') {
        wireAuditFilters('airpay-roles-audit-global-table', 0);
    }
};
