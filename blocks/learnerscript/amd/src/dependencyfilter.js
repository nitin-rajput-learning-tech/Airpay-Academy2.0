/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
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
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage block_learnerscript
 */
define(['block_learnerscript/select2',
    'block_learnerscript/responsive.bootstrap',
    'core/ajax',
    'core/templates',
], function(select2, DataTable,Ajax,Templates) {
    var dependencyfilter;

    return dependencyfilter = {
        init: function(params) {
            var reportid = $('.dependencyfilter').attr('id');
            var name = params.name;
            var orgtypefield = '#id_filter_'+name+'';
            var organization = $(orgtypefield).val();
            paramdata = {id:reportid,name:name,organization:organization};

            // if(organization > 0){
                var promise = Ajax.call([{
                    methodname: 'block_learnerscript_dependencies',
                    args: paramdata
                }]);

                promise[0].done(function(resp) {
                    if(resp.name == 'course'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.courses});
                        data.then(function(html,js){
                            $('#id_filter_course').html(html);
                        });
                    }
                    if(resp.name == 'local_classroom'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.classrooms});
                        data.then(function(html,js){
                            $('#id_filter_classrooms').html(html);
                        });
                    }
                    if(resp.name == 'local_certification'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.certifications});
                        data.then(function(html,js){
                            $('#id_filter_certificates').html(html);
                        });
                    }
                    if(resp.name == 'local_evaluations'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.feedbacks});
                        data.then(function(html,js){
                            $('#id_filter_feedbacks').html(html);
                        });
                    }
                    if(resp.name == 'local_learningplan'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.learningplans});
                        data.then(function(html,js){
                            $('#id_filter_learningplans').html(html);
                        });
                    }
                    if(resp.name == 'local_onlinetests'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.onlinetests});
                        data.then(function(html,js){
                            $('#id_filter_onlinetests').html(html);
                        });
                    }
                    if(resp.name == 'local_program'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.programs});
                        data.then(function(html,js){
                            $('#id_filter_programs').html(html);
                        });
                    }
                    if(name == 'organization'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.departments});
                        data.then(function(html,js){
                            $('#id_filter_departments').html(html);
                        });
                    }
                    // if(resp.name != 'user'){
                        var data = Templates.render('block_learnerscript/dependencyfilter', {response: resp.users});
                        data.then(function(html,js){
                            $('#id_filter_user').html(html);
                        });
                    // }


                    
                }).fail(function(ex) {
                    // do something with the exception
                    console.log(ex);
                });
            // }            
        },
        load: function(){

        }
    };
});