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
 * @package    local_users
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
             if(action === 'user_element_selector'){
                    $('[data-action="costcenter_element_selector"]').on('change', function(){
                        var elemvalue = $(this).val();
                        if(parseInt(elemvalue) > 0){
                            var depth = $(this).data('depth');
                            $.each($('[data-action="costcenter_element_selector"]'), function(index, value){
                                if($(value).data('depth') > depth){
                                    // $(value).html('');
                                    // $(value).parents().find('[data-fieldtype="autocomplete"] .form-autocomplete-selection').html($(value).data('selectstring'));
                                }

                            });
                        }
                    });
                }
            });

            formoptions = JSON.stringify(formoptions);

            promise = Ajax.call([{
                methodname: 'local_users_form_option_selector',
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
                var contexttemplate = 'local_users/form-option-selector-suggestion';

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
                    return Str.get_string('toomanyoptionstoshow', 'local_users', '>' + MAXOPTIONS).then(function(toomanyoptionstoshow) {
                        success(toomanyoptionstoshow);
                        return;
                    });
                }

            }).fail(failure);
        }
    }
});
