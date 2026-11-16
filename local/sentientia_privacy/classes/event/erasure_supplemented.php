<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The supplementary erasure ran for one data subject (\local_sentientia_privacy\local\erasure_completeness).
 *
 * It is the record of what the supplement deleted on top of the stock tool_dataprivacy erasure: DELETE
 * requests store no per-context record of their own (api.php:666), so without it nobody could tell what the
 * supplement did. Triggered once per supplementary delete, after the whole component loop, inside a try/catch
 * so that logging can never fail an erasure.
 *
 * The context is the system. The object is the tool_dataprivacy_request being processed (found by subject,
 * type DELETE and status PROCESSING), or none on the user-expiry path; then objecttable is cleared too.
 * There is deliberately NO relateduserid: logstore_standard discovers a user's log rows by relateduserid
 * (admin/tool/log/store/standard/classes/privacy/provider.php:76-87), so naming the subject would make this
 * record the subject's own personal data, to be erased by the next request. userid is whoever ran the task.
 *
 * Payload contract, `other` (counts and component names only, no ids, no personal data):
 *   path                           string  'request' | 'expiry' | 'cli'
 *   plan                           string  'cached' (from this process's discovery) | 'recomputed' | 'none'
 *   flags                          array   {truncation: bool, completion: bool} as applied
 *   components                     array   per component {contexts, dropped_by_registry, failed}: the
 *                                          supplementary hit sets, never own/system-context work. contexts =
 *                                          handed to the component; failed = 1 when any call for it threw, and
 *                                          then its contexts were attempted, NOT confirmed erased
 *   completion_courses             int     gap course contexts handed to core_course for course_completions
 *   completion_failed              int     1 when that core_course call threw (the courses were attempted, not
 *                                          confirmed erased), else 0
 *   completion_dropped_by_registry int     gap courses the data registry protected
 *   failures                       int     failed calls (also reported to the DPO by exception)
 * The description counts as erased only the contexts of components with failed = 0, and the completion courses
 * only when completion_failed = 0.
 *
 * @package    local_sentientia_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class erasure_supplemented extends \core\event\base {

    /**
     * Build the event from erasure_completeness::apply_delete()'s result.
     *
     * @param array $result The result (see erasure_completeness::apply_delete()).
     * @return self
     */
    public static function create_from_result(array $result): self {
        $components = [];
        foreach ($result['components'] ?? [] as $component => $counts) {
            $components[(string) $component] = [
                'contexts' => (int) ($counts['contexts'] ?? 0),
                'dropped_by_registry' => (int) ($counts['dropped_by_registry'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
            ];
        }
        $requestid = !empty($result['requestid']) ? (int) $result['requestid'] : null;
        $event = self::create([
            'context' => \context_system::instance(),
            'objectid' => $requestid,
            'other' => [
                'path' => (string) ($result['path'] ?? ''),
                'plan' => (string) ($result['plan'] ?? 'none'),
                'flags' => [
                    'truncation' => !empty($result['truncation']),
                    'completion' => !empty($result['completion']),
                ],
                'components' => $components,
                'completion_courses' => (int) ($result['completion_courses'] ?? 0),
                'completion_failed' => empty($result['completion_failed']) ? 0 : 1,
                'completion_dropped_by_registry' => (int) ($result['completion_dropped_by_registry'] ?? 0),
                'failures' => count($result['failures'] ?? []),
            ],
        ]);
        if ($requestid === null) {
            // The expiry path has no request: an objecttable without an objectid is a developer warning.
            $event->data['objecttable'] = null;
        }
        return $event;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'tool_dataprivacy_request';
    }

    /**
     * The event's name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventerasuresupplemented', 'local_sentientia_privacy');
    }

    /**
     * Plain-English description: the request id and counts only. Only work whose calls all succeeded is
     * described as deleted; a failed component's contexts (and failed completion courses) are reported as
     * attempted.
     *
     * @return string
     */
    public function get_description(): string {
        $contexts = 0;
        $components = 0;
        $failedcontexts = 0;
        $failedcomponents = 0;
        foreach ($this->other['components'] ?? [] as $counts) {
            $n = (int) ($counts['contexts'] ?? 0);
            if (!empty($counts['failed'])) {
                $failedcontexts += $n;
                $failedcomponents++;
            } else if ($n > 0) {
                $contexts += $n;
                $components++;
            }
        }
        $courses = (int) ($this->other['completion_courses'] ?? 0);
        $coursesfailed = !empty($this->other['completion_failed']);
        $target = $this->objectid ? "the data request with id '{$this->objectid}'"
            : "a data subject on the '" . ($this->other['path'] ?? '') . "' path";
        return "The supplementary erasure for {$target} deleted data in {$contexts} context(s) of {$components} "
            . 'component(s) that the stock approval had dropped, and ' . ($coursesfailed ? 0 : $courses)
            . ' course completion record set(s) that core never discovers. Attempted but failed (not confirmed '
            . "erased): {$failedcontexts} context(s) of {$failedcomponents} component(s) and "
            . ($coursesfailed ? $courses : 0) . ' course completion record set(s); '
            . (int) ($this->other['failures'] ?? 0) . ' call(s) failed.';
    }

    /**
     * The data requests page.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/admin/tool/dataprivacy/datarequests.php');
    }

    /**
     * Data requests are not part of a course backup.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'tool_dataprivacy_request', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Nothing in other is an id.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }

    /**
     * Refuse an event without a path.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->other['path'])) {
            throw new \coding_exception("erasure_supplemented: the 'path' value must be set in other.");
        }
    }
}
