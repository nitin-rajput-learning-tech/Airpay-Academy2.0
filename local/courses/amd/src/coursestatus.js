/**
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
		'core/str',
		'core/modal_factory',
		'core/modal_events',
		'core/fragment',
		'core/ajax',
		'core/yui',
		'jqueryui'],function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    var courseStatus = function(args){
    	var self = this;
        self.init(args);
    };

    courseStatus.prototype.modal = null;

    courseStatus.prototype.contextid = -1;

    courseStatus.prototype.init = function(args){
    	var self = this;
    	var buttonname = $("#progressbardisplay_course").attr('data-name');
    	return Str.get_string('course_status_popup', 'local_courses',buttonname).then(function(title) {
    		return ModalFactory.create({
                type: ModalFactory.types.CANCEL,
                title: title,
                body: self.getBody(args)
            });
            
    	}.bind(self)).then(function(modal) {
			self.modal = modal;
	        self.modal.show();
    		

	        self.modal.setLarge();
	        self.modal.getRoot().on(ModalEvents.hidden, function() {
	            self.modal.destroy();
	        }.bind(this));
	    }.bind(this));
	};

	courseStatus.prototype.getBody = function(args){
		console.log(args);
		return Fragment.loadFragment('local_courses', 'coursestatus_display', 1, args);
	};

	return /** @alias module:local_courses/statuspopup */ {
        // Public variables and functions.
        /**
         * @method statuspopup
         * @param {string} args
         * @return {Promise}
         */
    	statuspopup : function(args){
    		return new courseStatus(args);
    	},
    	
		load : function(){
		}
	};
});
