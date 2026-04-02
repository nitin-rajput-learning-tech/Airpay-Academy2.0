/**
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui','local_courses/jquery.dataTables'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    /**
     * Constructor
     *
     * @param {int} contextid
     * @param {int} categoryid
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewCategory = function(contextid, categoryid) {

        this.contextid = contextid;
        this.categoryid = categoryid;
        var self = this;
        self.init();
    };

    /**
     * @var {Modal} modal
     * @private
     */
    NewCategory.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    NewCategory.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    NewCategory.prototype.init = function() {
        var self = this;
        if (self.categoryid) {
            var head =  Str.get_string('editcategory', 'local_courses');
        }else{
           var head =  Str.get_string('addcategory', 'local_courses');
        }
        return head.then(function(title) {
            // Create the modal.
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: title,
                body: self.getBody()
            });
        }.bind(self)).then(function(modal) {

            // Keep a reference to the modal.
            self.modal = modal;
            self.modal.getRoot().addClass('openLMStransition');
            self.modal.show();
            // Forms are big, we want a big modal.
            self.modal.setLarge();

            // We want to reset the form every time it is opened.
            self.modal.getRoot().on(ModalEvents.hidden, function() {
                self.modal.setBody('');
                self.modal.getRoot().animate({"right":"-85%"}, 500);
                setTimeout(function(){
                    modal.destroy();
                }, 1000);
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
            self.modal.getRoot().animate({"right":"0%"}, 500);
            return this.modal;
        }.bind(this));
    };

    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    NewCategory.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        params = {};
        params.jsonformdata = JSON.stringify(formdata);
        params.categoryid = this.categoryid;
        return Fragment.loadFragment('local_courses', 'coursecategory_form', this.contextid, params);
    };

    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewCategory.prototype.handleFormSubmissionResponse = function() {
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
    NewCategory.prototype.handleFormSubmissionFailure = function(data) {
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
    NewCategory.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_courses_submit_create_category_form',
            args: {contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
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
    NewCategory.prototype.submitForm = function(e) {
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
         * @param {int} contextid The contextid for the course.
         * @param {int} categoryid categoryid.
         * @return {Promise}
         */
        init: function(args) {
            return new NewCategory(args.contextid, args.categoryid);
        },
        courselist: function(args) {
            // modal to show the courses in a category
            element = '.course_count_popup';
            if(!$(element).hasClass('clicked')){
                $(element).addClass('clicked');
                var params = { categoryid: args.categoryid};
                var returndata =  Fragment.loadFragment('local_courses', 'coursecategory_display', args.contextid, params);

                ModalFactory.create({
                    title: Str.get_string('categorypopup', 'local_courses', args.categoryname),
                    body: returndata
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();
                    modal.setLarge();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.setBody('');
                    }.bind(this));
                    modal.getRoot().find('[data-action="hide"]').on('click', function() {
                        $(element).removeClass('clicked');
                        modal.hide();
                        setTimeout(function(){
                             modal.destroy();
                        }, 500);
                    });
                });
            }
        },
        load: function() {
        }
    };
});