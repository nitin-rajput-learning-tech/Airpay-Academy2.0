// Skills + categories CRUD actions.
// @module     local_airpay_skills/skill_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openSkillForm = async (skillid, returnFocus) => {
    const titleKey = (skillid === 0) ? 'addskill' : 'editskill';
    const title = await getString(titleKey, 'local_airpay_skills');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_skills\\form\\edit_skill',
        args: {skillid: skillid},
        modalConfig: {title: title},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = (event.detail && event.detail.message) || 'Saved.';
        Notification.addNotification({message: message, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const openCategoryForm = async (categoryid, returnFocus) => {
    const titleKey = (categoryid === 0) ? 'addcategory' : 'editcategory';
    const title = await getString(titleKey, 'local_airpay_skills');
    const modalForm = new ModalForm({
        formClass: 'local_airpay_skills\\form\\edit_category',
        args: {categoryid: categoryid},
        modalConfig: {title: title},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        const message = (event.detail && event.detail.message) || 'Saved.';
        Notification.addNotification({message: message, type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const confirmDeleteSkill = async (skillid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deleteskill', 'local_airpay_skills'),
        getString('confirmdeleteskill', 'local_airpay_skills', name),
        getString('delete', 'core'),
        getString('skilldeleted', 'local_airpay_skills'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_skills_delete_skill',
                    args: {skillid: skillid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

const confirmDeleteCategory = async (categoryid, name, returnFocus) => {
    const [title, message, label, success] = await Promise.all([
        getString('deletecategory', 'local_airpay_skills'),
        getString('confirmdeletecategory', 'local_airpay_skills', name),
        getString('delete', 'core'),
        getString('categorydeleted', 'local_airpay_skills'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_airpay_skills_delete_category',
                    args: {categoryid: categoryid}}])[0]
            .then(() => {
                Notification.addNotification({message: success, type: 'success'});
                window.location.reload();
                return null;
            }).catch(Notification.exception);
        return true;
    }, () => null);
};

// ─── Phase A — skill-level definitions + designation-skill matrix ────────

const openLevelForm = async (skillid, level, returnFocus) => {
    const modalForm = new ModalForm({
        formClass: 'local_airpay_skills\\form\\edit_skill_level_dynamic_form',
        args: {skillid: skillid, level: level},
        modalConfig: {title: `Edit level ${level}`},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        Notification.addNotification({message: 'Level definition saved.', type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const openDesignationSkillForm = async (designation, rowid, returnFocus) => {
    const title = rowid === 0
        ? `Add required skill — ${designation}`
        : `Edit required skill — ${designation}`;
    const modalForm = new ModalForm({
        formClass: 'local_airpay_skills\\form\\edit_designation_skill_dynamic_form',
        args: {designation: designation, rowid: rowid},
        modalConfig: {title: title},
        returnFocus: returnFocus,
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        Notification.addNotification({message: 'Required skill saved.', type: 'success'});
        window.location.reload();
    });
    modalForm.show();
};

const confirmDeleteDesignationSkill = (rowid, skillname, returnFocus) => {
    Notification.deleteCancelPromise(
        'Remove required skill',
        `Remove required skill "${skillname}" from this designation?`,
        'Remove', returnFocus).then(() => {
        Ajax.call([{
            methodname: 'local_airpay_skills_delete_designation_skill',
            args: {id: rowid},
        }])[0].then(() => {
            Notification.addNotification({message: 'Removed.', type: 'success'});
            window.location.reload();
            return null;
        }).catch(Notification.exception);
        return true;
    }, () => null);
};

const promptCopyDesignation = async (fromDesignation, returnFocus) => {
    // Use a simple browser prompt for the target — modal would be overkill
    // for a one-string input. Validate non-empty + non-equal client-side.
    const target = window.prompt(
        `Copy all required skills from "${fromDesignation}" to which designation?`, '');
    if (!target || target.trim() === '' || target === fromDesignation) {
        if (returnFocus) returnFocus.focus();
        return;
    }
    Ajax.call([{
        methodname: 'local_airpay_skills_copy_designation',
        args: {fromdesignation: fromDesignation, todesignation: target.trim()},
    }])[0].then((result) => {
        Notification.addNotification({
            message: `Copied ${result.copied} required-skill row(s) to "${target.trim()}".`,
            type: 'success',
        });
        // Switch to the new designation in the URL.
        const url = new URL(window.location);
        url.searchParams.set('designation', target.trim());
        window.location = url.toString();
        return null;
    }).catch(Notification.exception);
};

const wireDesignationSelector = () => {
    const sel = document.getElementById('ap-skills-designation');
    if (!sel) return;
    sel.addEventListener('change', () => {
        const url = new URL(window.location);
        if (sel.value) {
            url.searchParams.set('designation', sel.value);
        } else {
            url.searchParams.delete('designation');
        }
        window.location = url.toString();
    });
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const id = parseInt(trigger.dataset.id || '0', 10);
    const name = trigger.dataset.name || 'this item';
    const skillid = parseInt(trigger.dataset.skillid || '0', 10);
    const level = parseInt(trigger.dataset.level || '0', 10);
    const designation = trigger.dataset.designation || '';
    const rowid = parseInt(trigger.dataset.rowid || '0', 10);
    switch (action) {
        case 'create-skill':    event.preventDefault(); openSkillForm(0, trigger); break;
        case 'edit-skill':      event.preventDefault(); openSkillForm(id, trigger); break;
        case 'delete-skill':    event.preventDefault(); confirmDeleteSkill(id, name, trigger); break;
        case 'create-category': event.preventDefault(); openCategoryForm(0, trigger); break;
        case 'edit-category':   event.preventDefault(); openCategoryForm(id, trigger); break;
        case 'delete-category': event.preventDefault(); confirmDeleteCategory(id, name, trigger); break;
        // Phase A
        case 'edit-level':                event.preventDefault(); openLevelForm(skillid, level, trigger); break;
        case 'add-designation-skill':     event.preventDefault(); openDesignationSkillForm(designation, 0, trigger); break;
        case 'edit-designation-skill':    event.preventDefault(); openDesignationSkillForm(designation, rowid, trigger); break;
        case 'delete-designation-skill':  event.preventDefault(); confirmDeleteDesignationSkill(rowid, name || trigger.dataset.skill || 'this skill', trigger); break;
        case 'copy-designation':          event.preventDefault(); promptCopyDesignation(trigger.dataset.from || '', trigger); break;
    }
};

export const init = (config = {}) => {
    // Page-specific wiring.
    if (config && config.page === 'designation_matrix') {
        wireDesignationSelector();
    }
    // Click delegation works on body for our two new admin pages too.
    const root = document.querySelector('[data-region="airpay-skills"]') || document.body;
    if (root.dataset.airpaySkillsInit === '1') return;
    root.dataset.airpaySkillsInit = '1';
    root.addEventListener('click', handleClick);
};
