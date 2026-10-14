// Skills + categories CRUD actions.
// @module     local_sentientia_skills/skill_actions
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import ModalForm from 'core_form/modalform';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const openSkillForm = async (skillid, returnFocus) => {
    const titleKey = (skillid === 0) ? 'addskill' : 'editskill';
    const title = await getString(titleKey, 'local_sentientia_skills');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_skills\\form\\edit_skill',
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
    const title = await getString(titleKey, 'local_sentientia_skills');
    const modalForm = new ModalForm({
        formClass: 'local_sentientia_skills\\form\\edit_category',
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
        getString('deleteskill', 'local_sentientia_skills'),
        getString('confirmdeleteskill', 'local_sentientia_skills', name),
        getString('delete', 'core'),
        getString('skilldeleted', 'local_sentientia_skills'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_skills_delete_skill',
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
        getString('deletecategory', 'local_sentientia_skills'),
        getString('confirmdeletecategory', 'local_sentientia_skills', name),
        getString('delete', 'core'),
        getString('categorydeleted', 'local_sentientia_skills'),
    ]);
    Notification.deleteCancelPromise(title, message, label, returnFocus).then(() => {
        Ajax.call([{methodname: 'local_sentientia_skills_delete_category',
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
        formClass: 'local_sentientia_skills\\form\\edit_skill_level_dynamic_form',
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
        formClass: 'local_sentientia_skills\\form\\edit_designation_skill_dynamic_form',
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
            methodname: 'local_sentientia_skills_delete_designation_skill',
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
        methodname: 'local_sentientia_skills_copy_designation',
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

// ─── Phase A.2 (2026-05-08) — course-skill mapping admin ─────────────────

const wireCourseSearch = () => {
    const search = document.getElementById('ap-skill-course-search');
    const list = document.getElementById('ap-skill-course-list');
    if (!search || !list) return;
    let timer;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const q = search.value.trim();
            Ajax.call([{
                methodname: 'local_sentientia_skills_search_courses',
                args: {q: q, limit: 50},
            }])[0].then((res) => {
                list.innerHTML = '';
                if (!res.rows.length) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item text-muted';
                    li.textContent = 'No courses match "' + q + '".';
                    list.appendChild(li);
                    return null;
                }
                for (const row of res.rows) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    const a = document.createElement('a');
                    a.href = '?courseid=' + row.id;
                    a.className = 'text-decoration-none flex-grow-1';
                    a.innerHTML = '<div class="fw-semibold"></div><div class="small text-muted"></div>';
                    a.querySelector('div.fw-semibold').textContent = row.fullname;
                    a.querySelector('div.small').textContent = row.shortname;
                    const span = document.createElement('span');
                    span.className = 'badge bg-secondary rounded-pill';
                    span.title = 'Skills mapped';
                    span.textContent = String(row.mapped_count);
                    li.appendChild(a);
                    li.appendChild(span);
                    list.appendChild(li);
                }
                return null;
            }).catch(Notification.exception);
        }, 250);
    });
};

const wireAddCourseMappingForm = () => {
    const form = document.getElementById('ap-skill-add-mapping');
    if (!form) return;
    const skillSel = document.getElementById('ap-skill-mapping-skill');
    const levelSel = document.getElementById('ap-skill-mapping-level');
    if (!skillSel || !levelSel) return;
    // Cap "level" options to selected skill's max_level.
    skillSel.addEventListener('change', () => {
        const opt = skillSel.options[skillSel.selectedIndex];
        const max = parseInt(opt && opt.dataset.max ? opt.dataset.max : '5', 10);
        for (const o of levelSel.options) {
            const v = parseInt(o.value, 10);
            o.disabled = v > max;
        }
        if (parseInt(levelSel.value, 10) > max) levelSel.value = String(max);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const trigger = form.querySelector('[data-action="add-course-skill"]');
        if (!trigger) return;
        const courseid = parseInt(trigger.dataset.courseid || '0', 10);
        const skillid = parseInt(skillSel.value || '0', 10);
        const teaches_level = parseInt(levelSel.value || '0', 10);
        if (!courseid || !skillid || !teaches_level) {
            Notification.addNotification({
                message: 'Please pick a skill and a level.',
                type: 'warning',
            });
            return;
        }
        Ajax.call([{
            methodname: 'local_sentientia_skills_save_course_skill',
            args: {courseid, skillid, teaches_level},
        }])[0].then(() => {
            Notification.addNotification({
                message: 'Mapping saved.',
                type: 'success',
            });
            window.location.reload();
            return null;
        }).catch(Notification.exception);
    });
};

const confirmDeleteCourseSkill = (rowid, skillname, returnFocus) => {
    Notification.deleteCancelPromise(
        'Remove skill mapping',
        `Remove "${skillname}" from this course's skill mappings?`,
        'Remove', returnFocus).then(() => {
        Ajax.call([{
            methodname: 'local_sentientia_skills_delete_course_skill',
            args: {id: rowid},
        }])[0].then(() => {
            Notification.addNotification({message: 'Mapping removed.', type: 'success'});
            window.location.reload();
            return null;
        }).catch(Notification.exception);
        return true;
    }, () => null);
};

// ─── P1 #26 (2026-05-20) — learner self-rate dialog ───────────────────────
//
// The view.php template renders an inert, hidden source block (id airpay-self-rate-modal) that holds the
// localised title, the save label and the level <select>. The first click on "Self-rate" builds a
// core/modal_save_cancel dialog from it and MOVES the select block into the dialog (so ids stay unique);
// later clicks show the same dialog again. Saving POSTs the level through
// `local_sentientia_skills_self_rate_skill` and reloads on success. Capability check is duplicated
// server-side in the WS — this code is just UX glue.
//
// Moodle 5.3 compat FX-20 (2026-10-08): the dialog used window.bootstrap.Modal, which exists in no tree,
// and fell back to the theme's Bootstrap 4 jQuery plugin. core/modal_save_cancel exists unchanged on
// Moodle 5.1, 5.2 and 5.3 and needs neither.

/**
 * Swap the contents of a button safely (no innerHTML, no XSS surface).
 * Replaces all children with an <i> icon (if iconClass given) + a text node.
 */
const setButtonContent = (btn, iconClass, text) => {
    while (btn.firstChild) btn.removeChild(btn.firstChild);
    if (iconClass) {
        const i = document.createElement('i');
        i.className = iconClass;
        i.setAttribute('aria-hidden', 'true');
        btn.appendChild(i);
        btn.appendChild(document.createTextNode(' '));
    }
    btn.appendChild(document.createTextNode(text));
};

let selfRateModal = null;
let selfRateOpening = false;

const submitSelfRate = async (source, modal) => {
    const skillid = parseInt(source.dataset.skillid || '0', 10);
    const levelSel = modal.getBody().find('#airpay-self-rate-level')[0];
    const level = parseInt(levelSel ? levelSel.value : '0', 10);
    if (!skillid || !level) {
        const msg = await getString('self_rate_pick_level', 'local_sentientia_skills');
        Notification.addNotification({message: msg, type: 'warning'});
        return;
    }
    const submitBtn = modal.getFooter().find('[data-action="save"]')[0];
    const submitLabel = submitBtn ? submitBtn.textContent.trim() : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        setButtonContent(submitBtn, 'fa fa-spinner fa-spin fa-fw', 'Saving...');
    }
    try {
        await Ajax.call([{
            methodname: 'local_sentientia_skills_self_rate_skill',
            args: {skillid: skillid, level: level, userid: 0},
        }])[0];
        const success = await getString('self_rate_saved', 'local_sentientia_skills');
        Notification.addNotification({message: success, type: 'success'});
        // Reload so the panel re-renders with the new current_level + the
        // Learners tab count updates if this was a first-time grant.
        setTimeout(() => window.location.reload(), 500);
    } catch (err) {
        Notification.exception(err);
        if (submitBtn) {
            submitBtn.disabled = false;
            setButtonContent(submitBtn, 'fa fa-check fa-fw', submitLabel || 'Save');
        }
    }
};

