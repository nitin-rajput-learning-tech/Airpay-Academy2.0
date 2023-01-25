define(['local_notifications/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events'],
        function(dataTable, $, Str, ModalFactory) {
    return {
        init: function() {
            $(document).on('change', '#id_notificationid, #id_open_costcenterid_select', function() {
                // console.log('init');
                var notificationid = $('#id_notificationid').find("option:selected").val();
                 var costcenterid = $('#id_open_costcenterid_select').find("option:selected").val();
                // console.log(notificationid);
                if (notificationid !== null) {
                      $.ajax({
                        method: "GET",
                        dataType: "json",
                        url: M.cfg.wwwroot + "/local/notifications/custom_ajax.php?notificationid="+notificationid+"&page="+1+"&costcenterid="+costcenterid,
                        success: function(data){
                            // console.log(data);
                            //$(".form-control-static").append(JSON.stringify(data));
                            $(".form-control-static").html(data.datastrings);
                            var template ='';
                            $.each( data.datamoduleids, function( index, value) {
                                template += '<option value = ' + value.id + ' >' +value.name + '</option>';
                           });
                            var completion_template ='';
                            $.each( data.completiondays, function( index, value) {
                                completion_template += '<option value = ' + index + ' >' + value + '</option>';
                            });
                            
                            if(template){
                                $(".module_label").css('display','block');
                                $("div .module_label .tag-info").html("");
                                $("#id_moduleid").html(template);
                                $(".module_label label").html(data.datamodule_label);
                                if(completion_template){
                                    $('#select_completiondays').html(completion_template);
                                    $('#completion_reminder_tag').html('Before Completion Days');
                                }else{
                                    $('#select_completiondays').html('<option value=0>Select Completion days</option>')
                                    $('#completion_reminder_tag').html('');
                                }
                            }else{
                                $(".module_label").css('display','none');
                            }
                            
                        }
                    });
                }
            });
            $(document).on('change', '#select_completiondays', function(){
                var notificationid = $('#id_notificationid').find("option:selected").val();
                var costcenterid = $('#id_costcenterid').find("option:selected").val();
                var completiondays = $(this).find("option:selected").val();
                $.ajax({
                    method: "POST",
                    dataType: "json",
                    url: M.cfg.wwwroot + "/local/notifications/custom_ajax.php",
                    data : {notificationid: notificationid, page: 4, costcenterid: costcenterid, completiondays: completiondays},
                    success: function(data){
                        var template ='';
                        $.each( data.datamoduleids, function( index, value) {
                            template += '<option value = ' + value.id + ' >' +value.name + '</option>';
                        });
                        if(template){
                            $("#id_moduleid").html(template);
                            $(".module_label label").html(data.datamodule_label);
                        }
                    }
                });
            });
        },
        notificationDatatable: function(args) {
            params = [];
            params.action = 'display';
            params.id = args.id;
            params.context = args.context;
            var oTable = $('#notification_info').dataTable({
                "bInfo" : false,
                "bLengthChange": false,
                "order": [],
                "language": {
                        "paginate": {
                            "next": ">",
                            "previous": "<"
                        }
                },
                "pageLength": 10
            });
        },
        deletenotification: function(elem) {
            return Str.get_strings([{
                key: 'deletenotification',
                component: 'local_notifications'
            }, {
                key: 'deleteconfirm_msg',
                component: 'local_notifications'
            }, {
                key: 'yes',
                component: 'moodle'
            }, {
                key: 'no',
                component: 'moodle'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+s[2]+'</button>&nbsp;' +
        '<button type="button" class="btn btn-secondary" data-action="cancel">'+s[3]+'</button>'
                }).done(function(modal) {
                    this.modal = modal;
                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        window.location.href ='index.php?delete='+elem+'&confirm=1&sesskey=' + M.cfg.sesskey;
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        }
        
    };
});
