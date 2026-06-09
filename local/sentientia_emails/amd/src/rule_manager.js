/**
 * Rule Manager — toggle rules, inline editing.
 *
 * @module local_sentientia_emails/rule_manager
 * @copyright 2026 Airpay Payment Services
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    return {
        init: function() {
            this._bindEvents();
        },

        _bindEvents: function() {
            // Toggle rule enabled/disabled via AJAX.
            $(document).on('change', '[data-rule-toggle]', function() {
                var ruleId = $(this).data('rule-toggle');
                var enabled = $(this).is(':checked') ? 1 : 0;
                var row = $(this).closest('tr');

                var promises = Ajax.call([{
                    methodname: 'local_sentientia_emails_toggle_rule',
                    args: {ruleid: ruleId, enabled: enabled}
                }]);

                promises[0].done(function() {
                    row.css('opacity', enabled ? 1 : 0.6);
                    Notification.addNotification({
                        message: 'Rule ' + (enabled ? 'enabled' : 'disabled') + '.',
                        type: 'success'
                    });
                }).fail(Notification.exception);
            });
        }
    };
});
