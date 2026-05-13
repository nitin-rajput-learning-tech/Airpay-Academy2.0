<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Certificate-email health widget for the site administration dashboard.
 *
 * Sprint B (2026-05-13) — pairs with the Sprint B course-completed
 * observer + email_to_user attachment pipeline. Surfaces:
 *
 *   - count of certificate emails SENT in the last 7 days
 *   - count of FAILED sends in the last 7 days
 *   - count of SUPPRESSED sends in the last 7 days
 *     (user-opt-out or local-dev $CFG->noemailever)
 *   - drill-down link to /local/airpay_emails/cli/cert_emails_report.php
 *     via the manage.php logs tab, with the same since/tenant filter.
 *
 * Same design and a11y pattern as block_airpay_cron_health:
 *   - role="region" landmark wrapper
 *   - severity badge (OK/Warning/Critical) for sighted colour-blind users
 *   - aria-label on each KPI for screen readers
 *   - small-text contrast palette (#15803d / #b45309 / #b91c1c, WCAG AA)
 *
 * Site-admin-only — silently hides itself otherwise (returns null
 * rather than an empty placeholder).
 *
 * @package block_airpay_cert_health
 */
class block_airpay_cert_health extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_airpay_cert_health');
    }

    public function applicable_formats(): array {
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
     * Render the widget. Returns null for non-site-admins so the
     * block silently hides itself.
     */
    public function get_content() {
        global $DB;

        if ($this->content !== null) {
            return $this->content;
        }
        if (!is_siteadmin()) {
            return null;
        }

        // The block depends on local_airpay_emails — bail gracefully
        // if the table isn't present (e.g. plugin disabled during
        // operational triage).
        if (!$DB->get_manager()->table_exists('local_airpay_email_log')) {
            return null;
        }

        $since = time() - 7 * 86400;

        // Count rows that carried a certificate attachment (Sprint B
        // schema additions: attachment_filename / certificate_issue_id
        // are non-null on the cert-bearing emails only).
        $cert_filter = "(attachment_filename IS NOT NULL "
            . "OR certificate_issue_id IS NOT NULL)";
        $sent = (int) $DB->count_records_select('local_airpay_email_log',
            "$cert_filter AND status = :status AND timecreated >= :since",
            ['status' => 'sent', 'since' => $since]);
        $failed = (int) $DB->count_records_select('local_airpay_email_log',
            "$cert_filter AND status = :status AND timecreated >= :since",
            ['status' => 'failed', 'since' => $since]);
        $suppressed = (int) $DB->count_records_select('local_airpay_email_log',
            "$cert_filter AND status IN ('suppressed', 'suppressed_completion') "
            . "AND timecreated >= :since",
            ['since' => $since]);

        // Build the HTML. Same a11y/design pattern as block_airpay_cron_health:
        //   <section role=region aria-label> wraps the whole widget
        //   each KPI card is its own role=group with aria-label that
        //   includes the number + label + severity word
        $html = \html_writer::start_tag('section', [
            'class'      => 'airpay-cert-health',
            'role'       => 'region',
            'aria-label' => get_string('region_label', 'block_airpay_cert_health'),
        ]);

        $html .= \html_writer::start_tag('div', [
            'class' => 'airpay-cert-health__kpis',
            'style' => 'display:flex;gap:8px;margin-bottom:8px;',
        ]);

        // Sent — green at any value (more = better). Becomes amber
        // only when the value is exactly 0 AND failures > 0 (i.e. the
        // pipeline is broken).
        $sent_severity = ($sent === 0 && $failed > 0) ? 'warning' : 'ok';
        $html .= $this->kpi_card(
            get_string('kpi_sent', 'block_airpay_cert_health'),
            $sent, $sent_severity);

        // Failed — green if zero, critical if anything > 0. Cert
        // failure is an operational alarm (compliance reports rely
        // on the cert email shipping).
        $failed_severity = $failed > 0 ? 'critical' : 'ok';
        $html .= $this->kpi_card(
            get_string('kpi_failed', 'block_airpay_cert_health'),
            $failed, $failed_severity);

        // Suppressed — green at zero, amber at non-zero. Suppressed
        // means user-opt-out or noemailever — not a system problem
        // but worth surfacing.
        $supp_severity = $suppressed > 0 ? 'warning' : 'ok';
        $html .= $this->kpi_card(
            get_string('kpi_suppressed', 'block_airpay_cert_health'),
            $suppressed, $supp_severity);
        $html .= \html_writer::end_tag('div');

        // Footer link → drills into the full logs tab.
        $html .= \html_writer::tag('div',
            \html_writer::link(
                new \moodle_url('/local/airpay_emails/manage.php',
                    ['tab' => 'logs']),
                get_string('view_full_log', 'block_airpay_cert_health')),
            ['class' => 'airpay-cert-health__footer-link',
             'style' => 'margin-top:10px;font-size:0.85em;']);

        $html .= \html_writer::end_tag('section');

        $this->content = (object) [
            'text'   => $html,
            'footer' => '',
        ];
        return $this->content;
    }

    /**
     * Build one KPI card. Same shape and a11y pattern as
     * block_airpay_cron_health::kpi_card() — see that file's docblock
     * for the rationale (WCAG 1.4.1 use-of-colour: every severity
     * has a visible text badge + an aria-label that bundles number +
     * label + severity word).
     *
     * @param string $label    Localised KPI label
     * @param int    $value    Count
     * @param string $severity One of ok|warning|critical
     */
    private function kpi_card(string $label, int $value, string $severity): string {
        // Large-text palette (>= 18.5px, used on the 1.6em number).
        $palette_lg = [
            'ok'       => '#16a34a',
            'warning'  => '#d97706',
            'critical' => '#dc2626',
        ];
        // Small-text palette (< 18.5px, used on the 0.7em severity badge).
        // Hex values chosen for WCAG AA 4.5:1 against #f8f9fc.
        $palette_sm = [
            'ok'       => '#15803d',
            'warning'  => '#b45309',
            'critical' => '#b91c1c',
        ];
        $fg    = $palette_lg[$severity] ?? $palette_lg['ok'];
        $fg_sm = $palette_sm[$severity] ?? $palette_sm['ok'];

        $severity_text = get_string("severity_$severity",
            'block_airpay_cert_health');

        // Combined aria-label so the card reads as a single unit.
        $a_label = get_string('kpi_aria_label', 'block_airpay_cert_health',
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
                ['class' => "airpay-cert-health__severity airpay-cert-health__severity--$severity",
                 'style' => "font-size:0.7em;font-weight:600;text-transform:uppercase;"
                          . "letter-spacing:0.5px;color:$fg_sm;margin-top:2px;",
                 'aria-hidden' => 'true']),
            ['class' => "airpay-cert-health__kpi airpay-cert-health__kpi--$severity",
             'role'  => 'group',
             'aria-label' => $a_label,
             'style' =>
                'flex:1;padding:8px;border:1px solid #e2e6ef;'
                . 'border-radius:8px;background:#f8f9fc;text-align:center;'
            ]);
    }
}
