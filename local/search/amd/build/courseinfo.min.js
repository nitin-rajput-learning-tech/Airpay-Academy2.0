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

    return {
        
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {string} selector The CSS selector used to find nodes that will trigger this module.
         * @param {int} contextid The contextid for the course.
         */
        init: function(args) {
            // console.log(args);
            var request = $.ajax({
                    url:  M.cfg.wwwroot + "/local/search/courseinfo.php",
                    method: "POST",
                    data: { id : args.courseid },
                    dataType: "html"
                });
                 
                request.done(function( returndata ) {
                    // alert(msg);
                    // $( "#page-content" ).html( returndata );
                    ModalFactory.create({
                            title: Str.get_string('courseinfo', 'local_search'),
                            body: returndata
                        }).done(function(modal) {
                        // Do what you want with your new modal.
                        modal.show();
                        modal.setLarge();
                        modal.getRoot().addClass('openLMStransition catalog_popup');
                        modal.getRoot().animate({"right":"0%"}, 500);
                        modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                            setTimeout(function(){
                                modal.destroy();
                            }, 1000);
                        }.bind(this));
                    });
            });

        },
       test: function(args){

         $.ajax({
             type: "GET",
             url : M.cfg.wwwroot + "/local/search/udemyajax.php?action=udemyuserlicense",
             success: function(response) {
             console.log(response);
             return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'course_self_enrol',
                component: 'local_search',
                param :args
            },
            {
                key: 'course_self_enrol_license',
                component: 'local_search',
                param: args
            },
            {
                key: 'confirm'
            }]).then(function(s) {
             
               if(response == true){ 
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: s[1]
                 }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[3]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                         $.ajax({
                            type: "GET",
                            url:  M.cfg.wwwroot + "/local/search/courseinfo.php?id="+args.courseid+"&enrol="+args.enroll+"",

                            success: function(returndata) {
                                // console.log(returndata);
                                modal.hide();
                                // $(".enrolled"+args.courseid+"").html(returndata);
                                window.location.href = M.cfg.wwwroot + "/course/view.php?id="+args.courseid;
                            }
                        });
                  }.bind(this));
                    modal.show();
                }.bind(this));
              }
              else if(response == false){
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.CANCEL,
                    body: s[2]
                }).done(function(modal) {
                    this.modal = modal;
                    modal.show();
                }.bind(this));
              }
            }.bind(this));
        }
    });
    },
    coursetest: function(args){
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'course_self_enrol',
                component: 'local_search',
                param :args
            },
            {
                key: 'confirm'
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
                        $.ajax({
                            type: "GET",
                            url:  M.cfg.wwwroot + "/local/search/courseinfo.php?id="+args.courseid+"&enrol="+args.enroll+"",
                            success: function(returndata) {
                                // console.log(returndata);
                                modal.hide();
                                // $(".enrolled"+args.courseid+"").html(returndata);
                                window.location.href = M.cfg.wwwroot + "/course/view.php?id="+args.courseid;
                            }
                        });
                        
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
   classroominfo: function(args) {
            // console.log(args);

            var request = $.ajax({
                    url:  M.cfg.wwwroot + "/local/search/classrrominfo.php",
                    method: "POST",
                    data: { crid : args.crid },
                    dataType: "html"
                });
                 
                request.done(function( returndata ) {
                    // alert(msg);
                    // $( "#page-content" ).html( returndata );
                    ModalFactory.create({
                            title: Str.get_string('classroom_info', 'local_classroom'),
                            body: returndata
                        }).done(function(modal) {
                        // Do what you want with your new modal.
                        modal.show();
                        modal.setLarge();
                        modal.getRoot().addClass('openLMStransition catalog_popup');
                        modal.getRoot().animate({"right":"0%"}, 500);
                        modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                            setTimeout(function(){
                                modal.destroy();
                            }, 1000);
                        }.bind(this));
                    });
            });

        },
        programinfo: function(args) {
            // console.log(args);

            var request = $.ajax({
                    url:  M.cfg.wwwroot + "/local/search/classrrominfo.php?programid="+args.programid+"",
                    method: "POST",
                    data: { programid : args.programid },
                    dataType: "html"
                });
                 
                request.done(function( returndata ) {
                    // alert(msg);
                    // $( "#page-content" ).html( returndata );
                    ModalFactory.create({
                            title: Str.get_string('program_info', 'local_program'),
                            body: returndata
                        }).done(function(modal) {
                        // Do what you want with your new modal.
                        modal.show();
                        modal.setLarge();
                        modal.getRoot().addClass('openLMStransition catalog_popup');
                        modal.getRoot().animate({"right":"0%"}, 500);
                        modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                            setTimeout(function(){
                                modal.destroy();
                            }, 1000);
                        }.bind(this));
                    });
            });
            
        },
         certificationinfo: function(args) {
            // console.log(args);

            var request = $.ajax({
                    url:  M.cfg.wwwroot + "/local/search/classrrominfo.php",
                    method: "POST",
                    data: { certificationid : args.certificationid },
                    dataType: "html"
                });
                 
                request.done(function( returndata ) {
                    // alert(msg);
                    // $( "#page-content" ).html( returndata );
                    ModalFactory.create({
                            title: Str.get_string('certification_info', 'local_certification'),
                            body: returndata
                        }).done(function(modal) {
                        // Do what you want with your new modal.
                        modal.show();
                        modal.setLarge();
                        modal.getRoot().addClass('openLMStransition catalog_popup');
                        modal.getRoot().animate({"right":"0%"}, 500);
                        modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                            setTimeout(function(){
                                modal.destroy();
                            }, 1000);
                        }.bind(this));
                    });
            });

        },
        learningplaninfo: function(args) {
            // console.log(args);
            
            var request = $.ajax({
                    url:  M.cfg.wwwroot + "/local/search/classrrominfo.php",
                    method: "POST",
                    data: { learningplanid : args.learningplanid },
                    dataType: "html"
                });
                 
                request.done(function( returndata ) {
                    // alert(msg);
                    // $( "#page-content" ).html( returndata );
                    ModalFactory.create({
                            title: Str.get_string('learningplan_info', 'local_search'),
                            body: returndata
                        }).done(function(modal) {
                        // Do what you want with your new modal.
                        modal.show();
                        modal.setLarge();
                        modal.getRoot().addClass('openLMStransition catalog_popup');
                        modal.getRoot().animate({"right":"0%"}, 500);
                        modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.getRoot().animate({"right":"-85%"}, 500);
                            setTimeout(function(){
                                modal.destroy();
                            }, 1000);
                        }.bind(this));
                    });
            });

        },
        courseexpiry: function(args){
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'course_expiry_user',
                component: 'local_search',
                param :args
            },
            {
                key: 'confirm'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.CANCEL,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                     modal.show();
                }.bind(this));
            }.bind(this));
        },
       
        load: function () {
            // return '';
        },
        copy_url: function(args){
            const el = document.createElement('textarea');
            el.value = M.cfg.wwwroot+'/local/costcenter/coursedetailsview.php?module='+args.module+'&moduleid='+args.moduleid;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
    };
});
