// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * P1 batch (2026-05-16) — populate the user chip-filter dropdowns
 * (designation, location, employmenttype, hrmsrole, region, grade) on
 * page load via the `local_airpay_users_list_filter_options` WS.
 *
 * Each select that has `data-airpay-users-filter="<key>"` and a matching
 * key in the WS response gets its <option> list filled in. The first
 * <option> (already in the markup with value="") is preserved as the
 * "All <foo>" placeholder.
 *
 * Existing change-event wiring in `templates/manage.mustache` keeps doing
 * its job — this module just fills the dropdowns; it doesn't intercept
 * change events.
 *
 * @module local_airpay_users/chip_filters
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    var init = function() {
        var selects = document.querySelectorAll(
            'select[data-airpay-users-filter]');
        if (!selects.length) {
            return;
        }

        Ajax.call([{
            methodname: 'local_airpay_users_list_filter_options',
            args: {fields: ''}
        }])[0].then(function(response) {
            // response = {designation: [...], location: [...], ...}
            selects.forEach(function(select) {
                var key = select.getAttribute('data-airpay-users-filter');
                if (!response[key] || !response[key].length) {
                    return;
                }
                // Preserve the empty placeholder; append all distinct values.
                var existingValue = select.value;
                response[key].forEach(function(val) {
                    if (val === '' || val === null) { return; }
                    var opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    select.appendChild(opt);
                });
                // Restore previously-selected value if it's still in the list.
                if (existingValue) {
                    select.value = existingValue;
                }
            });
            return null;
        }).catch(Notification.exception);
    };

    return {init: init};
});
