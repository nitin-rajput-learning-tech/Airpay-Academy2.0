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

namespace local_airpay_classroom\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit classroom dynamic form.
 *
 * @package    local_airpay_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_classroom extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $classroomid = (int) ($this->optional_param('classroomid', 0, PARAM_INT));
        $iscreate = ($classroomid === 0);

        $mform->addElement('hidden', 'classroomid', $classroomid);
        $mform->setType('classroomid', PARAM_INT);

        // ── Basic info ────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_airpay_classroom'));

        $mform->addElement('text', 'name', get_string('name', 'local_airpay_classroom'),
            ['size' => 50, 'maxlength' => 254]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_airpay_classroom'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Logistics ─────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_logistics', get_string('heading_logistics', 'local_airpay_classroom'));

        $mform->addElement('text', 'location', get_string('location', 'local_airpay_classroom'),
            ['size' => 40, 'maxlength' => 254, 'placeholder' => 'Mumbai HQ - Training Room A']);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('text', 'capacity', get_string('capacity', 'local_airpay_classroom'),
            ['size' => 5, 'placeholder' => '30']);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 30);

        // Trainer autocomplete using Moodle's user selector.
        $trainer_options = [
            'multiple' => false,
            'ajax' => 'core_user/form_user_selector',
            'noselectionstring' => '— Select trainer —',
            'valuehtmlcallback' => function ($userid) {
                $user = \core_user::get_user($userid);
                if (!$user) return false;
                return fullname($user) . ' (' . s($user->email) . ')';
            },
        ];
        $mform->addElement('autocomplete', 'trainerid',
            get_string('trainer', 'local_airpay_classroom'), [], $trainer_options);
        $mform->setType('trainerid', PARAM_INT);

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_org', 'local_airpay_classroom'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid', get_string('organisation', 'local_airpay_classroom'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);

        // ── Enrolment window (P1 batch 2026-05-16) ───────────────────
        // Both dates optional. When both are set, validation enforces
        // enddate >= startdate; same-day windows are allowed (single-day
        // workshop / compliance event).
        $mform->addElement('header', 'hdr_window',
            get_string('heading_window', 'local_airpay_classroom'));

        $mform->addElement('date_selector', 'startdate',
            get_string('startdate', 'local_airpay_classroom'),
            ['optional' => true]);
        $mform->setType('startdate', PARAM_INT);
        $mform->addHelpButton('startdate', 'startdate', 'local_airpay_classroom');

        $mform->addElement('date_selector', 'enddate',
            get_string('enddate', 'local_airpay_classroom'),
            ['optional' => true]);
        $mform->setType('enddate', PARAM_INT);
        $mform->addHelpButton('enddate', 'enddate', 'local_airpay_classroom');

        // ── Status ────────────────────────────────────────────────────
        if (!$iscreate) {
            $mform->addElement('header', 'hdr_status', get_string('heading_status', 'local_airpay_classroom'));
            $statusoptions = [
                \local_airpay_classroom\session_manager::STATUS_ACTIVE    => get_string('status_active', 'local_airpay_classroom'),
                \local_airpay_classroom\session_manager::STATUS_COMPLETED => get_string('status_completed', 'local_airpay_classroom'),
                \local_airpay_classroom\session_manager::STATUS_CANCELLED => get_string('status_cancelled', 'local_airpay_classroom'),
            ];
            $mform->addElement('select', 'status', get_string('status', 'local_airpay_classroom'), $statusoptions);
            $mform->setType('status', PARAM_INT);
        }
    }

    public function validation($data, $files) {
        $errors = [];
        if (isset($data['capacity']) && $data['capacity'] < 1) {
            $errors['capacity'] = get_string('capacityinvalid', 'local_airpay_classroom');
        }
        // P1 batch (2026-05-16) — enddate must be >= startdate when both set.
        $start = (int) ($data['startdate'] ?? 0);
        $end   = (int) ($data['enddate']   ?? 0);
        if ($start > 0 && $end > 0 && $end < $start) {
            $errors['enddate'] = get_string('enddate_before_start',
                'local_airpay_classroom');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $classroomid = (int) $data->classroomid;

        if ($classroomid === 0) {
            $newid = \local_airpay_classroom\session_manager::create($data);
            return ['classroomid' => $newid, 'message' => get_string('classroomcreated', 'local_airpay_classroom')];
        } else {
            \local_airpay_classroom\session_manager::update($classroomid, $data);
            return ['classroomid' => $classroomid, 'message' => get_string('classroomupdated', 'local_airpay_classroom')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $classroomid = (int) ($this->optional_param('classroomid', 0, PARAM_INT));

        if ($classroomid === 0) {
            $this->set_data((object) ['classroomid' => 0]);
            return;
        }

        $cr = $DB->get_record('local_airpay_classroom', ['id' => $classroomid], '*', MUST_EXIST);

        $this->set_data((object) [
            'classroomid'  => $cr->id,
            'name'         => $cr->name,
            'description'  => $cr->description ?? '',
            'location'     => $cr->location ?? '',
            'capacity'     => $cr->capacity ?? 30,
            'trainerid'    => $cr->trainerid ?? 0,
            'costcenterid' => $cr->costcenterid ?? 0,
            'status'       => $cr->status ?? 1,
            'startdate'    => (int) ($cr->startdate ?? 0),
            'enddate'      => (int) ($cr->enddate ?? 0),
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_classroom/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $classroomid = (int) ($this->optional_param('classroomid', 0, PARAM_INT));
        if ($classroomid === 0) {
            require_capability('local/airpay_classroom:create', $context);
        } else {
            require_capability('local/airpay_classroom:update', $context);
        }
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    private function get_org_options(): array {
        global $DB;
        $orgs = $DB->get_records('local_airpay_org', ['visible' => 1],
            'depth ASC, fullname ASC', 'id, fullname, depth');
        $options = [0 => '— No specific organisation —'];
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }
}
