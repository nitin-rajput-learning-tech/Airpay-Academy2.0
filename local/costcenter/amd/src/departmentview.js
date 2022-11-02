/**
 * Add a create new group modal to the page.
 *
 * @module     local_costcenter/costcenter
 * @package    local_costcenter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {object} args
     *
     * Each call to init gets it's own instance of this class.
     */
    var departmentview = function(args) {
        this.contextid = args.contextid;
        var self = this;
        self.init();
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    departmentview.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    departmentview.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    departmentview.prototype.init = function() {
        var self = this;
        
        var head = Str.get_string('fieldlabel', 'local_costcenter');

        return head.then(function(title) {
            // Create the modal.
            return ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: self.getBody(),
                footer: self.getFooter(),
            });
        }.bind(self)).then(function(modal) {
            
            // Keep a reference to the modal.
            self.modal = modal;
            //to hide close button
            modal.getRoot().find('[data-action="hide"]').css('display', 'none');

            modal.getFooter().find('[data-action="save"]').on('click', this.submitForm.bind(this));

            modal.getRoot().on('submit', 'form', function(form) {
                self.submitFormAjax(form, self.args);
            });
            this.modal.show();

            // do not close the modal, if we click anywhere in the page
            modal.getRoot().click(function(e) {
                this.modal.show();
            }.bind(this));


            return this.modal;

        }.bind(this));
        
    };
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    departmentview.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('local_costcenter', 'departmentview', this.contextid, params);
    };

    departmentview.prototype.getFooter = function() {
		var sub = Str.get_string('submit', 'local_costcenter');
        $footer = '<button type="button" class="btn btn-primary" data-action="save">'+sub+'</button>&nbsp;';
        return $footer;
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    departmentview.prototype.handleFormSubmissionResponse = function() {
        this.modal.hide();
        // We could trigger an event instead.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });
        document.location.reload();
    };
 
    /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    departmentview.prototype.handleFormSubmissionFailure = function(data) {
        // Ah wait - this is normal. We need to re-display the form with errors!
        this.modal.setBody(this.getBody(data));
    };

    departmentview.prototype.submitForm = function(e) {
        e.preventDefault();
        this.modal.getRoot().find('form').submit();
    };
 
    /**
     * Private method
     *
     * @method submitFormAjax
     * @private
     * @param {Event} e Form submission event.
     */
    departmentview.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();
        var self = this;
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        var methodname = 'local_costcenter_departmentview',
        params = {};
        params.jsonformdata = JSON.stringify(formData);
        var promise = Ajax.call([{
            methodname: methodname,
            args: params
        }]);
        promise[0].done(function(resp){
            // self.handleFormSubmissionResponse(self.args);
            self.handleFormSubmissionResponse(formData);
        }).fail(function(){
            // self.handleFormSubmissionFailure(formData);
            self.handleFormSubmissionFailure(formData);
        });
    };
 
    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    departmentview.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };
    return /** @alias module:local_costcenter/departmentview */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {object} args
         * @return {Promise}
        */
        init: function(args) {
            return new departmentview(args);
        },
        load: function(){
            // return new departmentview(args);
        },
    };
});