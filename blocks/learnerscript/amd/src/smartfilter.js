define(['jquery',
        'block_learnerscript/ajax',
        'block_learnerscript/reportwidget',
        'block_learnerscript/report',
        'block_learnerscript/jquery.serialize-object'],
    function($, ajax, reportwidget, report) {
        var BasicparamCourse = $('.basicparamsform #id_filter_courses');
        var BasicparamUser = $('.basicparamsform #id_filter_users');
        var BasicparamActivity = $('.basicparamsform #id_filter_activities');

        var FilterCourse = $('.filterform #id_filter_courses');
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
                    reportfilter.filter_courses = filter_courseid;
                }
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
                    $("#id_filter_courses").html(template);
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
                        orgid: args.orgid
                    },
                    url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
                });
                promise.done(function(response) {
                    var template = '';
                    $.each(response, function(key, value) {
                        template += '<option value = ' + key + '>' + value + '</option>';
                    });
                    $("#id_filter_departments").html(template);
                });
            }
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
            var currentcourse = $('#id_filter_courses').find(":selected").val();
            $('#id_filter_courses').find('option')
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
                            $('#id_filter_courses').append($("<option></option>")
                                .attr("value", key)
                                .attr('selected', 'selected')
                                .text(value));
                            if(typeof args.triggercourseactivities != 'undefined' && args.triggercourseactivities == true){
                                smartfilter.CourseActivities({ courseid: key });
                            }
                        } else {
                            $('#id_filter_courses').append($("<option></option>")
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