// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Attendance grid: collects radio changes, updates live counts,
 * supports "Mark all present" and saves all in one bulk WS call.
 *
 * @module     local_sentientia_classroom/attendance
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

const STATUS_KEYS = {0: 'absent', 1: 'present', 2: 'late', 3: 'excused'};

const recountFromGrid = (root) => {
    const counts = {present: 0, late: 0, excused: 0, absent: 0};
    root.querySelectorAll('[data-region="attendance-grid"] tr[data-userid]').forEach((row) => {
        const checked = row.querySelector('input[type=radio]:checked');
        const s = checked ? parseInt(checked.dataset.status, 10) : 0;
        const key = STATUS_KEYS[s] || 'absent';
        counts[key]++;
    });
    Object.keys(counts).forEach((k) => {
        const el = root.querySelector('[data-counter="' + k + '"]');
        if (el) { el.textContent = counts[k]; }
    });
};

const setDirty = (root, dirty) => {
    const hint = root.querySelector('[data-region="dirty-hint"]');
    if (hint) { hint.hidden = !dirty; }
    root.dataset.dirty = dirty ? '1' : '0';
};

const handleRadioChange = (root) => (event) => {
    const t = event.target;
    if (!t.matches('input[type=radio][data-userid]')) { return; }
    // The trainer acted on this learner: their row is sent on the next Save.
    const row = t.closest('tr[data-userid]');
    if (row) { row.dataset.touched = '1'; }
    setDirty(root, true);
    recountFromGrid(root);
};

const markAllPresent = (root) => {
    root.querySelectorAll('[data-region="attendance-grid"] tr[data-userid]').forEach((row) => {
        const presentRadio = row.querySelector('input[type=radio][data-status="1"]');
        if (presentRadio && !presentRadio.disabled) {
            presentRadio.checked = true;
            row.dataset.touched = '1';
        }
    });
    setDirty(root, true);
    recountFromGrid(root);
};

/**
 * The status the radios of a grid row currently show.
 *
 * @param {HTMLElement} row
 * @returns {Number}
 */
const rowStatus = (row) => {
    const checked = row.querySelector('input[type=radio]:checked');
    return checked ? parseInt(checked.dataset.status, 10) : 0;
};

/**
 * Has the trainer set this learner's mark since the grid was loaded (or last saved)?
 *
 * The rule reads the radios, not just whether a click was seen, so a mark the page did not see
 * being made (a radio the browser restored on a reload) is still saved:
 *  - A learner who has a stored row (data-hasmark="1") is sent whenever the radio differs from
 *    the stored status (data-original).
 *  - A learner with no stored row (data-hasmark="0") shows as Absent, but that is only what an
 *    empty row looks like. Any other status is the trainer's, touched or not. Absent is sent
 *    only when the trainer chose it (click or change), so a learner nobody touched keeps no
 *    row and can still scan the QR code.
 *
 * @param {HTMLElement} row
 * @returns {Boolean}
 */
const isSetByTrainer = (row) => {
    const status = rowStatus(row);
    if (row.dataset.hasmark === '1') {
        return status !== parseInt(row.dataset.original, 10);
    }
    return status !== 0 || row.dataset.touched === '1';
};

/**
 * Record that a row now matches what is stored, so the next Save does not resend it.
 *
 * @param {HTMLElement} row
 * @param {Number} status the status that is stored for the learner
 */
const markRowStored = (row, status) => {
    row.dataset.hasmark = '1';
    row.dataset.original = String(status);
    row.dataset.touched = '0';
};

/**
 * Show the mark that is stored for a learner: check that status and record it as the row's
 * stored state, so the next Save does not resend it.
 *
 * @param {HTMLElement} root
 * @param {Number} userid
 * @param {Number} status
 */
const showStoredMark = (root, userid, status) => {
    const radio = root.querySelector('tr[data-userid="' + userid + '"] input[type=radio][data-status="'
        + status + '"]');
    if (radio) {
        radio.checked = true;
        markRowStored(radio.closest('tr[data-userid]'), status);
    }
};

/**
 * Does any row still differ from what is stored for its learner?
 *
 * The page is clean only when none does. This reads the radios against the stored state
 * (isSetByTrainer()), not a flag set by a click, so a row changed while a Save was in flight
 * keeps the page dirty even though the Save that returned did not carry it.
 *
 * @param {HTMLElement} root
 * @returns {Boolean}
 */
const hasUnsavedRows = (root) => {
    return Array.from(root.querySelectorAll('[data-region="attendance-grid"] tr[data-userid]'))
        .some((row) => isSetByTrainer(row));
};

/**
 * Lock the grid while a Save is in flight: Save and "Mark all present" are disabled, and so are
 * the radios, so the trainer cannot change a row between the moment its status was read for the
 * request and the moment the answer arrives. A radio that was already disabled (attendance not
 * allowed) is left alone, and stays disabled when the lock is lifted.
 *
 * @param {HTMLElement} root
 * @param {Boolean} saving
 */
