// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * P1 batch (2026-05-16) — tenant-scoped supervisor autocomplete AMD module.
 *
 * Drop-in replacement for `core_user/form_user_selector` on the edit-user
 * form. Calls `local_sentientia_users_search_supervisors` instead of the
 * untyped Moodle user search, so a Public-tenant admin can't pick a manager
 * from the Airpay tenant.
 *
 * Moodle's autocomplete element requires a module that exports two methods:
 *   - `transport(selector, query, success, failure)` — fetch results
 *   - `processResults(selector, results)` — convert to {value, label} pairs
 *
 * The subject userid (the user being edited) is read from a hidden input
 * named `id` in the same form so we can intersect tenant scope with the
 * subject's tenant too — see search_supervisors::execute() for details.
 *
 * @module local_sentientia_users/supervisor_selector
 */

define(['core/ajax'], function(Ajax) {
    'use strict';

    /**
     * Read the subject user id from the same form, if present.
     * Returns 0 for the "create new user" case.
     *
     * @param {string} selector  CSS selector for the autocomplete <select>
     */
    function findSubjectUserid(selector) {
        var el = document.querySelector(selector);
        if (!el) {
            return 0;
        }
        // Find the enclosing form.
        var form = el.closest('form');
        if (!form) {
            return 0;
        }
        var idInput = form.querySelector('input[name="id"]');
        if (!idInput) {
            return 0;
        }
        return parseInt(idInput.value, 10) || 0;
    }

    return {
        /**
         * Fetch matching users for the given typed-in query.
         */
        transport: function(selector, query, success, failure) {
            var subjectUserid = findSubjectUserid(selector);
            var promise = Ajax.call([{
                methodname: 'local_sentientia_users_search_supervisors',
                args: {
                    query: query,
                    subject_userid: subjectUserid
                }
            }])[0];
            promise.then(success).catch(failure);
        },

        /**
         * Convert the WS response into the {value, label} format that
         * Moodle's autocomplete expects.
         */
        processResults: function(selector, results) {
            if (!results || !results.rows) {
                return [];
            }
            return results.rows.map(function(row) {
                var label = row.label;
                if (row.email) {
                    label += ' • ' + row.email;
                }
                if (row.empid) {
                    label += ' [' + row.empid + ']';
                }
                return {
                    value: row.id,
                    label: label
                };
            });
        }
    };
});
