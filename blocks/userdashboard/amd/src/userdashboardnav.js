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
 * @copyright  2018 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'],
    function($) {
        return {
            usernav_slider : function(subtab, index, showMore){
                $('.divslide .course-prev').show();
              //console.log(subtab);
                var selector = '#'+subtab+'.divslider .userdashboard_content';
                //$(".course-next").click(function () {

                var div = $("."+subtab)[0];
                // console.log($(div).hasClass('dashboardCard8'));
                if(showMore)
                    var limit = index-1;
                else
                    var limit = index-2;

                if($(div).data('index') == limit){
                    // console.log($('.divslide .course-next'));
                    $('.divslide .course-next').hide();
                }
                var classattr = $(div).attr('class');

                // var html = $(div).html();
                // $(selector).append("<div class=\""+classattr+"\">" + html + "</div>");
                $(selector).append(div);
                // $(div).remove();
                // });

            },
            usernavrev_slider:function(subtab, index){
                $('.divslide .course-next').show();
                //$(".course-prev").click(function () {               
                var selector = '#'+subtab+'.divslider .userdashboard_content';
                var div = $("."+subtab)[index];
                console.log($(div).data('index'));
                if($(div).data('index') == 0){
                    $('.divslide .course-prev').hide();
                }
                var html = $(div).html();
                var classattr = $(div).attr('class');
                // console.log(selector);
                // $(selector).prepend("<div class=\""+classattr+"\">" + html + "</div>");
                $(selector).prepend(div);
                // $(div).remove();
                // });
            },

            load: function(){

            }

        }
   
});
