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
 * A learner with no stored row (data-hasmark="0") shows as Absent, but that is only what
 * an empty row looks like: it is sent only when the trainer touched it (an explicit Absent
 * included), so a learner nobody touched keeps no row and can still scan the QR code. A
 * learner who already has a row is sent only when their status differs from the stored one.
 *
 * @param {HTMLElement} row
 * @returns {Boolean}
 */
const isSetByTrainer = (row) => {
    if (row.dataset.touched !== '1') { return false; }
    if (row.dataset.hasmark !== '1') { return true; }
    return rowStatus(row) !== parseInt(row.dataset.original, 10);
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

const saveAttendance = async (sessionid, root) => {
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
        setDirty(root, false);
        return;
    }

    try {
        // The time this grid was loaded: a learner who scanned the QR code after it keeps
        // that mark instead of being saved back to Absent (the server decides, see
        // session_manager::bulk_mark_attendance()).
        const loadedat = parseInt(root.dataset.loadedat, 10) || 0;
        const response = await Ajax.call([{
            methodname: 'local_sentientia_classroom_bulk_mark_attendance',
            args: {sessionid: sessionid, marks: marks, loadedat: loadedat},
        }])[0];
        // Every row that was sent now matches what is stored...
        Object.keys(sentRows).forEach((userid) => {
            markRowStored(sentRows[userid], rowStatus(sentRows[userid]));
        });
        // ...except the learners whose newer mark was kept: show the mark that stands.
        (response.keptmarks || []).forEach((kept) => {
            const radio = root.querySelector('tr[data-userid="' + kept.userid + '"] input[type=radio][data-status="'
                + kept.status + '"]');
            if (radio) {
                radio.checked = true;
                markRowStored(radio.closest('tr[data-userid]'), kept.status);
            }
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
        setDirty(root, false);
    } catch (e) {
        Notification.exception(e);
    }
};

const handleClick = (sessionid, root) => (event) => {
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

    // Warn before nav-away if dirty.
    window.addEventListener('beforeunload', (e) => {
        if (root.dataset.dirty === '1') {
            e.preventDefault();
            e.returnValue = '';
        }
    });
};
