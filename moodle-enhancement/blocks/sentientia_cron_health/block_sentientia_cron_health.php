<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Cron-health widget for the site administration dashboard.
 *
 * Surfaces the output of `\local_sentientia_platform\cron_health::summary()` as
 * an at-a-glance widget. Three KPI cards (Airpay stuck / other stuck /
 * in backoff) with a green / amber / red colour by severity AND a
 * matching text severity badge for WCAG 1.4.1 (no information conveyed
 * by colour alone). Drills down to a list of stuck tasks with overdue
 * duration formatted for humans.
 *
 * The widget reads in real time from the task_scheduled table — no
 * cache layer between the dashboard and the truth. Acceptable because
 * the underlying query is fast (indexed scan on a few hundred rows at
 * most) and the read is per-page-load not per-second.
 *
 * Site administrators only.
 *
 * A11Y notes
 * ----------
 * - Whole block wrapped in a `<section role="region" aria-label>` so
 *   screen reader users can jump to it via landmarks.
 * - Sub-section labels use `<h3>` (one level below the block title
 *   that Moodle's block chrome renders as `<h2>`) so heading order
 *   increases by one — fixes the heading-order axe-core finding from
 *   Engineering 20.
 * - Each KPI card has an `aria-label` that includes both the number
 *   AND the severity word, so screen readers don't rely on the
 *   green/amber/red colour cue.
 * - Visible severity text badge ("OK" / "Warning" / "Critical") next
 *   to the number gives sighted colour-blind users the same signal.
 *
 * @package block_sentientia_cron_health
 */
class block_sentientia_cron_health extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_sentientia_cron_health');
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

        $summary = \local_sentientia_platform\cron_health::summary();
        $stuck   = \local_sentientia_platform\cron_health::get_stuck_airpay_tasks();
        $backoff = \local_sentientia_platform\cron_health::get_tasks_in_failure_backoff();

        // Whole-block landmark — gives screen reader users a single
        // jump-target for the cron-health summary. The aria-label is
        // i18n-friendly (lang/en/block_sentientia_cron_health.php).
        $html = \html_writer::start_tag('section', [
            'class'      => 'airpay-cron-health',
            'role'       => 'region',
            'aria-label' => get_string('region_label', 'block_sentientia_cron_health'),
        ]);

        $html .= \html_writer::start_tag('div', [
            'class' => 'airpay-cron-health__kpis',
            'style' => 'display:flex;gap:8px;margin-bottom:8px;',
        ]);

        $html .= $this->kpi_card(
            get_string('kpi_stuck_airpay', 'block_sentientia_cron_health'),
            $summary['stuck_airpay'],
            $summary['stuck_airpay'] > 0 ? 'critical' : 'ok'
        );
        $html .= $this->kpi_card(
            get_string('kpi_stuck_other', 'block_sentientia_cron_health'),
            $summary['stuck_other'],
            $summary['stuck_other'] > 0 ? 'warning' : 'ok'
        );
        $html .= $this->kpi_card(
            get_string('kpi_in_backoff', 'block_sentientia_cron_health'),
            $summary['in_backoff'],
            $summary['in_backoff'] > 0 ? 'warning' : 'ok'
        );
        $html .= \html_writer::end_tag('div');

        if (!empty($stuck)) {
            // h3 keeps the heading order incrementing by one — Moodle's
            // block chrome renders the block title as h2, so the next
            // logical level is h3 (NOT h5, which skips h3/h4 and trips
            // axe-core heading-order rule).
            $html .= \html_writer::tag('h3',
                get_string('stuck_airpay_heading', 'block_sentientia_cron_health'),
                ['class' => 'airpay-cron-health__subheading',
                 'style' => 'margin:12px 0 6px;font-size:0.95em;']);
            $html .= \html_writer::start_tag('ul',
                ['class' => 'airpay-cron-health__list',
                 'style' => 'margin:0;padding-left:18px;font-size:0.85em;']);
            foreach ($stuck as $t) {
                $overdue = \local_sentientia_platform\cron_health::format_overdue(
                    (int) $t->overdue_seconds);
                // Build aria-label so screen readers announce
                // "<task name>, overdue by 5h 23m" as a single unit,
                // not two disconnected fragments.
                $a_label = s($t->classname) . ', '
                    . get_string('overdue_label', 'block_sentientia_cron_health', $overdue);
                $html .= \html_writer::tag('li',
                    \html_writer::tag('code', s($t->classname)) .
                    ' <span style="color:#c00;">overdue ' . s($overdue) . '</span>',
                    ['aria-label' => $a_label]);
            }
            $html .= \html_writer::end_tag('ul');
        }

        if (!empty($backoff)) {
            $html .= \html_writer::tag('h3',
                get_string('in_backoff_heading', 'block_sentientia_cron_health'),
                ['class' => 'airpay-cron-health__subheading',
                 'style' => 'margin:12px 0 6px;font-size:0.95em;']);
            $html .= \html_writer::start_tag('ul',
                ['class' => 'airpay-cron-health__list',
                 'style' => 'margin:0;padding-left:18px;font-size:0.85em;']);
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
                get_string('view_task_logs', 'block_sentientia_cron_health')),
            ['class' => 'airpay-cron-health__footer-link',
             'style' => 'margin-top:10px;font-size:0.85em;']);

        $html .= \html_writer::end_tag('section');

        $this->content = (object) [
            'text'   => $html,
            'footer' => '',
        ];
        return $this->content;
    }

    /**
     * Build one KPI card.
     *
     * The card combines THREE accessibility signals to satisfy WCAG
     * 1.4.1 (use of colour) and 1.3.1 (info & relationships):
     *
     *  1. Visible colour-coded number (sighted users)
     *  2. Visible text severity badge (colour-blind users)
     *  3. aria-label combining number + label + severity word
     *     (screen reader users)
     *
     * @param string $label    Localised KPI label, e.g. "Airpay tasks stuck"
     * @param int    $value    Count being reported
     * @param string $severity One of ok|warning|critical
     */
    private function kpi_card(string $label, int $value, string $severity): string {
        // Two palettes:
        // - $palette_lg is used for the LARGE KPI value (font-size 1.6em).
        //   At that size WCAG 2.1 AA requires 3:1 contrast — the
        //   regular brand greens/ambers pass.
        // - $palette_sm is used for the small severity badge text
        //   (font-size 0.7em). Small text requires 4.5:1, so we need
        //   darker variants of each hue (green, amber, red).
        // Background reference: #f8f9fc (the KPI card surface).
        $palette_lg = [
            'ok'       => '#16a34a',  //  3.4:1 — passes large-text 3:1
            'warning'  => '#d97706',  //  3.2:1 — passes large-text 3:1
            'critical' => '#dc2626',  //  4.6:1 — passes both
        ];
        $palette_sm = [
            'ok'       => '#15803d',  //  4.8:1 — passes small-text 4.5:1
            'warning'  => '#b45309',  //  5.5:1 — passes small-text 4.5:1
            'critical' => '#b91c1c',  //  5.8:1 — passes small-text 4.5:1
        ];
        $fg = $palette_lg[$severity] ?? $palette_lg['ok'];
        $fg_sm = $palette_sm[$severity] ?? $palette_sm['ok'];

        $severity_text = get_string("severity_$severity", 'block_sentientia_cron_health');

        // Combined aria-label so the card reads as one unit.
        $a_label = get_string('kpi_aria_label', 'block_sentientia_cron_health',
            (object) [
                'value'    => $value,
                'label'    => $label,
                'severity' => $severity_text,
            ]);

        return \html_writer::tag('div',
            \html_writer::tag('div', (string) $value,
                ['style' => "font-size:1.6em;font-weight:700;color:$fg;",
                 'aria-hidden' => 'true'])
            . \html_writer::tag('div', s($label),
                ['style' => "font-size:0.75em;color:#5a6070;",
                 'aria-hidden' => 'true'])
            . \html_writer::tag('div', s($severity_text),
                ['class' => "airpay-cron-health__severity airpay-cron-health__severity--$severity",
                 'style' => "font-size:0.7em;font-weight:600;text-transform:uppercase;"
                          . "letter-spacing:0.5px;color:$fg_sm;margin-top:2px;",
                 'aria-hidden' => 'true']),
            ['class' => "airpay-cron-health__kpi airpay-cron-health__kpi--$severity",
             'role'  => 'group',
             'aria-label' => $a_label,
             'style' =>
                'flex:1;padding:8px;border:1px solid #e2e6ef;'
                . 'border-radius:8px;background:#f8f9fc;text-align:center;'
            ]);
    }
}
