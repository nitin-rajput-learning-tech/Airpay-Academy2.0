// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * P1 #40 (2026-05-20) — wire the "Bulk-assign by audience" button on the
 * non-respondents admin page. Click → ModalForm → on submit, show toast
 * + reload (so the table refreshes with the newly-assigned learners).
 *
 * Lazy-loads `audience_form_helper` after the modal renders so the
 * filter dropdowns populate from local_airpay_users_list_filter_options
 * and the live preview wires up.
 *
 * @module local_sentientia_evaluation/non_respondents_actions
 */

define([
    'core_form/modalform',
    'core/str',
    'core/notification'
], function(ModalForm, Str, Notification) {
    'use strict';

    function openBulkAssignModal(evaluationid, returnFocus) {
        Str.get_string('bulk_assign_modal_title', 'local_sentientia_evaluation')
            .then(function(title) {
                var modalForm = new ModalForm({
                    formClass: 'local_sentientia_evaluation\\form\\bulk_assign_audience_form',
                    args: {evaluationid: evaluationid},
                    modalConfig: {title: title, large: true},
                    returnFocus: returnFocus
                });
                modalForm.addEventListener(modalForm.events.LOADED, function() {
                    var modalBody = document.querySelector('.modal.show .modal-body')
                        || document.querySelector('.modal-body');
                    if (modalBody) {
                        require(['local_sentientia_evaluation/audience_form_helper'],
                            function(helper) { helper.init(modalBody); });
                    }
                });
                modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, function(event) {
                    var detail = (event && event.detail) || {};
                    var msg = detail.message || 'Assigned.';
                    Notification.addNotification({message: msg, type: 'success'});
                    setTimeout(function() { window.location.reload(); }, 600);
                });
                modalForm.show();
                return null;
            }).catch(Notification.exception);
    }

    function handleClick(event) {
        var trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        if (trigger.dataset.action !== 'bulk-assign-audience') return;
        event.preventDefault();
        var evaluationid = parseInt(trigger.dataset.evaluationid || '0', 10);
        if (!evaluationid) return;
        openBulkAssignModal(evaluationid, trigger);
    }

    return {
        init: function() {
            var root = document.querySelector('[data-region="airpay-eval-non-respondents"]')
                || document.body;
            if (root.dataset.airpayNonRespondentsInit === '1') return;
            root.dataset.airpayNonRespondentsInit = '1';
            root.addEventListener('click', handleClick);
        }
    };
});
