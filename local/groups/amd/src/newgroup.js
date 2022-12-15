/**
 * Add a create new group modal to the page.
 *
 * @module     local_groups/groups
 * @class      Newgroups
 * @package    local_groups
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {object} args
     *
     * Each call to init gets it's own instance of this class.
     */
    var Newgroups = function(args) {
        this.contextid = args.contextid;
        this.groupsid = args.groupsid;
        // this.parentid = args.parentid;
        this.args = args;
        console.log(this);
        var self = this;
        self.init();
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    Newgroups.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    Newgroups.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    Newgroups.prototype.init = function() {
        var self = this;
        var editid = $(this).data('value');
         if (self.groupsid) {
             self.groupsid = editid;
             var head = Str.get_string('editgroup', 'local_groups');
        }
        else{
              var head = Str.get_string('create_group', 'local_groups');
           }
        // console.log(self);

        // if(self.groupsid && self.parentid == 0){
        //    var head =  Str.get_string('editgroups', 'local_groups');
        // }else if(self.parentid == 0){
        //    var head = Str.get_string('addnewgroups', 'local_groups');
        // }else if(self.parentid > 0 && self.groupsid){
        //     var head = Str.get_string('editgroup', 'local_groups');
        // }else if(self.parentid > 0 ){
        //     var head = Str.get_string('addnewgroups', 'local_groups');
        // }
          // var head = Str.get_string('addnewgroups', 'local_groups');


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
           
            self.modal.getRoot().addClass('openLMStransition local_groups');
            // Forms are big, we want a big modal.
            self.modal.setLarge();
 
            // We want to reset the form every time it is opened.
            self.modal.getRoot().on(ModalEvents.hidden, function() {
                self.modal.setBody(self.getBody());
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

            this.modal.show();
            this.modal.getRoot().animate({"right":"0%"}, 500);
            return this.modal;
        }.bind(this));
        
    };
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    Newgroups.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {groupsid:this.args.groupsid, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('local_groups', 'new_groupsform', this.args.contextid, params);
        // return 'here';
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    Newgroups.prototype.handleFormSubmissionResponse = function() {
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
    Newgroups.prototype.handleFormSubmissionFailure = function(data) {
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
    Newgroups.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();
 
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_groups_submit_groupsform_form',
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
    Newgroups.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };
    return /** @alias module:local_groups/Newgroups */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {object} args
         * @return {Promise}
        */
        init: function(args) {
            return new Newgroups(args);
        },
        load: function(){

        },
        /**
         * modal for course status.
         *
         * @method groupsStatus
         * @param {object} args
         * @return {modal}
        */
        groupsStatus: function(args) {
            console.log(args);
                ModalFactory.create({
                    title: args.actionstatus,
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: args.actionstatusmsg
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText('Confirm');
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                        args.confirm = true;
                        var promise = Ajax.call([{
                            methodname: 'local_groups_status_confirm',
                            args: args
                        }]);
                        promise[0].done(function(resp) {
                            window.location.href = window.location.href;
                        }).fail(function(ex) {
                            // do something with the exception
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
          
        }
    };
});