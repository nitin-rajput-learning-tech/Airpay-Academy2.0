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

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    const id = parseInt(trigger.dataset.id || '0', 10);
    const name = trigger.dataset.name || 'this item';
    switch (action) {
        case 'create-skill':    event.preventDefault(); openSkillForm(0, trigger); break;
        case 'edit-skill':      event.preventDefault(); openSkillForm(id, trigger); break;
        case 'delete-skill':    event.preventDefault(); confirmDeleteSkill(id, name, trigger); break;
        case 'create-category': event.preventDefault(); openCategoryForm(0, trigger); break;
        case 'edit-category':   event.preventDefault(); openCategoryForm(id, trigger); break;
        case 'delete-category': event.preventDefault(); confirmDeleteCategory(id, name, trigger); break;
    }
};

export const init = () => {
    const root = document.querySelector('[data-region="airpay-skills"]') || document.body;
    if (root.dataset.airpaySkillsInit === '1') return;
    root.dataset.airpaySkillsInit = '1';
    root.addEventListener('click', handleClick);
};
