define(['jquery',
        'block_learnerscript/ajax',
        'block_learnerscript/reportwidget',
        'block_learnerscript/report',
        'block_learnerscript/jquery.serialize-object'],
    function($, ajax, reportwidget, report) {
        var BasicparamCourse = $('.basicparamsform #id_filter_course');
        var BasicparamUser = $('.basicparamsform #id_filter_users');
        var BasicparamActivity = $('.basicparamsform #id_filter_activities');

        var FilterCourse = $('.filterform #id_filter_course');
        var FilterUser = $('.filterform #id_filter_users');
        var FilterActivity = $('.filterform #id_filter_activities');

        return smartfilter = {
            DurationFilter: function(value, reportdashboard) {
                var today = new Date();
                var endDate = today.getFullYear() + "/" + (today.getMonth() + 1) + "/" + today.getDate();
                var start_duration = '';
                if (value !== 'clear') {
                    $('#ls_fenddate').val(today.getTime() / 1000);
                    switch (value) {
                        case 'week':
                            start_duration = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 7);
                            break;
                        case 'month':
                            start_duration = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
                            break;
                        case 'year':
                            start_duration = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
                            break;
                        case 'custom':
                            $('#customrange').show();
                            break;
                        default:
                            break;
                    }
                    if (start_duration != '') {
                        $('#ls_fstartdate').val(start_duration.getTime() / 1000);
                    }
                } else {
                    $('#ls_fenddate').val("");
                    $('#ls_fstartdate').val("");
                }
                if (value !== 'custom') {
                    var reportid = $('input[name="reportid"]').val();
                    if (reportdashboard != false) {
                    	require(['block_learnerscript/reportwidget'], function(reportwidget) {
                        	reportwidget.DashboardTiles();
                        	reportwidget.DashboardWidgets();
                    	});
                    } else {
                    	require(['block_learnerscript/report'], function(report) {
                        	report.CreateReportPage({ reportid: reportid, instanceid: reportid, reportdashboard: reportdashboard });
                        });
                    }
                    $('#customrange').val("");
                    $('#customrange').hide();
                }
                if (reportdashboard != true) {
                    var reportid = $('input[name="reportid"]').val();
                    $('.plotgraphcontainer').removeClass('show').addClass('hide');
                    $('#plotreportcontainer' + reportid).html('');
                }

            },
            /**
             * [FilterData description]
             * @param {[type]} args [description]
             */
            FilterData: function(reportinstance) {
                var reportfilter = $(".filterform" + reportinstance).serializeObject();
                 $.urlParam = function(name){
                    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                    if (results === null || results == ' ' ){
                       return null;
                    } else{
                       return results[1] || 0;
                    }
                }
                var dashboardurl=$.urlParam('dashboardurl');
                if(dashboardurl == 'Course'){
                    var filter_courseid = $(".report_courses").val();
                    reportfilter.filter_course = filter_courseid;
                } 
                var filter_coscenterid = $("#dashboardcostcenters").val();
                reportfilter.filter_organization = filter_coscenterid;
                var filter_departmentid = $("#dashboarddepartment").val();
                reportfilter.filter_departments = filter_departmentid;
                return reportfilter;
            },
            BasicparamsData: function(reportinstance) {
                var basicparams = $(".basicparamsform" + reportinstance).serializeObject();
                return basicparams;
            },
            CourseData: function(args) {
                var FirstElementActive = false;
                if (BasicparamActivity.length > 0 || FilterActivity.length > 0) {
                    if (BasicparamActivity.length > 0) {
                        FirstElementActive = true;
                    }
                    if (args.courseid > 0) {
                        this.CourseActivities({ courseid: args.courseid, firstelementactive: FirstElementActive, activityid: args.filterrequests.filter_activity });
                    }
                }
                if (BasicparamUser.length > 0 || FilterUser.length > 0) {
                    if (BasicparamUser.length > 0) {
                        FirstElementActive = true;
                    }
                    // if (args.courseid > 0) {
                        // this.EnrolledUsers({
                        //     courseid: args.courseid,
                        //     reportid: args.reportid,
                        //     reporttype: args.reporttype,
                        //     components: args.components,
                        //     firstelementactive: FirstElementActive
                        // });
                    // }
                }
            },
           categoryCourses: function(args) {
            var currentcategory = $('#id_filter_coursecategories').find(":selected").val();
            if (currentcategory > 0) {
                var promise = ajax.call({
                    args: {
                        action: 'categorycourses',
                        basicparam: true,
                        reporttype: args.reporttype,
                        categoryid: args.categoryid
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    var template = '';
                    $.each(response, function(key, value) {
                        template += '<option value = ' + key + '>' + value + '</option>';
                    });
                    $("#id_filter_course").html(template);
                });
            }
        },

        orgdepartments: function(args) {
            var currentorgid = $('#id_filter_organization').find(":selected").val();
            if (currentorgid > 0) {
                var promise = ajax.call({
                    args: {
                        action: 'orgdepts',
                        basicparam: true,
                        reporttype: args.reporttype,
                        orgid: args.organizationid
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    var template = '';
                    $.each(response, function(key, value) {
                        template += '<option value = ' + key + '>' + value + '</option>';
                    });
                    $("#id_filter_departments").html(template);
                    var currentdepartment = $('.basicparamsform #id_filter_departments').find(":selected").val();
                    if (currentdepartment == 0 || currentdepartment == null) {
                        $('.basicparamsform #id_filter_departments').val($('.basicparamsform #id_filter_departments option:eq(1)').val());
                    }
                    $("#id_filter_departments").trigger('change');
                });
            }
        },

        orglearningpath: function(args) {
            var currentorgid = $('#id_filter_organization').find(":selected").val();
            if (currentorgid > 0) {
                var promise = ajax.call({
                    args: {
                        action: 'orglearningpath',
                        basicparam: true,
                        reporttype: args.reporttype,
                        orgid: args.organizationid
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    var template = '';
                    $.each(response, function(key, value) {
                        template += '<option value = ' + key + '>' + value + '</option>';
                    });
                    $("#id_filter_learningpath").html(template);
                    var currentlearningpath = $('.basicparamsform #id_filter_learningpath').find(":selected").val();
                    if (currentlearningpath == 0 || currentlearningpath == null) {
                        $('.basicparamsform #id_filter_learningpath').val($('.basicparamsform #id_filter_learningpath option:eq(1)').val());
                    }
                    $("#id_filter_learningpath").trigger('change');
                });
            }
        },

        DepartmentSubdepts: function(args) {
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deptsubdepartments',
                    basicparam: true,
                    reporttype: args.reporttype,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_subdepartments").html(template);
                var currentsubdepartment = $('.basicparamsform #id_filter_subdepartments').find(":selected").val();
                if (currentsubdepartment == 0 || currentsubdepartment == null) {
                    $('.basicparamsform #id_filter_subdepartments').val($('.basicparamsform #id_filter_subdepartments option:eq(1)').val());
                }
                $("#id_filter_subdepartments").trigger('change');
            });

        },

        Departmentl4depts: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deptl4departments',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_level4department").html(template);
                var currentsubdepartment = $('.basicparamsform #id_filter_level4department').find(":selected").val();
                if (currentsubdepartment == 0 || currentsubdepartment == null) {
                    $('.basicparamsform #id_filter_level4department').val($('.basicparamsform #id_filter_level4department option:eq(1)').val());
                }
                $("#id_filter_level4department").trigger('change');
            });

        },

        Departmentl5depts: function(args) {
            var currentl4depid = $('#id_filter_level4department').find(":selected").val();
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deptl5departments',
                    basicparam: true,
                    reporttype: args.reporttype,
                    currentl4depid: currentl4depid,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_level5department").html(template);
                var currentsubdepartment = $('.basicparamsform #id_filter_level5department').find(":selected").val();
                if (currentsubdepartment == 0 || currentsubdepartment == null) {
                    $('.basicparamsform #id_filter_level5department').val($('.basicparamsform #id_filter_level5department option:eq(1)').val());
                }
                $("#id_filter_level5department").trigger('change');
            });

        },

        DepartmentCohorts: function(args) {
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deptcohorts',
                    basicparam: true,
                    reporttype: args.reporttype,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_cohort").html(template);
                var currentcohort = $('.basicparamsform #id_filter_cohort').find(":selected").val();
                if (currentcohort == 0 || currentcohort == null) {
                    $('.basicparamsform #id_filter_cohort').val($('.basicparamsform #id_filter_cohort option:eq(1)').val());
                }
                $("#id_filter_cohort").trigger('change');
            });

        },

        deplearningpath: function(args) {
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deplearningpath',
                    basicparam: true,
                    reporttype: args.reporttype,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_learningpath").html(template);
                var currentlearningpath = $('.basicparamsform #id_filter_learningpath').find(":selected").val();
                if (currentlearningpath == 0 || currentlearningpath == null) {
                    $('.basicparamsform #id_filter_learningpath').val($('.basicparamsform #id_filter_learningpath option:eq(1)').val());
                }
                $("#id_filter_learningpath").trigger('change');
            });

        }, 
        deponlinecourses: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deponlinecourses',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_onlinecourses").html(template);
                var currentonlinecourse = $('.basicparamsform #id_filter_onlinecourses').find(":selected").val();
                if (currentonlinecourse == 0 || currentonlinecourse == null) {
                    $('.basicparamsform #id_filter_onlinecourses').val($('.basicparamsform #id_filter_onlinecourses option:eq(1)').val());
                }
                $("#id_filter_onlinecourses").trigger('change');
            });
        },
        deplabs: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'deplabs',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_labs").html(template);
                var currentlab = $('.basicparamsform #id_filter_labs').find(":selected").val();
                if (currentlab == 0 || currentlab == null) {
                    $('.basicparamsform #id_filter_labs').val($('.basicparamsform #id_filter_labs option:eq(1)').val());
                }
                $("#id_filter_labs").trigger('change');
            });
        },
        depassessments: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'depassessments',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_assessments").html(template);
                var currentassessment = $('.basicparamsform #id_filter_assessments').find(":selected").val();
                if (currentassessment == 0 || currentassessment == null) {
                    $('.basicparamsform #id_filter_assessments').val($('.basicparamsform #id_filter_assessments option:eq(1)').val());
                }
                $("#id_filter_assessments").trigger('change');
            });
        },
        depusergroups: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'depusergroups',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_usergroup").html(template);
                var currentlearningpath = $('.basicparamsform #id_filter_usergroup').find(":selected").val();
                if (currentlearningpath == 0 || currentlearningpath == null) {
                    $('.basicparamsform #id_filter_usergroup').val($('.basicparamsform #id_filter_usergroup option:eq(1)').val());
                }
                $("#id_filter_usergroup").trigger('change');
            });
        },        
        depwebinars: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'depwebinars',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_webinars").html(template);
                var currentwebinar = $('.basicparamsform #id_filter_webinars').find(":selected").val();
                if (currentwebinar == 0 || currentwebinar == null) {
                    $('.basicparamsform #id_filter_webinars').val($('.basicparamsform #id_filter_webinars option:eq(1)').val());
                }
                $("#id_filter_webinars").trigger('change');
            });
        },
        depprograms: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'depprograms',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_programs").html(template);
                var currentwebinar = $('.basicparamsform #id_filter_programs').find(":selected").val();
                if (currentwebinar == 0 || currentwebinar == null) {
                    $('.basicparamsform #id_filter_programs').val($('.basicparamsform #id_filter_programs option:eq(1)').val());
                }
                $("#id_filter_programs").trigger('change');
            });
        },
        depclassrooms: function(args) {
            var currentsubdepid = $('#id_filter_subdepartments').find(":selected").val();
            var currentdepid = $('#id_filter_departments').find(":selected").val();
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var promise = ajax.call({
                args: {
                    action: 'depclassrooms',
                    basicparam: true,
                    reporttype: args.reporttype,
                    subdepartmentid: currentsubdepid,
                    departmentid: currentdepid,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    template += '<option value = ' + key + '>' + value + '</option>';
                });
                $("#id_filter_classrooms").html(template);
                var currentclassroom = $('.basicparamsform #id_filter_classrooms').find(":selected").val();
                if (currentclassroom == 0 || currentclassroom == null) {
                    $('.basicparamsform #id_filter_classrooms').val($('.basicparamsform #id_filter_classrooms option:eq(1)').val());
                }
                $("#id_filter_classrooms").trigger('change');
            });
        },

        DepartmentCourses: function(args) { 
                var currentsubdepartment = $('#id_filter_subdepartments').find(":selected").val();
                var currentdepartment = $('#id_filter_departments').find(":selected").val();
                var currentorganization = $('#id_filter_organization').find(":selected").val();
                var requestedcourseid = args.courseid;
                // if (currentorganization > 0) {
                    var promise = ajax.call({
                        args: {
                            action: 'departmentcourses',
                            basicparam: true,
                            reporttype: args.reporttype,
                            subdepartmentid: args.subdepartmentid,
                            departmentid: args.departmentid,
                            orgid: args.organizationid
                            
                        },
                        url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                    });
                    promise.done(function(response) {
                        var template = '';
                        $.each(response, function(key, value) {
                            var selected = '';
                            if (key == requestedcourseid) {
                                selected = 'selected';
                            }
                            template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                        });
                        $("#id_filter_course").html(template);
                        var currentcourse = $('.basicparamsform #id_filter_course').find(":selected").val();
                        if (currentcourse == 0 || currentcourse == null) {
                            $('.basicparamsform #id_filter_course').val($('.basicparamsform #id_filter_course option:eq(1)').val());
                        }
                        $("#id_filter_course").trigger('change');
                    });
                // }
            }, 

            DepartmentUsers: function(args) { 
                var currentl5department = $('#id_filter_level5department').find(":selected").val();
                var currentl4department = $('#id_filter_level4department').find(":selected").val();
                var currentsubdepartment = $('#id_filter_subdepartments').find(":selected").val();
                var currentdepartment = $('#id_filter_departments').find(":selected").val();
                var currentorganization = $('#id_filter_organization').find(":selected").val();
                var requesteduserid = args.userid;
                // if (currentorganization > 0) {
                    var promise = ajax.call({
                        args: {
                            action: 'departmentusers',
                            basicparam: true,
                            reporttype: args.reporttype,
                            currentl5depid: args.currentl5department,
                            currentl4depid: args.currentl4department,
                            subdepartmentid: args.subdepartmentid,
                            departmentid: args.departmentid,
                            orgid: args.organizationid
                            
                        },
                        url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                    });
                    promise.done(function(response) {
                        var template = '';
                        $.each(response, function(key, value) {
                            var selected = '';
                            if (key == requesteduserid) {
                                selected = 'selected';
                            }
                            template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                        });
                        $("#id_filter_users").html(template);
                        var currentcourse = $('.basicparamsform #id_filter_users').find(":selected").val();
                        if (currentcourse == 0 || currentcourse == null) {
                            $('.basicparamsform #id_filter_users').val($('.basicparamsform #id_filter_users option:eq(1)').val());
                        }
                        $("#id_filter_users").trigger('change');
                    });
                // }
            },
        DepartmentUser: function(args) {
                var currentl5department = $('#id_filter_level5department').find(":selected").val();
                var currentl4department = $('#id_filter_level4department').find(":selected").val();
                var currentsubdepartment = $('#id_filter_subdepartments').find(":selected").val();
                var currentdepartment = $('#id_filter_departments').find(":selected").val();
                var currentorganization = $('#id_filter_organization').find(":selected").val();
                var requesteduserid = args.userid;
                // if (currentorganization > 0) {
                    var promise = ajax.call({
                        args: {
                            action: 'departmentusers',
                            basicparam: true,
                            reporttype: args.reporttype,
                            currentl5depid: args.currentl5department,
                            currentl4depid: args.currentl4department,
                            subdepartmentid: args.subdepartmentid,
                            departmentid: args.departmentid,
                            orgid: args.organizationid

                        },
                        url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                    });
                    promise.done(function(response) {
                        var template = '';
                        $.each(response, function(key, value) {
                            var selected = '';
                            if (key == requesteduserid) {
                                selected = 'selected';
                            }
                            template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                        });
                        $("#id_filter_user").html(template);
                        var currentcourse = $('.basicparamsform #id_filter_user').find(":selected").val();
                        if (currentcourse == 0 || currentcourse == null) {
                            $('.basicparamsform #id_filter_user').val($('.basicparamsform #id_filter_user option:eq(1)').val());
                        }
                        $("#id_filter_user").trigger('change');
                    });
                // }
            },
        GeoState: function(args){
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var requesteduserid = args.userid;
            var promise = ajax.call({
                args: {
                    action: 'geostates',
                    basicparam: true,
                    reporttype: args.reporttype,
                    orgid: currentorganization
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    var selected = '';
                    template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                });
                $("#id_filter_geostate").html(template);
                var currentstate = $('.basicparamsform #id_filter_geostate').find(":selected").val();
                if (currentstate == 0 || currentstate == null) {
                    $('.basicparamsform #id_filter_geostate').val($('.basicparamsform #id_filter_geostate option:eq(1)').val());
                }
                $("#id_filter_geostate").trigger('change');
            });
        },
        GeoDistrict: function(args){
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var currentstate = $('#id_filter_geostate').find(":selected").val();
            var requesteduserid = args.userid;
            var promise = ajax.call({
                args: {
                    action: 'geodistrict',
                    basicparam: true,
                    reporttype: args.reporttype,
                    orgid: currentorganization,
                    currentstate: currentstate
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    var selected = '';
                    template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                });
                $("#id_filter_geodistrict").html(template);
                var currentdistrict = $('.basicparamsform #id_filter_geodistrict').find(":selected").val();
                if (currentdistrict == 0 || currentdistrict == null) {
                    $('.basicparamsform #id_filter_geodistrict').val($('.basicparamsform #id_filter_geodistrict option:eq(1)').val());
                }
                $("#id_filter_geodistrict").trigger('change');
            });
        },
        GeoSubdistrict: function(args){
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var currentstate = $('#id_filter_geostate').find(":selected").val();
            var currentdistrict = $('#id_filter_geodistrict').find(":selected").val();
            var requesteduserid = args.userid;
            var promise = ajax.call({
                args: {
                    action: 'geosubdistrict',
                    basicparam: true,
                    reporttype: args.reporttype,
                    orgid: currentorganization,
                    currentstate: currentstate,
                    currentdistrict: currentdistrict
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    var selected = '';
                    template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                });
                $("#id_filter_geosubdistrict").html(template);
                var currentsubdistrict = $('.basicparamsform #id_filter_geosubdistrict').find(":selected").val();
                if (currentsubdistrict == 0 || currentsubdistrict == null) {
                    $('.basicparamsform #id_filter_geosubdistrict').val($('.basicparamsform #id_filter_geosubdistrict option:eq(1)').val());
                }
                $("#id_filter_geosubdistrict").trigger('change');
            });
        },
        GeoVillage: function(args){
            var currentorganization = $('#id_filter_organization').find(":selected").val();
            var currentstate = $('#id_filter_geostate').find(":selected").val();
            var currentdistrict = $('#id_filter_geodistrict').find(":selected").val();
            var currentsubdistrict = $('#id_filter_geosubdistrict').find(":selected").val();
            var requesteduserid = args.userid;
            var promise = ajax.call({
                args: {
                    action: 'geovillage',
                    basicparam: true,
                    reporttype: args.reporttype,
                    orgid: currentorganization,
                    currentstate: currentstate,
                    currentdistrict: currentdistrict,
                    currentsubdistrict: currentsubdistrict
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                var template = '';
                $.each(response, function(key, value) {
                    var selected = '';
                    template += '<option value = ' + key + ' ' + selected + ' >' + value + '</option>';
                });
                $("#id_filter_geovillage").html(template);
                var currentstate = $('.basicparamsform #id_filter_geovillage').find(":selected").val();
                if (currentstate == 0 || currentstate == null) {
                    $('.basicparamsform #id_filter_geovillage').val($('.basicparamsform #id_filter_geovillage option:eq(1)').val());
                }
                $("#id_filter_geovillage").trigger('change');
            });
        },
        CourseActivities: function(args) {
            var nearelement = args.element || $('#id_filter_activities');
            activityid = parseInt(args.activityid) || 0;
            var currentactivity = nearelement.val();
            nearelement.find('option')
                .remove()
                .end()
                .append('<option value=0>Select Activity</option>');
            if (args.courseid >= 0) {
                var promise = ajax.call({
                    args: {
                        action: 'courseactivities',
                        basicparam: true,
                        courseid: args.courseid
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    $.each(response, function(key, value) {
                        key = parseInt(key);
                        if(key == 0){
                            return true;
                        }
                        // (key != currentactivity && key != 0)
                        if (key != activityid) {
                            nearelement.append($("<option></option>")
                                .attr("value", key)
                                .text(value));
                        } else {
                            nearelement.append($("<option></option>")
                                .attr("value", key)
                                .attr('selected', 'selected')
                                .text(value));
                        }
                    });
                    var currentactivity = $('.basicparamsform #id_filter_activities').find(":selected").val();
                    if (currentactivity == 0 || currentactivity == null) {
                        $('.basicparamsform #id_filter_activities').val($('.basicparamsform #id_filter_activities option:eq(1)').val());
                    }
                    var basicparamactivtylen = nearelement.parents('.basicparamsform').length;
                    if (basicparamactivtylen > 0 && args.onloadtrigger) {
                        $(".basicparamsform #id_filter_apply").trigger('click');
                    }
                });
            }
        },
        UserCourses: function(args) {
            var currentcourse = $('#id_filter_course').find(":selected").val();
            $('#id_filter_course').find('option')
                .remove()
                .end()
                .append('<option value="">Select Course</option>');
            if (args.userid >= 0) {
                var promise = ajax.call({
                    args: {
                        action: 'usercourses',
                        basicparam: true,
                        userid: args.userid,
                        reporttype: args.reporttype,
                        reportid: args.reportid
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    $.each(response, function(key, value) {
                        if(key == 0){
                            return true;
                        }
                        if ((key == Object.keys(response)[0] && args.firstelementactive == 1) ||
                                (key == currentcourse && args.firstelementactive == 1)) {
                            $('#id_filter_course').append($("<option></option>")
                                .attr("value", key)
                                .attr('selected', 'selected')
                                .text(value));
                            if(typeof args.triggercourseactivities != 'undefined' && args.triggercourseactivities == true){
                                smartfilter.CourseActivities({ courseid: key });
                            }
                        } else {
                            $('#id_filter_course').append($("<option></option>")
                                .attr("value", key)
                                .text(value));
                        }
                    });

                });
            }
        },
        EnrolledUsers: function(args) {
            var nearelement = args.element || $('#id_filter_users');
            var currentuser = nearelement.val();
            nearelement.find('option')
                .remove()
                .end()
                .append('<option value="">Select User</option>');
                var promise = ajax.call({
                    args: {
                        action: 'enrolledusers',
                        basicparam: true,
                        reportid: args.reportid,
                        courseid: args.courseid,
                        reporttype: args.reporttype,
                        component: args.components
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    // if (typeof nearelement == 'undefined') {
                    //     nearelement.find('option')
                    //         .not(':eq(0), :selected')
                    //         .remove()
                    //         .end();
                    // }
                    $.each(response, function(key, value) {
                        if(key == 0){
                            return true;
                        }
                        if (key != currentuser) {
                            nearelement.append($("<option></option>")
                                .attr("value", key)
                                .text(value));
                        } else {
                            nearelement.append($("<option></option>")
                                .attr("value", key)
                                .attr('selected', 'selected')
                                .text(value));
                        }
                    });
                    if (!response.hasOwnProperty(currentuser)) {
                        // nearelement.select2('destroy').select2({ theme: 'classic' });
                        // nearelement.select2('val', 0);
                    } else {
                        nearelement.select2('val', "");
                        var basicparamuserlen = nearelement.parents('.basicparamsform').length;
                        if (basicparamuserlen > 0 && args.onloadtrigger) {
                            $(".basicparamsform #id_filter_apply").trigger('click');
                        }
                    }
                });

        }
        }
    });
