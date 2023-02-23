/**
 * Add a create new group modal to the page.
 *
 * @module     local_notification/newnotification
 * @class      NewNotification
 * @package    local_notification
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
    var NewNotification = function(args, notificationid, instance, plugin) {

        this.contextid = args.context;
        this.id = args.id;
        this.notificationid = notificationid;
        this.instance = instance;
        this.plugin = plugin;
        var self = this;
        this.args = args;
        self.init(args);
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    NewNotification.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    NewNotification.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    NewNotification.prototype.init = function(args) {
        //var triggers = $(selector);
        var self = this;
        // Fetch the title string.
            // $("#id_reminderdays").css("display","none");
            // $("input[name='reminderdays']").hide();
            // var editid = $(this).data("value");
            // var existclass = $(this).attr("class");
            // if (existclass) {
            //     self.notificationid = '';
            // } else if(editid){
            //     self.notificationid = editid;
            // }
            if(args.id){
                self.notificationid = args.id;
            }else{
                self.notificationid = 0;
            }
            if (self.notificationid) {
                // self.userid = editid;
                // console.log(self.userid);
                var head =  {key: 'update_notification', component: 'local_notifications'};
                // alert(self.userid);
            }else{
               var head =  {key: 'create_notification', component: 'local_notifications'};
            }
            var strings = Str.get_strings([head
            , {
                key: 'save_continue',
                component: 'local_users'
            }, {
                key: 'cancel',
                component: 'moodle'
            }, {
                key: 'no',
                component: 'moodle'
            }])
            return strings.then(function(str) {
                // Create the modal.
                return ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: str[0],
                body: this.getBody(),
                footer: this.getFooter(str),
                });
            }.bind(this)).then(function(modal) {
                // Keep a reference to the modal.
                this.modal = modal;
                // self.modal.show();
                // Forms are big, we want a big modal.
                this.modal.setLarge(); 
                
                this.modal.getRoot().addClass('openLMStransition local_notifications');

                // this.modal.getRoot().on(ModalEvents.hidden, function() {
                //     this.modal.setBody('');
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    //setTimeout(function(){
                        modal.destroy();
                    //}, 5000);
                }.bind(this));

                this.modal.getFooter().find('[data-action="save"]').on('click', this.submitForm.bind(this));
                // We also catch the form submit event and use it to submit the form with ajax.

                // this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                //     modal.setBody('');
                //     modal.hide();
                this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                        modal.destroy();
                    }, 5000);
                    // modal.destroy();
                });

                this.modal.getFooter().find('[data-action="skip"]').on('click', function() {
                    self.args.form_status = self.args.form_status + 1;
                    var data = self.getBody();
                    data.then(function(html, js) {
                        if(html === false) {
                            window.location.reload();
                        }
                    });
                    modal.setBody(data);
                });

                if(self.args.callback == 'notification_form'){
                    $(document).on('click', '.custom_notification_form_redirect', function(){
                        var instanceid = $("#notificationid").val();
                        if(instanceid > 0){
                            self.args.form_status = $(this).data('value');
                            var data = self.getBody();
                            data.then(function(html, js) {
                                if (html === false) {
                                  // window.location.reload();
                                    self.handleFormSubmissionResponse(args);
                                }
                            });
                            modal.setBody(data);
                            // if(self.args.form_status==0){
                            //     $('[data-action="skip"]').css('display', 'none');
                            //     $('[data-action="previous"]').css('display', 'none');
                            // }else{
                            //     $('[data-action="skip"]').css('display', 'block');
                            //     $('[data-action="previous"]').css('display', 'block');
                            // }
                        }
                    });
                }

                this.modal.getRoot().on('submit', 'form', function(form) {
                    self.submitFormAjax(form, self.args);
                });
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
    NewNotification.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // console.log(this);
        // alert(formdata);
        // Get the content of the modal.
        // this.args.userid = this.userid
        this.args.jsonformdata = JSON.stringify(formdata);
        return Fragment.loadFragment('local_notifications', 'new_notification_form', this.contextid, this.args);
    };
    /**
     * @method getFooter
     * @private
     * @return {Promise}
     */
    NewNotification.prototype.getFooter = function(str) {
        // var templateContext;
        // var modalPromise = Templates.render('local_classroom/form_actions', templateContext)
        // .done(function(html) {
        // console.log(html);
        // });

        $footer = '<button type="button" class="btn btn-primary" data-action="save">'+str[1]+'</button>&nbsp;';
        // $style = 'style="display:none;"';
        // $footer += '<button type="button" class="btn btn-secondary" data-action="skip" ' + $style + ' >Skip</button>&nbsp;';
        $footer += '<button type="button" class="btn btn-secondary" data-action="cancel">'+str[2]+'</button>';
        return $footer;
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewNotification.prototype.handleFormSubmissionResponse = function() {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });
        // document.location.reload();
        // This will be the context for our template. So {{name}} in the template will resolve to "Tweety bird".
        var context = { id: args.id};
        // // This will call the function to load and render our template.
        // templates.render('local_classroom/classroomview', context);

        // // It returns a promise that needs to be resoved.
        //     .then(function(html, js) {
                var modalPromise = ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    body: Templates.render('local_classroom/classroomview', context),
                });
                $.when(modalPromise).then(function(modal) {
                    // modal.setTitle('Hi');
                    // // modal.setBody('Hi');
                    // modal.show();
                    // return modal;
                }).fail(Notification.exception);


            //     // Here eventually I have my compiled template, and any javascript that it generated.
            //     // The templates object has append, prepend and replace functions.
            //     templates.appendNodeContents('.block_looneytunes .content', source, javascript);
            // }).fail(function(ex) {
            //     // Deal with this exception (I recommend core/notify exception function for this).
            // });
    };
 
    /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    NewNotification.prototype.handleFormSubmissionFailure = function(data) {
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
    NewNotification.prototype.submitFormAjax = function(e ,args) {
        // We don't want to do a real form submission.
        e.preventDefault();
        var self = this;
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // var methodname = args.plugintype + '_' + args.pluginname + '_submit_create_user_form';
        var methodname = 'local_notifications_submit_create_notification_form';
        var params = {};
        params.contextid = this.contextid;
        params.jsonformdata = JSON.stringify(formData);
        params.form_status = args.form_status;

        var promise = Ajax.call([{
            methodname: methodname,
            args: params
        }]);

         promise[0].done(function(resp){
            // alert(resp.form_status);
            if(resp.form_status !== -1 && resp.form_status !== false) {
                self.args.form_status = resp.form_status;
                self.args.id = resp.id;
                self.handleFormSubmissionFailure();
            } else {
                // self.handleFormSubmissionResponse(self.args);
                // alert('here');
                self.modal.hide();
                window.location.reload();
            }
            if(args.form_status > 0) {
                $('[data-action="skip"]').css('display', 'inline-block');
            }
        }).fail(function(ex){
            self.handleFormSubmissionFailure(formData);
        })
        // alert(this.contextid);
        // Now we can continue...
        // Ajax.call([{
        //     methodname: 'local_users_submit_create_user_form',
        //     args: {contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
        //     done: this.handleFormSubmissionResponse.bind(this, formData),
        //     fail: this.handleFormSubmissionFailure.bind(this, formData)
        // }]);
    };
 
    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    NewNotification.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };
 
    return /** @alias module:local_users/newuser */ {
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
            return new NewNotification(args);
        },
        load: function(){

        }
    };
});
