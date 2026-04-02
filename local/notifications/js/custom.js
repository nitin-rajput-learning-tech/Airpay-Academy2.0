    //$(function() {
    //        $( "#notification_info" ).tabs();
    //});
    
    
    //$(document).ready(function() {
    //  
    //  $( "#notifications_data" ).accordion();
    //  
    //  $('#id_notificationid').on('change', function(){       
    //    var ntypeid=$(this).val();
    //    if(ntypeid)
    //    $("#fitem_id_string_identifiers").css("display", "block");
    //    $.ajax({
    //        url: "ajax.php",
    //        type: "POST",
    //        data: 'action=notification&not_typeid='+ntypeid,
    //        //beforeSend: function(){
    //        //    $("#result").html("Sending....");
    //        //    
    //        //},
    //        success: function(data){
    //            $("#string_identifiers").html(data);
    //        }
    //    });
    //  });
    //  
    //  // for show courses dropdown based on completiondays
    //  $('#id_notificationid').on('change', function(){       
    //    var ntypeid=$(this).val();
    //    if(ntypeid)
    //    $("#fitem_id_string_identifiers").css("display", "block");
    //    $.ajax({
    //        url: "ajax.php",
    //        type: "POST",
    //        data: 'action=notification&not_typeid='+ntypeid,
    //        //beforeSend: function(){
    //        //    $("#result").html("Sending....");
    //        //    
    //        //},
    //        success: function(data){
    //            $("#string_identifiers").html(data);
    //        }
    //    });
    //  });
    //  
    //  
    //  
    //  
    //  
    //  $('#id_completiondays').on('change', function() {
    //    
    //    var dept = $('#id_costcenterid').find("option:selected").val();
    //    var completiondays = $(this).find("option:selected").val();
    //    if (completiondays !== null) {
    //      $.ajax({
    //        method: "GET",
    //        dataType: "json",
    //        url: M.cfg.wwwroot + "/local/notifications/ajax.php?action=compl_days&dept="+dept+"&days="+completiondays,
    //       
    //        success: function(data){
    //          var options = '';
    //          //options += '<option value = ' + null + ' >-- Select --</option>';
    //          if(data){
    //            $.each( data.data, function( index, value) {
    //              options +=	'<option value = ' + value.id + ' >' +value.fullname + '</option>';
    //            });
    //          }
    //          $("#id_courses").html(options);
    //        }
    //     
    //      });
    //    } 
    //  });
    //  
    //  
    //});