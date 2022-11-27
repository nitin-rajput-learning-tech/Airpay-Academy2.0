/**
 * Add a create new group modal to the page.
 *
 * @module     local_costcenter/costcenter
 * @class      NewCostcenter
 * @package    local_costcenter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var lpcreate = function(args) {
        this.contextid = args.contextid;
        this.planid = args.planid;
        this.args = args;
        var self = this;
        self.init(args);
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    lpcreate.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    lpcreate.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    lpcreate.prototype.init = function(args) {
        var self = this;

        // Fetch the title string.
                if(self.planid){
                    console.log(self.planid);
                    var head = Str.get_string('editlearningplan', 'local_learningplan');
                }
                else{
                   var head = Str.get_string('adnewlearningplan', 'local_learningplan');
                }
            return head.then(function(title) {
                // Create the modal.
                return ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: title,
                    body: self.getBody(),
                    // footer: this.getFooter(),
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
                this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        window.location.href =  window.location.href;
                });
                
                
                // We want to hide the submit buttons every time it is opened.
                self.modal.getRoot().on(ModalEvents.shown, function() {
                    self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                }.bind(this));
     
    
                // We catch the modal save event, and use it to submit the form inside the modal.
                // Triggering a form submission will give JS validation scripts a chance to check for errors.
                self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
                // We also catch the form submit event and use it to submit the form with ajax.
                self.modal.getRoot().on('submit', 'form', function(form) {
                    self.submitFormAjax(form, self.args);
                });
    

                this.modal.show();
                this.modal.getRoot().animate({"right":"0%"}, 500);
                $(".close").click(function(){
                    window.location.href =  window.location.href;
                });
                return this.modal;
            }.bind(this));       
        
    };
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    lpcreate.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        this.args.jsonformdata = JSON.stringify(formdata);
        return Fragment.loadFragment('local_learningplan', 'new_learningplan',this.contextid, this.args);
    };
 
     /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    lpcreate.prototype.handleFormSubmissionFailure = function(data) {
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
    lpcreate.prototype.submitFormAjax = function(e ,args) {
        // We don't want to do a real form submission.
        e.preventDefault();
        var self = this;
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        var methodname = 'local_learningplan_submit_learningplan_form';
        var params = {};
        params.id = this.planid
        params.contextid = this.contextid;
        params.jsonformdata = JSON.stringify(formData);
        params.form_status = args.form_status;

        var promise = Ajax.call([{
            methodname: methodname,
            args: params
        }]);

         promise[0].done(function(resp){
            if(resp.form_status !== -1 && resp.form_status !== false) {
                self.args.form_status = resp.form_status;
                self.args.id = resp.id;
                self.handleFormSubmissionFailure();
            } else {
                self.modal.hide();
                window.location.reload();
            }
        }).fail(function(ex){
            self.handleFormSubmissionFailure(formData);
        })
    };
 
    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    lpcreate.prototype.submitForm = function(e) {
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
                return new lpcreate(args);
            },
            load: function(){

            },
            deleteConfirm: function(args){
                return Str.get_strings([{
                key: 'confirm'
                },
                {
                key: 'deleteconfirm',
                component: 'local_learningplan',
                param : args
                },
                {
                key: 'deleteallconfirm',
                component: 'local_learningplan'
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
                        modal.setSaveButtonText(s[3]);
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault();
                            args.confirm = true;
                            var promise = Ajax.call([{
                                methodname: 'local_learningplan_' + args.action,
                                args: args
                            }]);
                            promise[0].done(function(resp) {
                                window.location.href = window.location.href;
                            }).fail(function(ex) {
                                // do something with the exception
                                 console.log(ex);
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }, 
            toggleVisible: function(args){
                return Str.get_strings([{
                    key: 'confirm'+args.visible,
                    component: 'local_learningplan',
                },
                {
                    key: 'activeconfirm'+args.visible,
                    component: 'local_learningplan',
                    param : args
                },
                {
                    key: 'confirm',
					component: 'local_learningplan',
                }]).then(function(s) {
                    ModalFactory.create({
                        title: s[2],
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: s[1]
                    }).done(function(modal) {
                        this.modal = modal;
                        modal.setSaveButtonText(s[2]);
                        // modal.setCancelButtonText(s[2]);
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault();
                            args.confirm = true;
                            var promise = Ajax.call([{
                                methodname: 'local_learningplan_' + args.action,
                                args: args
                            }]);
                            promise[0].done(function(resp) {
                                window.location.href = window.location.href;
                            }).fail(function(ex) {
                                // do something with the exception
                                 console.log(ex);
                            });
                        }.bind(this));
                        modal.show();
                        }.bind(this));
                }.bind(this));
            },
            unassignCourses: function(args){
                return Str.get_strings([{
                key: 'confirm'
                },
                {
                key: 'unassign_courses_confirm',
                component: 'local_learningplan',
                param : args
                },
                {   
                    key: 'unassign',
                    component:'local_learningplan',
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
                            params = {};
                            params.courseid = args.unassigncourseid;
                            params.planid = args.planid;
                            var promise = Ajax.call([{
                                methodname: 'local_learningplan_' + args.action,
                                args: params
                            }]);
                            promise[0].done(function(resp) {
                                window.location.href = window.location.href;
                            }).fail(function(ex) {
                                // do something with the exception
                                 console.log(ex);
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                    modal.show();
                }.bind(this));
            },
            unassignUsers: function(args){
                return Str.get_strings([{
                key: 'confirm'
                },
                {
                key: 'unassign_users_confirm',
                component: 'local_learningplan',
                param : args
                },
                {   
                    key: 'unassign',
                    component:'local_learningplan',
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
                            params = {};
                            params.userid = args.unassignuserid;
                            params.planid = args.planid;
                            var promise = Ajax.call([{
                                methodname: 'local_learningplan_' + args.action,
                                args: params
                            }]);
                            promise[0].done(function(resp) {
                                window.location.href = window.location.href;
                            }).fail(function(ex) {
                                // do something with the exception
                                 console.log(ex);
                            });
                        }.bind(this));
                        modal.show();
                    }.bind(this));
                    modal.show();
                }.bind(this));
            },
        };
});