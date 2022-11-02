<?php
// namespace local_courses;
// defined('MOODLE_INTERNAL') || die();
// // use stdClass;
// // use context_course;
// class custom_navigation extends \global_navigation{
// 	public function initiate_course_links(){
// 		$course = $this->page->course;
// 		$coursenode = $this->add_course($course, false, self::COURSE_CURRENT);
// 		return $coursenode;
// 	}
// 	public function add_course(\stdClass $course, $forcegeneric = false, $coursetype = self::COURSE_OTHER) {
//         global $CFG, $SITE;

//         // We found the course... we can return it now :)
//         if (!$forcegeneric && array_key_exists($course->id, $this->addedcourses)) {
//             return $this->addedcourses[$course->id];
//         }

//         $coursecontext = \context_course::instance($course->id);

//         if ($coursetype != self::COURSE_MY && $coursetype != self::COURSE_CURRENT && $course->id != $SITE->id) {
//             if (is_role_switched($course->id)) {
//                 // user has to be able to access course in order to switch, let's skip the visibility test here
//             } else if (!core_course_category::can_view_course_info($course)) {
//                 return false;
//             }
//         }

//         $issite = ($course->id == $SITE->id);
//         $shortname = format_string($course->shortname, true, array('context' => $coursecontext));
//         $fullname = format_string($course->fullname, true, array('context' => $coursecontext));
//         // This is the name that will be shown for the course.
//         $coursename = empty($CFG->navshowfullcoursenames) ? $shortname : $fullname;

//         if ($coursetype == self::COURSE_CURRENT) {
//             // if ($coursenode = $this->rootnodes['mycourses']->find($course->id, self::TYPE_COURSE)) {
//                 // return $coursenode;
//             // } else {
//                 $coursetype = self::COURSE_OTHER;
//             // }
//         }

//         // Can the user expand the course to see its content.
//         $canexpandcourse = true;
//         if ($issite) {
//             $parent = $this;
//             $url = null;
//             if (empty($CFG->usesitenameforsitepages)) {
//                 $coursename = get_string('sitepages');
//             }
//         } else if ($coursetype == self::COURSE_CURRENT) {
//             $parent = $this->rootnodes['currentcourse'];
//             $url = new \moodle_url('/course/view.php', array('id'=>$course->id));
//             $canexpandcourse = $this->can_expand_course($course);
//         } else if ($coursetype == self::COURSE_MY && !$forcegeneric) {
//             if (!empty($CFG->navshowmycoursecategories) && ($parent = $this->rootnodes['mycourses']->find($course->category, self::TYPE_MY_CATEGORY))) {
//                 // Nothing to do here the above statement set $parent to the category within mycourses.
//             } else {
//                 $parent = $this->rootnodes['mycourses'];
//             }
//             $url = new \moodle_url('/course/view.php', array('id'=>$course->id));
//         } else {
//             $parent = $this->rootnodes['courses'];
//             $url = new \moodle_url('/course/view.php', array('id'=>$course->id));
//             // They can only expand the course if they can access it.
//             $canexpandcourse = $this->can_expand_course($course);
//             if (!empty($course->category) && $this->show_categories($coursetype == self::COURSE_MY)) {
//                 // if (!$this->is_category_fully_loaded($course->category)) {
//                 //     // We need to load the category structure for this course
//                 //     $this->load_all_categories($course->category, false);
//                 // }
//                 // if (array_key_exists($course->category, $this->addedcategories)) {
//                 //     $parent = $this->addedcategories[$course->category];
//                 //     // This could lead to the course being created so we should check whether it is the case again
//                 //     if (!$forcegeneric && array_key_exists($course->id, $this->addedcourses)) {
//                 //         return $this->addedcourses[$course->id];
//                 //     }
//                 // }
//             }
//         }

//         $coursenode = $parent->add($coursename, $url, self::TYPE_COURSE, $shortname, $course->id, new pix_icon('i/course', ''));
//         $coursenode->showinflatnavigation = $coursetype == self::COURSE_MY;

//         $coursenode->hidden = (!$course->visible);
//         $coursenode->title(format_string($course->fullname, true, array('context' => $coursecontext, 'escape' => false)));
//         if ($canexpandcourse) {
//             // This course can be expanded by the user, make it a branch to make the system aware that its expandable by ajax.
//             $coursenode->nodetype = self::NODETYPE_BRANCH;
//             $coursenode->isexpandable = true;
//         } else {
//             $coursenode->nodetype = self::NODETYPE_LEAF;
//             $coursenode->isexpandable = false;
//         }
//         if (!$forcegeneric) {
//             $this->addedcourses[$course->id] = $coursenode;
//         }

//         return $coursenode;
//     }
// }