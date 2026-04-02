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
 * Contains class tags
 *
 * @package   local_tags
 * @copyright  2019 eabays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_tags;
defined('MOODLE_INTERNAL') || die();

class tags {
    private $db;

    private $user;

    public function __construct(){
        global $DB, $USER;
        $this->db = $DB;
        $this->user = $USER;
    }

    public function get_current_tags_to_user_site($userid) {
        global $PAGE;
        $array = array('userid' => $userid);
        $PAGE->requires->js_call_amd('local_tags/gettags', 'displaydialog', array($array));
        echo $out = \html_writer::div('', '', array('id' => 'sitetagdialog'));
    }

    public function get_item_tags($component, $itemtype, $itemid, $contextid, $arrayflag = 0, $more = 0) {
        global $CFG;
        $sql = "select tagid from {tag_instance} where component = ? AND itemtype = ? AND itemid =? AND contextid=?";
        $group = " group by tagid ";
        $order = " order by tagid ";

        $params = array($component, $itemtype, $itemid, $contextid);
        $tags = $this->db->get_records_sql($sql.$group.$order, $params);
        $data =array();
        $count = count($tags);
        $i = 1; 
        foreach ($tags as $key => $value) {
            $tag = $this->db->get_field('tag', 'name', ['id'=>$value->tagid]);

            // link removed for Tagnames by Anil
            // $url = $CFG->wwwroot.'/local/tags/index.php?id='.$value->tagid.'';
            // $data['link'] = \html_writer::link($url, $tag);

            $data['link'] = $tag;
            if ($i < 4)
            $links[] = $tag;
            elseif ($more == 1)
                $links[] = $tag;
            $i++;
            if ($i > 4 && $more == 0) {
                $alllinks[] = $tag;
            }
        }
        
        if ($count >= 4 && $more == 0) {
            $moreurl = $CFG->wwwroot.'/local/tags/index.php';
            $morelist = implode(', ', $alllinks);
            $links[] = \html_writer::link('#', '...', array('title'=>$morelist));
        }

        if ($arrayflag == 1) {
            return $links;
        }
        if(!empty($links)){

            $list = implode(', ', $links);
        }else{

            $list ='';
        }
        // send listed tags
        return $list;
    }
}