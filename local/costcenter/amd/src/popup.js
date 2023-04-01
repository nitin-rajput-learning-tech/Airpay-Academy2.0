/**
 * Add a create new group modal to the page.
 *
 * @module     local_courses/newcourse
 * @class      NewCourse
 * @package    local_courses
 * @copyright  2017 Shivani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_costcenter/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events',
        'core/fragment', 'core/ajax', 'core/yui', 'jqueryui'],
        function(DataTable, $, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewPopup = function(args) {
        this.contextid = args.contextid;
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
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    NewPopup.prototype.init = function(selector) {
            
 
 
        //var triggers = $(selector);
        var self = this;
        // Fetch the title string.
        $(selector).click(function(){
            alert($(this).data('roleid'));
            
            self.roleid = $(this).data('roleid');
            self.rolename = $(this).data('rolename');

            Str.get_string('assignrole', 'local_assignroles', self).then(function(title) {
            
                ModalFactory.create({
                    type: ModalFactory.types.CANCEL,
                    title: title,
                    body: self.getBody()
                }).done(function(modal) {
                    // Keep a reference to the modal.
                    self.modal = modal;
          

                    // Forms are big, we want a big modal.
                    self.modal.setLarge();
         
                    // We want to reset the form every time it is opened.
                    self.modal.getRoot().on(ModalEvents.hidden, function() {
                        // self.modal.setBody('');
                        self.modal.hide();
                        self.modal.destroy();
                    }.bind(this));

                    // We want to reset the form every time it is opened.
                    self.modal.getRoot().on(ModalEvents.cancel, function() {
                        // self.modal.setBody('');
                        self.modal.hide();
                        self.modal.destroy();
                    }.bind(this));
                    self.modal.show();

                    self.modal.getRoot().on(ModalEvents.bodyRendered, function() {
                         self.dataTableshow(self.roleid);
                    }.bind(this));                                    
                                  
                });    

            });
        });
                    
    };
    NewPopup.prototype.dataTableshow = function(roleid){
        Str.get_strings([{
            key: 'nodata_available',
            component: 'local_costcenter',
        },
        {
            key: 'search',
            component: 'local_costcenter',
        }
        ]).then(function(s) {
            $('#popup_user'+roleid).DataTable({
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
     * @return {Promise}
     */
    NewPopup.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }

        // Get the content of the modal.
        if(typeof this.roleid != 'undefined'){
            var params = {roleid:this.roleid, jsonformdata: JSON.stringify(formdata)};
        }else{
            var params = {};
        }
        // console.log(params);
        // alert(params);
        return Fragment.loadFragment('local_costcenter', 'roleusers_display', this.contextid, params);
    };
    var unassignuser = function(args){
        return Str.get_strings([{
            key: 'confirmation',
            component: 'local_costcenter',
        },
        {
            key: 'unassignconfirm',
            component: 'local_costcenter',
            param :args
        },
        {
            key: 'unassign',
            component: 'local_assignroles'
        }]).then(function(s) {
            ModalFactory.create({
                title: s[0],
                type: ModalFactory.types.SAVE_CANCEL,
                body: s[1]
            }).done(function(modal) {
                this.modal = modal;
                modal.setSaveButtonText(s[2]);
                modal.getRoot().on(ModalEvents.save, function(e) {
                    e.preventDefault();
                    args.confirm = true;
                   // console.log(args);
                    var params = {};
                    params.contextid = args.contextid;
                    params.roleid = args.roleid;
                    params.userid = args.userid;
                    var promise = Ajax.call([{
                        methodname: 'local_assignroles_unassign_role',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        // this.modal.hide();
                        // this.modal.destroy();
                        window.location.href = window.location.href;
                    }).fail(function(ex) {
                        // do something with the exception
                         //console.log(ex);
                    });
                }.bind(this));
                modal.show();
            }.bind(this));
        }.bind(this));
    }
 
 
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
        init: function(args) {
           
            this.Datatable();
            return new NewPopup(args);
        },
        Datatable: function() {
            
        },
        unassignConfirm: function(args) {
            return unassignuser(args);
        },
    };
});