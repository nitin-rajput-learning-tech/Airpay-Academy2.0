<?php
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
 * @package   Bizlms
 * @subpackage  my_event_calendar
 * @author eabyas  <info@eabyas.in>
**/
namespace block_my_event_calendar;
use core_component;
class calendarlib {
	public static function event_calendar_plugin_details($types = array()){
		if(empty($types)){
			$standard_calendartypes = array(get_string('elear','block_my_event_calendar'), get_string('classro','block_my_event_calendar'), get_string('learningpa','block_my_event_calendar'), get_string('prog','block_my_event_calendar'), get_string('certific','block_my_event_calendar'), get_string('onlinete','block_my_event_calendar'), get_string('feedba','block_my_event_calendar'));
		}else{
			$standard_calendartypes = $types;
		}
	    $returnarr = array();
	    foreach($standard_calendartypes as $type){
	        switch($type){

	        	case get_string('elear','block_my_event_calendar'): 
	            	$returnarr['elearning'] = 0;
                   	$plugin_exists = core_component::get_plugin_directory('local', 'courses');
                    if(!empty($plugin_exists)){
                        $returnarr['elearning'] = 1;
                    }
	            break;

	            case get_string('classro','block_my_event_calendar') : 
	            	$returnarr['classroom'] = 0;
                    $plugin_exists = core_component::get_plugin_directory('local', 'classroom'); 
                    if(!empty($plugin_exists)){
	            		$returnarr['classroom'] = 1;
                    }                 
                break;

	            case get_string('learningpa','block_my_event_calendar') :  
	            	$returnarr['learningplan'] = 0;
                    $plugin_exists = core_component::get_plugin_directory('local', 'learningplan'); 
                    if(!empty($plugin_exists)){
	            		$returnarr['learningplan'] = 1;
	                } // end of if condition
                break;

	            case get_string('prog','block_my_event_calendar'):
	            	$returnarr['program'] = 0;
	                $plugin_exists = core_component::get_plugin_directory('local', 'program'); 
					if(!empty($plugin_exists)){
	            		$returnarr['program'] = 1;
					}
				break;
	                                    
	            case get_string('certific','block_my_event_calendar'): 
	            	$returnarr['certification'] = 0;
                   	$plugin_exists = core_component::get_plugin_directory('local', 'certification');
                    if(!empty($plugin_exists)){
                        $returnarr['certification'] = 1;
                    }
	            break;

	            case get_string('onlinete','block_my_event_calendar'): 
	            	$returnarr['onlinetest'] = 0;
                   	$plugin_exists = core_component::get_plugin_directory('local', 'onlinetests');
                    if(!empty($plugin_exists)){
                        $returnarr['onlinetest'] = 1;
                    }
	            break;

	            case get_string('feedba','block_my_event_calendar'): 
	            	$returnarr['feedback'] = 0;
                   	$plugin_exists = core_component::get_plugin_directory('local', 'evaluation');
                    if(!empty($plugin_exists)){
                        $returnarr['feedback'] = 1;
                    }
	            break;
	                      

	        }// end of switch case
	    } // end of foreach
	    return $returnarr;
	}
}