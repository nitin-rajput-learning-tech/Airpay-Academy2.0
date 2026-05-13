<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Cron-health widget for the site administration dashboard.
 *
 * Surfaces the output of `\local_airpay_core\cron_health::summary()` as
 * an at-a-glance widget. Three KPI cards (Airpay stuck / other stuck /
 * in backoff) with a green / amber / red colour by severity. Drills down
 * to a list of stuck tasks with overdue duration formatted for humans.
 *
 * The widget reads in real time from the task_scheduled table — no
 * cache layer between the dashboard and the truth. Acceptable because
 * the underlying query is fast (indexed scan on a few hundred rows at
 * most) and the read is per-page-load not per-second.
 *
 * Site administrators only.
 *
 * @package block_airpay_cron_health
 */
class block_airpay_cron_health extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_airpay_cron_health');
    }

    public function applicable_formats(): array {
        // Only render on /my/ and admin pages — never inside a course.
        return [
            'my'   => true,
            'site' => true,
            'admin' => true,
            'course-view' => false,
            'mod' => false,
        ];
    }

    public function has_config(): bool {
        return false;
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Renders the widget. Returns null when the viewer is not a site
     * administrator (the block silently hides itself rather than showing
     * an empty placeholder).
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        if (!is_siteadmin()) {
            return null;
        }

        $summary = \local_airpay_core\cron_health::summary();
        $stuck   = \local_airpay_core\cron_health::get_stuck_airpay_tasks();
        $backoff = \local_airpay_core\cron_health::get_tasks_in_failure_backoff();

        // Build the rendered HTML using html_writer rather than a
        // template — the block is small enough that the template
        // overhead would dominate the actual rendering work.
        $html = \html_writer::start_tag('div', [
            'class' => 'airpay-cron-health',
            'style' => 'display:flex;gap:8px;margin-bottom:8px;',
        ]);

        $html .= $this->kpi_card(
            get_string('kpi_stuck_airpay', 'block_airpay_cron_health'),
            $summary['stuck_airpay'],
            $summary['stuck_airpay'] > 0 ? 'red' : 'green'
        );
        $html .= $this->kpi_card(
            get_string('kpi_stuck_other', 'block_airpay_cron_health'),
            $summary['stuck_other'],
            $summary['stuck_other'] > 0 ? 'amber' : 'green'
        );
        $html .= $this->kpi_card(
            get_string('kpi_in_backoff', 'block_airpay_cron_health'),
            $summary['in_backoff'],
            $summary['in_backoff'] > 0 ? 'amber' : 'green'
        );
        $html .= \html_writer::end_tag('div');

        if (!empty($stuck)) {
            $html .= \html_writer::tag('h5',
                get_string('stuck_airpay_heading', 'block_airpay_cron_health'),
                ['style' => 'margin:12px 0 6px;font-size:0.95em;']);
            $html .= \html_writer::start_tag('ul',
                ['style' => 'margin:0;padding-left:18px;font-size:0.85em;']);
            foreach ($stuck as $t) {
                $overdue = \local_airpay_core\cron_health::format_overdue(
                    (int) $t->overdue_seconds);
                $html .= \html_writer::tag('li',
                    \html_writer::tag('code', s($t->classname)) .
                    ' <span style="color:#c00;">overdue ' . s($overdue) . '</span>');
            }
            $html .= \html_writer::end_tag('ul');
        }

        if (!empty($backoff)) {
            $html .= \html_writer::tag('h5',
                get_string('in_backoff_heading', 'block_airpay_cron_health'),
                ['style' => 'margin:12px 0 6px;font-size:0.95em;']);
            $html .= \html_writer::start_tag('ul',
                ['style' => 'margin:0;padding-left:18px;font-size:0.85em;']);
            foreach ($backoff as $t) {
                $html .= \html_writer::tag('li',
                    \html_writer::tag('code', s($t->classname)) .
                    ' faildelay=' . (int) $t->faildelay . 's');
            }
            $html .= \html_writer::end_tag('ul');
        }

        $html .= \html_writer::tag('div',
            \html_writer::link(
                new \moodle_url('/admin/tasklogs.php'),
                get_string('view_task_logs', 'block_airpay_cron_health')),
            ['style' => 'margin-top:10px;font-size:0.85em;']);

        $this->content = (object) [
            'text'   => $html,
            'footer' => '',
        ];
        return $this->content;
    }

    /**
     * Build one KPI card.
     */
    private function kpi_card(string $label, int $value, string $colour): string {
        $palette = [
            'green' => '#16a34a',
            'amber' => '#d97706',
            'red'   => '#dc2626',
        ];
        $fg = $palette[$colour] ?? $palette['green'];
        return \html_writer::tag('div',
            \html_writer::tag('div', (string) $value,
                ['style' => "font-size:1.6em;font-weight:700;color:$fg;"])
            . \html_writer::tag('div', s($label),
                ['style' => "font-size:0.75em;color:#5a6070;"]),
            ['style' =>
                'flex:1;padding:8px;border:1px solid #e2e6ef;'
                . 'border-radius:8px;background:#f8f9fc;text-align:center;'
            ]);
    }
}
