/**
 * Add a create new group modal to the page.
 *
 * @module     local_courses/courseAjaxform
 * @class      courseAjaxform
 * @package    local_courses
 * @copyright  2018 Sreenivas
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
        'core/notification',
        'core/custom_interaction_events',
        /*'local_kpi/smarttable'*/],
        function($, Str, ModalFactory, ModalEvents, fragment, Ajax, Y, Templates, Notification, CustomEvents/*, Smarttable*/) {
        var SELECTORS = {
            ELEMENT: '[data-fg]',
        };
        var DATAATTRIBUTES = {
            ELEFG: 'fg',
            PLUGIN: 'plugin',
            METHOD: 'method',
            PARAMS: 'params'
        }
        var Fragment = function(fgelement) {
            this.contextid = M.cfg.contextid;
            this.fgelement = fgelement;
            this.id = fgelement.data('id') || 0;
            this.pluginname = fgelement.data(DATAATTRIBUTES.PLUGIN);
            this.method = fgelement.data(DATAATTRIBUTES.METHOD);
            this.level = fgelement.data(DATAATTRIBUTES.ELEFG);
            this.args = {};
            this.args.contextid = this.contextid;
            this.args.id = this.id;
            var params = {};
            if (typeof fgelement.data(DATAATTRIBUTES.PARAMS) !== 'undefined') {
                params = fgelement.data(DATAATTRIBUTES.PARAMS);
            }
            this.args.params = JSON.stringify(params);
            this.init();
        };

        Fragment.prototype.contextid = -1;

        Fragment.prototype.id = 0;

        Fragment.prototype.level = 'c';

        Fragment.prototype.strings = {};

        Fragment.prototype.args = {};

        Fragment.prototype.init = function () {
            var self = this;
            var stringsPromise = this.getStrings();
            var type = ModalFactory.types.SAVE_CANCEL;
            if (self.level == 'r') {
                type = ModalFactory.types.DEFAULT;
            }
            var modalPromise = ModalFactory.create({
                type: type
            });
            $.when(stringsPromise, modalPromise).then(function(strings, modal) {
                // Keep a reference to the modal.
                this.modal = modal;

                this.modal.setTitle(strings[0]);
                // Forms are big, we want a big modal.

                // We want to reset the form every time it is opened.
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.destroy();
                }.bind(this));

                if (self.level == 'd') {
                    this.modal.setBody(strings[1]);
                    this.modal.getRoot().on(ModalEvents.save, function() {
                        self.deleteInstance();
                    });
                } else {
                    this.modal.setBody(this.getBody());
                    this.modal.setLarge();
                    // We catch the modal save event, and use it to submit the form inside the modal.
                    // Triggering a form submission will give JS validation scripts a chance to check for errors.
                    this.modal.getRoot().on(ModalEvents.save, this.submitForm.bind(this));
                    // We also catch the form submit event and use it to submit the form with ajax.
                    this.modal.getRoot().on('submit', 'form', this.submitFormAjax.bind(this));
                }
                if(self.method == 'evaluation_update_status' || self.method == 'course_update_status'){
                    this.modal.setSaveButtonText(strings[0]);
                }else if(self.level != 'r'){
                    this.modal.setSaveButtonText(strings[1]);
                }
                

                // return this.modal;
                this.modal.show();
                // modal.getRoot().click(function(e) {
                //     e.preventDefault();
                //     // alert('here');
                //     this.modal.show();
                // }.bind(this));
                this.modal.getRoot().on(ModalEvents.bodyRendered, function() {
                    if (self.level == 'r' && (self.method == 'peer_allocation_status' || self.method == 'peer_badge_status')){
                        // Smarttable.init();
                    }else if(self.level == 'u' && self.method == 'addnew_question'){
                        var params = JSON.parse(this.args.params);
                        var itemid = params.itemid;
                        var evalid = params.evalid;
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
                        $('#select_question_type_survey').addClass('hidden');
                    }
                }.bind(this));
                if((self.level == 'c' || self.level == 'u') && self.method != 'evaluation_update_status'){
                    self.modal.getRoot().addClass('openLMStransition costcenter');
                    this.modal.getRoot().animate({"right":"0%"}, 500);
                    this.modal.getRoot().on(ModalEvents.hidden, function() {
                    //this.modal.destroy();
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                    modal.destroy();
                    }, 1000);
                    }.bind(this));
                }
            }.bind(this));
        };
        /**
         * [getStrings description]
         * @method getStrings
         * @param  {[type]}   StringData [description]
         * @return {[type]}              [description]
         */
        Fragment.prototype.getStrings = function() {
            var self = this;
            var StringData = this.requiredStrings();
            var RequiredStrings = [];
            var i = 0;
            $.each (StringData, function(key, value) {
                RequiredStrings[i] = {key: key, component: self.pluginname, param: value};
                i++;
            });
            var stringsPromise = Str.get_strings(RequiredStrings);
            return stringsPromise;
        };

        /**
         * @method getBody
         * @private
         * @return {Promise}
         */
        Fragment.prototype.getBody = function(formdata) {
            if (typeof formdata === "undefined") {
                formdata = {};
            }
            // Get the content of the modal.
            this.args.jsonformdata = JSON.stringify(formdata);
            return fragment.loadFragment(this.pluginname, this.method, this.contextid, this.args);
        };

        /**
         * @method handleFormSubmissionResponse
         * @private
         * @return {Promise}
         */
        Fragment.prototype.handleFormSubmissionResponse = function() {
            this.modal.hide();
            // We could trigger an event instead.
            // Yuk.
            Y.use('moodle-core-formchangechecker', function() {
                M.core_formchangechecker.reset_form_dirty_state();
            });
            // if(this.method == 'create_badge') {
            //     var params = JSON.parse(this.args.params);
            //     var badgetype = params.badgetype;
            //     var accountid = params.accountid;
            //     if(typeof badgetype == 'undefined'){
            //         var formcontent = this.modal.getRoot().find('form').serializeArray();
            //         var accountid = '';
            //         var badgetype = '';
            //         $.each(formcontent, function() {
            //             if(this.name == 'costcenterid'){
            //                 accountid = this.value;
            //             }else if(this.name == 'type'){
            //                 badgetype = this.value;
            //             }
            //         });
            //         $("#badgeaccountselect").val(accountid);
            //         var element = $(".nav-item.badgetype");
            //         $.each(element, function(){
            //             $(this).data('accountid', accountid);
            //         });
            //         $('.gamificationtab').removeClass('active');
            //         $("[data-badgetype='"+badgetype+"'] .gamificationtab").addClass('active');
            //         // var badgedefault = $(".nav-item.badgetype .active").parent();
            //         // var badgetype = badgedefault.data('badgetype');
            //         // var accountid = badgedefault.data('accountid');
            //     }
            //     $.ajax({
            //         method: "POST",
            //         dataType: "json",
            //         url:M.cfg.wwwroot+"/blocks/gamification/customajax.php",
            //         data: {action: "get_badge_table", accountid: accountid, type: badgetype},
            //         success: function(data){
            //             $('#badge_content').html(data);
            //         }
            //     });
            // } else if(this.method == 'award_peer_badge'){
                
            // } else {
                document.location.reload();
            // }
        };

        /**
         * @method handleFormSubmissionFailure
         * @private
         * @return {Promise}
         */
        Fragment.prototype.handleFormSubmissionFailure = function(data) {
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
        Fragment.prototype.submitFormAjax = function(e) {
            // We don't want to do a real form submission.
            e.preventDefault();
            var self = this;
            // Convert all the form elements values to a serialised string.
            var formData = this.modal.getRoot().find('form').serialize();
            this.args.jsonformdata = JSON.stringify(formData);
            // Now we can continue...
            var promise = Ajax.call([{
                methodname: this.pluginname + '_' + this.method,
                args: this.args,
                // done: this.handleFormSubmissionResponse.bind(this, formData),
                // fail: this.handleFormSubmissionFailure.bind(this, formData)
            }]);
             promise[0].done(function(resp){
                if (resp.nextstep) {
                    self.modal.destroy();
                    self.args.id = resp.id;
                    self.init(self.fgelement);
                    self.method = resp.method;
                } else {
                    // self.modal.destroy();
                    // window.location.reload();
                self.handleFormSubmissionResponse(formData);
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
        Fragment.prototype.submitForm = function(e) {
            e.preventDefault();
            this.modal.getRoot().find('form').submit();
        };

        Fragment.prototype.deleteInstance = function() {
            var self = this;
            Ajax.call([{
                methodname: this.pluginname + '_' + this.method,
                args: this.args
            }])[0].done(function(data){
                self.handleSubmissionResponse(data);
            }).fail(function(error){
                self.handleSubmissionFailure(error);
            });
        };
        /**
         * @method handleFormSubmissionResponse
         * @private
         * @return {Promise}
         */
        Fragment.prototype.handleSubmissionResponse = function() {
            this.modal.destroy();
            Notification.addNotification({
                message: 'Success',
                type: "success"
            });
            if(this.method == 'evaluation_update_status' || this.method == 'course_update_status'){
                $('form#filteringform.mform #id_filter_apply').trigger('click');
            }
        };

        /**
         * @method handleFormSubmissionFailure
         * @private
         * @return {Promise}
         */
        Fragment.prototype.handleSubmissionFailure = function(error) {
            this.modal.destroy();
            Notification.addNotification({
                message: error.message,
                type: "error"
            });
        };

        Fragment.prototype.requiredStrings = function() {
            var StringData = {};
            var PluginModule = require(this.pluginname  + '/' + this.pluginname.split('_')[1]);
            if (typeof PluginModule.requiredStrings === 'function') {
                StringData = PluginModule.requiredStrings(this);
            }
            switch (this.method) {
                case 'addnew_question':
                    switch(this.level){
                        case 'c':
                            StringData.le_createnewquestion = 'le_createnewquestion';
                            StringData.le_create = 'le_create';
                        break;
                        case 'u':
                            StringData.le_updatequestion = 'le_updatequestion';
                            StringData.le_update = 'le_update';
                        break;
                    }
                break;
                case 'evaluation_update_status':
                    StringData.confirm = '';
                    var localparams = JSON.parse(this.args.params);
                    var evalstatus = localparams.eval_status;
                    var evalname = localparams.evalname;
                    var published = localparams.published;
                    // if(published == 0){
                    //     StringData.publishevaluation = evalname;
                    // }else{
                        if(evalstatus == 1){
                            StringData.hideevaluation = evalname;
                        }else{
                            StringData.showevaluation = evalname;
                        }
                    // }
                break;
                case 'course_update_status':
                    StringData.courseconfirm = '';
                    var localparams = JSON.parse(this.args.params);
                    var coursename = localparams.coursename;
                    var coursestatus = localparams.coursestatus
                    if(coursestatus == 1){
                        StringData.disablecourse = coursename;
                    }else{
                        StringData.enablecourse = coursename;

                    }
                break; 
                default:
                break;
            }
            this.strings = StringData;
            return StringData;
        };
    return {
        init: function() {
            $(document).on('click', SELECTORS.ELEMENT, function(e) {
                e.preventDefault();
                var fgelement = $(this);
                var callFragment = new Fragment(fgelement);
            });
        }
    };
});