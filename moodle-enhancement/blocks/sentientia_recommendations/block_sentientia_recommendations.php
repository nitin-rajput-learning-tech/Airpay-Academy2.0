<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS — AI Course Recommendations block.
 *
 * Renders the top N personalised course recommendations for the current
 * learner. The data comes from local_sentientia_recommendations; this
 * block is a thin, read-only presentation layer.
 *
 * The block renders NOTHING (returns empty content) when:
 *   - the master feature flag is OFF, OR
 *   - the viewer lacks the :view capability, OR
 *   - the learner has no active recommendation batch.
 *
 * @package    block_sentientia_recommendations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_sentientia_recommendations extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_sentientia_recommendations');
    }

    public function applicable_formats(): array {
        return [
            'all'         => true,
            'my'          => true,
            'site-index'  => true,
            'course-view' => true,
        ];
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function has_config(): bool {
        return false;
    }

    public function get_content(): \stdClass {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new \stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Not for logged-out / guest users.
        if (empty($USER->id) || isguestuser()) {
            return $this->content;
        }

        // Master flag gate — render nothing when OFF.
        if (class_exists('\\local_airpay_core\\feature_flags')
                && !\local_airpay_core\feature_flags::is_enabled('sentientia.recommendations.enabled')) {
            return $this->content;
        }

        // Capability gate.
        $context = \context_system::instance();
        if (!has_capability('local/sentientia_recommendations:view', $context)) {
            return $this->content;
        }

        // How many to show — block instance config or default 3.
        $limit = 3;
        if (isset($this->config->limit) && (int)$this->config->limit > 0) {
            $limit = min((int)$this->config->limit, 5);
        }

        $recs = \local_sentientia_recommendations\recommendation_engine::latest_for_user(
            (int)$USER->id, $limit);

        if (empty($recs)) {
            // No active batch — keep the block quiet on the dashboard.
            return $this->content;
        }

        global $DB;

        // Defensive tenant gate (2026-05-29 broader-sweep follow-up to
        // db5242c9a). recommendation_engine::build_candidate_list was
        // unscoped before this sweep, so {local_sentientia_rec_log} can
        // hold stale cross-tenant courseids persisted prior to the fix
        // landing. Resolve the viewer's tenant_catid via accesslib and
        // skip any rec whose course sits outside that subtree, so the
        // user-facing block can't surface a pre-hotfix leak even when
        // the underlying log table hasn't been cleaned up.
        $viewer_tenant_catid = \local_airpay_org\accesslib::get_tenant_category_id(
            (string) ($USER->open_path ?? ''));
        $viewer_tenant_catpath = '';
        if ($viewer_tenant_catid) {
            $viewer_tenant_catpath = (string) $DB->get_field('course_categories',
                'path', ['id' => $viewer_tenant_catid]);
        }
        $can_check_tenant = ($viewer_tenant_catid && $viewer_tenant_catpath !== '');

        $items = [];
        $rank = 1;
        foreach ($recs as $r) {
            $course = $DB->get_record('course', ['id' => $r->courseid],
                'id, fullname, shortname, category', IGNORE_MISSING);
            if (!$course) {
                continue;
            }

            if ($can_check_tenant && !is_siteadmin()) {
                $course_cat_path = (string) $DB->get_field('course_categories',
                    'path', ['id' => $course->category]);
                $in_tenant = ($course->category === (int) $viewer_tenant_catid)
                    || ($course_cat_path !== ''
                        && strpos($course_cat_path, $viewer_tenant_catpath . '/') === 0);
                if (!$in_tenant) {
                    // Stale cross-tenant rec row — skip silently. The
                    // expected outcome after the recommendation_engine
                    // hotfix is zero such rows; this guard exists for
                    // log entries persisted BEFORE the fix.
                    continue;
                }
            }

            $items[] = [
                'rank'       => $rank++,
                'recid'      => (int)$r->id,
                'courseid'   => (int)$r->courseid,
                'coursename' => format_string($course->fullname),
                'courseurl'  => (new \moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false),
                'score'      => (int)$r->score,
                'reasoning'  => $r->reasoning !== null ? format_text($r->reasoning, FORMAT_PLAIN) : '',
                'has_reason' => !empty($r->reasoning),
            ];
        }

        if (empty($items)) {
            return $this->content;
        }

        $data = [
            'items'       => $items,
            'score_label' => get_string('block_score', 'local_sentientia_recommendations'),
            'why_label'   => get_string('block_why', 'local_sentientia_recommendations'),
        ];

        $this->content->text = $OUTPUT->render_from_template(
            'block_sentientia_recommendations/recommendations', $data);

        return $this->content;
    }
}
