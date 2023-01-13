/**
* Add a create new group modal to the page.
*
* @module     local_courses/newcourse
* @class      NewCourse
* @package
* @copyright  2017 Shivani
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
define(['local_assignroles/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events',
        'core/fragment','core/yui', 'jqueryui'],
        function(DataTable, $, Str, ModalFactory, ModalEvents, Fragment) {
    /**
     * Constructor
     * @param {String} args used to find triggers for the new group modal.
     * Each call to init gets it's own instance of this class.
     */
    var NewPopup = function(args) {
        this.contextid = args.contextid;
        this.id = args.id;
        var self = this;
        self.init(args.selector);
    };
    /**
     * @var {Modal} modal
     * @private
     */
    NewPopup.prototype.modal = null;
    /**
     * @var {int} contextid
     * @private
     */
    NewPopup.prototype.contextid = -1;
    /**
     * Initialise the class.
     *
     * @private
     * @return {Promise}
     */
    NewPopup.prototype.init = function() {
        var self = this;
        // Fetch the title string.
        // $(selector).click(function(){
        $(document).on('click', '.userpopup', function(){
            self.id = $(this).data('id');
            self.username = $(this).data('username');
            Str.get_string('usersrole', 'local_users', self).then(function(title) {

                ModalFactory.create({
                    // type: ModalFactory.types.CANCEL,
                    title: title,
                    body: self.getBody()
                }).done(function(modal) {
                    // Keep a reference to the modal.
                    self.modal = modal;

                   // Forms are big, we want a big modal.
                    self.modal.setLarge();

                   // We want to reset the form every time it is opened.
                    self.modal.getRoot().on(ModalEvents.hidden, function() {
                        self.modal.hide();
                        self.modal.destroy();
                    }.bind(this));

                   // We want to reset the form every time it is opened.
                    self.modal.getRoot().on(ModalEvents.cancel, function() {
                        self.modal.hide();
                        self.modal.destroy();
                    }.bind(this));
                    self.modal.show();

                   self.modal.getRoot().on(ModalEvents.bodyRendered, function() {
                         self.dataTableshow(self.id);
                    }.bind(this));

               });

            });

           });
    };
    NewPopup.prototype.dataTableshow = function(){
        Str.get_strings([
        ]).then(function(s) {
            $('#quiz_student_summary').DataTable({
                'bPaginate': true,
                'bFilter': true,
                'bLengthChange': true,
                'lengthMenu': [
                    [5, 10, 25, 50, 100, -1],
                    [5, 10, 25, 50, 100, 'All']
                ],
                'language': {
                    'emptyTable': s[0],
                    'infoEmpty': s[0],
                    'zeroRecords': s[0],
                    'paginate': {
                        'previous': '<',
                        'next': '>'
                    }
                },
                "oLanguage": {
                "sSearch": s[1]
                },

               'bProcessing': true,
            });
        }.bind(this));
        // $.fn.dataTable.ext.errMode = 'none';
    };
    /**
     * @method getBody
     * @private
     * @param {object} formdata
     * @return {Promise}
     */
    NewPopup.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        if(typeof this.id != 'undefined'){
            var params = {id:this.id, jsonformdata: JSON.stringify(formdata)};
        }else{
            var params = {};
        }
        return Fragment.loadFragment('local_users', 'userrole_display', this.contextid, params);
    };
    return /** @alias module:local_evaluation/newevaluation */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {string} args The CSS selector used to find nodes that will trigger this module.
         * @return {Promise}
         */
        init: function(args) {
            this.Datatable();
            return new NewPopup(args);
        },
        Datatable: function() {
        },
    };
});