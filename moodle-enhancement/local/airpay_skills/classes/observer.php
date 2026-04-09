<?php
namespace local_airpay_skills;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Course completed — update user's skill levels based on course-skill mapping.
     */
    public static function course_completed(\core\event\course_completed $event) {
        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        skills_manager::update_from_course($userid, $courseid);
    }
}
