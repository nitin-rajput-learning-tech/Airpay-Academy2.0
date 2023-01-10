/**
 * Add a create new feedback modal to the page.
 *
 * @module     local_evaluation/newevaluation
 * @class      NewEvaluation
 * @package    local_evaluation
 * @copyright  2019 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui', 'local_evaluation/jquery.dataTables'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewEvaluation = function(selector, contextid, evalid, instance, plugin) {
        this.contextid = contextid;
        this.evalid = evalid;
        this.instance = instance;
        this.plugin = plugin;
        var self = this;
        self.init(selector,plugin,evalid);
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    NewEvaluation.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    NewEvaluation.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    NewEvaluation.prototype.init = function(selector,plugin,evalid) {

        var self = this;       
        // Fetch the title string.
        if (plugin === 'classroom'||plugin === 'program'||plugin === 'certification') {
            var editid = evalid;
                if (editid>0) {
                    self.evalid = editid;
                    update_string = Str.get_string('update_evaluation', 'local_evaluation');
                } else {
                    self.evalid = -1;
                    update_string = Str.get_string('create_evaluation', 'local_evaluation');
                }
                
                return update_string.then(function(title) {
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
                    self.modal.getRoot().addClass('openLMStransition local_evaluation');
                    self.modal.getRoot().animate({"right":"0%"}, 500);
         
                    // We want to reset the form every time it is opened.
                    self.modal.getRoot().on(ModalEvents.hidden, function() {
                        //self.modal.setBody(self.getBody());
                        self.modal.getRoot().animate({"right":"-85%"}, 500);
                        setTimeout(function(){
                            modal.destroy();
                        }, 1000);
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
        }
        else {
            $(document).on('click', selector, function(){
                
                var editid = $(this).data("value");
                if (editid>0) {
                    self.evalid = editid;
                    update_string = Str.get_string('update_evaluation', 'local_evaluation');
                } else {
                    self.evalid = -1;
                    update_string = Str.get_string('create_evaluation', 'local_evaluation');
                }
                return update_string.then(function(title) {
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
                    self.modal.getRoot().addClass('openLMStransition local_evaluation');
                    self.modal.getRoot().animate({"right":"0%"}, 500);
         
                    // We want to reset the form every time it is opened.
                    self.modal.getRoot().on(ModalEvents.hidden, function() {
                        //self.modal.setBody(self.getBody());
                        self.modal.getRoot().animate({"right":"-85%"}, 500);
                        setTimeout(function(){
                            modal.destroy();
                        }, 1000);
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
            
            });
        }
        
    };
    
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    NewEvaluation.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        
        // Get the content of the modal.
        var params = {evalid:this.evalid, instance:this.instance, plugin:this.plugin, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('local_evaluation', 'new_evaluation_form', this.contextid, params);
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewEvaluation.prototype.handleFormSubmissionResponse = function(evalid) {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });

        // modal to show the procedure thereof
        var params = { id: evalid, sesskey: M.cfg.sesskey};
        var returndata =  Fragment.loadFragment('local_evaluation', 'addquestions_or_enrol', this.contextid, params);

        ModalFactory.create({
            title: Str.get_string('pluginname', 'local_evaluation'),
            body: returndata
        }).done(function(modal) {
            // Do what you want with your new modal.
            modal.show();
            modal.getRoot().find('[data-action="hide"]').on('click', function() {
            modal.hide();
            setTimeout(function(){
                 window.location.reload();
            }, 500);
            });
        });
    };
 
    /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    NewEvaluation.prototype.handleFormSubmissionFailure = function(data) {
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
    NewEvaluation.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();
 
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();

        // Now we can continue...
        var promise = Ajax.call([{
            methodname: 'local_evaluation_submit_create_evaluation_form',
            //args: {evalid:this.evalid, contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
            args: {contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
            fail: this.handleFormSubmissionFailure.bind(this, formData)
        }]);
        var self =this;
        promise[0].done(function(resp){
            self.handleFormSubmissionResponse(resp);        
        });
    };
 
    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    NewEvaluation.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };

    return /** @alias module:local_evaluation/newevaluation */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {string} selector The CSS selector used to find nodes that will trigger this module.
         * @param {int} contextid The contextid for the course.
         * @return {Promise}
         */
        init: function(selector, contextid,evalid, instance, plugin) {
            return new NewEvaluation(selector, contextid, evalid, instance, plugin);
        },
        enrolledusers: function(id, type, contextid,testname) {
            // modal to show the procedure thereof
            var params = { id: id, type:type};
            var returndata =  Fragment.loadFragment('local_evaluation', 'enrolledusers', contextid, params);

            ModalFactory.create({
                title: testname,
                body: returndata
            }).done(function(modal) {
                // Do what you want with your new modal.
                modal.show();
                modal.setLarge();
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.setBody('');
                }.bind(this));
                modal.getRoot().find('[data-action="hide"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                         modal.destroy();
                    }, 500);
                });
            });
        },
        deleteevaluation: function(elem,name) {
            return Str.get_strings([{
                key: 'deleteevalaution',
                component: 'local_evaluation'
            }, {
                key: 'confirmdelete',
                component: 'local_evaluation',
                param:name
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+M.util.get_string("yes", "moodle")+'</button>&nbsp;' +
                    '<button type="button" class="btn btn-secondary" data-action="cancel">'+M.util.get_string("no", "moodle")+'</button>'
                }).done(function(modal) {
                    this.modal = modal;
                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        window.location.href ='index.php?id='+elem+'&confirm=1&delete=1&sesskey=' + M.cfg.sesskey;
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        getdepartmentlist: function() {
            $(document).on('change', '#id_costcenterid', function() {
                var costcentervalue = $(this).find("option:selected").val();
                var title = M.util.get_string("select_department", "local_onlinetests");
                if (costcentervalue && costcentervalue != 'null') {
                    var promise = Ajax.call([{
                        methodname: 'local_costcenter_departmentlist',
                        args: {
                            orgid: costcentervalue
                        },
                    }]);
                    promise[0].done(function(resp) {
                       var template =  '<option value=null>'+M.util.get_string("select_department", "local_evaluation")+'</option>';                                    
                            $.each(JSON.parse(resp.departments), function( index, value) {
                                template += '<option value = ' + index.id + ' >' +value.fullname + '</option>';
                            });
                            $('#id_departmentid').html(template);
                    }).fail(function() {
                        // do something with the exception
                        alert('Error occured while processing request');
                        window.location.reload();
                    });
                } else {
                    var template =  '<option value=null>Select Department</option>';
                    $('#id_departmentid').html(template);
                }
            });            
        },

        displayquestion: function(itemid, evalid) {
            if (evalid) {
                var promise = Ajax.call([{
                methodname: 'local_evaluation_displayquestion',
                args: {
                    itemid: itemid,
                    evalid: evalid,
                    typ:0
                },
                }]);
                promise[0].done(function(resp) {
                        $('#displayform').html(resp.formdata);
                }).fail(function() {
                    // do something with the exception
                    alert('Error occured while processing request');
                    // window.location.reload();
                });
            } else {
                $(document).on("change", ".target", function() {
                // $( ".target" ).change(function() {
                    var cmid = $( "#id_questiontyp" ).attr( "value" ); // evalid
                    var typ = $( "#id_questiontyp option:selected" ).val();
                    if(typ == 'pagebreak'){
                        window.location.reload();
                    } 
                    var promise = Ajax.call([{
                    methodname: 'local_evaluation_displayquestion',
                    args: {
                        itemid: itemid,
                        evalid: cmid,
                        typ: typ
                    },
                    }]);
                    promise[0].done(function(resp) {
                            $('#displayform').html(resp.formdata);
                    }).fail(function() {
                        // do something with the exception
                        alert('Error occured while processing request');
                        // window.location.reload();
                    });   
                });
            }                   
        },
        displaytemplate: function(itemid, evalid) {
            $( "#id_templateid" ).change(function() {
                var id = $( "#id_templateid" ).attr( "value" );
                var templateid = $( "#id_templateid option:selected" ).val();  
                var promise = Ajax.call([{
                    methodname: 'local_evaluation_displaytemplate',
                    args: {
                        id: id,
                        templateid: templateid
                    },
                }]);
                promise[0].done(function(resp) {
                        $('#displaytempalteform').html(resp.formdata);
                }).fail(function() {
                    // do something with the exception
                    alert('Error occured while processing request');
                    // window.location.reload();
                });
            });
               
        },
        load: function () {
            $(document).on('click', '#page_reload_forced', function(){
                window.location.reload();
            });
        }
    };
});