const openSelfRateModal = async () => {
    if (selfRateModal) {
        selfRateModal.show();
        return;
    }
    const source = document.getElementById('airpay-self-rate-modal');
    const bodyEl = source ? source.querySelector('[data-region="self-rate-body"]') : null;
    if (!source || !bodyEl || selfRateOpening) {
        return;
    }
    selfRateOpening = true;
    const titleEl = source.querySelector('.modal-title');
    const labelEl = source.querySelector('[data-region="self-rate-save-label"]');
    let modal;
    try {
        modal = await ModalSaveCancel.create({
            // innerHTML, not textContent: the template already HTML-escaped the skill name, and the dialog
            // title is set as HTML.
            title: titleEl ? titleEl.innerHTML.trim() : '',
        });
    } catch (err) {
        // If the dialog cannot be built the click must not fail silently (nor leave an unhandled rejection).
        Notification.exception(err);
        return;
    } finally {
        selfRateOpening = false;
    }
    modal.setSaveButtonText(labelEl ? labelEl.textContent.trim() : 'Save');
    modal.getBody().append(bodyEl);
    modal.getRoot().on(ModalEvents.save, (event) => {
        // Keep the dialog open while the request runs; the page reload closes it on success.
        event.preventDefault();
        submitSelfRate(source, modal);
    });
    selfRateModal = modal;
    modal.show();
};

