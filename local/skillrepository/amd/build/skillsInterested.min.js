/**
 * Add a create new group modal to the page.
 *
 * @module     local_courses/skillsInterested
 * @class      skillsInterested
 * @package    local_courses
 * @copyright  eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 define(['local_courses/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui', 'core/templates'],
 function (dataTable, $, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y, Templates) {

   /**
   * Constructor
   *
   * @param {object} args
   *
   * Each call to init gets it's own instance of this class.
   */
   var skillsInterested = function (args) {
        this.contextid = 1;
        this.id = args.id;
        this.args = args;
        this.init(args);
   };

   /**
   * @var {Modal} modal
   * @private
   */
   skillsInterested.prototype.modal = null;

   /**
   * @var {int} contextid
   * @private
   */
   skillsInterested.prototype.contextid = -1;

   /**
   * Initialise the class.
   *
   * @param {String} selector used to find triggers for the new group modal.
   * @private
   * @return {Promise}
   */
  skillsInterested.prototype.init = function (args) {
    
     var self = this;
     return Str.get_string('skills_interested', 'local_skillrepository', self).then(function (title) {
         // Create the modal.
         return ModalFactory.create({
           type: ModalFactory.types.SAVE_CANCEL,
           title: title,
           body: self.getBody()
         });
     }.bind(self)).then(function (modal) {

       // Keep a reference to the modal.
       self.modal = modal;
      
       // We want to reset the form every time it is opened.
       this.modal.getRoot().on(ModalEvents.hidden, function () {
         //this.modal.getRoot().animate({ "right": "-85%" }, 500);
         setTimeout(function () {
           modal.destroy();
         }, 1000);
       }.bind(this));

       // We want to hide the submit buttons every time it is opened.
       self.modal.getRoot().on(ModalEvents.shown, function () {
         self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
         this.modal.getFooter().find('[data-action="cancel"]').on('click', function () {
           modal.hide();
           setTimeout(function () {
             modal.destroy();
           }, 1000);
           // modal.destroy();
         });
       }.bind(this));


       // We catch the modal save event, and use it to submit the form inside the modal.
       // Triggering a form submission will give JS validation scripts a chance to check for errors.
       self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
       // We also catch the form submit event and use it to submit the form with ajax.
       self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
       self.modal.show();
       this.modal.getRoot().animate({ "right": "10%" }, 800);
       return this.modal;
     }.bind(this));
   };

   /**
   * @method getBody
   * @private
   * @return {Promise}
   */
   skillsInterested.prototype.getBody = function (formdata) {
     if (typeof formdata === "undefined") {
       formdata = {};
     }
     //this.args.jsonformdata = JSON.stringify(formdata);
     return Fragment.loadFragment('local_skillrepository', 'skills_interested',this.contextid, this.args);

   };
   /**
   * @method handleFormSubmissionResponse
   * @private
   * @return {Promise}
   */
   skillsInterested.prototype.handleFormSubmissionResponse = function () {
     this.modal.hide();
     // We could trigger an event instead.
     // Yuk.
     Y.use('moodle-core-formchangechecker', function () {
       M.core_formchangechecker.reset_form_dirty_state();
     });
     document.location.reload();
   };

   /**
    * @method handleFormSubmissionFailure
    * @private
    * @return {Promise}
    */
   skillsInterested.prototype.handleFormSubmissionFailure = function (data) {
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
   skillsInterested.prototype.submitFormAjax = function (e) {
     // We don't want to do a real form submission.
     e.preventDefault();
     // Convert all the form elements values to a serialised string.
     var formData = this.modal.getRoot().find('form').serialize();
     var params = {};
     params.contextid = this.contextid;
     params.jsonformdata = JSON.stringify(formData);
   
     // Now we can continue...
     Ajax.call([{
       methodname: 'local_skillrepository_submit_skills_interested_form',
       args: params,
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
   skillsInterested.prototype.submitForm = function (e) {
     e.preventDefault();
     var self = this;
     self.modal.getRoot().find('form').submit();
   };

   return /** @alias module:local_skillrepository/skillsInterested */ {
     // Public variables and functions.
     /**
      * @method statuspopup
      * @param {string} args
      * @return {Promise}
      */
     init: function (args) {
        return new skillsInterested(args);
     },

     load: function () {
     }
   };

 });