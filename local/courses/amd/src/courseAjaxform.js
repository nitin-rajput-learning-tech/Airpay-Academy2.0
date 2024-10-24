/**
 * Add a create new group modal to the page.
 *
 * @module     local_courses/courseAjaxform
 * @class      courseAjaxform
 * @package    local_courses
 * @copyright  2018 Sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later 
 */
define(['local_courses/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui', 'core/templates'],
        function(dataTable, $, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y, Templates) {

    /**
     * Constructor
     *
     * @param {object} args
     *
     * Each call to init gets it's own instance of this class.
     */
    var courseAjaxform = function(args) {
        this.contextid = args.contextid ? args.contextid : 1;
        this.args = args;
        this.init(args);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    courseAjaxform.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    courseAjaxform.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    courseAjaxform.prototype.init = function(args) {
        // Fetch the title string.
        var self = this;
         if (args.courseid) {
            if (args.callback==='custom_selfcompletion_form') {
                var head =  {key: 'selfcompletionname',component: 'local_courses',param: args.coursename};
            }else{
                if (args.userid) {
                    var head =  {key:'browseevidencesname', component:'local_courses',param: args.coursename};
                }else{
                    var head =  {key:'editcourse', component:'local_courses'};
                }
            }
        } else if (args.callback == 'adddashboardcourse_form') {
                var head = { key: 'addcoursetodashboard', component: 'local_courses', param: args.coursename };
            } else{
           var head = {key:'createnewcourse', component:'local_courses'};
        }
        customstrings = Str.get_strings([head, {
                key: 'yes',
                component: 'customfield'
            },
            {
                key: 'no',
                component: 'customfield'
            },
            {
                key: 'saveandcontinue',
                component: 'local_courses'
            },
            {
                key: 'cancel',
                component: 'moodle'
            },
            {
                key: 'save',
                component: 'local_courses'
            }]);
        return customstrings.then(function(strings) {
            // Create the modal.
            return ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: strings[0],
                body: this.getBody(),
                footer: this.getFooter(strings),
            });
        }.bind(this)).then(function(modal) {
            // Keep a reference to the modal.
            this.modal = modal;
            
            if (args.callback !='custom_selfcompletion_form') {
                // Forms are big, we want a big modal.
                this.modal.setLarge();

                this.modal.getRoot().addClass('openLMStransition local_courses');

                // We want to reset the form every time it is opened.
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                    this.modal.setBody('');
                }.bind(this));
            }
         
            if (args.callback =='custom_selfcompletion_form') {
                this.modal.getFooter().find('[data-action="save"]').on('click', function() {
                    window.location.href = M.cfg.wwwroot + '/course/togglecompletion.php?confirm=1&course='+args.courseid+'&sesskey='+M.cfg.sesskey
                });

                this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {

                    window.location.reload();
                    
                });
                
                this.modal.getRoot().find('[data-action="hide"]').on('click', function() {
                    window.location.reload();
                   
                });
            }else{
                this.modal.footer.find('[data-action="save"]').on('click', this.submitForm.bind(this));
                // We also catch the form submit event and use it to submit the form with ajax.
                this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                    modal.setBody('');
                    modal.hide();
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                    if (args.form_status !== 0 ) {
                        window.location.reload();
                    }
                });
                
                this.modal.getRoot().find('[data-action="hide"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                     //modal.destroy();
                    if (args.form_status !== 0 ) {
                        window.location.reload();
                    }
                    
                });
            }

                if(self.args.callback == 'custom_course_form'){
                    $(document).on('click', '.custom_course_form_redirect', function(){
                        var instanceid = $("#courseid").val();
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
                        }
                    });
                }
                if (self.args.callback == 'adddashboardcourse_form') {
                    var data = self.getBody();
                    data.then(function (html, js) {
                        if (html === false) {
                            // window.location.reload();
                            self.handleFormSubmissionResponse();
                        }
                    });
                     modal.setBody(data);
                }

            this.modal.getRoot().on('submit', 'form', function(form) {
                self.submitFormAjax(form, self.args);
            });
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
    courseAjaxform.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        this.args.jsonformdata = JSON.stringify(formdata);
        return Fragment.loadFragment(this.args.component, this.args.callback, this.contextid, this.args);
    };
    /**
     * @method getFooter
     * @private
     * @return {Promise}
     */
    courseAjaxform.prototype.getFooter = function(customstrings) {
                var footer = '';
                if (this.args.callback==='custom_selfcompletion_form') {
                    footer += '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[1]+'</button>&nbsp;';
                    footer += '<button type="button" class="btn btn-secondary" data-action="cancel">'+customstrings[2]+'</button>';
                }else if (this.args.callback === 'adddashboardcourse_form') {
                    footer += '<button type="button" class="btn btn-primary" data-action="save">' + customstrings[5] + '</button>&nbsp;';
                    footer += '<button type="button" class="btn btn-secondary" data-action="cancel">' + customstrings[4] + '</button>';
            }else if(this.args.viewtype!='userview'){
                    footer+= '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[3]+'</button>&nbsp;';
                    footer += '<button type="button" class="btn btn-secondary" data-action="cancel">'+customstrings[4]+'</button>';
                }
            return footer;
        // }.bind(this));
    };
     /**
     * @method getFooter
     * @private
     * @return {Promise}
     */
    courseAjaxform.prototype.getcontentFooter = function() {
        return Str.get_strings([{
                key: 'cancel'
            }]).then(function(s) {
            $footer = '<button type="button" class="btn btn-secondary" data-action="cancel">'+s[0]+'</button>';
            return $footer;
        }.bind(this));
    };
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    courseAjaxform.prototype.handleFormSubmissionResponse = function(args) {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });

        if (args.userid) {
             this.modal.hide();
        }else if(args.return){
                this.modal.hide();
                Notification.addNotification({
                message: args.success,
                type: "success"
            }); 
             setTimeout(function () {
                                window.location.reload();
                            }, 2000);
        } else{
            return Str.get_strings([{
                key: 'courseoverview',
                component: 'local_courses'
        }]).then(function(s) {
                
                // This will be the context for our template. So {{name}} in the template will resolve to "Tweety bird".
                var context = { courseid: args.courseid, configpath: M.cfg.wwwroot, enrolid: args.enrolid, contextid:args.contextid};

                var modalPromise = ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    body: Templates.render('local_courses/courses', context),
                    footer: this.getcontentFooter(),
                });
                $.when(modalPromise).then(function(modal) {
                    modal.setTitle(s[0]);

                    // Forms are big, we want a big modal.
                    modal.setLarge();

                    modal.getRoot().addClass('openLMStransition');
                    modal.show();
                    modal.getRoot().animate({"right":"0%"}, 500);
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                        setTimeout(function(){
                            window.location.reload();
                        }, 600);
                    });
                    modal.getRoot().find('[data-action="hide"]').on('click', function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                        setTimeout(function(){
                            window.location.reload();
                        }, 200);
                    });
                    return modal;
                }).fail(Notification.exception);
                $('#coursesearch').dataTable().destroy();
            }.bind(this));
        }
        // Classroom.Datatable();
    };

    /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    courseAjaxform.prototype.handleFormSubmissionFailure = function(data) {
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
    courseAjaxform.prototype.submitFormAjax = function(e, args) {
        // We don't want to do a real form submission.
        e.preventDefault();
        var self = this;
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();

        if (args.userid) {
             var methodname = args.plugintype + '_' + args.pluginname + '_submit_evidence_course_form';
        }else if (self.args.callback == 'adddashboardcourse_form'){
                var methodname = args.plugintype + '_' + args.pluginname + '_submit_adddashboardcourses_form';
        }else{
            var methodname = args.plugintype + '_' + args.pluginname + '_submit_create_course_form';
        }
        // Now we can continue...
        var params = {};
        params.contextid = this.contextid;
        params.jsonformdata = JSON.stringify(formData);
        params.form_status = args.form_status;
        // params.id = args.id;

        var promise = Ajax.call([{
            methodname: methodname,
            args: params
        }]);
        promise[0].done(function(resp){
            self.args.courseid = resp.courseid;
            self.args.enrolid = resp.enrolid;
            if(resp.return == 1){
                    self.args.return = resp.return;
                    self.args.success = resp.success;
                    self.handleFormSubmissionResponse(self.args);
            }else if(resp.form_status !== -1 && resp.form_status !== false) {
                self.args.form_status = resp.form_status;
                self.handleFormSubmissionFailure();
            } else {
                self.handleFormSubmissionResponse(self.args);
            }
            // if(args.form_status > 0) {
                // $('[data-action="skip"]').css('display', 'inline-block');
            // }
        }).fail(function(){
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
    courseAjaxform.prototype.submitForm = function(e) {
        e.preventDefault();
        this.modal.getRoot().find('form').submit();
    };

    return /** @alias module:core_group/courseAjaxform */ {
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
            return new courseAjaxform(args);
        },
        deleteConfirm: function(args){
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'deleteconfirm',
                component: 'local_courses',
                param : args
            },
            {
                key: 'deleteallconfirm',
                component: 'local_courses'
            },
            {
                key: 'delete'
            },
            {
                key: 'yes',
                component: 'customfield'
            },
            {
                key: 'no',
                component: 'customfield'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+s[4]+'</button>&nbsp;' +
            '<button type="button" class="btn btn-secondary" data-action="cancel">'+s[5]+'</button>'
                }).done(function(modal) {
                    this.modal = modal;
                    
                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        args.confirm = true;
                        var promise = Ajax.call([{
                            methodname: 'local_courses_' + args.action,
                            args: args
                        }]);
                        promise[0].done(function() {
                            window.location.href = window.location.href;
                        }).fail(function(ex) {
                            // do something with the exception
                             console.log(ex);
                        });
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        courseaddtocart: function(args){
            var methodname = 'local_biz_cart_add_item';
            var promise = Ajax.call([{
                methodname: methodname,
                args: args
            }]);
            promise[0].done(function(resp){
                window.location.reload();
            }).fail(function(){
                console.log("fail");
            });

        },
        load: function () {}
    };
});
