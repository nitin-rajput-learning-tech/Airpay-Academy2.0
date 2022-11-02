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
 * Handle selection changes and actions on the competency tree.
 *
 * @module     block_userdasboard/navigations
 * @package    block_userdasboard
 * @copyright  2018 hemalathacarun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/url',
        'core/templates',
        'core/notification',
        'core/str',
        'core/ajax',
        'local_costcenter/cardPaginate',
        'local_courses/jquery.dataTables'
    ],
    function($,url, Templates, notification, str, Ajax, cardPaginate, dataTable) {
        var userdashboard = function(method, enabled_tab, thisdata){
            var params = {};
            // params.status = thisdata.status;
            // params.searchterm = thisdata.filter_text;
            // params.page = 0;//thisdata.page;
            // params.perpage = 10;//thisdata.perpage;
            params.filter = thisdata.status;
            params.filter_text = thisdata.filter_text;
            params.filter_offset = 0;
            params.filter_limit = 10;
            // console.log('<img src="'+M.cfg.wwwroot+'/local/ajax-loader.svg">');
            thisdata['container'].removeClass('justify-content-end');
            thisdata['container'].addClass('justify-content-center d-flex align-items-center');
            thisdata['container'].html('<img class="loading_img" src="'+M.cfg.wwwroot+'/local/ajax-loader.svg">');
            var promise = Ajax.call ([{
                methodname : method,
                args : params
            }]);

            promise[0].done(function(resp){
                var data_display_type = $('#card_list_view').data('displaytype');
                if(data_display_type == 'card'){
                    resp.card_view = 1;
                }else if(data_display_type == 'table'){
                    resp.list_view = 1;
                }else{
                    resp.card_view = 1;
                }


                Templates.render(thisdata.templatename, resp).then(function(html, js) {
                    // console.log(html);
                    // console.log(thisdata['container']);
                    // Templates.replaceNodeContents(thisdata['container'], html, js);
                    thisdata['container'].html(html);
                    thisdata['container'].removeClass('justify-content-center d-flex align-items-center');
                    thisdata['container'].addClass('justify-content-end');
                }).done(function() {
                    if(resp.list_view == 1){
                        $('.userdashboard_filter_container').hide();
                        $.fn.dataTable.ext.errMode = 'none';
                        $('#userdashboard_table_content').dataTable({
                            pageLength:2,
                            ordering: false
                        });
                    }else{
                        $('.userdashboard_filter_container').show();
                    }
                    
                });

                
            });

        };
        return {        
     
        /**
         * Initialise this page (attach event handlers etc).
         *
         * @method init
         * @param {Object} model The tree model provides some useful functions for loading and searching competencies.
         * @param {Number} pagectxid The page context ID.
         * @param {Object} taxonomies Constants indexed by level.
         * @param {Object} rulesMods The modules of the rules.
         */
        init: function() {

            // var methods = {};
            var container = $('.userdashboard_module_content');

            // methods.local_courses = 'local_courses_userdashboard_content';
            // methods.local_classroom = 'local_classroom_userdashboard_classrooms';
            // methods.local_certification = 'local_certification_userdashboard_certification';
            // methods.local_program = 'local_program_userdashboard_program';
            // methods.local_learningplan = 'local_learningplan_userdashboard_learningplans';
            // methods.local_evaluation = 'local_evaluation_userdashboard_evaluations';
            // methods.local_onlinetests = 'local_onlinetests_userdashboard_onlinetests';

            
            
            $(document).on('click', '.userdashboard_menu_link', function(){
                var active = $(this).parent('.dashboard-stat').hasClass('active_main_tab');
                // console.log(active);
                if(!active){
                    $('.dashboard-stat').removeClass('active_main_tab');
                    $(this).parent('.dashboard-stat').addClass('active_main_tab');
                    var filter_text = $('#userdashboard_filter').val();
                    if(filter_text == undefined){
                        filter_text = '';
                    }
                    var data = $(this).data();
                    data.container = container;
                    data.filter_text = filter_text;
                    var pluginname = data.pluginname;
                    var enabled_tab = data.tabname;
                    var method = pluginname+'_userdashboard_content';
                    return new userdashboard(method, enabled_tab, data);
                }
            });

            $(document).on('click', '.userdashboard_tab_link', function(){
                var filter_text = $('#userdashboard_filter').val();
                if(filter_text == undefined){
                    filter_text = '';
                }
                var data = $(this).data();
                data.container = container.find('.divslide');
                data.filter_text = filter_text;
                var pluginname = data.pluginname;
                var enabled_tab = data.tabname;
                $("#userdashboard_filter").data('filter', enabled_tab);
                $("#userdashboard_filter").data('status', enabled_tab);
                console.log($("#userdashboard_filter").data('filter'));
                var method = pluginname+'_userdashboard_content';
                return new userdashboard(method, enabled_tab, data);
            });
            $(document).on('keyup', '#userdashboard_filter', function(){
                var filter_text = $(this).val();
                var data = $(this).data();
                // console.log(filter_text);
                console.log(data);
                
                data.container = container.find('.divslide');
                data.filter_text = filter_text;
                var pluginname = data.pluginname;
                var enabled_tab = data.tabname;
                var method = pluginname+'_userdashboard_content';
                return new userdashboard(method, enabled_tab, data);
            });
            $(document).on('click', '#card_list_view', function(){
                var display_type = $(this).data('displaytype');
                if(display_type == 'card'){
                    $(this).data('displaytype','table');
                    $(this).find('i').removeClass('fa-bars').addClass('fa-th');
                    $(this).find('span').html('Card');
                }else if(display_type == 'table'){
                    $(this).data('displaytype','card');
                    $(this).find('i').removeClass('fa-th').addClass('fa-bars');
                    $(this).find('span').html('List');
                }
                var element = $('.userdashboard_module_content .userdashboard_tab_link.active');
                var filter_text = $('#userdashboard_filter').val();
                if(filter_text == undefined){
                    filter_text = '';
                }
                var data = element.data();
                console.log(element);
                data.container = container.find('.divslide');
                data.filter_text = filter_text;
                var pluginname = data.pluginname;
                var enabled_tab = data.tabname;
                $("#userdashboard_filter").data('filter', enabled_tab);
                $("#userdashboard_filter").data('status', enabled_tab);
                // console.log($("#userdashboard_filter").data('filter'));
                var method = pluginname+'_userdashboard_content';
                return new userdashboard(method, enabled_tab, data);
            });
            $(document).on('click', '#card_list_view_detailed', function(){
                var display_type = $(this).data('displaytype');
                if(display_type == 'card'){
                    var newtype = 'table';
                }else if(display_type == 'table'){
                    var newtype = 'card';
                }
                var url = new URL(window.location.href);
                url.searchParams.set("formattype", newtype);
                window.location.href = url.href;
            });
            $(document).ready(function(){
                var elem = $('.active_main_tab').find('a');
                var data = elem.data();
                data.container = container;
                data.filter_text = '';
                var pluginname = data.pluginname;
                var enabled_tab = data.tabname;
                var method = pluginname+'_userdashboard_content';
                return new userdashboard(method, enabled_tab, data);
            });

        },
        makeActive: function(identifier){
            $(document).ready(function(){
                if(!$("#"+identifier).hasClass('active')){
                    $("li.nav-item .nav-link.active").removeClass('active');
                    $("#"+identifier).addClass('active');
                }
            });
        },
        load: function(){
            $(document).on('click', '.userdashboard_tab_link', function(){
                var data = $(this).data();
                var url = window.location.href;
                var position = url.indexOf("?tab");
                var newurl = url.substr(0,url.indexOf("?tab"))+'?tab='+data.tabname;
                history.pushState('', '', newurl);
                var pluginname = data.pluginname;
                var targetid = pluginname.replace("local", 'dashboard');
                var content  = "<div data-region='"+targetid+"-count-container'></div><div data-region='"+targetid+"-list-container' id ='"+targetid+"id'></div><span class='overlay-icon-container hidden' data-region='overlay-icon-container'><span class='loading-icon icon-no-margin'></span></span>";
                var paginatedata = $('.userdashboard_content_detailed').data();
                var options = JSON.parse(JSON.parse(paginatedata.options));
                var dataoptions = JSON.parse(JSON.parse(paginatedata.dataoptions));
                var filterdata = JSON.parse(JSON.parse(paginatedata.filterdata));
                options.filter = data.tabname;
                var newoptions = options;
                $("#global_filter").val('');
                $("#global_filter").data('options', newoptions);
                $('#'+targetid).html(content);
                cardPaginate.reload(newoptions, dataoptions, filterdata);
            });
        }
    }; 
});
