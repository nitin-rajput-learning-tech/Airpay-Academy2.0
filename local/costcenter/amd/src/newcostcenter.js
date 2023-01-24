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
     * @param {object} args
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewCostcenter = function(args) {
        this.contextid = args.contextid;
        // this.costcenterid = args.costcenterid;
        // this.parentid = args.parentid;
        this.formtype = args.formtype;
        this.id = args.id;
        this.headstring = args.headstring;
        var self = this;
        self.init();
    };
 
    /**
     * @var {Modal} modal
     * @private
     */
    NewCostcenter.prototype.modal = null;
 
    /**
     * @var {int} contextid
     * @private
     */
    NewCostcenter.prototype.contextid = -1;
 
    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    NewCostcenter.prototype.init = function() {
        var self = this;
        var editid = $(this).data('value');
        if (editid) {
            self.costcenterid = editid;
        }
        console.log(self);

        // if(self.costcenterid && self.parentid == 0){
        //    var head =  Str.get_string('editcostcen', 'local_costcenter');
        // }else if(self.parentid == 0){
        //    var head = Str.get_string('adnewcostcenter', 'local_costcenter');
        // }else if(self.parentid > 0 && self.costcenterid){
        //     var head = Str.get_string('editdep', 'local_costcenter');
        // }else if(self.parentid > 0 ){
        //     var head = Str.get_string('adnewdep', 'local_costcenter');
        // }
        var head = Str.get_string(this.headstring, 'local_costcenter');
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
           
            self.modal.getRoot().addClass('openLMStransition local_costcenter');
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
        
    };
 
    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    NewCostcenter.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {jsonformdata: JSON.stringify(formdata), formtype:this.formtype, id:this.id};
        return Fragment.loadFragment('local_costcenter', 'new_costcenterform', this.contextid, params);
    };
 
    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewCostcenter.prototype.handleFormSubmissionResponse = function() {
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
    NewCostcenter.prototype.handleFormSubmissionFailure = function(data) {
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
    NewCostcenter.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();
 
        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_costcenter_submit_costcenterform_form',
            args: {contextid: this.contextid, jsonformdata: JSON.stringify(formData)},
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
    NewCostcenter.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };
    return /** @alias module:local_costcenter/NewCostcenter */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {object} args
         * @return {Promise}
        */
        init: function(args) {
            return new NewCostcenter(args);
        },
        load: function(){

        },
        changeElement: function(event){
            var elemvalue = $(event.target).val();
            // if(parseInt(elemvalue) > 0){
                var depth = $(event.target).data('depth');
                $.each($('[data-action="costcenter_element_selector"]'), function(index, value){
                    if($(value).data('depth') > depth){
                        $(value).html('');
                        $(value).parent().find('.form-autocomplete-selection').html($(value).data('selectstring'));
                    }
                });
                if(depth == 1){
                    var value = $('[data-class="supervisor_select"]');
                    value.html('');
                    value.parent().find('.form-autocomplete-selection').html(value.data('selectstring'));;
                }
            // }
        },
        /**
         * modal for course status.
         *
         * @method costcenterStatus
         * @param {object} args
         * @return {modal}
        */
        costcenterStatus: function(args) {
            console.log(args);
            return Str.get_strings([{
                key: 'confirm',
                component: 'local_costcenter',
            }]).then(function(str) {
                ModalFactory.create({
                    title: args.actionstatus,
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: args.actionstatusmsg
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(str[0]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                        args.confirm = true;
                        var promise = Ajax.call([{
                            methodname: 'local_costcenter_status_confirm',
                            args: args
                        }]);
                        promise[0].done(function(resp) {
                            window.location.href = window.location.href;
                        }).fail(function(ex) {
                            // do something with the exception
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
          
        },
        downloadtrigger: function(){
            $(document).on('click', '.custom_content_download', function (){
                var data = $(this).data();
                // var formdata = JSON.stringify(JSON.stringify($('#'+data.formid).serialize()));
                // var formdata = $('#'+data.formid).serializeArray();
                // filterdatavalue = [];
                // $.each(formdata, function (i, field) {
                //     valuedata = [];
                //     if(field.name != '_qf__filters_form' && field.name != 'sesskey'){
                //         if(field.name == 'options' || field.name == 'dataoptions'){
                //             values[field.name] = field.value;
                //         }else{
                //             var str = field.name;
                //             if(str.indexOf('[]') != -1){
                //                 field.name = str.substring(0, str.length - 2);
                //             }
                //             if(field.name in filterdatavalue){
                //                 filterdatavalue[field.name] = filterdatavalue[field.name]+','+field.value;
                //             }else{  
                //                 filterdatavalue[field.name] = field.value;
                //             }
                //         }

                //     }
                // });
                // var filtervalue = $('#global_filter').val();
                // if(filtervalue){
                //     filterdatavalue[$('#global_filter').attr('name')] = filtervalue;
                // }
                var formdata = $('#global_filter').attr('data-filterdata');
                window.location.href = data.href+'?formdata='+formdata;
            });
        }
    };
});