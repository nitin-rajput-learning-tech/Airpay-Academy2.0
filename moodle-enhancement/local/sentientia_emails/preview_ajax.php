<?php
/**
 * AJAX preview endpoint — renders email template with sample data.
 * Called by the editor's live preview on every keystroke (debounced).
 *
 * @package    local_sentientia_emails
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

if (!is_siteadmin() && !has_capability('local/sentientia_emails:manage_templates', context_system::instance())) {
    http_response_code(403);
    die('No permission');
}

$bodyhtml    = required_param('bodyhtml', PARAM_RAW);
$subject     = optional_param('subject', '', PARAM_RAW);
$templatekey = optional_param('template', '', PARAM_RAW);
$tenantid    = optional_param('tenant', 1, PARAM_INT);

// Get sample context for the template.
$samplecontext = \local_sentientia_emails\email_context::get_sample($templatekey);
$samplecontext['subject'] = $subject ?: ($samplecontext['subject'] ?? 'Preview');

// Render the custom body HTML through Mustache engine.
try {
    $mustache = new \Mustache_Engine();
    $body = $mustache->render($bodyhtml, $samplecontext);
} catch (\Exception $e) {
    $body = '<p style="color:#dc2626; font-family:monospace; padding:16px;">'
          . 'Mustache syntax error: ' . s($e->getMessage()) . '</p>';
}

// Wrap in the theme's branded email wrapper.
$tenant = \local_sentientia_emails\tenant_config::get($tenantid);
$renderer = $PAGE->get_renderer('core');
$wrappercontext = [
    'body'          => $body,
    'subject'       => $samplecontext['subject'],
    'sitefullname'  => format_string($SITE->fullname),
    'siteshortname' => format_string($SITE->shortname),
    'sitewwwroot'   => $CFG->wwwroot,
    'toname'        => $samplecontext['firstname'] ?? 'Preview User',
    'fromname'      => $tenant['name'],
];

echo $renderer->render_from_template('core/email_html', $wrappercontext);
