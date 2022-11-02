/**
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {int} contextid
     * @param {int} categoryid
     *
     * Each call to init gets it's own instance of this class.
     */
    var DelCategory = function(contextid, categoryid) {
        
        this.contextid = contextid;
        this.categoryid = categoryid;
       
        var self = this;
        self.init();
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    DelCategory.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    DelCategory.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    DelCategory.prototype.init = function() {
        var self = this;
        return Str.get_string('deletecategory', 'local_courses').then(function(title) {
            // Create the modal.
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: title,
                body: self.getBody()
            });
        }.bind(self)).then(function(modal) {
                
            // Keep a reference to the modal.
            self.modal = modal;
            self.modal.show();
            // Forms are big, we want a big modal.
            self.modal.setLarge();
 
            // We want to reset the form every time it is opened.
            self.modal.getRoot().on(ModalEvents.hidden, function() {
                self.modal.setBody('');
            }.bind(this));

            // We want to hide the submit buttons every time it is opened.
            self.modal.getRoot().on(ModalEvents.shown, function() {
                self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
            }.bind(this));
 

            // We catch the modal save event, and use it to submit the form inside the modal.
            // Triggering a form submission will give JS validation scripts a chance to check for errors.
            self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
            // We also catch the form submit event and use it to submit the form with ajax.
            self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
            return this.modal;
        }.bind(this));
    };
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    DelCategory.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        if(typeof this.categoryid != 'undefined'){
            var params = {categoryid:this.categoryid, jsonformdata: JSON.stringify(formdata)};
        }else{
            var params = {};
        }
        return Fragment.loadFragment('local_courses', 'deletecategory_form', this.contextid, params);
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    DelCategory.prototype.handleFormSubmissionResponse = function() {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
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
    DelCategory.prototype.handleFormSubmissionFailure = function(data) {
        // Oh noes! Epic fail :(
        // Ah wait - this is normal. We need to re-display the form with errors!
        this.modal.setBody(this.getBody(data));
    };
 
    /**
     * Private method
     *
     * @method submitFormAjax
     * @private
     * @param {Event} e Form submission event.
     */
    DelCategory.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();
 
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        console.log(formData);
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_courses_submit_delete_category_form',
            args: {contextid: this.contextid, jsonformdata: JSON.stringify(formData),categoryid:this.categoryid},
            done: this.handleFormSubmissionResponse.bind(this, formData),
            fail: this.handleFormSubmissionFailure.bind(this, formData)
        }]);
    };
 
    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    DelCategory.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };
 
    return /** @alias module:local_courses/init */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {int} contextid The contextid for the category.
         * @param {int} categoryid categoryid.
         * @return {Promise}
         */
        init: function(args) {
            return new DelCategory(args.contextid, args.categoryid);
        },
        load: function() {
        },
        reasonfor_unabletodelete: function(args) {
            return Str.get_string(
                'reason',
                'local_courses'
            ).then(function(s) {
                ModalFactory.create({
                    title: s,
                    type: ModalFactory.types.DEFAULT,
                    body: args.reason
                }).done(function(modal) {
                    this.modal = modal;
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
           suspendonlinetest: function(elem,visible,args) {
            return Str.get_strings([{
                key: 'suspendconfirm',
                component: 'local_courses',
                param: args,
            },
            {
                key: 'inactiveconfirm',
                component: 'local_courses',
                param: args,
             
            },
            {
                key: 'activeconfirm',
                component: 'local_courses',
                param: args,
            }]).then(function(s) {
                if (elem.status == "enable") {
                    s[1] = s[1];
                 } else if (elem.status == "disable") {
                    s[1] = s[2];
                 }
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+M.util.get_string("yes", "moodle")+'</button>&nbsp;' +
            '<button type="button" class="btn btn-secondary" data-action="cancel">'+M.util.get_string("no", "moodle")+'</button>'
                }).done(function(modal) {
                    this.modal = modal;
                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        window.location.href = M.cfg.wwwroot+'/local/courses/index.php?categoryid='+elem.id+'&visible='+elem.visible+'&hide=1&sesskey='+ M.cfg.sesskey;
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
    };
});