const handleClick = (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    // Pre-existing data attribute names from the templates: data-skillid
    // for skills (list_skills.php), data-categoryid for categories
    // (manage.mustache). Older code read dataset.id and silently passed 0
    // to every edit/delete handler — fixed 2026-05-08.
    const skillid    = parseInt(trigger.dataset.skillid    || '0', 10);
    const categoryid = parseInt(trigger.dataset.categoryid || '0', 10);
    const id         = parseInt(trigger.dataset.id         || '0', 10);  // legacy
    const name       = trigger.dataset.name || 'this item';
    const level      = parseInt(trigger.dataset.level || '0', 10);
    const designation = trigger.dataset.designation || '';
    const rowid      = parseInt(trigger.dataset.rowid || '0', 10);
    switch (action) {
        case 'create-skill':    event.preventDefault(); openSkillForm(0, trigger); break;
        case 'edit-skill':      event.preventDefault(); openSkillForm(skillid || id, trigger); break;
        case 'delete-skill':    event.preventDefault(); confirmDeleteSkill(skillid || id, name, trigger); break;
        case 'create-category': event.preventDefault(); openCategoryForm(0, trigger); break;
        case 'edit-category':   event.preventDefault(); openCategoryForm(categoryid || id, trigger); break;
        case 'delete-category': event.preventDefault(); confirmDeleteCategory(categoryid || id, name, trigger); break;
        // Phase A
        case 'edit-level':                event.preventDefault(); openLevelForm(skillid, level, trigger); break;
        case 'add-designation-skill':     event.preventDefault(); openDesignationSkillForm(designation, 0, trigger); break;
        case 'edit-designation-skill':    event.preventDefault(); openDesignationSkillForm(designation, rowid, trigger); break;
        case 'delete-designation-skill':  event.preventDefault(); confirmDeleteDesignationSkill(rowid, name || trigger.dataset.skill || 'this skill', trigger); break;
        case 'copy-designation':          event.preventDefault(); promptCopyDesignation(trigger.dataset.from || '', trigger); break;
        // Phase A.2 — course-skill mapping
        case 'delete-course-skill':       event.preventDefault(); confirmDeleteCourseSkill(rowid, name || trigger.dataset.skill || 'this mapping', trigger); break;
        // P1 #26 (2026-05-20) — learner self-rate
        case 'open-self-rate':
            event.preventDefault();
            openSelfRateModal();
            break;
    }
};

export const init = (config = {}) => {
    // Page-specific wiring.
    if (config && config.page === 'designation_matrix') {
        wireDesignationSelector();
    }
    if (config && config.page === 'course_mapping') {
        wireCourseSearch();
        wireAddCourseMappingForm();
    }
    // Click delegation works on body for our two new admin pages too.
    const root = document.querySelector('[data-region="airpay-skills"]') || document.body;
    if (root.dataset.airpaySkillsInit === '1') return;
    root.dataset.airpaySkillsInit = '1';
    root.addEventListener('click', handleClick);
};
