/**
 * Standard Report wrapper for Moodle. It calls the central JS file for Report plugin,
 * Also it includes JS libraries like Select2,Datatables and Highcharts
 * @module     block_learnerscript/report
 * @class      report
 * @package    block_learnerscript
 * @copyright  2017 Naveen kumar <naveen@eabyas.in>
 * @since      3.3
 */
define(['block_learnerscript/select2',
    'block_learnerscript/responsive.bootstrap',
    'block_learnerscript/reportwidget',
    'block_learnerscript/chart',
    'block_learnerscript/smartfilter',
    'block_learnerscript/helper',
    'block_learnerscript/ajaxforms',
    'jquery',
    'block_learnerscript/radioslider',
    'block_learnerscript/flatpickr',
    'core/templates',
    'jqueryui'
], function(select2, DataTable, reportwidget, chart, smartfilter, helper, AjaxForms,
    $, RadiosToSlider, flatpickr, templates) {
    var report;
    var BasicparamCourse = $('.basicparamsform #id_filter_course');
    var BasicparamUsers = $('.basicparamsform #id_filter_users');
    var BasicparamUser = $('.basicparamsform #id_filter_user');
    var BasicparamActivity = $('.basicparamsform #id_filter_activities'); 
    var BasicparamOrganization = $('.basicparamsform #id_filter_organization'); 
    var BasicparamDepartments = $('.basicparamsform #id_filter_departments'); 
    var BasicparamSubdepartments = $('.basicparamsform #id_filter_subdepartments');
    var Basicparaml4departments = $('.basicparamsform #id_filter_level4department');
    var Basicparaml5departments = $('.basicparamsform #id_filter_level5department');
    var BasicparamLearningpath = $('.basicparamsform #id_filter_learningpath'); 
    var BasicparamOnlinecourses = $('.basicparamsform #id_filter_onlinecourses'); 
    var BasicparamLabs = $('.basicparamsform #id_filter_labs'); 
    var BasicparamAssessments = $('.basicparamsform #id_filter_assessments');
    var BasicparamUsersgroups = $('.basicparamsform #id_filter_usergroup');
    var BasicparamWebinars = $('.basicparamsform #id_filter_webinars'); 
    var BasicparamClassrooms = $('.basicparamsform #id_filter_classrooms'); 
    var BasicparamPrograms = $('.basicparamsform #id_filter_programs');     
    var BasicparamCohort = $('.basicparamsform #id_filter_cohort');
    var BasicparamGeostate = $('.basicparamsform #id_filter_geostate');
    var BasicparamGeodistrict = $('.basicparamsform #id_filter_geodistrict');
    var BasicparamGeosubdistrict = $('.basicparamsform #id_filter_geosubdistrict');
    var BasicparamGeovillage = $('.basicparamsform #id_filter_geovillage');

    var FilterCourse = $('.filterform #id_filter_course');
    var FilterUsers = $('.filterform #id_filter_users');
    var FilterUser = $('.filterform #id_filter_user');
    var FilterActivity = $('.filterform #id_filter_activities');
    var FilterModule = $('.filterform #id_filter_modules');
    var FilterOrganization = $('.filterform #id_filter_organization');
    var FilterDepartments = $('.filterform #id_filter_departments');
    var FilterSubdepartments = $('.filterform #id_filter_subdepartments');
    var Filterl4departments = $('.filterform #id_filter_level4department');
    var Filterl5departments = $('.filterform #id_filter_level5department');
    var FilterLearningpath = $('.filterform #id_filter_learningpath');
    var FilterOnlinecourses = $('.filterform #id_filter_onlinecourses');
    var FilterLabs = $('.filterform #id_filter_labs');
    var FilterAssessments = $('.filterform #id_filter_assessments');
    var FilterUsersgroups = $('.filterform #id_filter_usergroup');
    var FilterWebinars = $('.filterform #id_filter_webinars');
    var FilterClassrooms = $('.filterform #id_filter_classrooms');
    var FilterPrograms = $('.filterform #id_filter_programs');
    var FilterCohort = $('.filterform #id_filter_cohort');
    var FilterGeostate = $('.filterform #id_filter_geostate');
    var FilterGeodistrict = $('.filterform #id_filter_geodistrict');
    var FilterGeosubdistrict = $('.filterform #id_filter_geosubdistrict');
    var FilterGeovillage = $('.filterform #id_filter_geovillage');

    var NumberOfBasicParams = 0;

    return report = {
        init: function(args) {
            /**
             * Initialization
             */
            $.ui.dialog.prototype._focusTabbable = $.noop;
            $.fn.dataTable.ext.errMode = 'none';

            $('.plotgraphcontainer').on('click', function() {
                var reportid = $(this).data('reportid');
                // $(this).removeClass('show').addClass('show');
                $('.plotgraphcontainer').removeClass('show').addClass('hide');
                $('#plotreportcontainer' + reportid).html('');

            })
            /**
             * Select2 initialization
             */
            $("select[data-select2='1']").select2({
                theme: "classic"
            }).on("select2:selecting", function(e) {
                if ($(this).val() && $(this).data('maximumSelectionLength') &&
                    $(this).val().length >= $(this).data('maximumSelectionLength')) {
                    e.preventDefault();
                    $(this).select2('close');
                }
            });

            /*
             * Report search
             */
            $("#reportsearch").val(args.reportid).trigger('change.select2');
            $("#reportsearch").change(function() {
                var reportid = $(this).find(":selected").val();
                window.location = M.cfg.wwwroot + '/blocks/learnerscript/viewreport.php?id=' + reportid;
            });
            /**
             * Duration buttons
             */
            RadiosToSlider.init($('#segmented-button'), {
                size: 'medium',
                animation: true,
                reportdashboard: false
            });
            /**
             * Duration Filter
             */
            flatpickr('#customrange', {
                mode: 'range',
                onOpen: function(selectedDates, dateStr,instance){
                    instance.clear();
                },
                onClose: function(selectedDates, dateStr, instance) {
                    if(selectedDates.length !== 0){
                        $('#ls_fstartdate').val(selectedDates[0].getTime() / 1000);
                        $('#ls_fenddate').val((selectedDates[1].getTime() / 1000) + (60 * 60 * 24));
                        require(['block_learnerscript/report'], function(report) {
                            report.CreateReportPage({ reportid: args.reportid, instanceid: args.reportid, reportdashboard: false });
                        });
                    }
                }
            });

            /*
             * Get Activities and Enrolled users for selected course
             */
            if (typeof BasicparamCourse != 'undefined' || typeof FilterCourse != 'undefined') {
                $('#id_filter_course').change(function() {
                    args.courseid = $(this).find(":selected").val();
                    smartfilter.CourseData(args);
                });
            }

            /*
             * Get Enrolled courses for selected user
             */
            $('#id_filter_users').change(function() {
                var userid = $(this).find(":selected").val();
                if (userid > 0 && (FilterCourse.length > 0 || BasicparamCourse.length > 0)) {
                    if(BasicparamUsers.length > 0){
                   // FirstElementActive = true;
                    smartfilter.UserCourses({ userid: userid, reporttype: args.reporttype, reportid: args.reportid, 
                                              firstelementactive: FirstElementActive, triggercourseactivities: true });

                 }
                }
            }); 

            /*
             * Get Departments for selected Organization
             */
            $('#id_filter_organization').change(function() {
                var organizationid = $(this).find(":selected").val();
                if(organizationid > 0){
                    if ((FilterDepartments.length > 0 || BasicparamDepartments.length > 0)) {
                        if(BasicparamDepartments.length > 0){
                            FirstElementActive = true;
                        }
                        var departmentid = args.filterrequests.filter_departments;
                        smartfilter.orgdepartments({ organizationid: organizationid, reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid });
                    }
                    if ((FilterLearningpath.length > 0 || BasicparamLearningpath.length > 0)) {
                        if(BasicparamLearningpath.length > 0){
                            FirstElementActive = true;
                        }
                        var departmentid = args.filterrequests.filter_departments;
                        smartfilter.orglearningpath({ organizationid: organizationid, reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid });
                    }
                    if (FilterUser.length > 0 || BasicparamUser.length > 0) {
                        if(BasicparamUser.length > 0){
                            FirstElementActive = true;
                        }
                        smartfilter.DepartmentUser({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                    }
                    if (FilterGeostate.length > 0 || BasicparamGeostate.length > 0) {
                        if(BasicparamGeostate.length > 0){
                            FirstElementActive = true;
                        }
                        smartfilter.GeoState({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                    }
                }
            }); 
            $('#id_filter_geostate').change(function() {
                var geostate = $(this).find(":selected").val();
                if (FilterGeodistrict.length > 0 || BasicparamGeodistrict.length > 0) {
                    if(BasicparamGeodistrict.length > 0){
                        FirstElementActive = true;
                    }
                    smartfilter.GeoDistrict({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                }
            });
            $('#id_filter_geodistrict').change(function() {
                var geostate = $(this).find(":selected").val();
                if (FilterGeosubdistrict.length > 0 || BasicparamGeosubdistrict.length > 0) {
                    if(BasicparamGeosubdistrict.length > 0){
                        FirstElementActive = true;
                    }
                    smartfilter.GeoSubdistrict({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                }
            });
            $('#id_filter_geosubdistrict').change(function() {
                var geostate = $(this).find(":selected").val();
                if (FilterGeovillage.length > 0 || BasicparamGeovillage.length > 0) {
                    if(BasicparamGeovillage.length > 0){
                        FirstElementActive = true;
                    }
                    smartfilter.GeoVillage({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                }
            });
            $('#id_filter_departments').change(function() { 
                var departmentid = $(this).find(":selected").val();
                var organizationid = $('#id_filter_organization').find(":selected").val(); 
                if ((FilterSubdepartments.length > 0 || BasicparamSubdepartments.length > 0)) {
                    if(BasicparamSubdepartments.length > 0){
                        FirstElementActive = true;
                    }
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.DepartmentSubdepts({ organizationid: organizationid, departmentid: departmentid, reporttype: args.reporttype, firstelementactive: FirstElementActive, subdepartmentid: subdepartmentid });
                }
                if ((FilterCohort.length > 0 || BasicparamCohort.length > 0)) {
                    if(BasicparamCohort.length > 0){
                        FirstElementActive = true;
                    }
                    var cohortid = args.filterrequests.filter_cohort;
                    smartfilter.DepartmentCohorts({ organizationid: organizationid, departmentid: departmentid, reporttype: args.reporttype, firstelementactive: FirstElementActive, cohortid: cohortid });
                }
                if (FilterUser.length > 0 || BasicparamUser.length > 0) {
                    if(BasicparamUser.length > 0){
                        FirstElementActive = true;
                    }
                    smartfilter.DepartmentUser({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                }
            }); 

            $('#id_filter_subdepartments').change(function() { 
                var subdepartmentid = $(this).find(":selected").val();
                var departmentid = $('#id_filter_departments').find(":selected").val();
                var organizationid = $('#id_filter_organization').find(":selected").val(); 
                // if (FilterUsers.length > 0 && args.basicparams) {
                //     if (args.basicparams.length == 3 && args.basicparams[2].name == 'course') {
                //         // return false;
                //     }
                // } else { 
                    // if (BasicparamUsers.length > 0) {
                        if ((FilterUsers.length > 0 || BasicparamUsers.length > 0)) {
                            if(BasicparamUsers.length > 0){
                                FirstElementActive = true;
                            }
                            var userid = args.filterrequests.filter_users;
                            smartfilter.DepartmentUsers({ organizationid: organizationid, departmentid: departmentid, subdepartmentid: subdepartmentid, reporttype: args.reporttype, firstelementactive: FirstElementActive, userid: userid });
                        }
                        if ((FilterUser.length > 0 || BasicparamUser.length > 0)) {
                            if(BasicparamUser.length > 0){
                                FirstElementActive = true;
                            }
                            smartfilter.DepartmentUser({ organizationid: organizationid, departmentid: departmentid, subdepartmentid: subdepartmentid, reporttype: args.reporttype, firstelementactive: FirstElementActive, userid: userid });
                        }
                    // }
                // }
                // if (FilterCourse.length > 0 && args.basicparams){
                //     if (args.basicparams.length == 3 && args.basicparams[2].name == 'users') {
                //         // return false;
                //     }
                // } else {
                    if (BasicparamCourse.length > 0 || FilterCourse.length > 0) {
                        if ((FilterCourse.length > 0 || BasicparamCourse.length > 0)) {
                            if(BasicparamCourse.length > 0){
                                FirstElementActive = true;
                            }
                            var courseid = args.filterrequests.filter_course; 
                            smartfilter.DepartmentCourses({ organizationid: organizationid, departmentid: departmentid, subdepartmentid: subdepartmentid,reporttype: args.reporttype, firstelementactive: FirstElementActive, courseid: courseid });
                        }
                    }
                // } 
                if ((Filterl4departments.length > 0 || Basicparaml4departments.length > 0)) {
                    if(Basicparaml4departments.length > 0){
                        FirstElementActive = true;
                    }
                    smartfilter.Departmentl4depts({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                }
                if ((FilterLearningpath.length > 0 || BasicparamLearningpath.length > 0)) {
                    if(BasicparamLearningpath.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.deplearningpath({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid});
                }
                if ((FilterOnlinecourses.length > 0 || BasicparamOnlinecourses.length > 0)) {
                    if(BasicparamOnlinecourses.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.deponlinecourses({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }
                if ((FilterLabs.length > 0 || BasicparamLabs.length > 0)) {
                    if(BasicparamLabs.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.deplabs({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }
                if ((FilterAssessments.length > 0 || BasicparamAssessments.length > 0)) {
                    if(BasicparamAssessments.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.depassessments({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }
                if ((FilterUsersgroups.length > 0 || BasicparamUsersgroups.length > 0)) {
                    if(BasicparamUsersgroups.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.depusergroups({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }                
                if ((FilterWebinars.length > 0 || BasicparamWebinars.length > 0)) {
                    if(BasicparamWebinars.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.depwebinars({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }
                if ((FilterClassrooms.length > 0 || BasicparamClassrooms.length > 0)) {
                    if(BasicparamClassrooms.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.depclassrooms({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }
                if ((FilterPrograms.length > 0 || BasicparamPrograms.length > 0)) {
                    if(BasicparamPrograms.length > 0){
                        FirstElementActive = true;
                    }
                    var departmentid = args.filterrequests.filter_departments;
                    var subdepartmentid = args.filterrequests.filter_subdepartments;
                    smartfilter.depprograms({reporttype: args.reporttype, firstelementactive: FirstElementActive, departmentid: departmentid, subdepartmentid: subdepartmentid });
                }                
            });

            $('#id_filter_level4department').change(function() {
                var l4departmentid = $(this).find(":selected").val();
                var subdepartmentid = $('#id_filter_subdepartments').find(":selected").val();
                var departmentid = $('#id_filter_departments').find(":selected").val();
                var organizationid = $('#id_filter_organization').find(":selected").val();

                if ((FilterUsers.length > 0 || BasicparamUsers.length > 0)) {
                    if(BasicparamUsers.length > 0){
                        FirstElementActive = true;
                    }
                    var userid = args.filterrequests.filter_users;
                    smartfilter.DepartmentUsers({ organizationid: organizationid, departmentid: departmentid, subdepartmentid: subdepartmentid, reporttype: args.reporttype, firstelementactive: FirstElementActive, userid: userid });
                }
                if ((Filterl5departments.length > 0 || Basicparaml5departments.length > 0)) {
                    if(Basicparaml5departments.length > 0){
                        FirstElementActive = true;
                    }
                    smartfilter.Departmentl5depts({reporttype: args.reporttype, firstelementactive: FirstElementActive});
                }
            });

            $('#id_filter_coursecategories').change(function() {
                var categoryid = $(this).find(":selected").val();
                smartfilter.categoryCourses({ categoryid: categoryid, reporttype: args.reporttype });
            });

            // $('#id_filter_organization').change(function() {
            //     var orgid = $(this).find(":selected").val();
            //     smartfilter.requieddependencies({ orgid: orgid, reporttype: args.reporttype, 'orgtype': 'org' });
            // });

            schedule.SelectRoleUsers();

            if (args.basicparams != null) {
                if (args.basicparams[0].name == 'course') {
                    $("#id_filter_course").trigger('change');
                    NumberOfBasicParams++;
                }
            }
            if (args.basicparams != null) {
                var FirstElementActive = false;
                if (args.basicparams[0].name == 'users') {
                    if (BasicparamCourse.length > 0) {
                        FirstElementActive = true;
                    }
                    var userid = $("#id_filter_users").find(":selected").val();
                    if (userid > 0) {
                        //args.courseid = $(this).find(":selected").val();
                        args.courseid = $('#id_filter_course').find(":selected").val();
                        smartfilter.CourseData(args);
                        // smartfilter.UserCourses({ userid: userid, reportid: args.reportid, reporttype: args.reporttype,
                        //                           firstelementactive: FirstElementActive, triggercourseactivities: true });
                    }
                }
            }

            //For forms formatting..can't make unique everywhere, so little trick ;)
            $('.filterform' + args.reportid + ' .fitemtitle').hide();
            $('.filterform' + args.reportid + ' .felement').attr('style', 'margin:0');

            $('.basicparamsform' + args.reportid + ' .fitemtitle').hide();
            $('.basicparamsform' + args.reportid + ' .felement').attr('style', 'margin:0');

            /*
             * Filter form submission
             */
            $(".filterform #id_filter_clear").click(function(e) {
                var NumberOfBasicParams = 0;
                $(".filterform" + args.reportid).trigger("reset");
                var activityelement = $(this).parent().find('#id_filter_activities');
                if (FilterUsers.length > 0) {
                    if (FilterCourse.length > 0 || BasicparamCourse.length > 0) {
                        if(BasicparamCourse.length > 0){
                            FirstElementActive = true;
                        }
                        // smartfilter.UserCourses({ userid: 0, reportid: args.reportid, reporttype: args.reporttype, firstelementactive: FirstElementActive });
                    }
                    // $("select[data-select2='1']").select2("destroy").select2({ theme: "classic" });
                }
                if (FilterCourse.length > 0) {
                    if (FilterUsers.length > 0 || BasicparamUsers.length > 0) {
                        // smartfilter.EnrolledUsers({ courseid: 0, reportid: args.reportid, reporttype: args.reporttype, components: args.components });
                    }

                    if (FilterActivity.length > 0 || BasicparamActivity.length > 0) {
                        smartfilter.CourseActivities({ courseid: 0 });
                    }
                    // $("select[data-select2='1']").select2("destroy").select2({ theme: "classic" });
                }
                if (FilterActivity.length > 0 || FilterModule.length > 0) {
                    if ((FilterCourse.length > 0 || BasicparamCourse.length > 0) && BasicparamUsers.length == 0) {
                        if(BasicparamCourse.length > 0 && BasicparamUsers.length == 0){
                            FirstElementActive = true;
                        }
                        // smartfilter.UserCourses({ userid: 0, reportid: args.reportid, reporttype: args.reporttype, firstelementactive: FirstElementActive });
                    }
                    if (BasicparamCourse.length > 0 && BasicparamUsers.length > 0) {
                            $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                    }
                    // $("select[data-select2='1']").select2("destroy").select2({ theme: "classic" });
                }

                if ($(".basicparamsform #id_filter_apply").length > 0) {
                    $(document).ajaxComplete(function(event, xhr, settings) {
                        // if (settings.url.indexOf("blocks/learnerscript/ajax.php") > 0) {
                        //     if (typeof settings.data != 'undefined') {
                        //         var ajaxaction = $.parseJSON(settings.data);
                        //         if (typeof ajaxaction.basicparam != 'undefined' && ajaxaction.basicparam == true) {
                        //             NumberOfBasicParams++;
                        //         }
                        //     }
                        //     if (args.basicparams.length == NumberOfBasicParams) {
                        //         $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                        //     }
                        // }
                    });
                    $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                } else {
                    args.reporttype = $('.ls-plotgraphs_listitem.ui-tabs-active').data('cid');
                    report.CreateReportPage({ reportid: args.reportid, reporttype: args.reporttype, instanceid: args.reportid });
                }
                $(".filterform select[data-select2='1']").select2("destroy").select2({ theme: "classic" });
                $(".filterform select[data-select2-ajax='1']").val('0').trigger('change');
                $('.filterform')[0].reset();
                $(".filterform #id_filter_clear").attr('disabled', 'disabled');
                $('.plotgraphcontainer').removeClass('show').addClass('hide');
                $('#plotreportcontainer' + args.instanceid).html('');
            });

            /*
             * Basic parameters form submission
             */
            $(".basicparamsform #id_filter_apply,.filterform #id_filter_apply").click(function(e, validate) {
                var getreport = helper.validatebasicform(validate);
                e.preventDefault();
                e.stopImmediatePropagation();
                $(".filterform" + args.reportid).show();
                args.instanceid = args.reportid;
                if(e.currentTarget.value != 'Get Report'){
                    $(".filterform #id_filter_clear").removeAttr('disabled');
                }
                if ($.inArray(0, getreport) != -1) {
                    $("#report_plottabs").hide();
                    $("#reportcontainer" + args.reportid).html("<div class='alert alert-info'>No data available</div>");
                } else {
                    $("#report_plottabs").show();
                    args.reporttype = $('.ls-plotgraphs_listitem.ui-tabs-active').data('cid');
                    report.CreateReportPage({ reportid: args.reportid, reporttype: args.reporttype, instanceid: args.instanceid, reportdashboard: false });
                }
                $('.plotgraphcontainer').removeClass('show').addClass('hide');
                $('#plotreportcontainer' + args.instanceid).html('');
            });
            /*
             * Generate Plotgraph
             */
            if (args.basicparams == null) {
                report.CreateReportPage({ reportid: args.reportid, reporttype: args.reporttype, instanceid: args.reportid, reportdashboard: false });
            } else {
                if (args.basicparams.length == 1 || args.basicparams.length == 2 || args.basicparams.length == 3 || args.basicparams.length == 4) {
                    $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                } else {
                        $(document).ajaxComplete(function(event, xhr, settings) {
                            if (settings.url.indexOf("blocks/learnerscript/ajax.php") > 0) {
                                if (typeof settings.data != 'undefined') {
                                    var ajaxaction = $.parseJSON(settings.data);
                                    if (typeof ajaxaction.basicparam != 'undefined' && ajaxaction.basicparam == true) {
                                        NumberOfBasicParams++;
                                    }
                                }
                                if (args.basicparams.length == NumberOfBasicParams
                                    && ajaxaction.action != 'plotforms' && ajaxaction.action != 'pluginlicence') {
                                    $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                                }
                            }
                        });
                }
            }

            /*
             * Make sure will have vertical tabs for plotoptions for report
             */
            // $tabs = $('#report_plottabs').tabs().addClass("ui-tabs-vertical ui-helper-clearfix");
            // $("#report_plottabs li").removeClass("ui-corner-top").addClass("ui-corner-left");

            // helper.tabsdraggable($tabs);

        },
        CreateReportPage: function(args) {
            var disabletable = 0;
            if (args.reportdashboard == false) {
                var disabletable = $('#disabletable').val();
                if (disabletable) {
                    args.reporttype = $($('.ls-plotgraphs_listitem')[0]).data('cid');
                }
            }
            if (disabletable == 1 && args.reporttype.length > 0) {
                chart.HighchartsAjax({
                    'reportid': args.reportid,
                    'action': 'generate_plotgraph',
                    'cols': args.cols,
                    'reporttype': args.reporttype
                });
            } else if (disabletable == 0) {
                reportwidget.CreateDashboardwidget({
                    reportid: args.reportid,
                    reporttype: 'table',
                    instanceid: args.reportid,
                    reportdashboard: args.reportdashboard
                });
            } else {

            }
        },
        /**
         * Generates graph widget with given Highcharts ajax response
         * @param  object response Ajax response
         * @return Creates highchart widget with given response based on type of chart
         */
        generate_plotgraph: function(response) {
            var returned;
            response.containerid = 'plotreportcontainer' + response.reportinstance;
            switch (response.type) {
                case 'pie':
                    chart.piechart(response);
                    break;
                case 'spline':
                case 'bar':
                case 'column':
                    chart.lbchart(response);
                    break;
                case 'solidgauge':
                    chart.solidgauge(response);
                    break;
                case 'combination':
                    chart.combinationchart(response);
                    break;
                case 'map':
                    chart.WorldMap(response);
                    break;
                case 'treemap':
                    chart.TreeMap(response);
                    break;
            }
        },
        /**
         * Datatable serverside for all table type reports
         * @param object args reportid
         * @return Apply serverside datatable to report table
         */
        ReportDatatable: function(args) {
            var self = this;
            var params = {};
            var reportinstance = args.instanceid ? args.instanceid : args.reportid;
            params['filters'] = args.filters;
            params['basicparams'] = args.basicparams || JSON.stringify(smartfilter.BasicparamsData(reportinstance));
            params['reportid'] = args.reportid;
            params['columns'] = args.columns;
            //
            // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
            //
            $.fn.dataTable.pipeline = function(opts) {
                // Configuration options
                var conf = $.extend({
                    url: '', // script url
                    data: null, // function or object with parameters to send to the server
                    method: 'POST' // Ajax HTTP method
                }, opts);

                return function(request, drawCallback, settings) {
                    var ajax = true;
                    var requestStart = request.start;
                    var drawStart = request.start;
                    var requestLength = request.length;
                    var requestEnd = requestStart + requestLength;

                    if (typeof args.data != 'undefined' && request.draw == 1) {
                        json = args.data;
                        json.draw = request.draw; // Update the echo for each response
                        json.data.splice(0, requestStart);
                        json.data.splice(requestLength, json.data.length);
                        drawCallback(json);
                    } else if (ajax) {
                        // Need data from the server
                        request.start = requestStart;
                        request.length = requestLength;
                        $.extend(request, conf.data);

                        settings.jqXHR = $.ajax({
                            "type": conf.method,
                            "url": conf.url,
                            "data": request,
                            "dataType": "json",
                            "cache": false,
                            "success": function(json) {
                                drawCallback(json);
                            }
                        });
                    } else {
                        json = $.extend(true, {}, cacheLastJson);
                        json.draw = request.draw; // Update the echo for each response
                        json.data.splice(0, requestStart - cacheLower);
                        json.data.splice(requestLength, json.data.length);
                        drawCallback(json);
                    }
                }
            };
            if (args.reportname == 'Users profile' || args.reportname == 'Course profile') {
                var lengthoptions = [
                    [50, 100, -1],
                    ["Show 50", "Show 100", "Show All"]
                ];
            } else {
                var lengthoptions = [
                    [10, 25, 50, 100, -1],
                    ["Show 10", "Show 25", "Show 50", "Show 100", "Show All"]
                ];
            }
            var oTable = $('#reporttable_' + reportinstance).DataTable({
                'processing': true,
                'serverSide': true,
                'destroy': true,
                'dom': '<"co_report_header"Bf <"report_header_skew"  <"report_header_skew_content" Bl<"report_header_showhide" ><"report_calculation_showhide" >> > > tr <"co_report_footer"ip>',
                'ajax': $.fn.dataTable.pipeline({
                    "type": "POST",
                    "url": M.cfg.wwwroot + '/blocks/learnerscript/components/datatable/server_processing.php?sesskey=' + M.cfg.sesskey,
                    "data": params
                }),
                'columnDefs': args.columnDefs,
                "fnDrawCallback": function(oSettings, json) {
                    chart.SparkLineReport();
                    helper.DrilldownReport();
                },
                "oScroll": {},
                'responsive': true,
                "fnInitComplete": function() {
                    this.fnAdjustColumnSizing(true);
                    $(".drilldown" + reportinstance + " .ui-dialog-title").html(args.reportname);

                    if (args.reportname == 'Users profile' || args.reportname == 'Course profile') {
                        $("#reporttable_" + reportinstance + "_wrapper .co_report_header").remove();
                        $("#reporttable_" + reportinstance + "_wrapper .co_report_footer").remove();
                    }
                    if (args.reportname == 'Learning') {
                        $("#reporttable_" + reportinstance + "_wrapper .co_report_footer").remove();
                        $( "#reporttable_" + reportinstance + "_filter" ).hide();
                    }
                    $('.download_menu' + reportinstance + ' li a').each(function(index) {
                        var link = $(this).attr('href');
                        if (typeof args.basicparams != 'undefined') {
                            var basicparamsdata = JSON.parse(args.basicparams);
                            $.each(basicparamsdata, function(key, value) {
                                if (key.indexOf('filter_') == 0) {
                                    link += '&' + key + '=' + value;
                                }
                            });
                        }
                        if (typeof(args.filters) != 'undefined') {
                            var filters = JSON.parse(args.filters);
                            $.each(filters, function(key, value) {
                                if (key.indexOf('filter_') == 0) {
                                    link += '&' + key + '=' + value;
                                }
                                if(key.indexOf('ls_') == 0) {
                                    link += '&' + key + '=' + value;
                                }
                            });
                        }
                        $(this).attr('href', link);
                    });
                },
                "fnRowCallback": function(nRow, aData, iDisplayIndex) {
                    $(nRow).children().each(function(index, td) {
                        $(td).css("word-break", args.columnDefs[index].wrap);
                        $(td).css("width", args.columnDefs[index].width);
                    });
                    return nRow;
                },
                "autoWidth": false,
                'aaSorting': [],
                'language': {
                    'paginate': {
                        'previous': '<',
                        'next': '>'
                    },
                    'sProcessing': "<img src='" + M.util.image_url('loading', 'block_learnerscript') + "'>",
                    'search': "_INPUT_",
                    'searchPlaceholder': "Search",
                    'lengthMenu': "_MENU_",
                    "emptyTable": "<div class='alert alert-info'>No data available</div>"
                },
                "lengthMenu": lengthoptions
            });
            $("#page-blocks-learnerscript-viewreport #reporttable_" + args.reportid + "_wrapper div.report_header_showhide").
            html($('#export_options' + args.reportid).html());
            if ($('.reportcalculation' + args.reportid).length > 0) {
                $("#page-blocks-learnerscript-viewreport #reporttable_" + args.reportid + "_wrapper div.report_calculation_showhide").
                html('<img src="' + M.util.image_url('calculationicon', 'block_learnerscript') + '" onclick="(function(e){ require(\'block_learnerscript/helper\').reportCalculations({reportid:' + args.reportid + '}) })(event)" title ="Calculations" />');
            }
            // $('#export_options' + args.reportid).remove();
        },
        AddExpressions: function(e, value) {
            $(e.target).on('select2:unselecting', function(e){
                $('#fitem_id_'+e.params.args.data.id+'').remove();
            });
            var columns = $(e.target).val();
            $.each(columns, function(index){
                if($('#fitem_id_'+columns[index]).length > 0){
                    return;
                }
                var column = [];
                 column['name'] = columns[index];
                 column.conditionsymbols = [];
                 var conditions = ["=", ">", "<", ">=", "<=", "<>"];
                 $.each(conditions, function(index, value){
                    column.conditionsymbols.push({
                        'value': value
                    })
                 });
                var requestdata = { column: column };
                templates.render('block_learnerscript/plotconditions', requestdata).then(function(html){
                    //$(e.target).closest('form').find('#fitem_id_yaxis_bar').after(html);
                    //$(e.target).closest('form').find('#fitem_id_yaxis').after(html);
                    if(value == 'yaxisbarvalue') {
                        $('#yaxis_bar1').append(html);
                    } else {
                        $('#yaxis1').append(html);
                    }

                }).fail(function(ex){});
            });
        }
    };
});
