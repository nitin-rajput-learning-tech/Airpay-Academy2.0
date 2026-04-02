define(['core/ajax',
        'block_learnerscript/report',
        'block_learnerscript/reportwidget',
        'block_learnerscript/schedule',
        'block_learnerscript/helper',
        'block_learnerscript/ajax',
        'block_learnerscript/select2',
        'block_learnerscript/jquery.dataTables',
        'block_learnerscript/radioslider',
        'block_learnerscript/flatpickr',
        'core/str',
        'jquery',
        'jqueryui',
        'block_learnerscript/bootstrapnotify',
        'block_reportdashboard/inplace_editable'
    ],
    function(Ajax, report, reportwidget, schedule, helper, ajax, select2, DataTable,RadiosToSlider,flatpickr, Str, $) {
        return {
            init: function() {
                    $(document).ajaxStop(function() {
                         $(".loader").fadeOut("slow");
                    });
                    var options = $('#compliancedashboardfilter').find(":selected").text();
                    if(options == 'All') {
                        setTimeout(function(){ 
                            $(".select2-selection.select2-selection--multiple").append("<span title='All' class='complianceselect' style='padding-left:8px'>All</span>");
                        }, 1000);
                    } else {
                        setTimeout(function(){ 
                            $(".select2-selection.select2-selection--multiple").append("<span title='Selected'  class='complianceselect' style='padding-left:8px'>Selected</span>"); 
                        }, 1000);
                    }

                    helper.Select2Ajax({});
                    $(".dashboardcourses").change(function(){
                        var courseid = $(this).val();
                        $(".report_courses").val(courseid);
                        reportwidget.DashboardTiles();
                        reportwidget.DashboardWidgets();
                        $(".viewmore").each(function(){
                            var ahref = $(this).attr('href');
                        });
                        $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                    });
                    $(".dashboardcompliance").change(function(){
                        var selected = $(this).val();
                        if(selected.includes("0")) {
                            var data = $("select.dashboardcompliance option[value='0']").attr("class");
                            if(data == 'complianceall') {
                                var option_all = $("select.dashboardcompliance option:selected").map(function () {
                                    return $(this).text();
                                }).get().join(',');
                                var array = option_all.split(',');
                                for(let i=1; i<selected.length; i++) {
                                    $(".dashboardcompliance option[value="+ selected[i] +"]").remove();
                                }
                                var selectedcompliance = $(this).val();
                                var complianceid = selectedcompliance;
                                for(let i=1; i<selected.length; i++) {
                                    $(".dashboardcompliance option").eq(selected[i]).before($("<option class='optioncompliance'></option>").val(selected[i]).text(array[i]));
                                }
                                $(".complianceselect").text("All");
                                var complianceid = 0;
                                $("#compliancedashboardfilter option[value='0']").attr("class", 'selectedcompliance');
                            } else if(data == 'selectedcompliance') {
                                $(".complianceselect").text("Selected");
                               $("#compliancedashboardfilter option[value='0']").remove();
                                var selected = $(this).val();
                                var complianceid = selected;
                               $('.dashboardcompliance').prepend(`<option value='0' class='complianceall'>All</option>`);
                            }
                        } else {
                            var complianceid = selected;
                            $(".complianceselect").text("Selected");
                        }
                        //var costcenterid = $(".report_costcenter").val();
                         var costcenterid = $('.dashboardcostcenters').find(":selected").val();
                        //var departmentid = $(".report_department").val();
                        var departmentid = $('.dashboarddepartment').find(":selected").val();
                        var subdepartmentid = $('.dashboardsubdepartment').find(":selected").val();
                        if (costcenterid == '' || costcenterid == null){
                            var costcenterid = $(".report_costcenter").val();
                        }
                        if (departmentid == '' || departmentid == null){
                            var departmentid = $(".report_department").val();
                        }
                        if (subdepartmentid == '' || subdepartmentid == null){
                           var subdepartmentid = $(".report_subdepartment").val();
                        }
                        $(".report_compliance").val(complianceid);
                        var params = {};
                        params.costcenter = costcenterid;
                        params.department = departmentid;
                        params.subdepartment = subdepartmentid;
                        params.complianceid = JSON.stringify(complianceid);
                        var promise = Ajax.call([{
                            methodname: 'block_reportdashboard_complianceslist',
                            args: params
                        }]);
                        promise[0].done(function(data) {
                            $(".compliancetabs").html(JSON.parse(data.compliancetabs));
                            var cid =  $('.compliance').data('complianceid');
                            var params = {};
                            params.complianceid = cid;
                            params.costcenter = costcenterid;
                            params.department = departmentid;
                            params.subdepartment = subdepartmentid;
                            var promise = Ajax.call([{
                                methodname: 'block_reportdashboard_compliancedetails',
                                args: params
                            }]);
                            promise[0].done(function(data) {
                                $(".allpercentage").html(data.overallpercentage+ '%');
                                $(".compliancetracking").html(data.tracking);
                                $(".section_content").html(JSON.parse(data.sections));
                                $("td a").click(function(){
                                    var url = $(this).attr('href');
                                    var tableid = 'popupoverlay';
                                    event.preventDefault();
                                    var reportid = $(this).data('reportid');
                                    var reporttype = 'table';
                                    var instanceid = $(this).data('reportid');
                                    require(['block_learnerscript/helper'], function(helper) {
                                        helper.ReportModelFromLink({container: $(this), url: url,
                                            dashboard:'compliance',
                                            tableid: tableid, 
                                                            reportid: reportid,
                                                            reporttype: reporttype,
                                                            instanceid: instanceid
                                        });
                                    });
                                });
                            });
                            $(".compliance").click(function(){
                                var complianceid = $(this).data("complianceid");
                                var params = {};
                                params.complianceid = complianceid;
                                params.costcenter = costcenterid;
                                params.department = departmentid;
                                params.subdepartment = subdepartmentid;
                                var promise = Ajax.call([{
                                    methodname: 'block_reportdashboard_compliancedetails',
                                    args: params
                                }]);
                                promise[0].done(function(data) {
                                    $(".allpercentage").html(data.overallpercentage+ '%');
                                    $(".compliancetracking").html(data.tracking);
                                    $(".section_content").html(JSON.parse(data.sections));
                                    $("td a").click(function(){
                                        var url = $(this).attr('href');
                                        var tableid = 'popupoverlay';
                                        event.preventDefault();
                                        var reportid = $(this).data('reportid');
                                        var reporttype = 'table';
                                        var instanceid = $(this).data('reportid');
                                        require(['block_learnerscript/helper'], function(helper) {
                                            helper.ReportModelFromLink({container: $(this), url: url,
                                                dashboard:'compliance',
                                                tableid: tableid, 
                                                                reportid: reportid,
                                                                reporttype: reporttype,
                                                                instanceid: instanceid
                                            });
                                        });
                                    });
                                });
                            });
                        });
                    });
                    $("#ls_onlinecourseid").change(function(){
                        var onlinecourseid = $(this).val();
                        if (onlinecourseid > 0) {
                            $(".report_onlinecourse").val(onlinecourseid);
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                            $(".viewmore").each(function(){
                                var ahref = $(this).attr('href');
                            });
                            $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                        }
                    });
                    $("#ls_labid").change(function(){
                        var labid = $(this).val();
                        $(".report_lab").val(labid);
                        if (labid > 0) {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                            $(".viewmore").each(function(){
                                var ahref = $(this).attr('href');
                            });
                            $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                        }
                    });
                    $("#ls_assessmentid").change(function(){
                        var assessmentid = $(this).val();
                        $(".report_assessments").val(assessmentid);
                        if (assessmentid > 0) {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                            $(".viewmore").each(function(){
                                var ahref = $(this).attr('href');
                            });
                            $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                        }
                    });
                    $("#ls_webinarid").change(function(){
                        var webinarid = $(this).val();
                        $(".report_webinars").val(webinarid);
                        if (webinarid > 0) {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                            $(".viewmore").each(function(){
                                var ahref = $(this).attr('href');
                            });
                            $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                        }
                    });
                    $("#ls_classroomid").change(function(){
                        var classroomid = $(this).val();
                        $(".report_classrooms").val(classroomid);
                        if (classroomid > 0) {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                            $(".viewmore").each(function(){
                                var ahref = $(this).attr('href');
                            });
                            $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                        }
                    });
                    $("#ls_learningpathid").change(function(){
                        var learningpathid = $(this).val();
                        $(".report_learningpath").val(learningpathid);
                        if (learningpathid > 0) {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                            $(".viewmore").each(function(){
                                var ahref = $(this).attr('href');
                            });
                            $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                        }
                    });
                    $(".dashboardcostcenters").change(function(){
                        var costcenterid = $(this).val();
                        $(".report_costcenter").val(costcenterid);
                        $(".viewmore").each(function(){
                            var ahref = $(this).attr('href');
                        });
                        var args = {};
                        args.action = 'departmentlist';
                        args.costcenter = costcenterid;
                        senddata = JSON.stringify(args);
                        var promise = ajax.call({
                            args:args,
                            url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        });
                        promise.done(function(response) {
                            var template = '';
                            $.each(response, function(key, value) {
                                template += '<option value = ' + key + '>' + value + '</option>';
                            });
                            $("#dashboarddepartment").html(template);
                            $("#dashboarddepartment").val($(' #dashboarddepartment option:eq(0)').val());
                            $("#dashboarddepartment").trigger('change');
                        });
                        $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                    });
                    $(".dashboarddepartment").change(function(){
                        var costcenterid = $(".report_costcenter").val();
                        var departmentid = $(this).val();
                        $(".report_department").val(departmentid);
                        $(".viewmore").each(function(){
                            var ahref = $(this).attr('href');
                        });
                        var args = {};
                        args.action = 'subdepartmentlist';
                        args.costcenter = costcenterid;
                        args.department = departmentid;
                        senddata = JSON.stringify(args);
                        var promise = ajax.call({
                            args:args,
                            url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        });
                        promise.done(function(response) {
                            var template = '';
                            $.each(response, function(key, value) {
                                template += '<option value = ' + key + '>' + value + '</option>';
                            });
                            $("#dashboardsubdepartment").html(template);
                            $("#dashboardsubdepartment").val($(' #dashboardsubdepartment option:eq(0)').val());
                            $("#dashboardsubdepartment").trigger('change');
                        });
                        // $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                    });
                    $(".dashboardsubdepartment").change(function(){
                        var costcenterid = $(".report_costcenter").val();
                        var departmentid = $(".report_department").val();
                        var subdepartmentid = $(this).val();
                        $(".report_subdepartment").val(subdepartmentid);
                        $(".report_department").val(departmentid);
                        $(".viewmore").each(function(){
                            var ahref = $(this).attr('href');
                        });
                        var args = {};
                        args.action = 'l4departmentlist';
                        args.costcenter = costcenterid;
                        args.department = departmentid;
                        args.subdepartment = subdepartmentid;
                        senddata = JSON.stringify(args);
                        var promise = ajax.call({
                            args:args,
                            url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        });
                        promise.done(function(response) {
                            var template = '';
                            $.each(response, function(key, value) {
                                template += '<option value = ' + key + '>' + value + '</option>';
                            });
                            $("#dashboardl4department").html(template);
                            $("#dashboardl4department").val($('#dashboardl4department option:eq(0)').val());
                            $("#dashboardl4department").trigger('change');
                        });
                        $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());

                        // var promise = Ajax.call([{
                        //     methodname: 'block_reportdashboard_complianceslist',
                        //     args: params
                        // }]);
                        // promise[0].done(function(data) {
                        //     $(".compliancetabs").html(JSON.parse(data.compliancetabs));
                        //     var cid =  $('.compliance').data('complianceid');
                        //     var params = {};
                        //     params.costcenter = costcenterid;
                        //     params.department = departmentid;
                        //     params.subdepartment = subdepartmentid;
                        //     params.complianceid = cid;
                        //     var promise = Ajax.call([{
                        //         methodname: 'block_reportdashboard_compliancedetails',
                        //         args: params
                        //     }]);
                        //     promise[0].done(function(data) {
                        //         $(".allpercentage").html(data.overallpercentage+ '%');
                        //         $(".section_content").html(JSON.parse(data.sections));
                        //     });
                        //     $(".compliance").click(function(){
                        //         var complianceid = $(this).data("complianceid");
                        //         var params = {};
                        //         params.costcenter = costcenterid;
                        //         params.department = departmentid;
                        //         params.subdepartment = subdepartmentid;
                        //         params.complianceid = complianceid;
                        //         var promise = Ajax.call([{
                        //             methodname: 'block_reportdashboard_compliancedetails',
                        //             args: params
                        //         }]);
                        //         promise[0].done(function(data) {
                        //             $(".allpercentage").html(data.overallpercentage+ '%');
                        //             $(".section_content").html(JSON.parse(data.sections));
                        //             $("td a").click(function(){
                        //                 var url = $(this).attr('href');
                        //                 var tableid = 'popupoverlay';
                        //                 event.preventDefault();
                        //                 var reportid = $(this).data('reportid');
                        //                 var reporttype = 'table';
                        //                 var instanceid = $(this).data('reportid');
                        //                 require(['block_learnerscript/helper'], function(helper) {
                        //                     helper.ReportModelFromLink({container: $(this), url: url,
                        //                         dashboard:'compliance',
                        //                         tableid: tableid,
                        //                         reportid: reportid,
                        //                         reporttype: reporttype,
                        //                         instanceid: instanceid
                        //                     });
                        //                 });
                        //             });
                        //         });
                        //     });
                        // });
                        // reportwidget.DashboardTiles();
                        // reportwidget.DashboardWidgets();

                        // var args = {};
                        // args.action = 'departmentcourses';
                        // args.department = departmentid;
                        // args.costcenter = costcenterid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     $.each(response, function(key, value) {
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     $("#coursedashboardfilter").html(template);
                        //     $("#coursedashboardfilter").val($(' #coursedashboardfilter option:eq(0)').val());
                        //     $("#coursedashboardfilter").trigger('change');
                        // });
                        // var args = {};
                        // args.action = 'departmentcompliances';
                        // args.department = departmentid;
                        // args.costcenter = costcenterid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     $.each(response, function(key, value) {
                        //         if(value == 'ALL') {
                        //             template += '<option value = ' + key + ' class="complianceall">' + value + '</option>';
                        //         } else {
                        //             template += '<option value = ' + key + ' class="optioncompliance">' + value + '</option>';
                        //         }
                        //     });
                        //     $("#compliancedashboardfilter").html(template);
                        //     $("#compliancedashboardfilter").val($(' #compliancedashboardfilter option:eq(0)').val());
                        //     $("#compliancedashboardfilter").trigger('change');
                        // });
                        // var args = {};
                        // args.action = 'onlinecourselist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-onlinecourses, .region-onlinecourses-filter').hide();
                        //     } else {
                        //         $('.region-onlinecourses, .region-onlinecourses-filter').show();
                        //     }
                        //     $("#ls_onlinecourseid").html(template);
                        //     $("#ls_onlinecourseid").val($(' #ls_onlinecourseid option:eq(0)').val());
                        //     $("#ls_onlinecourseid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_onlinecourses").html(template);
                        //             $("#id_filter_onlinecourses").val($(' #id_filter_onlinecourses option:eq(0)').val());
                        //             $("#id_filter_onlinecourses").trigger('change');
                        //         }
                        //     });
                        // });
                        // var args = {};
                        // args.action = 'lablist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-labs, .region-labs-filter').hide();
                        //     } else {
                        //         $('.region-labs, .region-labs-filter').show();
                        //     }
                        //     $("#ls_labid").html(template);
                        //     $("#ls_labid").val($(' #ls_labid option:eq(0)').val());
                        //     $("#ls_labid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_labs").html(template);
                        //             $("#id_filter_labs").val($(' #id_filter_labs option:eq(0)').val());
                        //             $("#id_filter_labs").trigger('change');
                        //         }
                        //     });
                        // });
                        // var args = {};
                        // args.action = 'assessmentlist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-assessments, .region-assessments-filter').hide();
                        //     } else {
                        //         $('.region-assessments, .region-assessments-filter').show();
                        //     }
                        //     $("#ls_assessmentid").html(template);
                        //     $("#ls_assessmentid").val($(' #ls_assessmentid option:eq(0)').val());
                        //     $("#ls_assessmentid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_assessments").html(template);
                        //             $("#id_filter_assessments").val($('#id_filter_assessments option:eq(0)').val());
                        //             $("#id_filter_assessments").trigger('change');
                        //         }
                        //     });
                        // });
                        // var args = {};
                        // args.action = 'webinarlist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';

                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-webinars, .region-webinars-filter').hide();
                        //     } else {
                        //         $('.region-webinars, .region-webinars-filter').show();
                        //     }

                        //     $("#ls_webinarid").html(template);
                        //     $("#ls_webinarid").val($(' #ls_webinarid option:eq(0)').val());
                        //     $("#ls_webinarid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_webinars").html(template);
                        //             $("#id_filter_webinars").val($(' #id_filter_webinars option:eq(0)').val());
                        //             $("#id_filter_webinars").trigger('change');
                        //         }
                        //     });
                        // });
                        // var args = {};
                        // args.action = 'classroomlist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-classroom, .region-classroom-filter').hide();
                        //     } else {
                        //         $('.region-classroom, .region-classroom-filter').show();
                        //     }
                        //     $("#ls_classroomid").html(template);
                        //     $("#ls_classroomid").val($(' #ls_classroomid option:eq(0)').val());
                        //     $("#ls_classroomid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_classrooms").html(template);
                        //             $("#id_filter_classrooms").val($(' #id_filter_classrooms option:eq(0)').val());
                        //             $("#id_filter_classrooms").trigger('change');
                        //         }
                        //     });
                        // });
                        // var args = {};
                        // args.action = 'programlist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-program, .region-program-filter').hide();
                        //     } else {
                        //         $('.region-program, .region-program-filter').show();
                        //     }
                        //     $("#ls_programid").html(template);
                        //     $("#ls_programid").val($(' #ls_programid option:eq(0)').val());
                        //     $("#ls_programid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_programs").html(template);
                        //             $("#id_filter_programs").val($(' #id_filter_programs option:eq(0)').val());
                        //             $("#id_filter_programs").trigger('change');
                        //         }
                        //     });
                        // });
                        // var args = {};
                        // args.action = 'learningpathlist';
                        // args.costcenter = costcenterid;
                        // args.department = departmentid;
                        // args.subdepartment = subdepartmentid;
                        // senddata = JSON.stringify(args);
                        // var promise = ajax.call({
                        //     args:args,
                        //     url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        // });
                        // promise.done(function(response) {
                        //     var template = '';
                        //     let i = 0;
                        //     $.each(response, function(key, value) {
                        //         i++;
                        //         template += '<option value = ' + key + '>' + value + '</option>';
                        //     });
                        //     if (i < 2) {
                        //         $('.region-learningpath, .region-learningpath-filter').hide();
                        //     } else {
                        //         $('.region-learningpath, .region-learningpath-filter').show();
                        //     }
                        //     $("#ls_learningpathid").html(template);
                        //     $("#ls_learningpathid").val($(' #ls_learningpathid option:eq(0)').val());
                        //     $("#ls_learningpathid").trigger('change');
                        //     $(".report_schedule.dropdown-item").click(function(){
                        //     setTimeout(myFunction, 100);
                        //         function myFunction() {
                        //             $("#id_filter_learningpath").html(template);
                        //             $("#id_filter_learningpath").val($(' #id_filter_learningpath option:eq(0)').val());
                        //             $("#id_filter_learningpath").trigger('change');
                        //         }
                        //     });
                        // });
                        // $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());
                    });
                    $(".dashboardl4department").change(function(){
                        var costcenterid = $(".report_costcenter").val();
                        var departmentid = $(".report_department").val();
                        var subdepartmentid = $('.report_subdepartment').val();
                        var l4departmentid = $(this).val();
                        $(".report_subdepartment").val(subdepartmentid);
                        $(".report_department").val(departmentid);
                        $(".viewmore").each(function(){
                            var ahref = $(this).attr('href');
                        });
                        var args = {};
                        args.action = 'l5departmentlist';
                        args.costcenter = costcenterid;
                        args.department = departmentid;
                        args.subdepartment = subdepartmentid;
                        args.l4department = l4departmentid;
                        senddata = JSON.stringify(args);
                        var promise = ajax.call({
                            args:args,
                            url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        });
                        promise.done(function(response) {
                            var template = '';
                            $.each(response, function(key, value) {
                                template += '<option value = ' + key + '>' + value + '</option>';
                            });
                            $("#dashboardl5department").html(template);
                            $("#dashboardl5department").val($('#dashboardl4department option:eq(0)').val());
                            $("#dashboardl5department").trigger('change');
                        });
                        // $('.breadcrumb-button.pull-xs-right').html($(this).find('option:selected').text());

                    });
                    $("#dashboardl5department").change(function(){
                        reportwidget.DashboardTiles();
                        reportwidget.DashboardWidgets();
                    })


                    $( "#createdashbaord_form" ).submit(function( event ) {
                        var dashboardname = $( "#id_dashboard" ).val();
                        var name = dashboardname.trim();
                        if(name == '' || name == null){
                            $( "#id_error_dashboard" ).css('display', 'block');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'none');
                            event.preventDefault();
                        }
                        spaceexist = name.indexOf(" ");
                        if(spaceexist > 0 && spaceexist != ''){
                            $( "#id_error_dashboard" ).css('display', 'none');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'block');
                            event.preventDefault();
                        }
                    });


                $.ui.dialog.prototype._focusTabbable = $.noop;
                var DheaderPosition = $("#dashboard-header").position();
                $(".sidenav").offset({top: 0});
                $("#internalbsm").offset({top: DheaderPosition.top});
                /**
                * Select2 Options
                */
                $("select[data-select2='1']").select2();
                helper.Select2Ajax({
                    action: 'reportlist',
                    multiple: true
                });
               /**
                * Filter area
                */
                $(document).on('click',".filterform #id_filter_clear",function(e) {
                    $(this).parents('.mform').trigger("reset");
                    var activityelement = $(this).parents('.mform').find('#id_filter_activities');
                    var instancelement = $(this).parents('.block_reportdashboard').find('.report_dashboard_container');
                    var reportid = instancelement.data('reportid');
                    var reporttype = instancelement.data('reporttype');
                    var instanceid = instancelement.data('blockinstance');
                    var userelement = $(this).parents('.mform').find('#id_filter_users');
                    // reportjs.UserCourses({ userid: 0, reporttype: args.reporttype ,element: activityelement});
                    // smartfilter.EnrolledUsers({ courseid: 0, element: activityelement});
                    smartfilter.CourseActivities({ courseid: 0,element: activityelement });
                    // $(".filterform select[data-select2='1']").select2("destroy").select2({ theme: "classic" });
                    $(".filterform select[data-select2-ajax='1']").val('0').trigger('change');
                    $('.filterform')[0].reset();
                    $(".filterform #id_filter_clear").attr('disabled', 'disabled');
                    // $("select[data-select2='1']").select2("destroy").select2({ theme: "classic" });
                    reportwidget.CreateDashboardwidget({reportid: reportid, reporttype: reporttype, instanceid: instanceid});
                    // $(".filterform #id_filter_clear").attr('disabled', 'disabled');
                });
                $(document).on('change', "select[name='filter_coursecategories']", function(){
                    var categoryid = this.value;
                    var courseelement = $(this).closest('.mform').find('#id_filter_courses');
                    if(courseelement.length != 0){
                        smartfilter.categoryCourses({ categoryid: categoryid ,element: courseelement});
                    }
                });
                $(document).on('change', "select[name='filter_courses']", function(){
                    var courseid = this.value;
                    var activityelement = $(this).closest('.mform').find('#id_filter_activities');
                    var userelement = $(this).closest('.mform').find('#id_filter_users');
                    if(activityelement.length != 0){
                        smartfilter.CourseActivities({ courseid: courseid ,element: activityelement});
                    }
                });

                /**
                * Duration buttons
                */
                RadiosToSlider.init($('#segmented-button'), {
                    size: 'medium',
                    animation: true,
                    reportdashboard: true
                });
                /**
                * Duration Filter
                */
                flatpickr('#customrange',{
                    mode: 'range',
                    onOpen: function(selectedDates, dateStr,instance){
                        instance.clear();
                    },
                    onClose: function(selectedDates, dateStr, instance) {
                        $('#ls_fstartdate').val(selectedDates[0].getTime() / 1000);
                        $('#ls_fenddate').val((selectedDates[1].getTime() / 1000) + (60 * 60 * 24));
                        require(['block_learnerscript/reportwidget'], function(reportjs) {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                        });
                    }
                });
                /**
                 * Escape dropdown on click of window
                 */
                window.onclick = function(event) {
                    if (!event.target.matches('.dropbtn')) {
                        var dropdowns = document.getElementsByClassName("dropdown-content");
                        var i;
                        for (i = 0; i < dropdowns.length; i++) {
                            var openDropdown = dropdowns[i];
                            if ($(openDropdown).hasClass('show')) {
                                $(openDropdown).toggleClass('show');
                            }
                        }
                    }
                }
            },
            /**
             * Add reports as blocks to dashboard
             * @return {[type]} [description]
             */
            addblocks_to_dashboard: function() {
                Str.get_string('addblockstodashboard','block_reportdashboard'
                ).then(function(s) {
                    if($('.reportslist').html().length > 0){
                        console.log("here");
                         $('.reportslist').dialog();
                    } else{

                    $.urlParam = function(name){
                    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                    if (results === null || results == ' ' ){
                       return null;
                    } else{
                       return results[1] || 0;
                    }
                }
                var role=$.urlParam('role');
                var dashboardurl=$.urlParam('dashboardurl');
                var promise = Ajax.call([{
                    methodname: 'block_reportdashboard_addwidget_to_dashboard',
                    args: {
                        role: role,
                        dashboardurl: dashboardurl,
                    },
                }]);
                promise[0].done(function(response) {
                    var widget_title_img = "<img class='dialog_title_icon' alt='Add Widgets' src='" +
                        M.util.image_url("add_widgets_icon", "block_reportdashboard") + "'/>";
                    $('.reportslist').dialog({
                        title: 'Add widgets to dashboard',
                        modal: true,
                        minWidth: 700,
                        maxHeight: 600
                    });
                    $('.reportslist').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                    $('.reportslist').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(widget_title_img + 'Add widgets to dashboard');
                     resp = JSON.parse(response);
                     console.log(resp);
                    $('.reportslist').html(resp);
                }).fail(function(ex) {
                    // do something with the exception
                     console.log('Add Tiles');
                });
            }
                });
            },
            addtiles_to_dashboard: function() {
                Str.get_string('addtilestodashboard','block_reportdashboard'
                ).then(function(s) {
                    if($('.statistics_reportslist').html().length > 0) {
                        $('.statistics_reportslist').dialog();
                    } else {
                     $.urlParam = function(name){
                    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                    if (results === null || results == ' ' ){
                       return null;
                    } else{
                       return results[1] || 0;
                    }
                }

                var role=$.urlParam('role');
                var dashboardurl=$.urlParam('dashboardurl');
                var promise = Ajax.call([{
                    methodname: 'block_reportdashboard_addtiles_to_dashboard',
                     args: {
                        role: role,
                        dashboardurl: dashboardurl,
                    },
                }]);
                 promise[0].done(function(response) {
                    var tile_title_img = "<img class='dialog_title_icon' alt='Add Tiles' src='" +
                        M.util.image_url("add_tiles_icon", "block_reportdashboard") + "'/>";
                    $('.statistics_reportslist').dialog({
                        title: 'Add tiles to dashboard',
                        modal: true,
                        minWidth: 600,
                        maxHeight: 600
                    });
                    $('.statistics_reportslist').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                    $('.statistics_reportslist').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(tile_title_img + 'Add tiles to dashboard');
                    resp = JSON.parse(response);
                    $('.statistics_reportslist').html(resp);
                    }).fail(function(ex) {
                    // do something with the exception
                     console.log('Add Tiles');
                });
                }
                });
            },
            getcompliance: function(arg) {
                var costcenterid = $(".report_costcenter").val();
                var departmentid = $(".report_department").val();
                var subdepartmentid = $('.report_subdepartment').find(":selected").val();
                var params = {};
                params.complianceid = arg;
                params.costcenter = costcenterid;
                params.department = departmentid;
                params.subdepartment = subdepartmentid;
                var promise = Ajax.call([{
                    methodname: 'block_reportdashboard_compliancedetails',
                    args: params
                }]);
                promise[0].done(function(data) {
                    $(".allpercentage").html(data.overallpercentage+ '%');
                    $(".compliancetracking").html(data.tracking);
                    $(".section_content").html(JSON.parse(data.sections));
                    $("td a").click(function(){
                        var url = $(this).attr('href');
                        var tableid = 'popupoverlay';
                        event.preventDefault();
                        var reportid = $(this).data('reportid');
                        var reporttype = 'table';
                        var instanceid = $(this).data('reportid');
                        require(['block_learnerscript/helper'], function(helper) {
                            helper.ReportModelFromLink({container: $(this), url: url,
                                dashboard:'compliance',
                                tableid: tableid, 
                                                reportid: reportid,
                                                reporttype: reporttype,
                                                instanceid: instanceid
                            });
                        });
                    });
                });
            },
            addnewdashboard: function() {
                Str.get_string('addnewdashboard','block_reportdashboard'
                ).then(function(s) {
                    document.getElementById("id_dashboard").value = '';
                    $("#id_error_dashboard").css('display', 'none');
                    var tile_title_img = "<img class='dialog_title_icon' alt='Add new dashboard' src='" +
                        M.util.image_url("add_tiles_icon", "block_reportdashboard") + "'/>";
                    $('.newreport_dashboard').dialog({
                        title: s,
                        modal: true,
                        minWidth: 450,
                        maxHeight: 600
                    });
                    $('.newreport_dashboard').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                        var Closebutton = $('.ui-icon-closethick').parent();
                        $(Closebutton).attr({
                            "title" : "Close"
                        });
                    $('.newreport_dashboard').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(tile_title_img + s);
                });
            },
            updatedashboard: function(oldname,role){
                Str.get_string('updatedashboard','block_reportdashboard'
                ).then(function(s) {
                    $( "#id_error_dashboard" ).css('display', 'none');
                    $( "#id_error_dashboard_nospaces" ).css('display', 'none');
                    var tile_title_img = "<img class='dialog_title_icon' alt='Add new dashboard' src='" +
                        M.util.image_url("add_tiles_icon", "block_reportdashboard") + "'/>";
                    $('.newreport_dashboard').dialog({
                        title: s,
                        modal: true,
                        minWidth: 450,
                        maxHeight: 600
                    });
                    $('.newreport_dashboard').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                        var Closebutton = $('.ui-icon-closethick').parent();
                        $(Closebutton).attr({
                            "title" : "Close"
                        });
                    $('.newreport_dashboard').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(tile_title_img + s);
                    document.getElementById("id_dashboard").value = oldname;
                    $("#createdashbaord_form").submit(function(event){

                        var dashboardname = $( "#id_dashboard" ).val();
                        var name = dashboardname.trim();
                        if (name == '' || name == null) {
                            $( "#id_error_dashboard" ).css('display', 'block');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'none');
                            event.preventDefault();
                            return false;
                        }
                        spaceexist = name.indexOf(" ");
                        if (spaceexist > 0 && spaceexist != '') {
                            $( "#id_error_dashboard" ).css('display', 'none');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'block');
                            event.preventDefault();
                            return false;
                        }

                        var args = {};
                        args.action = 'updatedashboard';
                        args.role = role;
                        args.oldname = oldname;
                        args.newname = document.getElementById("id_dashboard").value ;
                        senddata = JSON.stringify(args);
                        var promise = ajax.call({
                            args:args,
                            url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        });
                    });
                });
            },
            sendreportemail: function(args) {
                Str.get_strings([{
                    key: 'sendemail',
                    component: 'block_reportdashboard'
                }]).then(function(s) {
                    var url = M.cfg.wwwroot + '/blocks/learnerscript/ajax.php';
                    args.nodeContent = 'sendreportemail' + args.instanceid;
                    args.action = 'sendreportemail';
                    args.title = s;
                    AjaxForms = require('block_learnerscript/ajaxforms');
                    AjaxForms.init(args, url);
                });
            },
            reportfilter: function(args) {
                var self = this;
                if ($('.report_filter_' + args.instanceid).length < 1) {
                    var promise = Ajax.call([{
                        methodname: 'block_learnerscript_reportfilter',
                        args: {
                            action: 'reportfilter',
                            reportid: args.reportid,
                            instance: args.instanceid
                        }
                    }]);
                    promise[0].done(function(resp) {
                        $('body').append("<div class='report_filter_" + args.instanceid + "' style='display:none;'>" + resp + "</div>");
                        $("select[data-action='tagfilters']").select2();
                        $("select[data-select2-ajax='1']").each(function() {
                            if (!$(this).hasClass('select2-hidden-accessible')) {
                                helper.Select2Ajax({});
                            }
                        });
                        self.reportFilterFormModal(args);
                         $('.filterform'+args.instanceid+' .fitemtitle').hide();
                          $('.filterform'+args.instanceid+' .felement').attr('style','margin:0');
                    });
                } else {
                    self.reportFilterFormModal(args);
                }
            },
            reportFilterFormModal: function (args) {
                Str.get_string('reportfilters','block_reportdashboard'
                ).then(function(s) {
                    var title_img = "<img class='dialog_title_icon' alt='Filter' src='" +
                        M.util.image_url("reportfilter", "block_reportdashboard") + "'/>";
                    $(".report_filter_" + args.instanceid).dialog({
                        title: s,
                        dialogClass: 'reportfilter-popup',
                        modal: true,
                        resizable: true,
                        autoOpen: true,
                        draggable: false,
                        width: 517,
                        height: 'auto',
                        appendTo: "#inst" + args.instanceid,
                        position: {
                            my: "center",
                            at: "center",
                            of: "#inst" + args.instanceid,
                            within: "#inst" + args.instanceid
                        },
                        open: function(event, ui) {
                        $(this).closest(".ui-dialog")
                            .find(".ui-dialog-titlebar-close")
                            .removeClass("ui-dialog-titlebar-close")
                            .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                            var Closebutton = $('.ui-icon-closethick').parent();
                            $(Closebutton).attr({
                                "title" : "Close"
                            });

                        $(this).closest(".ui-dialog")
                            .find('.ui-dialog-title').html(title_img + s);

                        /* Submit button */
                        $(".report_filter_" + args.instanceid + " form  #id_filter_apply").click(function(e) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            if ($("#reportcontainer" + args.instanceid).html().length > 0 ) {
                                args.reporttype = $("#reportcontainer" + args.instanceid).data('reporttype');
                            } else {
                                args.reporttype = $("#plotreportcontainer" + args.instanceid).data('reporttype');
                            }
                            var reportfilter = $(".filterform" + args.instanceid).serializeArray();
                            args.container = '#reporttype_' + args.reportid;

                            require(['block_learnerscript/reportwidget'], function(reportwidget) {
                                reportwidget.CreateDashboardwidget({reportid: args.reportid,
                                                             reporttype: args.reporttype,
                                                             instanceid: args.instanceid});
                                $(".report_filter_" + args.instanceid).dialog('close');
                            });
                            $(".report_filter_" + args.instanceid + " form #id_filter_clear").removeAttr('disabled');
                        });
                    }
                });
                $(".report_filter_" + args.instanceid + " form #id_filter_clear").click(function(e) {
                    e.preventDefault();
                    $(".filterform" + args.reportid).trigger("reset");
                    require(['block_learnerscript/reportwidget'], function(reportwidget) {
                        reportwidget.DashboardWidgets(args);
                        $(".report_filter_" + args.instanceid).dialog('close');
                    });
                    $(".report_filter_" + args.instanceid).dialog('close');
                });
            });
            },
            DeleteWidget: function(args) {
                Str.get_string('deletewidget','block_reportdashboard'
                ).then(function(s) {
                    var trainers = $("#delete_dialog" + args.instanceid).dialog({
                        resizable: true,
                        autoOpen: true,
                        width: 460,
                        height: 210,
                        title: s,
                        modal: true,
                        // dialogClass: 'dialog_fixed',
                        appendTo: "#inst" + args.instanceid,
                        position: {
                            my: "center",
                            at: "center",
                            of: "#inst" + args.instanceid,
                            within: "#inst" + args.instanceid
                        },
                        open: function(event, ui) {
                            $(this).closest(".ui-dialog")
                                .find(".ui-dialog-titlebar-close")
                                .removeClass("ui-dialog-titlebar-close")
                                .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                                var Closebutton = $('.ui-icon-closethick').parent();
                                $(Closebutton).attr({
                                    "title" : "Close"
                                });
                        }
                    });
                });
            },
            Deletedashboard: function(args) {
                Str.get_string('deletedashboard','block_reportdashboard'
                ).then(function(s) {
                    var instancename = args.instance;
                        $( "#dashboard_delete_popup_"+args.random).dialog({
                            resizable: false,
                            height: 150,
                            width: 375,
                            modal: true,
                            title : s,
                            open: function(event) {
                            $(this).closest(".ui-dialog")
                                .find(".ui-dialog-titlebar-close")
                                .removeClass("ui-dialog-titlebar-close")
                                .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                                var Closebutton = $('.ui-icon-closethick').parent();
                                $(Closebutton).attr({
                                    "title" : "Close"
                                });
                            },
                            close: function(event, ui) {
                                $(this).dialog('destroy').hide();
                            }
                        });
                });
            },
            /**
             * Schedule report form in popup in dashboard
             * @param  object args reportid
             * @return Popup with schedule form
             */
            schreportform: function(args) {
                var self = this;
                Str.get_string('schedulereport','block_reportdashboard'
                ).then(function(s) {
                    var url = M.cfg.wwwroot + '/blocks/learnerscript/ajax.php';
                    args.title = s;
                    args.nodeContent = 'schreportform' + args.instanceid;
                    args.action = 'schreportform';
                    AjaxForms = require('block_learnerscript/ajaxforms');
                    AjaxForms.init(args, url);
                });
            }
        };
    });
