/**
 * Add a create new group modal to the page.
 *
 * @module     block_gamification/gamification
 * @class      gamification
 * @package    block_gamification
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_courses/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function (DataTable, $, Str, ModalFactory, ModalEvents, Fragment, Ajax) {
return {
		load :function(){
			
            
		},
		getdepartmentlist: function() {
            $(document).on('change', '#id_costcenterid', function() {
                var costcentervalue = $(this).find("option:selected").val();
                // var title = M.util.get_string("select_department", "local_onlinetests");
                if (costcentervalue && costcentervalue != 'null') {
                    var promise = Ajax.call([{
                        methodname: 'local_users_get_departments_list',
                        args: {
                            costcenterid: costcentervalue,
                            contextid: 1
                        },
                    }]);
                    promise[0].done(function(resp) {
                        var resp = JSON.parse(resp);
                        var template = '';                                    
                        $.each(resp, function( index, value) {
                            if(index == 0){
                                return true;
                            }
                            template += '<option value = ' + index + ' >' +value+ '</option>';
                        });
                        $('#id_departmentid').html(template);
                    }).fail(function() {
                        // do something with the exception
                        alert('Error occured while processing request');
                        window.location.reload();
                    });
                }
            });
        },
        gridchangedata: function(){
            $(document).on('change', '#select_copyvalue', function(){
                var copyval = $(this).val();
                var evalid = $(this).data('evalid');
                $.ajax({
                    method: "POST",
                    dataType: "json",
                    data : {action: 'getgriddetails', copyval: copyval, evalid: evalid},
                    url: M.cfg.wwwroot+"/local/evaluation/customajax.php",
                    success: function(data){
                        $("#grid_display_table").html(data);
                    }
                }); 
            });
        }
	};
});