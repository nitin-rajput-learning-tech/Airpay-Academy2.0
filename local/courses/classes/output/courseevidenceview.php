<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This courselister is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This courselister is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this courselister.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage local_courses
 */


namespace local_courses\output;

use local_courses\plugin;
use context_course;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use moodle_url;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Class view
 * @package   local_courses
 * @copyright 2020 Fortech inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class courseevidenceview implements renderable, templatable {

    /** @var stdClass|null */
    private $courseid;

    private $userid;

    /**
     * blockview constructor.
     * @param stdClass|null $config
     */
    public function __construct($courseid,$userid,$viewtype) {
        $this->courseid = $courseid;
        $this->userid = $userid;
        $this->viewtype = $viewtype;
    }

    /**
     * Generate template
     * @param renderer_base $output
     * @return array
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $CFG, $USER;

        if($this->viewtype=='courseview'){
            $fullname=$DB->get_field('course','fullname',array('id'=>$this->courseid));
        }elseif($this->viewtype=='userview'){

            $fullname=$DB->get_field_sql("SELECT CONCAT(firstname,' ',lastname) as fullname FROM {user} where id=:userid ",array('userid'=>$this->userid));
        }

        $context =array('courseid'=>$this->courseid,'userid'=>$this->userid,'fullname'=>format_string($fullname),'viewtype'=>$this->viewtype);

        return $context;
    }
}
