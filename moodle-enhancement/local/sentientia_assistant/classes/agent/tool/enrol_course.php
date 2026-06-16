<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent\tool;

use local_sentientia_assistant\agent\tool;
use local_sentientia_assistant\agent\tool_result;
use local_sentientia_assistant\agent\invalid_tool_args;

defined('MOODLE_INTERNAL') || die();

/**
 * Tool: enrol the CURRENT learner into a self-enrollable course.
 *
 * Guard chain (enforced by tool::authorise_and_run):
 *   - validate_args: courseid must be a positive int, the course must
 *     exist, be visible, and expose an ACTIVE `self` enrolment instance.
 *     We refuse to act on any course that isn't self-enrollable — the
 *     copilot must not bypass manual/cohort enrolment policy.
 *   - capability:    local/sentientia_assistant:enrol
 *   - tenant:        the course's open_path tenant root must be the
 *     acting user's tenant. A learner can never be enrolled across tenants.
 *   - idempotency:   if already actively enrolled, OUTCOME_NOOP.
 *   - execute:       enrol_self_plugin::enrol_user() inside a transaction.
 *
 * @package local_sentientia_assistant
 */
class enrol_course extends tool {

    public function name(): string {
        return 'enrol_course';
    }

    public function capability(): string {
        return 'local/sentientia_assistant:enrol';
    }

    public function label(): string {
        return get_string('tool_enrol_course', 'local_sentientia_assistant');
    }

    public function schema(): array {
        return [
            'name'        => $this->name(),
            'description' => 'Enrol the current learner into a self-enrollable course they are not yet enrolled in.',
            'args'        => [
                'courseid' => 'integer — the id of the course to self-enrol into',
            ],
        ];
    }

    protected function validate_args(array $rawargs, int $userid): array {
        global $DB;

        $courseid = isset($rawargs['courseid']) ? (int) $rawargs['courseid'] : 0;
        if ($courseid <= 1) {
            // Reject 0/negative and the site course (id=1).
            throw new invalid_tool_args('bad_courseid');
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, visible, open_path');
        if (!$course || (int) $course->visible !== 1) {
            throw new invalid_tool_args('course_missing_or_hidden');
        }

        // The course MUST expose an active self-enrolment instance. The
        // copilot only ever uses the self-enrol path; it never bypasses
        // manual/cohort/policy enrolment.
        $selfinstance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol'    => 'self',
            'status'   => ENROL_INSTANCE_ENABLED,
        ], 'id, courseid, enrol, status, customint6', IGNORE_MULTIPLE);
        if (!$selfinstance) {
            throw new invalid_tool_args('no_self_enrol');
        }

        return [
            'courseid'   => $courseid,
            'open_path'  => (string) ($course->open_path ?? ''),
            'enrolid'    => (int) $selfinstance->id,
        ];
    }

    protected function resource_tenant(array $args, int $userid): int {
        // Derive the course's tenant root from its open_path. Empty path
        // (legacy/unscoped) falls back to the acting user's tenant via the
        // base orchestrator's 0-handling.
        $path = trim((string) ($args['open_path'] ?? ''), '/');
        if ($path === '') {
            return 0;
        }
        $first = explode('/', $path)[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }

    protected function is_noop(array $args, int $userid): bool {
        global $DB;
        // Already actively enrolled in this course?
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND ue.userid = :userid
                AND ue.status = :active",
            [
                'courseid' => $args['courseid'],
                'userid'   => $userid,
                'active'   => ENROL_USER_ACTIVE,
            ]
        );
    }

    protected function execute(array $args, int $userid): tool_result {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/enrol/self/lib.php');

        $instance = $DB->get_record('enrol', ['id' => $args['enrolid']], '*', MUST_EXIST);
        // Defensive re-check: instance must still be self + enabled + same course.
        if ($instance->enrol !== 'self'
                || (int) $instance->status !== ENROL_INSTANCE_ENABLED
                || (int) $instance->courseid !== (int) $args['courseid']) {
            return new tool_result(
                tool_result::OUTCOME_FAILED,
                get_string('agent_failed', 'local_sentientia_assistant'),
                false
            );
        }

        $plugin = enrol_get_plugin('self');
        $transaction = $DB->start_delegated_transaction();
        try {
            $plugin->enrol_user($instance, $userid, $instance->roleid ?: null,
                time(), 0, ENROL_USER_ACTIVE);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        $coursename = format_string(
            $DB->get_field('course', 'fullname', ['id' => $args['courseid']]));
        return new tool_result(
            tool_result::OUTCOME_EXECUTED,
            get_string('tool_enrol_done', 'local_sentientia_assistant', $coursename),
            true
        );
    }
}
