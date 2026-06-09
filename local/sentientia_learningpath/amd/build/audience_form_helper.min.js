// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * P1 #11 (2026-05-16) — wire the bulk-enrol-by-audience modal form:
 *   1. Populate filter dropdowns from `local_sentientia_users_list_filter_options`
 *   2. On any filter change, call `local_sentientia_learningpath_preview_audience`
 *      and render count + 5-name sample in the preview pane
 *
 * Called from path_actions.js right after the modal opens.
 *
 * @module local_sentientia_learningpath/audience_form_helper
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Populate the four filter dropdowns + wire change handlers. The
     * modal root is passed in so we don't trip on other page elements.
     *
     * @param {HTMLElement} root  The modal body element (or any ancestor)
     */
    function init(root) {
        if (!root) {
            return;
        }
        var selects = root.querySelectorAll(
            'select[data-airpay-audience-filter]');
        if (!selects.length) {
            return;
        }

        // ── Populate the open_* dropdowns from the existing WS ────────
        Ajax.call([{
            methodname: 'local_sentientia_users_list_filter_options',
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

        // ── Live preview on any filter change ─────────────────────────
        var previewTimer = null;
        selects.forEach(function(sel) {
            sel.addEventListener('change', function() {
                if (previewTimer) {
                    clearTimeout(previewTimer);
                }
                // Debounce briefly so rapid clicks don't flood the WS.
                previewTimer = setTimeout(function() {
                    refreshPreview(root);
                }, 200);
            });
        });
    }

    /**
     * Read filter values from the modal and call the preview WS,
     * rendering the count + sample.
     */
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
            methodname: 'local_sentientia_learningpath_preview_audience',
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
