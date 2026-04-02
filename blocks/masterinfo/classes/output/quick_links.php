<?php
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
 * Classroom View
 *
 * @package    block_masterinfo
 * @copyright  2017 Syed Hameed Ullah <hameed@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_masterinfo\output;

use html_writer;
use core_component;

class quick_links{
    public function display_masterinfo_links(){
        global $CFG;
        $core_component = new core_component();
        $local_pluginlist = $core_component::get_plugin_list('local');
        $tool_pluginlist = $core_component::get_plugin_list('tool');
        $block_pluginlist = $core_component::get_plugin_list('block');
        $out = html_writer::start_tag('div class="master_info_block row"');

        $pluginnavs = array();
        foreach($local_pluginlist as $key => $local_pluginname){
            if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
                require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
                $functionname = 'local_'.$key.'_masterinfo';
                if(function_exists($functionname)){
                    $data = $functionname();
                    foreach($data as  $key => $val){
                        $pluginnavs[$key][] = $val;
                    }
                }
            }
        }
        foreach($tool_pluginlist as $key => $tool_pluginname){
            if(file_exists($CFG->dirroot.'/admin/tool/'.$key.'/lib.php')){
                require_once($CFG->dirroot.'/admin/tool/'.$key.'/lib.php');
                $functionname = 'tool_'.$key.'_masterinfo';
                if(function_exists($functionname)){
                    $data = $functionname();
                    foreach($data as  $key => $val){
                        $pluginnavs[$key][] = $val;
                    }
                }
            }
        }
        foreach($block_pluginlist as $key => $block_pluginname){
            if(file_exists($CFG->dirroot.'/blocks/'.$key.'/lib.php')){
                require_once($CFG->dirroot.'/blocks/'.$key.'/lib.php');
                $functionname = 'block_'.$key.'_masterinfo';
                if(function_exists($functionname)){
                    $data = $functionname();
                    foreach($data as  $key => $val){
                        $pluginnavs[$key][] = $val;
                    }
                }
            }
        }

        ksort($pluginnavs);
        $data = array();
        foreach($pluginnavs as $pluginnav){
            foreach($pluginnav  as $key => $value){
                $data[] = $value;
            }
        }
        
        $out .= $data[0].$data[1].$data[2];
        $out .= $data[3].$data[4];
        $out .= $data[5].$data[6].$data[7].$data[8];
        $out .= html_writer::end_tag('div');
      	return $out;
    }
}
