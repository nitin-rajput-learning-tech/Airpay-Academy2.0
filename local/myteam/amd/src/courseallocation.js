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
], function(Str, ModalFactory, ModalEvents, Ajax, Templates, $) {
    var courseallocation;
    return {
        init: function() {
            $('.allocate_button').prop( "disabled", true );
            $('.allocation_course_type_btn').prop( "disabled", true );
        },
        teamsearch: function(params) {
            return Str.get_strings([{
                key: 'selectausertoproceed',
                component: 'local_myteam',
            }]).then(function(s) {
                var searchtype = params.searchtype;
                var search = params.searchvalue;
                if(typeof(search) == 'undefined'){
                    return false;
                }
                var user = $("input[name='allocateuser']:checked").val();
                if(searchtype === 'myteam'){
                    var target = '.departmentmyteam';
                    var selectedcontent = $('#nominate_myteamlist').val();

                    var params = {};
                    params.action = 'searchdata';
                    params.learningtype = searchtype;
                    params.search = search;
                    params.user = '';

                    var promise = Ajax.call([{
                        methodname: 'local_myteam_teamallocation_view',
                        args: params
                    }]);
                    promise[0].done(function(resp) {
                        var data = Templates.render('local_myteam/searchallocateusers', {response: resp});
                        data.then(function(response){
                             $(".departmentmyteam").html(response);
                        });
                    }).fail(function(ex) {
                        // do something with the exception
                        console.log(ex);
                    });
                }else{
                    var target = '.departmentcourses';
                    var selecteduser = $('#nominate_myteamlist').val();
                    var selectedcontent = $('#nominate_courseslist').val();
                    var learningtype = $('#learning_type').val();
                    var learningtype_search = $('input[name="search_learningtypes"]').val();
                    if(searchtype != learningtype){
                        searchtype = learningtype;
                    }
                    if(user == undefined || user == null){
                        var data = '<div class="alert alert-danger">'+s[0]+'</div>'
                        $(target).html(data);
                    }else{
                        var params = {};
                        params.action = 'searchdata';
                        params.learningtype = searchtype;
                        params.search = search;
                        params.user = selecteduser;
                        params.allocatecourse = false;

                        var promise = Ajax.call([{
                            methodname: 'local_myteam_courseallocation_view',
                            args: params
                        }]);
                        promise[0].done(function(resp) {
                            var data = Templates.render('local_myteam/courseallocatemoduledata', {response: resp});
                            data.then(function(response){
                                 $(".departmentcourses").html(response);
                            });
                        }).fail(function(ex) {
                            // do something with the exception
                            console.log(ex);
                        });
                    }
                }
            }.bind(this));
        },
        select_type: function(params) {
            var user = params.user;
            var learningtype = params.learningtype;
            var pluginname = params.pluginname;

            $('input[name="search_learningtypes"]').val('');
            $('#learning_type').val(params.learningtype);
            
            if(user != null && typeof(user) != undefined){
                $('#nominate_myteamlist').val(user);
            }

            var selected_user = $('input[name="allocateuser"]:checked').val();

            if(user == undefined && selected_user == null){

                ModalFactory.create({
                    title: Str.get_string('warning'),
                    type: ModalFactory.types.DEFAULT,
                    body: Str.get_string('team_select_user', 'local_myteam')
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        modal.destroy();
                    });
                     //return modal;
                });
                return false;
            }else{
                user = selected_user;
            }

            switch(learningtype){
                case 1:
                    type = pluginname;
                break;
                case 2:
                    type = pluginname;
                break;
                case 3:
                    type = pluginname;
                break;
                case 4:
                    type = pluginname;
                break;
                default:
                    type = pluginname;
                break;
            }
            $('.allocation_course_type').html(type);
            var params = {};
            params.action = 'departmentmodules';
            params.learningtype = learningtype;
            params.user = user;
            params.search = false;
            params.allocatecourse = false;

            var promise = Ajax.call([{
                methodname: 'local_myteam_courseallocation_view',
                args: params
            }]);
            promise[0].done(function(resp) {
                var data = Templates.render('local_myteam/courseallocatemoduledata', {response: resp});
                data.then(function(response){
                     $(".departmentcourses").html(response);
                });
            }).fail(function(ex) {
                // do something with the exception
                console.log(ex);
            });
        },
        select_list: function(params) {
            var user = params.user;
            var courseid = params.courses;
            var learningtype = params.learningtype;
            var coursecheckedstatus = params.element.checked;

            var current_courses = $('#nominate_courseslist').val();
            var selected_courses = $('input[name="allocatecourse"]:checked').val();
            $('input[name="search_learningtypes"]').val('');
            
            var allocate = false;

            switch(learningtype){
                case 1:
                    if(courseid > 1){
                        if(coursecheckedstatus == true){
                            allocate = true;
                        }
                    }
                break;
                case 2:
                    if(courseid){
                        if(coursecheckedstatus == true){
                            allocate = true;
                        }
                    }
                break;
                case 3:
                    if(courseid > 1){
                        if(coursecheckedstatus == true){
                            allocate = true;
                        }
                    }
                break;
                case 4:
                    if(courseid > 1){
                        if(coursecheckedstatus == true){
                            allocate = true;
                        }
                    }
                break;
                default:
                    allocate = false;
                break;
            }
            
            if(allocate == true){
                $('.allocate_button').prop( "disabled", false);
                $('.allocation_course_type_btn').prop( "disabled", false);
            }else{
                $('.allocate_button').prop( "disabled", true);
                $('.allocation_course_type_btn').prop( "disabled", true);
            }

            switch(learningtype){
                case 1:
                    
                break;
                case 2:
                    
                break;
                case 3:
                    
                break;
                case 4:
                    
                break;
                default:
                    
                break;
            }
        },
        allocator: function() {
            var allocateuser = $('#nominate_myteamlist').val();
            var learningtype = $('#learning_type').val();
            var allocatecourse = [];
            $('input[name="search_learningtypes"]').val('');
             var selected_courses = $('input[name="allocatecourse[]"]:checked').each(function () {
                var courseid_selected = $(this).val();
                allocatecourse.push(courseid_selected);
             });
            if(!allocateuser.length){
                ModalFactory.create({
                    title: Str.get_string('warning'),
                    type: ModalFactory.types.DEFAULT,
                    body: Str.get_string('team_select_user', 'local_myteam')
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        // setTimeout(function(){
                            modal.destroy();
                        // }, 1000);
                    });
                });
                return false;
            }
            if(!allocatecourse.length){


                ModalFactory.create({
                    title: Str.get_string('warning'),
                    type: ModalFactory.types.DEFAULT,
                    body: Str.get_string('team_select_course_s', 'local_myteam')
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                    modal.getRoot().on(ModalEvents.cancel, function() {
                        // setTimeout(function(){
                            modal.destroy();
                        // }, 1000);
                    });
                });
                return false;
            }

            Str.get_strings([{
                key: 'requestprocessing',
                component: 'local_myteam',
            },
            {
                key: 'information',
                component: 'local_myteam',
            },{
                key: 'learningtypeallocated',
                component: 'local_myteam',
            }]).then(function(s) {
                ModalFactory.create({
                    title: Str.get_string('team_confirm_selected_allocation', 'local_myteam'),
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: Str.get_string('allocate_confirm_allocate', 'local_myteam')
                }).done(function(modal) {
                    this.modal = modal;
                    // Do what you want with your new modal.
                    modal.setSaveButtonText(Str.get_string('allocate_yes', 'local_myteam'));

                     //For cancel button string changed//
                    var value=Str.get_string('reject_no', 'local_myteam');
                    var button = this.modal.getFooter().find('[data-action="cancel"]');
                    this.modal.asyncSet(value, button.text.bind(button));
               

                    modal.getRoot().on(ModalEvents.save, function(e) {
                        this.modal.setBody('<span class="loading-icon icon-no-margin"><img src='+M.cfg.wwwroot + '/local/ajax-loader.svg></span>');
                        this.modal.hideFooter();
                        this.modal.setTitle(s[0]);
                        $('[data-action="hide"]').css('display','none');
                        
                        e.preventDefault();
        
                        var params = {};
                        params.action = 'courseallocate';
                        params.learningtype = learningtype;
                        params.user = allocateuser;
                        params.search = false;
                        params.allocatecourse = JSON.stringify(allocatecourse);


                        var promise = Ajax.call([{
                            methodname: 'local_myteam_modulecourse_allocation',
                            args: params
                        }]);
                        promise[0].done(function(resp) {
                                var bobytext='';
                             $.each(resp.records, function(index, element) {
                                if(element.enrolledornot==true && element.return_status !=''){
                                     bobytext=bobytext+element.return_status;
                                     //modal.setBody(element.return_status);
                                     //modal.setTitle('Information');
                                     //$('[data-action="hide"]').css('display','block');
                                  
                                }
                                else if(element.enrolledornot==true && element.return_status ==''){
                                    var textbody='<div class="alert alert-info" role="alert"><button type="button" class="close" data-dismiss="alert">×</button>'+s[2]+'</div>';
                                    if(bobytext!=textbody){
                                        bobytext=bobytext+textbody;
                                    }
                                    
                                    // $('#allocation_notifications').html('<div class="alert alert-info" role="alert"><button type="button" class="close" data-dismiss="alert">×</button>Selected learning types has been allocated.</div>');
                                }
                             });
                            modal.setBody(bobytext);
                             modal.setTitle(s[1]);
                             $('[data-action="hide"]').css('display','block');
                            
                            //modal.hide();
                            //modal.destroy();
                            //window.location.href = window.location.href;
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
        load: function(){

        }
    };
});