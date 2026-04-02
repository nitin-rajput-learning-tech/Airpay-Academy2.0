/**
 * Add a create new group modal to the page.
 *
 * @module     local_myteam/popupcount
 * @class      popupcount
 * @package    local_myteam
 * @copyright  2018 sarath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events',
        'core/fragment', 'core/ajax', 'core/yui', 'jqueryui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {
 
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
        // this.id = args.id;
        // this.username = args.username;
        // this.moduletype = args.moduletype;
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
        // $(selector).click(function(){
        $(document).on('click', selector, function(){
            // self.contextid = 1;
            self.id = $(this).data('userid');
            self.username = $(this).data('username');
            self.moduletype = $(this).data('moduletype');
  
            Str.get_string('myteaminfo', 'local_myteam',self).then(function(title) {
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
                         //self.dataTableshow();
                    }.bind(this));                                    
                                  
                });    

            });
        });
                    
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
        if(typeof this.id != 'undefined'){
            var params = {id:this.id, moduletype:this.moduletype, jsonformdata: JSON.stringify(formdata)};
        }else{
            var params = {};
        }
        return Fragment.loadFragment('local_myteam', 'users_display_modulewise', this.contextid, params);
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
        init: function(args) {
            // alert('there');
            return new NewPopup(args);
        },
    };
});