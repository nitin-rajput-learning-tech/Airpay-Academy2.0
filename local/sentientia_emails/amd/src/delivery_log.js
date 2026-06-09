/**
 * Delivery Log — filter controls and CSV export.
 *
 * @module local_sentientia_emails/delivery_log
 * @copyright 2026 Airpay Payment Services
 */
define(['jquery'], function($) {

    return {
        init: function(baseUrl) {
            this.baseUrl = baseUrl;
            this._bindEvents();
        },

        _bindEvents: function() {
            var self = this;

            // Filter form submission.
            $('#ap-log-filter-form').on('submit', function(e) {
                e.preventDefault();
                var status = $('#ap-log-filter-status').val();
                var channel = $('#ap-log-filter-channel').val();
                var url = self.baseUrl + '&tab=logs';
                if (status) { url += '&status=' + status; }
                if (channel) { url += '&channel=' + channel; }
                window.location = url;
            });

            // CSV export button.
            $('#ap-log-export').on('click', function(e) {
                e.preventDefault();
                window.location = self.baseUrl + '&tab=logs&action=export&sesskey=' + M.cfg.sesskey;
            });
        }
    };
});
