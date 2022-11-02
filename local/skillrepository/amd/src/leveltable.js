define([
    'local_skillrepository/jquery.dataTables',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/fragment',
    'jquery',
    'jqueryui',
], function (dataTable, Str, ModalFactory, ModalEvents, Ajax, Fragment, $) {
    var Newlevel = function(args){
            this.args = args;
            var self = this;
            self.init(args);
        };

        /**
         * @var {Modal} modal
         * @private
         */
        Newlevel.prototype.modal = null;
     
        /**
         * @var {int} contextid
         * @private
         */
        Newlevel.prototype.contextid = -1;

        Newlevel.prototype.init = function(args) {
            // console.log(args);
            //var triggers = $(selector);
            var self = this;
            if(args.levelid){
                var head = Str.get_string('update_level', 'local_skillrepository');
            }else{
                var head = Str.get_string('create_level', 'local_skillrepository');
            }
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
                // Forms are big, we want a big modal.
                self.modal.setLarge();
                this.modal.getRoot().addClass('openLMStransition local_skillrepository');
     
                // We want to reset the form every time it is opened.
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                        modal.destroy();
                    }, 5000);
                }.bind(this));
                // self.modal.getRoot().on(ModalEvents.hidden, function() {
                //     modal.hide();
                //         setTimeout(function(){
                //             modal.destroy();
                //         }, 5000);
                //     //     self.modal.setBody(self.getBody());
                //     }.bind(this));
                        self.modal.getRoot().on(ModalEvents.shown, function() {
                        self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                        this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                            modal.hide();
                            setTimeout(function(){
                                modal.destroy();
                            }, 5000);
                            // modal.destroy();
                        });
                    }.bind(this));
        
                    // We catch the modal save event, and use it to submit the form inside the modal.
                    // Triggering a form submission will give JS validation scripts a chance to check for errors.
                    self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
                    // We also catch the form submit event and use it to submit the form with ajax.
                    self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
                    self.modal.show();
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
        Newlevel.prototype.getBody = function(formdata) {
            if (typeof formdata === "undefined") {
                formdata = {};
            }
            // Get the content of the modal.
            var params = {levelid:this.args.levelid, jsonformdata: JSON.stringify(formdata)};
            return Fragment.loadFragment('local_skillrepository', 'level_form', this.args.contextid, params);
        
        };

        /**
         * @method handleFormSubmissionResponse
         * @private
         * @return {Promise}
         */
        Newlevel.prototype.handleFormSubmissionResponse = function() {
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
        Newlevel.prototype.handleFormSubmissionFailure = function(data) {
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
        Newlevel.prototype.submitFormAjax = function(e) {
            // We don't want to do a real form submission.
            e.preventDefault();
     
            // Convert all the form elements values to a serialised string.
            var formData = this.modal.getRoot().find('form').serialize();
            
            // Now we can continue...
            Ajax.call([{
                methodname: 'local_skillrepository_submit_level_form',
                args: {contextid: this.args.contextid, jsonformdata: formData},
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
        Newlevel.prototype.submitForm = function(e) {
            e.preventDefault();
            var self = this;
            self.modal.getRoot().find('form').submit();
        };
	return{
        init: function(args){
            return new Newlevel(args);
        },
		leveltable: function(){
			$("#all_levels_display_table").dataTable({
                "processing": true,
                "bServerSide": true,
                "sAjaxSource":M.cfg.wwwroot + "/local/skillrepository/ajax.php?action=getlevelstable",
                "aaSorting": [],
                "pageLength": 10,
            });
            $("#all_levels_display_table").css('width', '100%');
		},
		deletelevel: function(args){
			return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'deletelevelconfirm',
                component: 'local_skillrepository',
                param :args
            },
            {
                key: 'delete'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.CONFIRM,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    //modal.setSaveButtonText(s[2]);
                    modal.setSaveButtonText(Str.get_string('yes_delete', 'local_skillrepository'));


                    //For cancel button string changed//
                    var value=Str.get_string('cancel', 'local_skillrepository');
                    var button = this.modal.getFooter().find('[data-action="cancel"]');
                    this.modal.asyncSet(value, button.text.bind(button));
                    
                    modal.getRoot().on(ModalEvents.yes, function(e) {
                        e.preventDefault();
                        args.confirm = true;
                        $.ajax({
                            method: "POST",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/skillrepository/ajax.php?action=deletelevel&levelid="+args.levelid,
                            success: function(data){
                                window.location.reload();
                            }
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));

		},
	}
});
