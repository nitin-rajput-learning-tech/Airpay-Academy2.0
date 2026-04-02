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
 * Functions for component local_tags
 *
 * To set or get item tags refer to the class {@link local_tags_tag}
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return a list of page types
 *
 * @package local_tags
 * @param   string   $pagetype       current page type
 * @param   stdClass $parentcontext  Block's parent context
 * @param   stdClass $currentcontext Current context of block
 */
function local_tags_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return array(
        'tag-*'=>get_string('page-tag-x', 'local_tags'),
        'tag-index'=>get_string('page-tag-index', 'local_tags'),
        'tag-search'=>get_string('page-tag-search', 'local_tags'),
        'tag-manage'=>get_string('page-tag-manage', 'local_tags')
    );
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function local_tags_inplace_editable($itemtype, $itemid, $newvalue) {

    $context = (new \local_tags\lib\accesslib())::get_module_context();
    
    \external_api::validate_context($context);
    if ($itemtype === 'tagname') {
        return \local_tags\output\tagname::update($itemid, $newvalue);
    } else if ($itemtype === 'tagareaenable') {
        return \local_tags\output\tagareaenabled::update($itemid, $newvalue);
    } else if ($itemtype === 'tagareacollection') {
        return \local_tags\output\tagareacollection::update($itemid, $newvalue);
    } else if ($itemtype === 'tagareashowstandard') {
        return \local_tags\output\tagareashowstandard::update($itemid, $newvalue);
    } else if ($itemtype === 'tagcollname') {
        return \local_tags\output\tagcollname::update($itemid, $newvalue);
    } else if ($itemtype === 'tagcollsearchable') {
        return \local_tags\output\tagcollsearchable::update($itemid, $newvalue);
    } else if ($itemtype === 'tagflag') {
        return \local_tags\output\tagflag::update($itemid, $newvalue);
    } else if ($itemtype === 'tagisstandard') {
        return \local_tags\output\tagisstandard::update($itemid, $newvalue);
    }
}

/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
// function local_tags_leftmenunode(){
    
//     $systemcontext =(new \local_tags\lib\accesslib())::get_module_context();
//     $tagnode = '';
//     if(has_capability('local/tags:view',$systemcontext) || is_siteadmin()){
//         $tagnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browsetags', 'class'=>'pull-left user_nav_div browsetags'));
            
//             if(has_capability('local/tags:manage',$systemcontext)){
//                 $tag_url = new moodle_url('/local/tags/manage.php?tc=1');
//                 $lable = get_string('managetags','local_tags');
//             } else {
//                 $tag_url = new moodle_url('/local/tags/index.php');
//                 $lable = get_string('tags','local_tags');
//             }
//             $tags = html_writer::link($tag_url, '<i class="fa fa-tags" aria-hidden="true"></i><span class="user_navigation_link_text">'.$lable.'</span>',array('class'=>'user_navigation_link'));
//             $tagnode .= $tags;
//         $tagnode .= html_writer::end_tag('li');
//     }

//     return array('16' => $tagnode);
// }
