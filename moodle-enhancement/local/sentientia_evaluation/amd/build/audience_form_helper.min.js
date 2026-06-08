// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * P1 #40 (2026-05-20) — wire the bulk-assign-by-audience modal in the
 * evaluation admin UI. Sibling of `local_airpay_classroom/audience_form_helper`
 * (P1 #13) and `local_airpay_programs/audience_form_helper` (P1 #14) —
 * same shape, different WS endpoint.
 *
 * Strategy:
 *   1. Populate filter dropdowns from
 *      `local_airpay_users_list_filter_options` (the shared W2 #1 WS).
 *   2. Debounced (200 ms) preview via
 *      `local_airpay_evaluation_preview_audience` (P1 #39's WS).
 *
 * @module local_airpay_evaluation/audience_form_helper
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    function init(root) {
        if (!root) {
            return;
        }
        var selects = root.querySelectorAll(
            'select[data-airpay-audience-filter]');
        if (!selects.length) {
            return;
        }

        Ajax.call([{
            methodname: 'local_airpay_users_list_filter_options',
            args: {fields: ''}
        }])[0].then(function(response) {
            selects.forEach(function(sel) {
                var key = sel.getAttribute('data-airpay-audience-filter');
                if (!response[key] || !response[key].length) {
                    return;
                }
                response[key].forEach(function(val) {
                    if (val === '' || val === null) { return; }
                    var opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    sel.appendChild(opt);
                });
            });
            return null;
        }).catch(Notification.exception);

        var previewTimer = null;
        selects.forEach(function(sel) {
            sel.addEventListener('change', function() {
                if (previewTimer) {
                    clearTimeout(previewTimer);
                }
                previewTimer = setTimeout(function() {
                    refreshPreview(root);
                }, 200);
            });
        });
    }

    function refreshPreview(root) {
        var filters = {};
        root.querySelectorAll('select[data-airpay-audience-filter]')
            .forEach(function(sel) {
                var key = sel.getAttribute('data-airpay-audience-filter');
                var val = sel.value;
                if (val && val !== '0' && val !== '') {
                    filters[key] = key === 'cohortid' ? parseInt(val, 10) : val;
                }
            });

        var countEl = root.querySelector('[data-airpay-audience-count]');
        var sampleEl = root.querySelector('[data-airpay-audience-sample]');

        if (Object.keys(filters).length === 0) {
            if (countEl)  { countEl.textContent = '0'; }
            if (sampleEl) { sampleEl.textContent = ''; }
            return;
        }

        Ajax.call([{
            methodname: 'local_airpay_evaluation_preview_audience',
            args: {filters: JSON.stringify(filters)}
        }])[0].then(function(result) {
            if (countEl) {
                countEl.textContent = String(result.count);
                if (result.count === 0) {
                    countEl.className = 'text-warning';
                } else if (result.count >= result.capped_at) {
                    countEl.className = 'text-danger';
                } else {
                    countEl.className = 'text-success';
                }
            }
            if (sampleEl) {
                if (result.sample && result.sample.length) {
                    var names = result.sample
                        .slice(0, 5)
                        .map(function(s) { return s.fullname; })
                        .join(', ');
                    if (result.count > 5) {
                        names += ' + ' + (result.count - 5) + ' more';
                    }
                    sampleEl.textContent = 'e.g. ' + names;
                } else {
                    sampleEl.textContent = '';
                }
            }
            return null;
        }).catch(Notification.exception);
    }

    return {
        init: init
    };
});