const setSaving = (root, saving) => {
    root.dataset.saving = saving ? '1' : '0';
    root.setAttribute('aria-busy', saving ? 'true' : 'false');
    root.querySelectorAll('[data-action="save-attendance"], [data-action="mark-all-present"]').forEach((button) => {
        button.disabled = saving;
    });
    root.querySelectorAll('[data-region="attendance-grid"] input[type=radio][data-userid]').forEach((radio) => {
        if (saving) {
            if (!radio.disabled) {
                radio.disabled = true;
                radio.dataset.savelocked = '1';
            }
        } else if (radio.dataset.savelocked === '1') {
            radio.disabled = false;
            delete radio.dataset.savelocked;
        }
    });
};

const saveAttendance = async (sessionid, root) => {
    if (root.dataset.saving === '1') {
        // A Save is already on its way; its answer decides what the rows show.
        return;
    }
    const marks = [];
    const sentRows = {};
    root.querySelectorAll('[data-region="attendance-grid"] tr[data-userid]').forEach((row) => {
        const userid = parseInt(row.dataset.userid, 10);
        if (userid > 0 && isSetByTrainer(row)) {
            marks.push({userid: userid, status: rowStatus(row), notes: ''});
            sentRows[userid] = row;
        }
    });

    if (marks.length === 0) {
        // Nothing the trainer changed: nothing to write (and no row is created for a
        // learner they did not touch).
        const nothing = await getString('attendance_nothing_to_save', 'local_sentientia_classroom');
        Notification.addNotification({message: nothing, type: 'info'});
        setDirty(root, hasUnsavedRows(root));
        return;
    }

    setSaving(root, true);
    try {
        // The time this grid was loaded: a learner who scanned the QR code after it keeps
        // that mark instead of being saved back to Absent (the server decides, see
        // session_manager::bulk_mark_attendance()).
        const loadedat = parseInt(root.dataset.loadedat, 10) || 0;
        const response = await Ajax.call([{
            methodname: 'local_sentientia_classroom_bulk_mark_attendance',
            args: {sessionid: sessionid, marks: marks, loadedat: loadedat},
        }])[0];
        // Every row that was sent now matches what is stored. What is stored is what was SENT
        // (the status in the payload), never what the radio shows when the answer arrives: a
        // row that changed in the meantime was not saved by this call and must stay different
        // from its stored value, so the next Save carries it.
        marks.forEach((mark) => {
            markRowStored(sentRows[mark.userid], mark.status);
        });
        // ...except the learners whose newer mark was kept: show the mark that stands.
        (response.keptmarks || []).forEach((kept) => {
            showStoredMark(root, kept.userid, kept.status);
        });
        // Every mark somebody else made since the grid was loaded, including a learner the
        // trainer never touched (so was never sent) who scanned the QR code meanwhile. It must
        // be on screen BEFORE the load time moves forward: only then is "the trainer has seen
        // it" true, and a later Save may replace it with a deliberate correction.
        (response.newermarks || []).forEach((newer) => {
            showStoredMark(root, newer.userid, newer.status);
        });
        // The server's time is the grid's new load time: the trainer has now seen every mark
        // made before it, so a later Save must not keep one of those against a correction.
        if (response.savedat > 0) {
            root.dataset.loadedat = String(response.savedat);
        }
        recountFromGrid(root);
        Notification.addNotification({
            message: response.message || 'Attendance saved.',
            type: response.kept > 0 ? 'warning' : 'success',
        });
        // Clean only if every row now matches what is stored. A row that still differs (changed
        // while the call was in flight, however that came about) keeps the hint and the
        // leave-page warning.
        setDirty(root, hasUnsavedRows(root));
    } catch (e) {
        Notification.exception(e);
    } finally {
        setSaving(root, false);
    }
};

const handleClick = (sessionid, root) => (event) => {
    // While a Save is in flight the grid is locked (setSaving()): a click that still gets
    // through (a child of a disabled button, say) changes nothing.
    if (root.dataset.saving === '1') {
        if (event.target.closest('[data-action]')) { event.preventDefault(); }
        return;
    }
    // Choosing a status the learner already shows (Absent on an unmarked learner, say) fires
    // no "change" event, but it is still the trainer setting that mark on purpose: an explicit
    // Absent is written and stands against a later scan.
    if (event.target.matches('input[type=radio][data-userid]') && !event.target.disabled) {
        const row = event.target.closest('tr[data-userid]');
        if (row) { row.dataset.touched = '1'; }
        setDirty(root, true);
    }
    const trigger = event.target.closest('[data-action]');
    if (!trigger) { return; }
    const action = trigger.dataset.action;
    if (action === 'mark-all-present') {
        event.preventDefault();
        markAllPresent(root);
    } else if (action === 'save-attendance') {
        event.preventDefault();
        saveAttendance(sessionid, root);
    }
};

export const init = (sessionid) => {
    const root = document.querySelector('[data-region="airpay-attendance"]');
    if (!root) { return; }
    if (root.dataset.airpayAttendanceInit === '1') { return; }
    root.dataset.airpayAttendanceInit = '1';

    root.addEventListener('change', handleRadioChange(root));
    root.addEventListener('click', handleClick(sessionid, root));
    // The grid sits in a form only for its autocomplete="off": never submit it.
    root.addEventListener('submit', (event) => { event.preventDefault(); });

    // Warn before nav-away if dirty.
    window.addEventListener('beforeunload', (e) => {
        if (root.dataset.dirty === '1') {
            e.preventDefault();
            e.returnValue = '';
        }
    });
};
