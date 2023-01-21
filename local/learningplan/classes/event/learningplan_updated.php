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
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage local_learningplan
 */
namespace local_learningplan\event;
use stdclass;
defined('MOODLE_INTERNAL') || die();

class learningplan_updated extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_learningplan';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        global $DB;
        $firstname=$DB->get_field_sql("SELECT concat(firstname,' ',lastname) FROM {user} where id=$this->userid");
        $lpname = $this->other['lpname'];
        // $learningplan_name=$DB->get_field_sql("SELECT name FROM {local_learningplan} where id=$this->objectid");
        if($lpname){
            return "The user with id '$firstname ($this->userid)' has updated the Learning Path with id '$lpname ($this->objectid) '.";
        }else{
            return "The user with id '$firstname ($this->userid)' has updated the Learning Path with id '$this->objectid'.";
        }
        
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventlearningplanupdated', 'local_learningplan');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        global $DB;
        $learningplan_name=$DB->get_field_sql("SELECT name FROM {local_learningplan} where id=$this->objectid");
        if($learningplan_name){
         $url = new \moodle_url('/local/learningplan/plan_view.php',array('id'=>$this->objectid));
        }else{
          $url = new \moodle_url('/local/learningplan/index.php');
        }
        $url->set_anchor('p'.$this->objectid);
        return $url;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        // The legacy log table expects a relative path to /local/forum/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/local/learningplan/'));

        return array($this->objectid, 'learningplan', 'update learningpath', $logurl, $this->objectid, $this->contextinstanceid);
    }

    

    public static function get_objectid_mapping() {
        return array('db' => 'local_learningplan', 'restore' => 'local_learningplan');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['learningplanid'] = array('db' => 'local_learningplan', 'restore' => 'local_learningplan');

        return $othermapped;
    }
}
