<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Modal form: create / edit a single classroom session.
 *
 * Loaded via core_form/modalform from the classroom view's Sessions tab.
 *
 * @package    local_airpay_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_session extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;

        $classroomid = (int) $this->optional_param('classroomid', 0, PARAM_INT);
        $sessionid   = (int) $this->optional_param('sessionid',   0, PARAM_INT);
        $iscreate = ($sessionid === 0);

        $mform->addElement('hidden', 'classroomid', $classroomid);
        $mform->setType('classroomid', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', $sessionid);
        $mform->setType('sessionid', PARAM_INT);

        // ── Basic ──────────────────────────────────────────────────────
        $mform->addElement('text', 'title',
            get_string('session_title', 'local_airpay_classroom'),
            ['size' => 50, 'maxlength' => 254,
             'placeholder' => 'e.g. Day 2 — Hands-on lab']);
        $mform->setType('title', PARAM_TEXT);

        // date_time_selector returns a unix timestamp; we use it for both
        // start and end so the user picks date+time once per field.
        $mform->addElement('date_time_selector', 'starttime',
            get_string('session_starttime', 'local_airpay_classroom'),
            ['step' => 5, 'optional' => false]);

        $mform->addElement('date_time_selector', 'endtime',
            get_string('session_endtime', 'local_airpay_classroom'),
            ['step' => 5, 'optional' => false]);

        $mform->addElement('text', 'location',
            get_string('session_location', 'local_airpay_classroom'),
            ['size' => 40, 'maxlength' => 254,
             'placeholder' => 'Mumbai HQ — Training Room A']);
        $mform->setType('location', PARAM_TEXT);

        // Trainer picker — overrides classroom-level default trainer.
        $trainer_options = [
            'multiple' => false,
            'ajax' => 'core_user/form_user_selector',
            'noselectionstring' => '— Use classroom default —',
            'valuehtmlcallback' => function ($userid) {
                $user = \core_user::get_user($userid);
                if (!$user) return false;
                return fullname($user) . ' (' . s($user->email) . ')';
            },
        ];
        $mform->addElement('autocomplete', 'trainerid',
            get_string('session_trainer', 'local_airpay_classroom'), [], $trainer_options);
        $mform->setType('trainerid', PARAM_INT);

        $mform->addElement('textarea', 'notes',
            get_string('session_notes', 'local_airpay_classroom'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('notes', PARAM_TEXT);
    }

    public function validation($data, $files) {
        $errors = [];
        $start = (int) ($data['starttime'] ?? 0);
        $end   = (int) ($data['endtime']   ?? 0);
        if ($start <= 0) {
            $errors['starttime'] = get_string('invalidsessiontime', 'local_airpay_classroom');
        }
        if ($end <= 0) {
            $errors['endtime'] = get_string('invalidsessiontime', 'local_airpay_classroom');
        }
        if ($start > 0 && $end > 0 && $end <= $start) {
            $errors['endtime'] = get_string('endbeforestart', 'local_airpay_classroom');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $classroomid = (int) $data->classroomid;
        $sessionid   = (int) $data->sessionid;

        // sessiondate = starttime (we don't expose it as a separate field).
        $data->sessiondate = (int) $data->starttime;

        if ($sessionid === 0) {
            $newid = \local_airpay_classroom\session_manager::create_session($classroomid, $data);
            return [
                'classroomid' => $classroomid,
                'sessionid'   => $newid,
                'message'     => get_string('session_created', 'local_airpay_classroom'),
            ];
        }

        \local_airpay_classroom\session_manager::update_session($sessionid, $data);
        return [
            'classroomid' => $classroomid,
            'sessionid'   => $sessionid,
            'message'     => get_string('session_updated', 'local_airpay_classroom'),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $sessionid = (int) $this->optional_param('sessionid', 0, PARAM_INT);

        if ($sessionid === 0) {
            // Default times: today 09:00 to today 13:00.
            $today = strtotime('today 09:00');
            $end   = strtotime('today 13:00');
            $this->set_data((object) [
                'classroomid' => (int) $this->optional_param('classroomid', 0, PARAM_INT),
                'sessionid'   => 0,
                'starttime'   => $today,
                'endtime'     => $end,
            ]);
            return;
        }

        $s = $DB->get_record('local_airpay_classroom_sessions',
            ['id' => $sessionid], '*', MUST_EXIST);

        $this->set_data((object) [
            'classroomid' => (int) $s->classroomid,
            'sessionid'   => (int) $s->id,
            'title'       => $s->title ?? '',
            'starttime'   => (int) $s->starttime,
            'endtime'     => (int) $s->endtime,
            'location'    => $s->location ?? '',
            'trainerid'   => (int) ($s->trainerid ?? 0),
            'notes'       => $s->notes ?? '',
        ]);
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_classroom:update', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $classroomid = (int) $this->optional_param('classroomid', 0, PARAM_INT);
        return new \moodle_url('/local/airpay_classroom/view.php',
            ['id' => $classroomid, 'tab' => 'sessions']);
    }
}
