/**
 * Add a create new group modal to the page.
 *
 * @module     blocks_announcement/announcement
 * @class      Announcement
 * @package    blocks_announcement
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/fragment',
    'core/ajax',
    'core/yui',
    'block_announcement/jquery.dataTables'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y, dataTable) {
 
    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var Announcement = function(args) {
        this.contextid = args.contextid;
        this.id = args.id;
        var self = this;
        self.init(args.selector);
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    Announcement.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    Announcement.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    Announcement.prototype.init = function(args) {
        // console.log(args);
        //var triggers = $(selector);
        var self = this;

        // Fetch the title string.
        // $('.'+args.selector).click(function(){
            

            // var editid = $(this).data('value');
            // if (editid) {
                // self.planid = editid;
                if(self.id){
                    console.log(self.id);
                    var head = Str.get_string('editannouncement', 'block_announcement');
                }
                else{
                   var head = Str.get_string('adnewannouncement', 'block_announcement');
                }
                //console.log(self.costcenterid);
                //alert(self.costcenterid);
            // }
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
        
        
        // });
        
    };
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    Announcement.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // alert(JSON.stringify(formdata));
        // alert(this.planid);
        // alert(this.contextid);
        // Get the content of the modal.
        var params = {id:this.id, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('block_announcement', 'announcement_form',this.contextid, params);
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    Announcement.prototype.handleFormSubmissionResponse = function() {
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
    Announcement.prototype.handleFormSubmissionFailure = function(data) {
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
    Announcement.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        //alert(this.id);
        // Now we can continue...
        Ajax.call([{
            methodname: 'block_announcement_submit_create_announcement_form',
            args: {id:this.id, contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
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
    Announcement.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };
    return /** @alias module:local_costcenter/newcostcenter */ {
            // Public variables and functions.
            /**
             * Attach event listeners to initialise this module.
             *
             * @method init
             * @param {string} selector The CSS selector used to find nodes that will trigger this module.
             * @param {int} contextid The contextid for the course.
             * @return {Promise}
             */
            init: function(args) {
              // console.log(args);
                // alert(args.contextid);
                return new Announcement(args);
            },
            load: function(){

            },
            deleteConfirm: function(args){
                return Str.get_strings([{
                key: 'confirm'
                },
                {
                key: 'deleteconfirm',
                component: 'block_announcement',
                param : args
                },
                {   
                    key: 'delete'
                }]).then(function(s) {
                    ModalFactory.create({
                        title: s[0],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function(modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[2]);
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault();
                            args.confirm = true;
                            $.ajax({
                                method: "POST",
                                dataType: "json",
                                url: M.cfg.wwwroot + "/blocks/announcement/ajax.php?reason="+args.selector+"&id="+args.id,
                                success: function(data){
                                    window.location.reload();
                                }
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                    modal.show();
                }.bind(this));
            },
            statusConfirm: function(args){
                return Str.get_strings([{
                    key: 'confirm'
                },
                {
                    key: 'statusconfirm',
                    component: 'block_announcement',
                    param : args
                }]).then(function(s) {
                    ModalFactory.create({
                        title: s[0],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function(modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[0]);
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault();
                            args.confirm = true;
                            $.ajax({
                                method: "POST",
                                dataType: "json",
                                url: M.cfg.wwwroot + "/blocks/announcement/ajax.php?reason="+args.selector+"&id="+args.id+"&visible="+args.visible,
                                success: function(data){
                                    window.location.reload();
                                }
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                    modal.show();
                }.bind(this));
            },
            DatatablesAnnounce: function(args){
                $('#table_block_announcement').dataTable({
                    'pageLength': 10,
                    'bLengthChange': false,
                    'language': {
                        'emptyTable': 'No Records Found',
                        paginate: {
                            'previous': '<',
                            'next': '>'
                        }
                    },
                    ordering: false
                });
            }
    };
});