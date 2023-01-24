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
        }else{
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
            
            // this.getFooter().then(function(s){
            //     // this.modal.footer = s;
            //     this.modal.setFooter(s);
            //     // console.log(this.modal.footer);
            // }.bind(this));
            // this.modal.showFooter();
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

            // // We want to hide the submit buttons every time it is opened.
            // this.modal.getRoot().on(ModalEvents.shown, function() {
            //     this.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
            // }.bind(this));

            // We catch the modal save event, and use it to submit the form inside the modal.
            // Triggering a form submission will give JS validation scripts a chance to check for errors.
            // this.modal.getRoot().on(ModalEvents.save, this.submitForm.bind(this));
         
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
        // return Str.get_strings([{
        //         key: 'yes',
        //         component: 'customfield'
        //     },
        //     {
        //         key: 'no',
        //         component: 'customfield'
        //     },
        //     {
        //         key: 'saveandcontinue',
        //         component: 'local_courses'
        //     },
        //     {
        //         key: 'cancel'
        //     }]).then(function(s) {
                var footer = '';
                if (this.args.callback==='custom_selfcompletion_form') {
                    footer += '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[1]+'</button>&nbsp;';
                    // if(this.args.form_status == 0) {
                    //     $style = 'style="display:none;"';
                    //     $footer += '<button type="button" class="btn btn-secondary" data-action="skip" ' + $style + '>Skip</button>&nbsp;';
                    // }
                    footer += '<button type="button" class="btn btn-secondary" data-action="cancel">'+customstrings[2]+'</button>';
                }
                else if(this.args.viewtype!='userview'){
                    footer+= '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[3]+'</button>&nbsp;';
                    // if(this.args.form_status == 0) {
                    //     $style = 'style="display:none;"';
                    //     $footer += '<button type="button" class="btn btn-secondary" data-action="skip" ' + $style + '>Skip</button>&nbsp;';
                    // }
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
        }else{
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
                    // modal.getRoot().on(ModalEvents.hidden, function() {
                    //     modal.hide();
                    // });
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
            if(resp.form_status !== -1 && resp.form_status !== false) {
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
        getCatlist: function() {
            // $('#id_open_path').on('change',function(){
            //     return Str.get_strings([{
            //         key: 'selectdept',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'selectcat',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'selectlevel',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'select_certificate',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'errorinrequestprocessing',
            //         component: 'local_courses'
            //     }]).then(function(s) {
            //         var orgID = $(this).val();
            //         if(orgID){
            //             var promise = Ajax.call([{
            //                 methodname: 'local_courses_departmentlist',
            //                 args: {
            //                     orgid: orgID,
            //                     depid: 0,
            //                     flag: 0,
            //                 },
            //             }]);
            //             promise[0].done(function(resp) {
            //                var template =  '<option value=null>'+s[0]+'</option>';                                    
            //                     $.each(JSON.parse(resp.departments), function( index, value) {
            //                         template += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_open_departmentid').html(template);
            //                     var cattemplate =  '<option value=\'\'>'+s[1]+'</option>';
            //                     $.each(JSON.parse(resp.categories), function( index, value) {
            //                         cattemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_category').html(cattemplate);
            //                     var leveltemplate =  '<option value=\'\'>'+s[2]+'</option>';
            //                     $.each(JSON.parse(resp.levels), function( index, value) {
            //                         leveltemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_open_level').html(leveltemplate);

            //                     var certtemplate =  '<option value=\'\'>'+s[3]+'</option>';
            //                     $.each(JSON.parse(resp.certificates), function( index, value) {
            //                         certtemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_open_certificateid').html(certtemplate);
            //             }).fail(function(ex) {
            //                 console.log(ex);
            //                 // do something with the exception
            //                 alert(s[3]);
            //                 // window.location.reload();
            //             });
            //         } else {
            //             var template =  '<option value=\'\'>'+s[0]+'</option>';
            //             $('#id_open_departmentid').html(template);
            //             var cattemplate =  '<option value=\'\'>'+s[1]+'</option>';
            //             $('#id_category').html(cattemplate);
            //             var leveltemplate =  '<option value=\'\'>'+s[2]+'</option>';
            //             $('#id_open_level').html(leveltemplate);
            //             var certtemplate = '<option value=\'\'>'+s[3]+'</option>';
            //             $('#id_open_certificateid').html(certtemplate);
            //         }
            //     }.bind(this));
            // });

            // $('#id_open_departmentid').on('change',function(){
            //     return Str.get_strings([{
            //         key: 'selectcat',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'selectsubdept',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'errorinrequestprocessing',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'selectdept',
            //         component: 'local_courses'
            //     }]).then(function(s) {
            //         var depID = $(this).val();
            //         if(depID && depID != 'null'){
            //             var promise = Ajax.call([{
            //                 methodname: 'local_courses_departmentlist',
            //                 args: {
            //                     orgid: 0,
            //                     depid: depID,
            //                     flag: 1,
            //                 },
            //             }]);
            //             promise[0].done(function(resp) {
            //                var cattemplate =  '<option value=\'\'>'+s[0]+'</option>';
            //                     $.each(JSON.parse(resp.categories), function( index, value) {
            //                         cattemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_category').html(cattemplate);
            //                 var subdepartment = '<option value=\'\'>'+s[1]+'</option>';
            //                 $.each(resp.subdepartments, function(index,value) {
            //                     template += '<option value = ' + index + ' >' +value + '</option>';
            //                 });
            //                 $("#id_open_subdepartment").html(template);
            //             }).fail(function(ex) {
            //                 console.log(ex);
            //                 // do something with the exception
            //                 alert(s[2]);
            //                 // window.location.reload();
            //             });
            //             var params = {};
            //             params.departmentid = depID;
            //             params.contextid = 1;
            //             var promise = Ajax.call([{
            //                 methodname: 'local_courses_subdepartments_list',
            //                 args: params
            //             }]);
            //             promise[0].done(function(resp) {
            //                 var resp = JSON.parse(resp);
            //                 var template = '';
            //                 $.each(resp, function(index,value) {
            //                     template += '<option value = ' + index + ' >' +value + '</option>';
            //                 });
            //                 $("#id_open_subdepartment").html(template);
            //             });
            //         } else {
            //             var empty_subdept_template = '<option>'+s[1]+'</option>'
            //             $("#id_open_subdepartment").html(empty_subdept_template);
            //             costcenter = $('#id_open_path').val();
            //             if (!costcenter) 
            //                 costcenter = $('input[name="open_path"]').val();
            //             if(costcenter){
            //                 var promise = Ajax.call([{
            //                 methodname: 'local_courses_departmentlist',
            //                 args: {
            //                     orgid: costcenter,
            //                     depid: 0,
            //                     flag: 0,
            //                 },
            //             }]);
            //             promise[0].done(function(resp) {
            //                var template =  '<option value=null>'+s[3]+'</option>';                                    
            //                     $.each(JSON.parse(resp.departments), function( index, value) {
            //                         template += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_open_departmentid').html(template);
            //                     var cattemplate =  '<option value=\'\'>'+s[0]+'</option>';
            //                     $.each(JSON.parse(resp.categories), function( index, value) {
            //                         cattemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_category').html(cattemplate);
            //             }).fail(function(ex) {
            //                 console.log(ex);
            //                 // do something with the exception
            //                 alert(s[2]);
            //                 // window.location.reload();
            //             });
            //             }
            //          }
            //     }.bind(this));
            // });
            // $('#id_open_subdepartment').on('change',function(){
            //     return Str.get_strings([{
            //         key: 'selectcat',
            //         component: 'local_courses'
            //     },
            //     {
            //         key: 'errorinrequestprocessing',
            //         component: 'local_courses'
            //     }]).then(function(s) {
            //         var subdepID = $(this).val();
            //         if(subdepID && subdepID != 0 && subdepID != 'null'){
            //             var promise = Ajax.call([{
            //                 methodname: 'local_courses_departmentlist',
            //                 args: {
            //                     orgid: 0,
            //                     depid: subdepID,
            //                     flag: 1,
            //                 },
            //             }]);
            //             promise[0].done(function(resp) {
            //                var cattemplate =  '<option value=\'\'>'+s[0]+'</option>';
            //                     $.each(JSON.parse(resp.categories), function( index, value) {
            //                         cattemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                     });
            //                     $('#id_category').html(cattemplate);
            //             }).fail(function(ex) {
            //                 console.log(ex);
            //                 // do something with the exception
            //                 alert(s[1]);
            //                 // window.location.reload();
            //             });
            //         }else{
            //             var depID = $("#id_open_departmentid").val();
            //             if(depID && depID != 'null'){
            //                 var promise = Ajax.call([{
            //                     methodname: 'local_courses_departmentlist',
            //                     args: {
            //                         orgid: 0,
            //                         depid: depID,
            //                         flag: 1,
            //                     },
            //                 }]);
            //                 promise[0].done(function(resp) {
            //                    var cattemplate =  '<option value=\'\'>'+s[0]+'</option>';
            //                         $.each(JSON.parse(resp.categories), function( index, value) {
            //                             cattemplate += '<option value = ' + index + ' >' +value + '</option>';
            //                         });
            //                         $('#id_category').html(cattemplate);
            //                 }).fail(function(ex) {
            //                     console.log(ex);
            //                     // do something with the exception
            //                     alert(s[1]);
            //                     // window.location.reload();
            //                 });
            //             }
            //         }
            //     }.bind(this));
            // });
        },
        load: function () {}
    };
});