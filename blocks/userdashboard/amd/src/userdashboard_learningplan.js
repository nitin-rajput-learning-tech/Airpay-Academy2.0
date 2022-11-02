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
 * @copyright  2018 Maheshchandra <maheshchandra@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/url',
        'core/templates',
        'core/notification',
        'core/str',
        'core/ajax'
        ], function($,url, templates, notification, str, ajax) {


    var userdashboardLearningplan = function(filter){       
      self._filter ='';         
      self._template='';
      self._targetSelector='';
      self._menu=0;
      self.filter_text = '';
    };

    userdashboardLearningplan.prototype._getajaxCourses = function(){

        self._menu=0;
        if(self._filter=='menu'){
            self._menu = 1;
            self._filter='inprogress';
        }
        var removeRelated = ajax.call([{
            methodname: 'block_userdashboard_data_for_learningplan_courses',
                args:  {
                    filter: self._filter,
                    filter_text: self._filter_text                       
                }
            }
        ]);

        removeRelated[0].done(function(context) {
      
            context.inprogress_elearning=$.parseJSON(context.inprogress_elearning);
            templates.render('block_userdashboard/loading', {});
            templates.render(self._template, context).then(function(html,js) { 
                if(self._menu==1){
                    userdashboardLearningplan.prototype.frommenu_target_learningplan(html,'#learningplan_courses');
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


    userdashboardLearningplan.prototype._setter = function(filter,filter_text){      
  
        self._filter = filter;
        self._filter_text = filter_text;
         
        if(self._filter =='inprogress'){
            self._template='block_userdashboard/learningplan_courses_innercontent';
            self._targetSelector ='#elearning_inprogress';
        }

        if(self._filter =='completed'){
           self._template='block_userdashboard/learningplan_courses_innercontent';
           self._targetSelector ='#elearning_completed';
        }

        if(self._filter=='menu'){
          self._template ='block_userdashboard/userdashboard_courses';
          self._targetSelector ='#elearning_inprogress';
        }

        return  userdashboardLearningplan.prototype._getajaxCourses();

    };
    
    userdashboardLearningplan.prototype.frommenu_target_learningplan = function (html, menu_targetselector){
        $('.dashboard-stat').removeClass('active_main_tab');
        $('#elearning_courses').addClass('active_main_tab');
        $(menu_targetselector).parent('.dashboard-stat').addClass('active_main_tab');
        $("#linked_course_details_info").html(html);
    };

    userdashboardLearningplan.callLearningplan = function(filter,filter_text){
        userdashboardLearningplan.prototype._setter(filter,filter_text);
    };

     /** @alias module:block_userdashboard/userdashboard_elearning.js userdashboardElearning  */ 
    return userdashboardLearningplan;
}); // end of main function