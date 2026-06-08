/**
 * Template Editor — WYSIWYG editing with live preview.
 *
 * Handles:
 * - Loading template content into editor
 * - Live preview rendering via AJAX
 * - Placeholder insertion toolbar
 * - Save/revert actions
 *
 * @module local_sentientia_emails/template_editor
 * @copyright 2026 Airpay Payment Services
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var Editor = {
        previewTimer: null,
        currentTemplate: null,
        currentTenant: 0,
        isDirty: false,

        /**
         * Initialize the template editor.
         * @param {string} templateKey - e.g. 'compliance/deadline_warning'
         * @param {number} tenantId - costcenter ID
         */
        init: function(templateKey, tenantId) {
            this.currentTemplate = templateKey;
            this.currentTenant = tenantId || 0;

            this._bindEvents();
            if (templateKey) {
                this.loadTemplate(templateKey);
            }
        },

        /**
         * Bind UI event handlers.
         */
        _bindEvents: function() {
            var self = this;

            // Subject field change triggers preview.
            $('#ap-email-subject').on('input', function() {
                self.isDirty = true;
                self._debouncePreview();
            });

            // Body editor change triggers preview.
            $('#ap-email-body').on('input', function() {
                self.isDirty = true;
                self._debouncePreview();
            });

            // Save button.
            $('#ap-email-save').on('click', function(e) {
                e.preventDefault();
                self.saveTemplate();
            });

            // Revert button.
            $('#ap-email-revert').on('click', function(e) {
                e.preventDefault();
                if (confirm('Revert to the default Mustache template? Any DB overrides will be deleted.')) {
                    self.revertTemplate();
                }
            });

            // Placeholder insertion buttons.
            $(document).on('click', '.ap-placeholder-btn', function() {
                var placeholder = $(this).data('placeholder');
                self.insertPlaceholder(placeholder);
            });

            // Warn before leaving with unsaved changes.
            $(window).on('beforeunload', function() {
                if (self.isDirty) {
                    return 'You have unsaved changes.';
                }
            });
        },

        /**
         * Load a template's content for editing.
         */
        loadTemplate: function(templateKey) {
            var self = this;
            self.currentTemplate = templateKey;

            var promises = Ajax.call([{
                methodname: 'local_sentientia_emails_get_template',
                args: {templatekey: templateKey, tenantid: self.currentTenant}
            }]);

            promises[0].done(function(response) {
                var data = JSON.parse(response.data);
                $('#ap-email-subject').val(data.subject || '');
                $('#ap-email-body').val(data.body_html || '');
                $('#ap-email-source-badge').text(data.source || 'file');
                self.isDirty = false;
                self._updatePreview();
            }).fail(Notification.exception);
        },

        /**
         * Save the current template as a DB override.
         */
        saveTemplate: function() {
            var self = this;
            var subject = $('#ap-email-subject').val();
            var bodyHtml = $('#ap-email-body').val();

            if (!subject && !bodyHtml) {
                Notification.alert('Error', 'Subject and body cannot both be empty.');
                return;
            }

            var promises = Ajax.call([{
                methodname: 'local_sentientia_emails_save_template',
                args: {
                    templatekey: self.currentTemplate,
                    tenantid: self.currentTenant,
                    subject: subject,
                    bodyhtml: bodyHtml
                }
            }]);

            promises[0].done(function() {
                self.isDirty = false;
                Notification.addNotification({
                    message: 'Template saved successfully.',
                    type: 'success'
                });
                $('#ap-email-source-badge').text('db_override');
            }).fail(Notification.exception);
        },

        /**
         * Delete the DB override and revert to file-based template.
         */
        revertTemplate: function() {
            var self = this;

            var promises = Ajax.call([{
                methodname: 'local_sentientia_emails_revert_template',
                args: {
                    templatekey: self.currentTemplate,
                    tenantid: self.currentTenant
                }
            }]);

            promises[0].done(function() {
                self.loadTemplate(self.currentTemplate);
                Notification.addNotification({
                    message: 'Reverted to default template.',
                    type: 'info'
                });
            }).fail(Notification.exception);
        },

        /**
         * Insert a Mustache placeholder at cursor position.
         */
        insertPlaceholder: function(placeholder) {
            var textarea = document.getElementById('ap-email-body');
            if (!textarea) {
                return;
            }
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var text = textarea.value;
            var insert = '{{' + placeholder + '}}';
            textarea.value = text.substring(0, start) + insert + text.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + insert.length;
            textarea.focus();
            this.isDirty = true;
            this._debouncePreview();
        },

        /**
         * Debounce preview rendering (500ms).
         */
        _debouncePreview: function() {
            var self = this;
            clearTimeout(self.previewTimer);
            self.previewTimer = setTimeout(function() {
                self._updatePreview();
            }, 500);
        },

        /**
         * Render the preview via AJAX.
         */
        _updatePreview: function() {
            var self = this;
            var bodyHtml = $('#ap-email-body').val();

            if (!bodyHtml) {
                $('#ap-email-preview-frame').attr('srcdoc', '<p style="padding:40px;color:#999;text-align:center;">Enter template content to see preview</p>');
                return;
            }

            var promises = Ajax.call([{
                methodname: 'local_sentientia_emails_preview_template',
                args: {
                    templatekey: self.currentTemplate,
                    tenantid: self.currentTenant,
                    bodyhtml: bodyHtml,
                    subject: $('#ap-email-subject').val()
                }
            }]);

            promises[0].done(function(response) {
                var iframe = document.getElementById('ap-email-preview-frame');
                if (iframe) {
                    iframe.srcdoc = response.html;
                    // Auto-resize iframe height.
                    iframe.onload = function() {
                        try {
                            iframe.style.height = iframe.contentDocument.body.scrollHeight + 40 + 'px';
                        } catch (e) {
                            // Cross-origin protection — ignore.
                        }
                    };
                }
            }).fail(function() {
                // Silently fail preview — don't interrupt editing.
                $('#ap-email-preview-frame').attr('srcdoc',
                    '<p style="padding:20px;color:#dc2626;">Preview rendering failed. Check your Mustache syntax.</p>');
            });
        }
    };

    return Editor;
});
