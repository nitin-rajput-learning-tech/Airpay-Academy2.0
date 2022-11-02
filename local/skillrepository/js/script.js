$(document).ready(function() {
    $("#skill_categories").dataTable({
        searching: true,
        responsive: true,
        "bLengthChange": true,
        // "bPaginate": false,
        //"bFilter": true,
        //"bInfo": false,
        //"bAutoWidth": false,
		"fnDrawCallback": function(oSettings) {
        }, 
        "aaSorting": [],
        "lengthMenu": [[5, 10, 25,50,100, -1], [5,10,25, 50,100, "All"]],
        //"aoColumnDefs": [{ "bSortable": true, "aTargets": [ 0 ] }],
		"language": {
            "paginate": {
                "previous": "<",
                "next": ">"
            }
        }
    });
});


$(document).ready(function() {
    $("#additionalinfo").dataTable({
        searching: true,
        responsive: true,
        "bLengthChange": true,
        // "bPaginate": false,
        //"bFilter": true,
        //"bInfo": false,
        //"bAutoWidth": false,
	   "fnDrawCallback": function(oSettings) {
        }, 
        "aaSorting": [],
        "lengthMenu": [[5, 10, 25,50,100, -1], [5,10,25, 50,100, "All"]],
        //"aoColumnDefs": [{ "bSortable": true, "aTargets": [ 0 ] }],
	    "language": {
            "paginate": {
                "previous": "<",
                "next": ">"
            }
        }
    });
});