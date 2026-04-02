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
 * @module     block_userdasboard/elearning
 * @package    block_userdasboard
 * @copyright  2018 hemalathacarun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/url',
        'core/templates',
        'core/notification',
        'core/str',
        'core/ajax'
        ], function($,url, templates, notification, str, ajax) {


    var userdashboardElearning = function(filter){       
      self._filter ='';         
      self._template='';
      self._targetSelector='';
      self._menu=0;
      self.filter_text = '';
    
    };

    userdashboardElearning.prototype._getajaxCourses = function(){
        // alert('4');
        // alert(self._filter_text);
        self._menu = 0;
        if(self._filter=='menu'){
            self._menu = 1;
            self._filter = 'inprogress';
            self._offset = 0;
            self._limit = 2;
        }

        var removeRelated = ajax.call([{
            methodname: 'block_userdashboard_data_for_elearning_courses',
                args:  {
                    filter: self._filter,
                    filter_text: self._filter_text,
                    filter_offset: self._offset,
                    filter_limit: self._limit
                }
            }
        ]);
        
        removeRelated[0].done(function(context) {
            
            context.inprogress_elearning=$.parseJSON(context.inprogress_elearning);
            // context.filter_form = $.parseJSON(context.filter_form);
            templates.render('block_userdashboard/loading', {});
            templates.render(self._template, context).then(function(html,js) { 
                if(self._menu==1){
                    userdashboardElearning.prototype.frommenu_target_elearning(html,'#elearning_courses');
                }              
                else {
                    var existing_active = $(".divslide .active_subtab").attr('id');
                    $('#'+existing_active).html('');
                    $('#userdashboard_filter').attr("data-filter" , self._filter);
                    $(self._targetSelector).siblings().removeClass('active_subtab');         
                    $(self._targetSelector).addClass('active_subtab');
                    $(self._targetSelector).html(html);  
                }           
            }).fail(notification.exception);
        }).fail(notification.exception);
    };


    userdashboardElearning.prototype._setter = function(filter,filter_text){      
        // alert('3');
        // alert(filter_text);
        self._filter = filter;
        self._filter_text = filter_text;
        if(self._filter =='inprogress'){
            self._template='block_userdashboard/elearning_courses_innercontent';
            self._targetSelector ='#elearning_inprogress';
        }

        if(self._filter =='completed'){
           self._template='block_userdashboard/elearning_courses_innercontent';
           self._targetSelector ='#elearning_completed';
        }

        if(self._filter=='menu'){
          self._template ='block_userdashboard/userdashboard_courses';
          self._targetSelector ='#elearning_inprogress';
        }

        return  userdashboardElearning.prototype._getajaxCourses();

    };
    
    userdashboardElearning.prototype.frommenu_target_elearning= function (html, menu_targetselector){
        // alert('5');
        $('.dashboard-stat').removeClass('active_main_tab');
        $('#elearning_courses').addClass('active_main_tab');
        $(menu_targetselector).parent('.dashboard-stat').addClass('active_main_tab');
        $("#linked_course_details_info").html(html);
    };

    userdashboardElearning.callElearning = function(filter,filter_text){
        // alert('2');      
        userdashboardElearning.prototype._setter(filter,filter_text);
    };

   

    /** @alias module:block_userdashboard/userdashboard_elearning.js userdashboardElearning  **/ 
        return userdashboardElearning;
    
}); // end of main function