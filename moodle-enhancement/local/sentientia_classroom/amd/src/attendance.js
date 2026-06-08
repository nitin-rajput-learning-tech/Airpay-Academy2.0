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
    setDirty(root, true);
    recountFromGrid(root);
};

const markAllPresent = (root) => {
    root.querySelectorAll('[data-region="attendance-grid"] tr[data-userid]').forEach((row) => {
        const presentRadio = row.querySelector('input[type=radio][data-status="1"]');
        if (presentRadio && !presentRadio.disabled) {
            presentRadio.checked = true;
        }
    });
    setDirty(root, true);
    recountFromGrid(root);
};

const saveAttendance = async (sessionid, root) => {
    const marks = [];
    root.querySelectorAll('[data-region="attendance-grid"] tr[data-userid]').forEach((row) => {
        const userid = parseInt(row.dataset.userid, 10);
        const checked = row.querySelector('input[type=radio]:checked');
        const status = checked ? parseInt(checked.dataset.status, 10) : 0;
        if (userid > 0) {
            marks.push({userid: userid, status: status, notes: ''});
        }
    });

    if (marks.length === 0) {
        const empty = await getString('no_attendance_yet', 'local_sentientia_classroom');
        Notification.addNotification({message: empty, type: 'warning'});
        return;
    }

    try {
        const response = await Ajax.call([{
            methodname: 'local_sentientia_classroom_bulk_mark_attendance',
            args: {sessionid: sessionid, marks: marks},
        }])[0];
        Notification.addNotification({
            message: response.message || 'Attendance saved.',
            type: 'success',
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
