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
 * Add a create actions in tooltip to the page.
 *
 * @package    theme_epsilon
 * @module     theme_epsilon/quickactions
 * @copyright  2018 eAbyas Info Solutons Pvt Ltd, India
 * @author     eAbyas  <info@eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'],
    function($) {
        return {
            load: function(){},
            quickactionsCall: function(args) {
                if(args){
                    var selector = args.selector;
                }
                var isOnDiv = false;
                var quickactions_Count_class = '';
                var quick_actions_list_count = $('.' +selector+' .options_container li').length;

                if(quick_actions_list_count > 8){
                    quickactions_Count_class = 'quick_actions_list_8_plus';
                }else{
                    quickactions_Count_class = 'quick_actions_list'+quick_actions_list_count;
                }
                $('#'+selector+'').mouseenter(function(){
                    isOnDiv = false;
                    // $('#'+selector+'').hasClass(quickactions_Count_class);
                    $('#'+selector+'').addClass(quickactions_Count_class);
                    });
                    $('#'+selector+'').mouseleave(function(){
                        isOnDiv = true;
                        $('#'+selector+'').removeClass(quickactions_Count_class);
                    });
                if($('.'+selector+'').hasClass('opened')){
                    // $('.actionicons').hasClass(quickactions_Count_class);
                    
                    setTimeout(function(e){ 
                        if(isOnDiv = true){
                            $('.'+selector+'').removeClass('opened');
                            $('.actionicons').removeClass(quickactions_Count_class);
                        }
                    }, 1000);
                }else{
                    $('.'+selector+'').addClass('opened');
                    $('.actionicons').addClass(quickactions_Count_class);
                }
            },
        };
    });