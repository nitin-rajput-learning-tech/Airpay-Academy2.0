/**
 * Add a create new group modal to the page.
 *
 * @module     core_group/AjaxForms
 * @class      AjaxForms
 * @package    core_group
 * @copyright  2017 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/templates',
    'jquery',
    'local_costcenter/cardPaginate',
    'jqueryui'
], function(Str, ModalFactory, ModalEvents, Ajax, Templates, $,Cardpaginate) {
    var classroom;
    return classroom = {
        init: function(args) {
            this.AssignUsers(args);
        },
        completionInfo: function(params) {
            
            var name = params.name;
            var target = "."+name;
            var promise = Ajax.call([{
                methodname: 'local_classroom_classroomview'+name,
                args: params
            }]);
            $("#sessions_tabdataid").empty();
            $("#coursesid").empty();
            $("#usersid").empty();
            $("#feedbacksid").empty();
            $("#requestedusersid").empty();
            $(".tab-pane").removeClass('active');
            $("#"+name).addClass('active');

            promise[0].done(function(resp) {
                var data = Templates.render('local_classroom/classroomview'+name, {response: resp});
                data.then(function(response){
                    $(target).html(response);
                });
            }).fail(function(ex) {
                // do something with the exception
                console.log(ex);
            });
        },
        classroomsData: function(params) {
            var targetid = 'all';
            var view_type = params;
        
            if(view_type == 'card'){
            var options = {targetID: targetid,
                        templateName: 'local_classroom/classrooms_list',
                        methodName: 'local_classroom_get_classrooms',
                        perPage: 6,
                        cardClass: 'col-md-6 col-12',
                        viewType: 'card'};
              }else {
               var options = {targetID: targetid,
                        templateName: 'local_classroom/classrooms_catalog_list',
                        methodName: 'local_classroom_get_classrooms',
                        perPage: 10,
                        cardClass: 'tableformat',
                        viewType: 'table'};
              }
            var dataoptions = {status: '-1', view_type: params};

            Cardpaginate.reload(options, dataoptions);
        },
        
        sessionsData: function(params) {
            var targetid = 'sessions_tabdata';
            var options = {targetID: targetid,
                        templateName: 'local_classroom/classroomviewsessions',
                        methodName: 'local_classroom_classroomviewsessions',
                        perPage: 5,
                        cardClass: 'col-md-6 col-12',
                        viewType: 'card'};

            var dataoptions = {tabname: 'sessions',classroomid: params};
            var filterdata = {};

            Cardpaginate.reload(options, dataoptions,filterdata);
        },
        deleteConfirm: function(args) {
            console.log(args);
            return Str.get_strings([{
                key: 'confirmation',
                component: 'local_classroom'
            },
            {
                key: 'deleteconfirm',
                component: 'local_classroom',
                param: args.classroomname,
            },
            {
                key: 'deleteallconfirm',
                component: 'local_classroom',
                param: args.sessionname,
            },
            {
                key: 'yes'
            },
            {
                key: 'deletecourseconfirm',
                component: 'local_classroom',
                param: args.name,
            },
            {
                key: 'unenrollclassroom',
                component: 'local_classroom',
                param: args.classroomname,
            },
            
            {
                key: 'requestprocessing',
                component: 'local_classroom'
            },
            {
                key: 'deletefeedbackconfirm',
                component: 'local_classroom',
                param: args.feedbackname,
            },


            ]).then(function(s) {
                 if(args.action=="deleteclassroom"){
                    s[1]=s[1];
                 }
                 else if(args.action=="unenrollclassroom"){
                    s[1]=s[5];
                 }
                 else if(args.action=="deleteclassroomcourse"){
                    s[1]=s[4];
                 }
                 else if(args.action=="deleteclassroomevaluation"){
                    s[1]=s[7];
                 }
                 else{
                     s[1]=s[2];
                 }
                ModalFactory.create({

                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[3]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        this.modal.setBody('<span class="loading-icon icon-no-margin"><img src='+M.cfg.wwwroot + '/local/ajax-loader.svg></span>');
                        this.modal.hideFooter();
                        this.modal.setTitle(s[6]);
                        $('[data-action="hide"]').css('display','none');
                        e.preventDefault();
                        args.confirm = true;
                        var promise = Ajax.call([{
                            methodname: 'local_classroom_' + args.action,
                            args: args
                        }]);
                        promise[0].done(function(resp) {
                            if(args.action=="deleteclassroom"){
                                 window.location.href = M.cfg.wwwroot + '/local/classroom/index.php';
                            }else{
                                 window.location.href = M.cfg.wwwroot + '/local/classroom/view.php?cid='+args.classroomid;
                            }
                           
                        }).fail(function(ex) {
                            // do something with the exception
                             console.log(ex);
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        AssignUsers: function(args) {
            $('.usersselect').click(function() {
                var type = $(this).data('id');

                if (type === 'removeselect') {
                    $('input#remove').prop('disabled', false);
                    $('input#add').prop('disabled', true);
                } else if (type === 'addselect') {
                    $('input#remove').prop('disabled', true);
                    $('input#add').prop('disabled', false);
                }

                if ($(this).hasClass('select_all')) {
                    $('#' + type + ' option').prop('selected', true);
                } else if ($(this).hasClass('remove_all')) {
                    $('#' + type ).val('').trigger("change");
                }
            });
        },
        classroomStatus: function(args) {
            return Str.get_strings([
            {
                key: 'confirmation',
                component: 'local_classroom'
            },
            {
                key: args.actionstatusmsg,
                component: 'local_classroom'
            },
            {
                key: 'yes'
            },
            {
                key: 'requestprocessing',
                component: 'local_classroom'
            }
            ]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[2]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        this.modal.setBody('<span class="loading-icon icon-no-margin"><img src='+M.cfg.wwwroot + '/local/ajax-loader.svg></span>');
                        this.modal.hideFooter();
                        this.modal.setTitle(s[3]);
                        $('[data-action="hide"]').css('display','none');
                        e.preventDefault();
                        args.confirm = true;
                        var promise = Ajax.call([{
                            methodname: 'local_classroom_' + args.action,
                            args: args
                        }]);
                        promise[0].done(function(resp) {
                            window.location.href = window.location.href;
                        }).fail(function(ex) {
                            // do something with the exception
                             console.log(ex);
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        ManageclassroomStatus: function(args) {
            return Str.get_strings([{
                key: 'confirmation',
                component: 'local_classroom'
            },
            {
                key: args.actionstatusmsg,
                component: 'local_classroom',
                param: args.classroomname,
            },
            {
                key: 'deleteallconfirm',
                component: 'local_classroom'
            },
            {
                key: 'yes'
            },
            {
                key: 'requestprocessing',
                component: 'local_classroom'
            },
            {
                key: 'information',
                component: 'local_classroom'
            }
            ]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    modal.setSaveButtonText(s[3]);
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        this.modal.setBody('<span class="loading-icon icon-no-margin"><img src='+M.cfg.wwwroot + '/local/ajax-loader.svg></span>');
                        this.modal.hideFooter();
                        this.modal.setTitle(s[4]);
                        $('[data-action="hide"]').css('display','none');

                        e.preventDefault();
                        args.confirm = true;
                        var promise = Ajax.call([{
                            methodname: 'local_classroom_manageclassroomStatus',
                            args: args
                        }]);
                        promise[0].done(function(resp) {
                            if(args.action=="enrolrequest"){
                                location.reload();

                            }else if(args.action=="selfenrol"){
                                if(resp.return_status ==''){
                                    // location.reload();
                                    window.location.href = M.cfg.wwwroot + '/local/classroom/view.php?cid='+args.classroomid;
                                }else{
                                    modal.setBody(resp.return_status);
                                    modal.setTitle(s[5]);
                                    $('[data-action="hide"]').css('display','block');
                                }

                            }else{
                                window.location.href = M.cfg.wwwroot + '/local/classroom/view.php?cid='+args.classroomid;

                            }
                            $(".close").click(function(){
                                    location.reload();
                            });
                        }).fail(function(ex) {
                            // do something with the exception
                             console.log(ex);
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        load: function () {
        }
    };
});  