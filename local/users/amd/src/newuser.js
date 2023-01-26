/**
 * Add a create new group modal to the page.
 *
 * @module     local_users/newuser
 * @class      NewUser
 * @package    local_users
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui','local_courses/jquery.dataTables'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewUser = function(args) {

        this.contextid = args.context;
        this.id = args.id;
        var self = this;
        this.args = args;
        self.init(args);
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    NewUser.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    NewUser.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    NewUser.prototype.init = function(args) {
        //var triggers = $(selector);
        var self = this;
        // Fetch the title string.
            if (self.id) {
                var strings  = Str.get_strings([
                                {
                                    key: 'edituser',
                                    component: 'local_users'
                                },
                                {
                                    key: 'save_continue',
                                    component: 'local_users'
                                },
                                {
                                    key: 'skip',
                                    component: 'local_users'
                                },
                                {
                                    key: 'previous',
                                    component: 'local_users'
                                },
                                {
                                    key: 'cancel',
                                    component: 'local_users'
                                }]);
            }else{
               var strings  = Str.get_strings([
                            {
                                key: 'adnewuser',
                                component: 'local_users'
                            },
                            {
                                key: 'save_continue',
                                component: 'local_users'
                            },
                            {
                                key: 'skip',
                                component: 'local_users'
                            },
                            {
                                key: 'previous',
                                component: 'local_users'
                            },
                            {
                                key: 'cancel',
                                component: 'local_users'
                            }]);
            }

            return strings.then(function(strings) {
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
                // self.modal.show();
                // Forms are big, we want a big modal.
                this.modal.setLarge(); 
                
                this.modal.getRoot().addClass('openLMStransition local_users');

                // this.modal.getRoot().on(ModalEvents.hidden, function() {
                //     this.modal.setBody('');
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                        modal.destroy();
                    }, 500);
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
                    }, 500);
                    window.location.reload();
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
                    if(self.args.form_status==2){
                        $('[data-action="skip"]').css('display', 'none');
                    }
                });
                this.modal.getFooter().find('[data-action="previous"]').on('click', function() {
                    self.args.form_status = self.args.form_status - 1;
                    var data = self.getBody();
                    data.then(function(html, js) {
                        if(html === false) {
                            window.location.reload();
                        }
                    });
                    modal.setBody(data);
                    if(self.args.form_status==0){
                        $('[data-action="skip"]').css('display', 'none');
                        $('[data-action="previous"]').css('display', 'none');
                    }else{
                        $('[data-action="skip"]').css('display', 'block');
                        $('[data-action="previous"]').css('display', 'block');
                    }
                });

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
    NewUser.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // console.log(this);
        // alert(formdata);
        // Get the content of the modal.
        // this.args.userid = this.userid
        this.args.jsonformdata = JSON.stringify(formdata);
        return Fragment.loadFragment('local_users', 'new_create_user', this.contextid, this.args);
    };
    /**
     * @method getFooter
     * @private
     * @return {Promise}
     */
    NewUser.prototype.getFooter = function(strings) {
        $footer = '<button type="button" class="btn btn-primary" data-action="save">'+ strings[1] +'</button>&nbsp;';
        $style = 'style="display:none;"';
        $footer += '<button type="button" class="btn btn-secondary" data-action="previous" ' + $style + ' >'+ strings[3] +'</button>&nbsp;';
        $footer += '<button type="button" class="btn btn-secondary" data-action="skip" ' + $style + ' >'+ strings[2] +'</button>&nbsp;';
        $footer += '<button type="button" class="btn btn-secondary" data-action="cancel">'+ strings[4] +'</button>';
        return $footer;
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewUser.prototype.handleFormSubmissionResponse = function() {
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
    NewUser.prototype.handleFormSubmissionFailure = function(data) {
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
    NewUser.prototype.submitFormAjax = function(e ,args) {
        // We don't want to do a real form submission.
        e.preventDefault();
        var self = this;
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // var methodname = args.plugintype + '_' + args.pluginname + '_submit_create_user_form';
        var methodname = 'local_users_submit_create_user_form';
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
                $('[data-action="previous"]').css('display', 'inline-block');
            }

            if(args.form_status == 2) {
                $('[data-action="skip"]').css('display', 'none');
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
    NewUser.prototype.submitForm = function(e) {
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
            return new NewUser(args);
        },
        load: function(){
            $(document).on('change', '#id_open_costcenterid', function() {
              var costcentervalue = $(this).find("option:selected").val();
               if (costcentervalue !== null) {
                    var params = {};
                    params.costcenterid = costcentervalue;
                    params.contextid = 1;
                    var promise = Ajax.call([{
                        methodname: 'local_users_get_departments_list',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        var resp = JSON.parse(resp);
                        var template = '';
                        $.each(resp, function(index,value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        $("#id_open_departmentid").html(template);
                    });
                } 
                $('#id_open_departmentid').trigger('change');
                $('#id_open_subdepartment').trigger('change');
            });
            $(document).on('change', '#id_open_departmentid', function() {
              var departmentvalue = $(this).find("option:selected").val();
               if (departmentvalue !== null) {
                    var params = {};
                    params.departmentid = departmentvalue;
                    params.contextid = 1;
                    var promise = Ajax.call([{
                        methodname: 'local_users_get_subdepartments_list',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        var resp = JSON.parse(resp);
                        var template = '';
                        $.each(resp, function(index,value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        $("#id_open_subdepartment").html(template);
                    });
                }
            });
            $(document).on('change', '#id_open_costcenterid', function() {
                var costcentervalue = $(this).find("option:selected").val();
                if (costcentervalue != 0) {
                    var params = {};
                    params.costcenterid = costcentervalue;
                    params.contextid = 1;
                    var promise = Ajax.call([{
                        methodname: 'local_users_get_supervisors_list',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        var resp = JSON.parse(resp);
                        var template = '';
                        $.each(resp, function(index,value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        $("#open_supervisorid").html(template);
                    });
                }
                $('#open_supervisorid').trigger('change');    
            });

            $(document).on('change', '#id_open_costcenterid', function() {
                var costcentervalue = $(this).find("option:selected").val();
                if (costcentervalue != 0) {
                    var params = {};
                    params.costcenterid = costcentervalue;
                    params.contextid = 1;
                    var promise = Ajax.call([{
                        methodname: 'local_users_get_domains_list',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        var resp = JSON.parse(resp);
                        var template = '';
                        $.each(resp, function(index,value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        console.log(template);
                        $("#id_open_domainid").html(template);
                    });
                }
                $('#id_open_domainid').trigger('change');    
            });

            $(document).on('change', '#id_open_domainid', function() {
                // var costcentervalue = $('#id_open_costcenterid').val();
                // if(costcentervalue > 0) {
                //     costcentervalue = costcentervalue;
                // } else {
                //     costcentervalue = $('input[name=open_costcenterid]').val();
                // }
                var costcentervalue = $(this).data('costcenterid')
                var domainvalue = $(this).find("option:selected").val();
                if (costcentervalue != 0 && domainvalue != 0) {
                    var params = {};
                    params.costcenterid = costcentervalue;
                    params.domain = domainvalue;
                    params.contextid = 1;
                    var promise = Ajax.call([{
                        methodname: 'local_users_get_positions_list',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        var resp = JSON.parse(resp);
                        var template = '';
                        $.each(resp, function(index,value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        $("#id_open_positionid").html(template);
                    });
                }
                $('#id_open_positionid').trigger('change');    
            });
        },
        changeElement: function(event){
            var depth = $(event.target).data('depth');
            $.each($('[data-action="userprofile_element_selector"]'), function(index, value){
                if($(value).data('depth') > depth){
                    $(value).html('');
                    $(value).parent().find('.form-autocomplete-selection').html($(value).data('selectstring'));
                }
            });
        },
        deleteConfirm: function(args) {
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'deleteconfirm',
                component: 'local_users',
                param :args
            },
            {
                key: 'deleteallconfirm',
                component: 'local_users'
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
                        var params = {};
                        params.id = args.id;
                        params.contextid = args.contextid;
                    
                        var promise = Ajax.call([{
                            methodname: 'local_users_'+args.action,
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
            }.bind(this));
        },
        userSuspend: function(args) {
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'suspendconfirm'+args.status,
                component: 'local_users',
                param :args
            },
            {
                key: 'suspendallconfirm',
                component: 'local_users'
            },
            {
                key: 'confirm'
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
                        var params = {};
                        params.id = args.id;
                        params.contextid = args.contextid;
                    
                        var promise = Ajax.call([{
                            methodname: 'local_users_suspend_user',
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
            }.bind(this));
        },
        skillslist: function(args) {
            // modal to show the courses in a category
            element = '.competencynames';
            if(!$(element).hasClass('clicked')){
                $(element).addClass('clicked');
                var params = { categoryid: args.categoryid, costcenterid: args.costcenterid, positionid: args.positionid, userid: args.userid};
                var returndata =  Fragment.loadFragment('local_skillrepository', 'competency_skills_display', args.contextid, params);

                ModalFactory.create({
                    title: Str.get_string('categorypopup', 'local_users', args.categoryname),
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
        }
    };
});