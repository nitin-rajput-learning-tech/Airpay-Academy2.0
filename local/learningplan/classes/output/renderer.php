<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This learningplan is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This learningplan is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this learningplan.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage local_learningplan
 */
namespace local_learningplan\output;
defined('MOODLE_INTERNAL') || die;

use context_system;
use html_table;
use html_writer;
use plugin_renderer_base;
use moodle_url;
use stdClass;

class renderer extends plugin_renderer_base {


    /**
     * Renders html to print list of learningplans tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
  public function tagged_learningplans($tagid, $exclusivemode, $ctx, $rec, $displayoptions, $count = 0, $sort='') {
    global $CFG, $DB, $USER;
    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    if ($count > 0)
    $sql =" select count(c.id) from {local_learningplan} c ";
    else
    $sql =" select c.* from {local_learningplan} c ";

    $where = " where c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";
    $joinsql = $groupby = $orderby = '';
    if (!empty($sort)) {
      switch($sort) {
        case 'highrate':
        if ($DB->get_manager()->table_exists('local_rating')) {
          $joinsql .= " LEFT JOIN {local_rating} as r ON r.itemid = c.id AND r.ratearea = 'local_learningplan' ";
          $groupby .= " group by c.id ";
          $orderby .= " order by AVG(rating) desc ";
        }        
        break;
        case 'lowrate':  
        if ($DB->get_manager()->table_exists('local_rating')) {  
          $joinsql .= " LEFT JOIN {local_rating} as r ON r.itemid = c.id AND r.ratearea = 'local_learningplan' ";
          $groupby .= " group by c.id ";
          $orderby .= " order by AVG(rating) asc ";
        }
        break;
        case 'latest':
        $orderby .= " order by c.timecreated desc ";
        break;
        case 'oldest':
        $orderby .= " order by c.timecreated asc ";
        break;
        default:
        $orderby .= " order by c.timecreated desc ";
        break;
        }
    }
    $whereparams = array();
    $conditionalwhere = '';
    if (!is_siteadmin()) {
        $wherearray = orgsql($systemcontext); // get records department wise
        $whereparams = $wherearray['params'];
        $conditionalwhere = $wherearray['sql'];
    }    

    $tagparams = array('tagid' => $tagid, 'itemtype' => 'learningplan', 'component' => 'local_learningplan');
    $params = array_merge($tagparams, $whereparams);
    if ($count > 0) {
      $records = $DB->count_records_sql($sql.$where.$conditionalwhere, $params);
      return $records;
    } else {
      $records = $DB->get_records_sql($sql.$joinsql.$where.$conditionalwhere.$groupby.$orderby, $params);
    }
    $tagfeed = new \local_tags\output\tagfeed(array(), 'learningplans');
    $img = $this->output->pix_icon('i/course', '');
    foreach ($records as $key => $value) {
      $url = $CFG->wwwroot.'/local/learningplan/view.php?cid='.$value->id.'';
      $imgwithlink = html_writer::link($url, $img);
      $modulename = html_writer::link($url, $value->name);
      $testdetails = get_learningplan_details($value->id);
      $details = '';
      $details = $this->render_from_template('local_learningplan/tagview', $testdetails);
      $tagfeed->add($imgwithlink, $modulename, $details);
    }
    return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));
  }
    public function get_userdashboard_learningplan($tab, $filter = false,$view_type = 'card'){
        $systemcontext = (new \local_users\lib\accesslib())::get_module_context();

        $templateName = 'local_learningplan/userdashboard_paginated';
        $cardClass = 'col-md-6 col-12';
        $perpage = 6;
        if($view_type=='table'){
            $templateName = 'local_learningplan/userdashboard_paginated_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 
       
        $options = array('targetID' => 'dashboard_plans', 'perPage' => $perpage, 'cardClass' => $cardClass , 'viewType' => $view_type);
        $options['methodName']='local_learningplan_userdashboard_content_paginated';
        $options['templateName']= $templateName;
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $systemcontext->id));
        $context = [
                'targetID' => 'dashboard_plans',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }
}
