<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Recurring-subscription enrolment plugin (ADR-023, increment 2 skeleton).
 *
 * The enrolment LIFECYCLE lives in \enrol_sentientiasub\subscription_manager.
 * This class is the Moodle enrol-plugin contract + the feature-flag gate. The
 * Airpay subscription checkout (enrol_page_hook) + the standard editing form
 * are increment 3 — until then use_standard_editing_ui() is false and, with the
 * flag OFF (default), no instances can be added, so no UI path is reached.
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

class enrol_sentientiasub_plugin extends enrol_plugin {

    /**
     * Master feature gate. Default OFF (CLAUDE.md §13). Guarded so the plugin
     * degrades safely if local_sentientia_platform is somehow absent.
     */
    public function feature_enabled(): bool {
        return class_exists('\local_sentientia_platform\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.subscriptions.enabled');
    }

    /**
     * Only offer subscription instances when the feature is ON and the user
     * may configure enrolment. OFF (default) => false => platform unchanged.
     */
    public function can_add_instance($courseid) {
        if (!$this->feature_enabled()) {
            return false;
        }
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('enrol/sentientiasub:config', $context);
    }

    /** We assign the configured role on activation, so the role is not protected. */
    public function roles_protected() {
        return false;
    }

    public function allow_enrol(stdClass $instance) {
        return true;
    }

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    /** Increment 3 supplies the standard add/edit instance form. */
    public function use_standard_editing_ui() {
        return false;
    }

    /**
     * The course-enrolment-methods info icon.
     *
     * @param array $instances enrol_sentientiasub instances on a course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        return [new pix_icon('i/recurring', get_string('pluginname', 'enrol_sentientiasub'),
            'enrol_sentientiasub')];
    }

    /**
     * Subscription checkout placeholder. Increment 3 renders the Airpay
     * mandate/sb_* checkout here; until then nothing is shown.
     *
     * @param stdClass $instance
     * @return string
     */
    public function enrol_page_hook(stdClass $instance) {
        if (!$this->feature_enabled()) {
            return '';
        }
        // Increment 3: render the Airpay subscription checkout (sb_* mandate request).
        return '';
    }
}
