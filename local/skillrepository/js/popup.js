$(document).ready(function () {
    $("#page-local-skillrepository-index #fitem_id_category").append('<img id="categories" class="add_category" src="' + M.util.image_url('t/add', 'core') +
            '" />');
    $("#page-local-skillrepository-index #fitem_id_sub_category").append('<img id="subcategory" class="add_category" src="' + M.util.image_url('t/add', 'core') +
            '" />');
    $(".generaltable").DataTable({
    });
    // Define Dialog Box Properies
    var dlg = $('#dialog_box').dialog({
        resizable: true,
        autoOpen: false,
        width: 400,
        modal: true
    });
// Call Dialog Box on Click Method
    $('.add_category').click(function (e) {
        e.preventDefault();
        resetpopup(); //reset popup form
        var c = $(this).attr('id');
        $(".set_cat").val(c);
        dlg.dialog('open');
        dlg.dialog("option", "title", 'Add New ' + c);
    });

    $('.collapsibleregioncaption').click(function () {
        var id_src = $("#id_add_img").attr('src');
        if (id_src.indexOf('add') > 0)
            var src = id_src.replace('add', 'less');
        else if (id_src.indexOf('less') > 0)
            var src = id_src.replace('less', 'add');
        $('.add_img').src = src;
    });
});


