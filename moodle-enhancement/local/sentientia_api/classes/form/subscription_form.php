<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_sentientia_api\webhooks\subscription;

/**
 * Add-subscription form for the outbound webhooks admin page (ADR-030 Wave A).
 *
 * @package local_sentientia_api
 */
class subscription_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('webhook_name', 'local_sentientia_api'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'url', get_string('webhook_url', 'local_sentientia_api'), ['size' => 100]);
        $mform->setType('url', PARAM_URL);
        $mform->addRule('url', null, 'required', null, 'client');

        foreach (subscription::EVENTS as $event) {
            $key = 'ev_' . str_replace('.', '_', $event);
            $mform->addElement('advcheckbox', $key, get_string('webhook_events', 'local_sentientia_api'),
                get_string('event_' . str_replace('.', '_', $event), 'local_sentientia_api'));
            $mform->setDefault($key, 1);
        }

        $mform->addElement('text', 'costcenterid', get_string('webhook_tenant', 'local_sentientia_api'), ['size' => 8]);
        $mform->setType('costcenterid', PARAM_INT);
        $mform->setDefault('costcenterid', 0);
        $mform->addHelpButton('costcenterid', 'webhook_tenant', 'local_sentientia_api');

        $mform->addElement('advcheckbox', 'enabled', get_string('webhook_enabled', 'local_sentientia_api'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons(false, get_string('webhook_add', 'local_sentientia_api'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            subscription::validate_url((string) ($data['url'] ?? ''));
        } catch (\moodle_exception $e) {
            $errors['url'] = $e->getMessage();
        }
        if (self::selected_events($data) === []) {
            $errors['ev_course_completed'] = get_string('webhook_events_required', 'local_sentientia_api');
        }
        return $errors;
    }

    /**
     * Event keys ticked in submitted form data.
     *
     * @param array|\stdClass $data
     * @return string[]
     */
    public static function selected_events($data): array {
        $data = (array) $data;
        $out = [];
        foreach (subscription::EVENTS as $event) {
            if (!empty($data['ev_' . str_replace('.', '_', $event)])) {
                $out[] = $event;
            }
        }
        return $out;
    }
}
