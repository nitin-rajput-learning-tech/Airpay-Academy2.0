/*
* This file is a part of e abyas Info Solutions.
*
* Copyright e abyas Info Solutions Pvt Ltd, India.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author e abyas  <info@eabyas.com>
*/
/**
 * Defines form autocomplete (types of form element)
 *
 * @package    local_kpichallenge
 * @copyright  e abyas  <info@eabyas.com>
 */

 define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {

    /** @var {Number} Maximum number of options to show. */
    var MAXOPTIONS = 100;

    return /** @alias module:enrol_manual/form-potential-option-selector */ {

        processResults: function(selector, results) {
            var options = [];
            if ($.isArray(results)) {
                $.each(results, function(index, option) {
                    options.push({
                        value: option.id,
                        label: option._label
                    });
                });
                return options;

            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            var promise;
            contextid = parseInt($(selector).data('contextid'), 10);
            action = $(selector).data('action');
            formoptions = $(selector).data('options');
            var defaultstrings = Str.get_strings([
                {
                    key:'selectdept',
                    component: 'local_courses',
                },
                {
                    key:'selectsubdept',
                    component: 'local_courses',
                },
                {
                    key:'selectcat',
                    component: 'local_courses',
                },
                {
                    key:'selectcoursetype',
                    component: 'local_courses',
                }
            ]);
            defaultstrings.then(function(s){
                var departmentselect = '<span><span>'+s[0]+'</span></span>';
                var subdeptartmentselect = '<span><span>'+s[1]+'</span></span>';
                var categoryselect = '<span><span>'+s[2]+'</span></span>';
                var course_typeselect = '<span><span>'+s[3]+'</span></span>';
                if (action === 'costcenter_organisation_selector' || action === 'costcenter_element_selector') {
                    $("#id_open_costcenterid_select").on('change', function() {
                        var department = $('#id_open_departmentid').val();
                        if(parseInt(department) >= 0){
                            $('#id_open_departmentid').html('');
                            $('.departmentselect .form-autocomplete-selection').html(departmentselect);
                        }
                        var subdept = $('#id_open_subdepartment').val();
                        if(parseInt(subdept) >= 0){
                            $('#id_open_subdepartment').html('');
                            $('.subdepartmentselect .form-autocomplete-selection').html(subdeptartmentselect);
                        }
                        var category = $('#id_category').val();
                        if(parseInt(category) > 0){
                            $('#id_category').html('');
                            $('.categoryselect .form-autocomplete-selection').html(categoryselect);
                        }
                        var category = $('#id_open_categoryid').val();
                        if(parseInt(category) > 0){
                            $('#id_open_categoryid').html('');
                            $('.idparentselect .form-autocomplete-selection').html(categoryselect);
                        }
                        var identifiedas = $('#id_identifiedtype').val();
                        if(parseInt(identifiedas) > 0){
                            $('#id_identifiedtype').html('');
                            $('.identifiedasselect .form-autocomplete-selection').html(course_typeselect);
                        }

                    });
                }else if(action === 'costcenter_department_selector' || action === 'costcenter_subdepartment_selector'){
                    $('#id_open_departmentid').on('change', function(){
                        var subdept = $('#id_open_subdepartment').val();
                        if(parseInt(subdept) >= 0){
                            $('#id_open_subdepartment').html('');
                            $('.subdepartmentselect .form-autocomplete-selection').html(subdeptartmentselect);
                        }
                        var category = $('#id_category').val();
                        if(parseInt(category) > 0){
                            $('#id_category').html('');
                            $('.categoryselect .form-autocomplete-selection').html(categoryselect);
                        }
                    });
                    $('#id_open_subdepartment').on('change', function(){
                        var category = $('#id_category').val();
                        if(parseInt(category) > 0){
                            $('#id_category').html('');
                            $('.categoryselect .form-autocomplete-selection').html(categoryselect);
                        }
                    });
                }else if(action === 'costecenter_coursetype_selector'){
                    $('#id_open_identifiedtype').on('change', function(){
                        var category = $('#id_open_identifiedtype').val();
                        if(parseInt(category) > 0){
                            $('#id_open_identifiedtype').html('');
                            $('.identifiedasselect .form-autocomplete-selection').html(course_typeselect);
                        }
                    });
                }else if(action === 'costcenter_element_selector'){
                    $('[data-action="costcenter_element_selector"]').on('change', function(){
                        var elemvalue = $(this).val();
                        if(parseInt(elemvalue) > 0){
                            var depth = $(this).data('depth');
                            $.each($('[data-action="costcenter_element_selector"]'), function(index, value){
                                if($(value).data('depth') > depth){
                                    $(value).html('');
                                    $(value).parent().find('.form-autocomplete-selection').html($(value).data('selectstring'));
                                }
                            });
                            if(depth == 1){
                                var value = $('[data-class="supervisor_select"]');
                                value.html('');
                                value.parent().find('.form-autocomplete-selection').html(value.data('selectstring'));;
                            }
                        }
                    });
                }
            });
            if(action === 'costcenter_department_selector' || action === 'costcenter_subdepartment_selector' || action === 'costecenter_coursetype_selector' || action === 'costcenter_element_selector' || action === 'custom_category_selector' || action === 'user_supervisor_selector'){
                var parentid = $('[data-class="' + $(selector).data('parentclass') + '"]').val();
                if(!(parentid == undefined && formoptions.parentid > 0)){
                    formoptions.parentid = parentid;
                }
            }else if(action === 'costcenter_category_selector'){
                formoptions.organisationid = $("#id_open_costcenterid").val();
                formoptions.departmentid = $("#id_open_departmentid").val();
                formoptions.subdepartment = $("#id_open_subdepartment").val();
                formoptions.name = $("#id_open_identifiedtype").val();

            }
            formoptions = JSON.stringify(formoptions);

            promise = Ajax.call([{
                methodname: 'local_costcenter_form_option_selector',
                args: {
                    query: query,
                    context: {contextid: contextid},
                    action: action,
                    options: formoptions,
                    searchanywhere: true,
                    page: 0,
                    perpage: MAXOPTIONS + 1
                }
            }]);

            promise[0].then(function(results) {
                results = $.parseJSON(results);
                var promises = [],
                    i = 0;
                var contexttemplate;
                // if (action === 'kpichallenge_kpi_selector') {
                //     contexttemplate = 'local_kpichallenge/form-option-kpiselector-suggestion';
                // }else if (action === 'kpichallenge_challengee_selector' || action === 'challenge_challengee_selector' || action === 'challenge_challenger_selector') {
                    contexttemplate = 'local_costcenter/form-option-selector-suggestion';
                // } else {
                //     contexttemplate = 'local_kpichallenge/form-option-selector-suggestion';
                // }    
                if (results.length <= MAXOPTIONS) {
                    // Render the label.
                    $.each(results, function(index, option) {
                        var ctx = option,
                            identity = [];
                            ctx.hasidentity = true;
                        ctx.identity = identity.join(', ');
                        promises.push(Templates.render(contexttemplate, ctx));
                    });

                    // Apply the label to the results.
                    return $.when.apply($.when, promises).then(function() {
                        var args = arguments;
                        $.each(results, function(index, option) {
                            option._label = args[i];
                            i++;
                        });
                        success(results);
                        return;
                    });

                } else {
                    return Str.get_string('toomanyoptionstoshow', 'local_costcenter', '>' + MAXOPTIONS).then(function(toomanyoptionstoshow) {
                        success(toomanyoptionstoshow);
                        return;
                    });
                }

            }).fail(failure);
        }
    }
});
