<?php
/**
 * AJAX web service API for template operations.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class template_api extends external_api {

    // ── get_template ──

    public static function get_template_parameters() {
        return new external_function_parameters([
            'templatekey' => new external_value(PARAM_RAW, 'Template key e.g. compliance/deadline_warning'),
            'tenantid'    => new external_value(PARAM_INT, 'Tenant costcenter ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_template(string $templatekey, int $tenantid = 0): array {
        global $PAGE, $CFG;

        $params = self::validate_parameters(self::get_template_parameters(), [
            'templatekey' => $templatekey,
            'tenantid'    => $tenantid,
        ]);

        $context = \context_system::instance();
        if (!is_siteadmin()) {
            self::validate_context($context);
        }

        $override = \local_sentientia_emails\template_manager::get_override($params['templatekey'], $params['tenantid']);

        if ($override) {
            $data = [
                'subject'   => $override->subject ?? '',
                'body_html' => $override->body_html ?? '',
                'source'    => $override->source ?? 'db_override',
            ];
        } else {
            // Read from Mustache file.
            $samplecontext = \local_sentientia_emails\email_context::get_sample($params['templatekey']);
            $data = [
                'subject'   => $samplecontext['subject'] ?? '',
                'body_html' => '', // Empty = file-based (must read the .mustache file).
                'source'    => 'file',
            ];

            // Try to read the actual Mustache file content.
            $filepath = $CFG->dirroot . '/local/sentientia_emails/templates/' . $params['templatekey'] . '.mustache';
            if (file_exists($filepath)) {
                $data['body_html'] = file_get_contents($filepath);
            }
        }

        return ['data' => json_encode($data)];
    }

    public static function get_template_returns() {
        return new external_single_structure([
            'data' => new external_value(PARAM_RAW, 'JSON encoded template data'),
        ]);
    }

    // ── save_template ──

    public static function save_template_parameters() {
        return new external_function_parameters([
            'templatekey' => new external_value(PARAM_RAW, 'Template key'),
            'tenantid'    => new external_value(PARAM_INT, 'Tenant ID'),
            'subject'     => new external_value(PARAM_RAW, 'Subject line'),
            'bodyhtml'    => new external_value(PARAM_RAW, 'HTML body'),
        ]);
    }

    public static function save_template(string $templatekey, int $tenantid, string $subject, string $bodyhtml): array {
        $params = self::validate_parameters(self::save_template_parameters(), [
            'templatekey' => $templatekey,
            'tenantid'    => $tenantid,
            'subject'     => $subject,
            'bodyhtml'    => $bodyhtml,
        ]);

        $context = \context_system::instance();
        if (!is_siteadmin()) {
            self::validate_context($context);
            require_capability('local/sentientia_emails:manage_templates', $context);
        }

        $id = \local_sentientia_emails\template_manager::save_override(
            $params['templatekey'], $params['tenantid'], $params['subject'], $params['bodyhtml']
        );

        return ['data' => json_encode(['id' => $id, 'success' => true])];
    }

    public static function save_template_returns() {
        return new external_single_structure([
            'data' => new external_value(PARAM_RAW, 'JSON result'),
        ]);
    }

    // ── revert_template ──

    public static function revert_template_parameters() {
        return new external_function_parameters([
            'templatekey' => new external_value(PARAM_RAW, 'Template key'),
            'tenantid'    => new external_value(PARAM_INT, 'Tenant ID'),
        ]);
    }

    public static function revert_template(string $templatekey, int $tenantid): array {
        global $DB;

        $params = self::validate_parameters(self::revert_template_parameters(), [
            'templatekey' => $templatekey,
            'tenantid'    => $tenantid,
        ]);

        $context = \context_system::instance();
        if (!is_siteadmin()) {
            self::validate_context($context);
            require_capability('local/sentientia_emails:manage_templates', $context);
        }

        $DB->delete_records('local_sentientia_email_overrides', [
            'template_key' => $params['templatekey'],
            'tenant_id'    => $params['tenantid'],
        ]);

        return ['data' => json_encode(['success' => true])];
    }

    public static function revert_template_returns() {
        return new external_single_structure([
            'data' => new external_value(PARAM_RAW, 'JSON result'),
        ]);
    }

    // ── preview_template ──

    public static function preview_template_parameters() {
        return new external_function_parameters([
            'templatekey' => new external_value(PARAM_RAW, 'Template key'),
            'tenantid'    => new external_value(PARAM_INT, 'Tenant ID', VALUE_DEFAULT, 1),
            'bodyhtml'    => new external_value(PARAM_RAW, 'Custom body HTML to preview', VALUE_DEFAULT, ''),
            'subject'     => new external_value(PARAM_RAW, 'Custom subject', VALUE_DEFAULT, ''),
        ]);
    }

    public static function preview_template(string $templatekey, int $tenantid = 1,
                                             string $bodyhtml = '', string $subject = ''): array {
        global $PAGE, $CFG, $SITE;

        $params = self::validate_parameters(self::preview_template_parameters(), [
            'templatekey' => $templatekey,
            'tenantid'    => $tenantid,
            'bodyhtml'    => $bodyhtml,
            'subject'     => $subject,
        ]);

        $context = \context_system::instance();
        if (!is_siteadmin()) {
            self::validate_context($context);
        }

        $samplecontext = \local_sentientia_emails\email_context::get_sample($params['templatekey']);
        $samplecontext['subject'] = $params['subject'] ?: ($samplecontext['subject'] ?? 'Preview');

        // If custom body provided, render it directly. Otherwise use file template.
        if (!empty($params['bodyhtml'])) {
            try {
                $mustache = new \Mustache_Engine();
                $body = $mustache->render($params['bodyhtml'], $samplecontext);
            } catch (\Exception $e) {
                $body = '<p style="color:#dc2626;">Mustache error: ' . s($e->getMessage()) . '</p>';
            }
        } else {
            $fullname = 'local_sentientia_emails/' . $params['templatekey'];
            $body = \local_sentientia_emails\email_renderer::render($fullname, $samplecontext);
        }

        // Wrap in theme email_html.mustache.
        $renderer = $PAGE->get_renderer('core');
        $wrappercontext = [
            'body'          => $body,
            'subject'       => $samplecontext['subject'],
            'sitefullname'  => format_string($SITE->fullname),
            'siteshortname' => format_string($SITE->shortname),
            'sitewwwroot'   => $CFG->wwwroot,
            'toname'        => $samplecontext['firstname'] ?? 'Preview User',
            'fromname'      => 'Airpay Academy',
        ];
        $html = $renderer->render_from_template('core/email_html', $wrappercontext);

        return ['html' => $html];
    }

    public static function preview_template_returns() {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered HTML preview'),
        ]);
    }
}
