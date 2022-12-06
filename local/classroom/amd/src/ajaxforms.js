/**
 * Add a create new group modal to the page.
 *
 * @module     core_group/AjaxForms
 * @class      AjaxForms
 * @package    core_group
 * @copyright  2017 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/str',
        'core/modal_factory',
        'core/modal_events',
        'core/fragment',
        'core/ajax',
        'core/yui',
        'core/templates',
        'local_classroom/select2',
        'local_classroom/classroom'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y, Templates, select2, Classroom) {

    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    classroomlastchildpopup=function(args) {
            params = {classroomid : args.id, contextid : 1};

            var promise = Ajax.call([{
              methodname: 'local_classroom_classroomlastchildpopup',
              args: params
            }]);

            promise[0].done(function(returndata) {
              var data = Templates.render('local_classroom/classroomview', {response: returndata});
                data.then(function(response){
                    ModalFactory.create({
                        title: Str.get_string('classroom_info', 'local_classroom'),
                        body: response
                      }).done(function(modal) {
                        // Do what you want with your new modal.
                        modal.show();
                         modal.setLarge();
                         modal.getRoot().addClass('openLMStransition');
                            modal.getRoot().animate({"right":"0%"}, 500);
                            modal.getRoot().on(ModalEvents.hidden, function() {
                            modal.getRoot().animate({"right":"-85%"}, 500);
                                    setTimeout(function(){
                                    modal.destroy();
                                }, 1000);
                            }.bind(this));
                            $(".close").click(function(){
                                window.location.href =  window.location.href;
                            });
                      });
                });
                
            }).fail(function(ex) {
                // do something with the exception
                console.log(ex);
            });
    };
    var AjaxForms = function(args) {
        this.contextid = args.contextid;
        this.args = args;
        this.init(args);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    AjaxForms.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    AjaxForms.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    AjaxForms.prototype.init = function(args) {
        // Fetch the title string.
        var self = this;
        switch (args.callback) {
          case 'classroom_form':
              switch (args.id) {
                case 0:
                  var head =  {key:'createclassroom', component:'local_classroom'};
                  break;
                default:
                  var head =  {key:'updateclassroom', component:'local_classroom'};
              }
              break;
          case 'session_form':
            switch (args.id) {
              case 0:
                var head =  {key:'addsession', component:'local_classroom'};
                break;
              default:
                var head =  {key:'updatesession', component:'local_classroom'};
            }
          break;
          case 'course_form':
            switch (args.id) {
              case 0:
                var head =  {key:'addcourses', component:'local_classroom'};
                break;
              default:
                var head =  {key:'updatecourses', component:'local_classroom'};
            }
            break;
          case 'classroom_completion_form':
            var head =  {key:'classroom_completion_settings', component:'local_classroom'};
            break;
        }
        customstrings = Str.get_strings([head,{
                        key: 'savecontinue',
                        component: 'local_classroom'
                    },
                    {
                        key: 'assign',
                        component: 'local_classroom'
                    },
                    {
                        key: 'save',
                        component: 'local_classroom'
                    },
                    {
                        key: 'previous',
                        component: 'local_classroom'
                    },
                    {
                        key: 'skip',
                        component: 'local_classroom'
                    },
                    {
                        key: 'cancel',
                        component: 'local_classroom'
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

            // Forms are big, we want a big modal.
            this.modal.setLarge();

            this.modal.getRoot().addClass('openLMStransition local_classroom');

            // We want to reset the form every time it is opened.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                this.modal.getRoot().animate({"right":"-85%"}, 500);
                setTimeout(function(){
                    modal.destroy();
                }, 1000);
                // this.modal.destroy();
            }.bind(this));
            this.modal.getFooter().find('[data-action="save"]').on('click', this.submitForm.bind(this));
            this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                window.location.href =  window.location.href;
                //modal.hide();
                //setTimeout(function(){
                //    modal.destroy();
                //}, 1000);
                // modal.destroy();
            });
            this.modal.getFooter().find('[data-action="skip"]').on('click', function() {
                self.args.form_status = self.args.form_status + 1;
                 //console.log(args.form_status);
                 // OL-1042 Add Target Audience to Classrooms//
                 // if (args.form_status == 4) {
                // OL-1042 Add Target Audience to Classrooms//
                    // classroomlastchildpopup(args);
                 // }
                var data = self.getBody();
                data.then(function(html, js) {
                    if (html === false) {
                        // window.location.reload();
                        self.handleFormSubmissionResponse(args);
                        
                    }
                });
                modal.setBody(data);
                if(self.args.form_status == 3){
                    $('[data-action="skip"]').css('display', 'none');
                }
            });
            this.modal.getFooter().find('[data-action="previous"]').on('click', function() {
                self.args.form_status = self.args.form_status - 1;
                 //console.log(args.form_status);
                 // OL-1042 Add Target Audience to Classrooms//
                 // if (args.form_status == 4) {
                // OL-1042 Add Target Audience to Classrooms//
                    // classroomlastchildpopup(args);
                 // }
                var data = self.getBody();
                data.then(function(html, js) {
                    if (html === false) {
                        // window.location.reload();
                        self.handleFormSubmissionResponse(args);
                        
                    }
                });
                modal.setBody(data);
                if (self.args.form_status == 0) {
                    $('[data-action="skip"]').css('display', 'none');
                    $('[data-action="previous"]').css('display', 'none');
                }else if(self.args.form_status < 3){
                    $('[data-action="skip"]').css('display', 'inline-block');
                    $('[data-action="previous"]').css('display', 'inline-block');
                }
            });
            // added for custom navigating from the top lists.
            
            if(self.args.callback == 'classroom_form'){
                // if(instanceid > 0){
                $(document).on('click', '.custom_classroom_form_redirect', function(){
                    var instanceid = $("#classroomid").val();
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
                        if (self.args.form_status == 0) {
                            $('[data-action="skip"]').css('display', 'none');
                            $('[data-action="previous"]').css('display', 'none');
                        }else if(self.args.form_status < 4){
                            $('[data-action="skip"]').css('display', 'inline-block');
                            $('[data-action="previous"]').css('display', 'inline-block');
                        }
                    }
                });
            }
            // added for custom navigating from the top lists ends here.
            this.modal.getRoot().on('submit', 'form', function(form) {
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
    AjaxForms.prototype.getBody = function(formdata) {
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
    AjaxForms.prototype.getFooter = function(customstrings) {

            var footer = '';

            var style = 'style="display:none;"';

            if(this.args.callback == 'classroom_form'){
              footer += '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[1]+'</button>&nbsp;';
            }else if(this.args.callback == 'course_form'){
              footer += '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[2]+'</button>&nbsp;';
            }else{
             footer += '<button type="button" class="btn btn-primary" data-action="save">'+customstrings[3]+'</button>&nbsp;';
            }
        
            if (this.args.form_status == 0) {
                
                footer += '<button type="button" class="btn btn-secondary" data-action="previous" ' + style + '>'+customstrings[4]+'</button>&nbsp;';
                footer += '<button type="button" class="btn btn-secondary" data-action="skip" ' + style + '>'+customstrings[5]+'</button>&nbsp;';
            }
            footer += '<button type="button" class="btn btn-secondary" data-action="cancel">'+customstrings[6]+'</button>';
          return footer;
                  
    };
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    AjaxForms.prototype.handleFormSubmissionResponse = function(args) {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });
        if (args.form_status == -2 || args.form_status == 3 || args.callback == 'course_form') {
            window.location.reload();
        }
        classroomlastchildpopup(args);
    };

    /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    AjaxForms.prototype.handleFormSubmissionFailure = function(data) {
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
    AjaxForms.prototype.submitFormAjax = function(e, args) {
        // We don't want to do a real form submission.
        e.preventDefault();
        var self = this;
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        var methodname = args.plugintype + '_' + args.pluginname + '_submit_instance';
        // Now we can continue...
        var params = {};
        params.contextid = this.contextid;
        params.jsonformdata = JSON.stringify(formData);
        params.form_status = args.form_status;

        var promise = Ajax.call([{
            methodname: methodname,
            args: params
        }]);
        promise[0].done(function(resp){
            self.args.form_status = resp.form_status;
            if (resp.form_status >= 0 && resp.form_status !== false) {
                self.args.form_status = resp.form_status;
                self.args.id = resp.id;
                self.handleFormSubmissionFailure();
            } else {
                self.handleFormSubmissionResponse(self.args);
                window.location.reload();
            }
            if (args.form_status > 0) {
                $('[data-action="skip"]').css('display', 'inline-block');
                $('[data-action="previous"]').css('display', 'inline-block');
            }
            if (args.form_status == 3 ) {
                $('[data-action="skip"]').css('display', 'none');
            }
            
        }).fail(function(ex){
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
    AjaxForms.prototype.submitForm = function(e) {
        e.preventDefault();
        this.modal.getRoot().find('form').submit();
    };

    return /** @alias module:core_group/AjaxForms */ {
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
            return new AjaxForms(args);
        },
        load: function () {
            $(document).on('click', '#fitem_id_department .form-autocomplete-selection .badge.badge-info, #fitem_id_department .form-autocomplete-suggestions [role="option"]', function(){
                $('#fitem_id_subdepartment .form-autocomplete-selection .badge.badge-info').trigger('click');
            });
        }
    };
});