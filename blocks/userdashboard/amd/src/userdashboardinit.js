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
        'block_userdashboard/userdashboard_elearning',
        'block_userdashboard/userdashboard_classroom',
        'block_userdashboard/userdashboard_program',
        'block_userdashboard/userdashboard_certification',
        'block_userdashboard/userdashboard_learningplan',
        'block_userdashboard/userdashboard_evaluation',
        'block_userdashboard/userdashboard_onlinetest',
        'block_userdashboard/userdashboard_xseed'
        ],

       function($,url, templates, notification, str, ajax, userdashboardElearning, userdashboardClassroom, userdashboardProgram, userdashboardCertification, userdashboardLearningplan, userdashboardEvaluation, userdashboardOnlinetest, userdashboardXseed) {
        var userdashboard;
        return userdashboard = {        
     
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
           //  eleobj= {
           //    elearning_template :'block_userdashboard/elearning_courses_innercontent',
           //    target_selector :'#elearning_inprogress',
           //    filter :'',
           //    menu :'elearning',
           // };
           // var classobj={
           //   menu: 'classroom',
           // };

           $("#elearning_courses").on('click',this.menu_elearning_courses.bind(this)); 
           $("#classroom_courses").on('click',this.menu_classroom_courses.bind(this));
           $("#program_courses").on('click',this.menu_program_courses.bind(this));
           $("#certification_courses").on('click',this.menu_certification_courses.bind(this));
           $("#learningplan_courses").on('click',this.menu_learningplan_courses.bind(this));
           $("#evaluation_courses").on('click',this.menu_evaluation_courses.bind(this));
           $("#onlinetest_courses").on('click',this.menu_onlinetests_courses.bind(this));
           $("#xseed").on('click',this.menu_xseed.bind(this));
           // $("#userdashboard_filter").on('click',this.call_dashboard_courses.bind(this));
           // $(document).on('keyup', '#userdashboard_filter',this.call_dashboard_courses.bind(this));
        },

        menu_elearning_courses : function(){
          return this.elearning_courses('menu','', 0, 2);
        },
        menu_classroom_courses: function(){
           return this.classroom_courses('menu','');          
        },
        menu_program_courses : function(){
            return this.program_courses('menu','');
        },
        menu_certification_courses : function(){
            return this.certification_courses('menu','');
        },
        menu_learningplan_courses : function(){
            return this.learningplan_courses('menu','');
        },
        menu_evaluation_courses : function(){
            return this.evaluation_courses('menu');
        },
        menu_onlinetests_courses : function(){
            return this.onlinetests_courses('menu');
        },
        menu_xseed : function(){
            return this.xseed('menu');
        },
        call_dashboard_courses: function(){
          var filter_text = $('#userdashboard_filter').val();
          var component = $('#userdashboard_filter').attr("data-component");
          var filter = $('#userdashboard_filter').attr("data-filter");
          switch(component){
            case "elearning_courses":
            // alert('here');
              return this.elearning_courses(filter,filter_text, 0, 2);
              break;
            case "classroom_courses":
              return this.classroom_courses(filter,filter_text);
              break;
            case "program_courses":
              return this.program_courses(filter,filter_text);
              break;
            case "certification_courses":
              return this.certification_courses(filter,filter_text);
              break;
            case "learningplan_courses":
              return this.learningplan_courses(filter,filter_text);
              break;
            case "evaluation_courses":
              return this.evaluation_courses(filter,filter_text);
              break;
            case "onlinetests_courses":
              return this.onlinetests_courses(filter,filter_text);
              break;
            case "xseed":
              return this.xseed(filter,filter_text);
              break;
          };
          
        }, 

        elearning_courses: function(filter,filter_text, offset, limit){
            // alert('1');
            // alert(filter_text);
            userdashboardElearning.callElearning(filter,filter_text, offset, limit); 
        },
        classroom_courses: function(filter,filter_text){
            userdashboardClassroom.callClassroom(filter,filter_text); 
        },
        program_courses: function(filter,filter_text){
            userdashboardProgram.callProgram(filter,filter_text); 
        },
        certification_courses : function(filter,filter_text){
            userdashboardCertification.callCertification(filter,filter_text);
        },
        learningplan_courses: function(filter,filter_text){
            userdashboardLearningplan.callLearningplan(filter,filter_text); 
        },
        evaluation_courses: function(filter,filter_text){
            userdashboardEvaluation.callEvaluation(filter,filter_text); 
        },
        onlinetests_courses: function(filter,filter_text){
            userdashboardOnlinetest.callOnlinetest(filter,filter_text); 
        },
        xseed: function(filter,filter_text){
            userdashboardXseed.callXseed(filter,filter_text); 
        },
        makeActive: function(tab){
            $(document).ready(function(){
                if(!$("#courses_"+tab).hasClass('active')){
                    $("li.nav-item .nav-link.active").removeClass('active');
                    $("#courses_"+tab).addClass('active');
                }
            });
        }
    }; 
});
