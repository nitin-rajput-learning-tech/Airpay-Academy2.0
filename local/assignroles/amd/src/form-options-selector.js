// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * option selector module.
 *
 * @module     tool_lp/form-option-selector
 * @class      form-option-selector
 * @package    tool_lp
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates'], function ($, Ajax, Templates) {

    return /** @alias module:tool_lp/form-option-selector */ {

        processResults: function (selector, results) {
            var options = [];
            $.each(results, function (index, option) {
                options.push({
                    value: option.id,
                    label: option._label
                });
            });
            return options;
        },

        transport: function (selector, query, success, failure) {
            var promise;
            action = $(selector).data('action');

            formoptions = $(selector).data('options');

            $('#id_roleid').on('change', function() {
                
                $('#id_users').empty();
                $('#fitem_id_users .form-autocomplete-selection').empty();
                
            });

            if (action === 'role_costcenterusers') {


                // if (formoptions.roleid){

                //     formoptions.roleid = formoptions.roleid;

                // }else{

                //     console.log($("#id_roleid").val());

                    formoptions.roleid = $("#id_roleid").val();
                // }

                // if (formoptions.formtype){

                //     formoptions.formtype = formoptions.formtype;
                    
                // }else{

                    formoptions.formtype = $("#id_formtype").val();
                // }

            }

            if (action === 'role_users') {
                formoptions.roleid = formoptions.roleid;
                if (formoptions.costcenterid)
                    formoptions.organisationid = formoptions.costcenterid;
                else
                    formoptions.organisationid = $("#id_open_costcenterid").val();
            }
            formoptions = JSON.stringify(formoptions);
            promise = Ajax.call([{
                methodname: 'local_assignroles_form_option_selector',
                args: {
                    query: query,
                    action: action,
                    options: formoptions,
                    searchanywhere: true,
                    page: 0,
                    perpage: 25
                }
            }]);

            promise[0].then(function (results) {
                //console.log($results);return false;
                results = $.parseJSON(results);
                var promises = [],
                    i = 0;
                var contexttemplate;
                contexttemplate = 'local_assignroles/form-option-selector-suggestion';
                // Render the label.
                /*$.each(results, function(index, option) {
                    promises.push(Templates.render('local_assignroles/form-option-selector-suggestion', option));
                });*/
                $.each(results, function (index, option) {
                    var ctx = option,
                        identity = [];
                    ctx.hasidentity = true;
                    ctx.identity = identity.join(', ');
                    promises.push(Templates.render(contexttemplate, ctx));
                });
                // Apply the label to the results.
                return $.when.apply($.when, promises).then(function () {
                    var args = arguments;
                    $.each(results, function (index, option) {
                        option._label = args[i];
                        i++;
                    });
                    success(results);
                    return;
                });

            }).catch(failure);
        }

    };

});
