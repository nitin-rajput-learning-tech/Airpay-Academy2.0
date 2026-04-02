$(document).ready(function () {
    var cate = $('#id_category option:selected').val(); 
    //
    // Helper functions
    //    
    // extract pre-selected IDs from associated element (HTML 'value' attributes or .val() function)
    // element: object
    function extract_preselected_ids(element){
        var preselected_ids = [];
        var delimiter = ',';
        if(element.val()) {
            if(element.val().indexOf(delimiter) != -1)            
                $.each(element.val().split(delimiter), function () {
                    preselected_ids.push({id: this[0]});
                });
            else
                preselected_ids.push({id: element.val()});            
        }
        return preselected_ids;
    };
    
    // find all objects with the pre-selected IDs
    // preselected_ids: array of IDs
    function find_preselections(preselected_ids){
        var pre_selections = []
        for(index in pre_selections)
            for(id_index in preselected_ids) {
                var objects = find_object_with_attr(pre_selections[index], {key:'id', val:preselected_ids[id_index].id})
                if(objects.length > 0)
                    pre_selections = pre_selections.concat(objects);
            }
        return pre_selections;
    };
    
    // check if the given object has the specified attribute
    // object: object
    // attr: 
    function find_object_with_attr(object, attr) {
        var objects = [];
        for (var index in object) {
            if (!object.hasOwnProperty(index)) // make sure object has a property. Otherwise, skip to next object.
                continue;
            if (object[index] && typeof object[index] == 'object') { // recursive call into children objects.
                objects = objects.concat(find_object_with_attr(object[index], attr));
            }
            else if (index == attr['key'] && object[attr['key']] == attr['val'])
                objects.push(object);
        }
        return objects;
    }

    function formatRepo (repo) {
        if (repo.loading) return repo.names;
            markup = repo.names;
            return markup;
    }
    function formatRepoSelection (repo) {
      return repo.names || repo.shortname;
    }

    $('.categories').select2({
        width: '100%',
        multiple: true,
        maximumSelectionLength : 1,
    });
    

//select3.select2('val', defaults);
    $("#id_bu").select2();
    $("#page-local-skillrepository-index #fitem_id_category").append('<img id="categories" class="add_category" data-id = "0" src="' +
        M.util.image_url('add', 'theme') + '" />');
    $("#page-local-skillrepository-index #fitem_id_sub_category").append('<img id="subcategory" class="add_category" data-id= "0" src="' +
        M.util.image_url('add', 'theme') + '" />');
    $("#generaltable").DataTable();
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
        var dataid = $(this).attr('data-id');
        dlg.dialog('open');
        if (dataid)
            dlg.dialog("option", "title", 'Create New ' + c);
        else 
            dlg.dialog("option", "title", 'Edit ' + c);
        

        if (dataid == 0) {
        }else{
            $.ajax({
                type: "post",
                url: 'ajax.php',
                data : {
                    table : c,
                    dataid : dataid,
                    edit : 1,
                    action : 'edit'
                    },
                cache: false,
                success: function (resp) {
                    document.forms["skillcategory"]['id'].value = resp.data.id;
                    document.forms["skillcategory"]['name'].value = resp.data.name;
                    document.forms["skillcategory"]['shortname'].value = resp.data.shortname;
                } 
            })
        }
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

// submit Category form through AJAX 
function addSkillCategory() {

    var table = document.forms["skillcategory"]['cat'].value
    var id = document.forms["skillcategory"]['id'].value
    var name = document.forms["skillcategory"]['name'].value
    var shortname = document.forms["skillcategory"]['shortname'].value

    var fields = ['name', 'shortname'];

    var i, l = fields.length;
    var fieldname;
    for (i = 0; i < l; i++) {
      fieldname = fields[i];
        if (document.forms["skillcategory"][fieldname].value === "" || document.forms["skillcategory"][fieldname].value === 'NULL') {
            $("#id_error_"+fieldname).css("display", "initial");
            return false;
        } else {
            $("#id_error_"+fieldname).css("display", "none");
        }
    }
    
    var action = id ? 'update' : 'insert';
    
    // AJAX code to submit form.
    $.ajax({
        type: "post",
        url: 'ajax.php',
        data: {
            id : id,
            name: name,
            shortname : shortname,
            table : table,
            action: action
        },

        success: function (resp) {
            if (resp === "SHORTNAME") {
                $("#id_error_shortname").html('Short Name Already Exists');
                $("#id_error_shortname").css("display", "initial");

            } else if (!isNaN(resp)) {    
                if (action === 'update') {
 //                   $('.' + table).append('<option selected="selected" value="' + shortname + '">' + name + '</option>');    //Appending Option in Select Box and set as selected
//                    serverSelect(table);
                    $('#'+table).attr('src', M.util.image_url('add', 'theme'));
                } else {
                    $('#'+table).attr('src', M.util.image_url('edit', 'theme'));
//                    $('.' + table).append('<option selected="selected" value="' + shortname + '">' + name + '</option>');    //Appending Option in Select Box and set as selected
//                    serverSelect(table);
                }
                $('#'+table).attr('data-id', resp);
                popup_close('dialog_box');  //Close Popup Box
            }
        }
    });
    return true;
}

// Close popup window
function popup_close(win) {
    $('#' + win).dialog('close');
    return true;
}

// Reset Popup form as a new
function resetpopup() {
    $('#popup_form')[0].reset();    // Reset Popup Form Fileds
    return true;
}

// Change Icons Of Image Tag By Src on mouseover
function changeIcons(id) {
     var src = document.getElementById(id).src;
     var src1 = src + 1;
     document.getElementById(id).src = src1;
}
 // Change Icons Of Image Tag By Src on mousedown
function removeChangeIcons(id) {
     var src1 = document.getElementById(id).src;
     var lastChar = src1.substr(src1.length - 1);
     if (lastChar == 1)
     {
         var src1 = document.getElementById(id).src;
         src = src1.slice(0, -1);
         document.getElementById(id).src = src;
     }
     return true;
}
