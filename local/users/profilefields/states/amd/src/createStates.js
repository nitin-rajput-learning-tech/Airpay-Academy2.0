/**
 * Add a create new group modal to the page.
 *
 * @module     usersprofilefields/states
 * @class      states
 * @package
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    /**
     * Constructor
     * @type {srgs}
     *
     *
     * Each call to init gets it's own instance of this class.
     */
    var createStates = function(args) {
        this.contextid = args.contextid;
        this.statesid = args.statesid;
        var self = this;
        self.init(args);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    createStates.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    createStates.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     *
     * @type {srgs}
     * @private
     * @return {Promise}
     */
    createStates.prototype.init = function() {

        var self = this;
        var head;
        if(this.statesid){
            head = Str.get_string('updatestates', 'usersprofilefields_states');
        }else{
            head = Str.get_string('createstates', 'usersprofilefields_states');
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

            // We want to reset the form every time it is opened.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                setTimeout(function(){
                    modal.destroy();
                }, 1000);
            }.bind(this));

            // We want to hide the submit buttons every time it is opened.
            self.modal.getRoot().on(ModalEvents.shown, function() {
                self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                });
            }.bind(this));


            // We catch the modal save event, and use it to submit the form inside the modal.
            // Triggering a form submission will give JS validation scripts a chance to check for errors.
            self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
            // We also catch the form submit event and use it to submit the form with ajax.
            self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
            self.modal.show();
            return this.modal;
        }.bind(this));
    };

    /**
     * @method getBody
     * @private
     * @return {Promise}
     * @type {formdata}
     */
    createStates.prototype.getBody = function(formdata) {
        // alert(this);
        // console.log(this);
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {id: this.statesid, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('usersprofilefields_states', 'create_states', this.contextid, params);
    };

    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    createStates.prototype.handleFormSubmissionResponse = function() {
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
     * @type {data}
     */
    createStates.prototype.handleFormSubmissionFailure = function(data) {
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
    createStates.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // alert(this.contextid);
        // Now we can continue...
        Ajax.call([{
            methodname: 'usersprofilefields_states_create_states',
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
    createStates.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };

    return /** @alias module:usersprofilefields_states/states */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         *
         *
         * @return {Promise}
         * @type {srgs}
         */
        init: function(args) {

            // alert(args.contextid);
            // console.log(args);
            return new createStates(args);
        },
        deleteField: function(args) {

            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'deletefieldconfirmstates',
                component: 'usersprofilefields_states',
                param :args
            },
            {
                key: 'yesdelete',
                component: 'usersprofilefields_states',
                param :args
            },
            {
                key: 'no',
                component: 'usersprofilefields_states',
                param :args
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+ s[2] +'</button>&nbsp;' +
                    '<button type="button" class="btn btn-secondary" data-action="cancel">'+ s[3] +'</button>'
                })
                .done(function(modal) {
                    this.modal = modal;

                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        args.confirm = true;
                        $.ajax({
                            method: "POST",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/users/profilefields/states/ajax.php?reason="+
                                args.selector+"&componentid="+args.compid,
                            success: function(){
                                window.location.reload();
                            }
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
        nodeleteField: function(args) {

            return Str.get_strings([{
                key: 'reason',
                component: 'usersprofilefields_states',
            },
            {
                key: 'deletenotconfirm',
                component: 'usersprofilefields_states',
                param :args
            },
            {
                key: 'delete',
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                })
                .done(function(modal) {
                    this.modal = modal;
                    modal.show();
                }.bind(this));

            }.bind(this));
        },
        profileTableDataTables: function(args){
            $("#"+args).dataTable({
                "searching": true,
                "autoWidth":false,
                "responsive": false,
                "language": {
                    "paginate": {
                        "previous": "<",
                        "next": ">"
                    }
                },
                "aaSorting": [],
                "pageLength": 10,
            });
        },
        load: function(){
        }
    };
});