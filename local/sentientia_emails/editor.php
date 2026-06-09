<?php
/**
 * Email Design Studio — Pro visual editor for notification templates.
 *
 * Full-page editor with:
 * - Smart block insertion sidebar
 * - Code/Visual toggle
 * - Live branded preview with device switching
 * - Per-tenant override management
 * - Template version tracking
 *
 * URL: /local/sentientia_emails/editor.php?template=compliance/deadline_warning&tenant=1
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
if (!is_siteadmin() && !has_capability('local/sentientia_emails:manage_templates', $context)) {
    throw new \moodle_exception('nopermissions', 'error', '', 'edit templates');
}

$templatekey = optional_param('template', '', PARAM_RAW);
$templatekey = preg_replace('/[^a-zA-Z0-9_\/]/', '', $templatekey);
$tenantid    = optional_param('tenant', 0, PARAM_INT);

if (empty($templatekey)) {
    redirect(new moodle_url('/local/sentientia_emails/manage.php', ['tab' => 'templates']));
}

$PAGE->set_url(new moodle_url('/local/sentientia_emails/editor.php',
    ['template' => $templatekey, 'tenant' => $tenantid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded'); // Full-width, no blocks.
$PAGE->set_title('Email Design Studio');

// Handle save action (POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $subject = required_param('subject', PARAM_RAW);
    $bodyhtml = required_param('bodyhtml', PARAM_RAW);
    \local_sentientia_emails\template_manager::save_override($templatekey, $tenantid, $subject, $bodyhtml);
    redirect(new moodle_url('/local/sentientia_emails/editor.php',
        ['template' => $templatekey, 'tenant' => $tenantid, 'saved' => 1]));
}

// Handle revert action.
if (optional_param('revert', 0, PARAM_INT) && confirm_sesskey()) {
    global $DB;
    $DB->delete_records('local_sentientia_email_overrides', [
        'template_key' => $templatekey, 'tenant_id' => $tenantid]);
    redirect(new moodle_url('/local/sentientia_emails/editor.php',
        ['template' => $templatekey, 'tenant' => $tenantid, 'reverted' => 1]));
}

// Load current template content.
$override = \local_sentientia_emails\template_manager::get_override($templatekey, $tenantid);
$samplecontext = \local_sentientia_emails\email_context::get_sample($templatekey);

// If override exists, use it. Otherwise read the Mustache file.
$currentsubject = $samplecontext['subject'] ?? '';
$currentbody = '';
$source = 'file';

if ($override && !empty($override->body_html)) {
    $currentsubject = $override->subject ?? $currentsubject;
    $currentbody = $override->body_html;
    $source = $override->source;
} else {
    $filepath = $CFG->dirroot . '/local/sentientia_emails/templates/' . $templatekey . '.mustache';
    if (file_exists($filepath)) {
        $currentbody = file_get_contents($filepath);
    }
}

// Generate the live preview.
$previewhtml = '';
try {
    $fulltemplatename = 'local_sentientia_emails/' . $templatekey;
    $previewhtml = \local_sentientia_emails\email_renderer::render_preview(
        $fulltemplatename, $samplecontext, $tenantid ?: 1);
} catch (\Exception $e) {
    $previewhtml = '<p style="color:red;">Preview error: ' . s($e->getMessage()) . '</p>';
}

// Find template label.
$label = $templatekey;
$category = '';
foreach (\local_sentientia_emails\email_renderer::get_template_list() as $cat) {
    foreach ($cat['templates'] as $tpl) {
        if ($tpl['key'] === $templatekey) {
            $label = $tpl['label'];
            $category = $cat['category'];
            break 2;
        }
    }
}

// Filter sample context: only pass scalar values to placeholders.
// Array values (overdue_members, courses, data_categories) are for template iteration only.
$scalarcontext = array_filter($samplecontext, fn($v) => !is_array($v) && !is_object($v));

// Get available placeholders for this template type.
$placeholders = array_keys($scalarcontext);
$placeholdergroups = [
    'User'    => array_filter($placeholders, fn($p) => in_array($p, ['firstname','lastname','fullname','email','designation','department','username'])),
    'Course'  => array_filter($placeholders, fn($p) => str_contains($p, 'course') || in_array($p, ['path_name','path_url'])),
    'Dates'   => array_filter($placeholders, fn($p) => str_contains($p, 'date') || str_contains($p, 'days') || str_contains($p, 'time') || str_contains($p, 'expiry')),
    'URLs'    => array_filter($placeholders, fn($p) => str_contains($p, 'url')),
    'Content' => array_filter($placeholders, fn($p) => str_contains($p, 'reason') || str_contains($p, 'description') || str_contains($p, 'score') || str_contains($p, 'progress')),
    'System'  => array_filter($placeholders, fn($p) => in_array($p, ['tenant_name','site_url','site_name','support_email','privacy_url','current_year','subject'])),
];
// Remove empty groups and format for template.
$placeholdersdata = [];
foreach ($placeholdergroups as $group => $items) {
    if (!empty($items)) {
        $itemsdata = [];
        foreach ($items as $item) {
            $sampleval = $scalarcontext[$item] ?? '';
            if (is_array($sampleval) || is_object($sampleval)) { continue; }
            $sampleval = (string)$sampleval;
            if (strlen($sampleval) > 40) { $sampleval = substr($sampleval, 0, 40) . '...'; }
            $itemsdata[] = ['name' => $item, 'sample' => s($sampleval)];
        }
        $placeholdersdata[] = ['group' => $group, 'items' => $itemsdata];
    }
}

// Smart blocks — pre-built email components.
$smartblocks = [
    ['id' => 'cta_button', 'label' => 'CTA Button', 'icon' => 'fa-mouse-pointer',
     'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">' . "\n" .
               '<tr><td align="center" bgcolor="#0066A7" style="background-color:#0066A7; border-radius:8px;">' . "\n" .
               '<a href="{{course_url}}" style="display:inline-block; padding:14px 32px; font-family:\'Montserrat\',Arial,sans-serif; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">Button Text</a>' . "\n" .
               '</td></tr></table>'],
    ['id' => 'info_box', 'label' => 'Info Box', 'icon' => 'fa-info-circle',
     'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;">' . "\n" .
               '<tr><td style="border-left:4px solid #0066A7; background-color:#e8f2f9; padding:16px 20px; border-radius:0 8px 8px 0;">' . "\n" .
               '<span style="font-family:\'Montserrat\',Arial,sans-serif; font-size:14px; color:#0066A7; font-weight:600;">Info Title</span><br/>' . "\n" .
               '<span style="font-family:\'Montserrat\',Arial,sans-serif; font-size:13px; color:#5a6070;">Your message here.</span>' . "\n" .
               '</td></tr></table>'],
    ['id' => 'warning_box', 'label' => 'Warning Box', 'icon' => 'fa-exclamation-triangle',
     'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;">' . "\n" .
               '<tr><td style="border-left:4px solid #d97706; background-color:#fef3c7; padding:16px 20px; border-radius:0 8px 8px 0;">' . "\n" .
               '<span style="font-family:\'Montserrat\',Arial,sans-serif; font-size:14px; color:#d97706; font-weight:600;">Warning Title</span><br/>' . "\n" .
               '<span style="font-family:\'Montserrat\',Arial,sans-serif; font-size:13px; color:#5a6070;">Warning message here.</span>' . "\n" .
               '</td></tr></table>'],
    ['id' => 'danger_box', 'label' => 'Urgent Box', 'icon' => 'fa-exclamation-circle',
     'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;">' . "\n" .
               '<tr><td style="border-left:4px solid #dc2626; background-color:#fef2f2; padding:16px 20px; border-radius:0 8px 8px 0;">' . "\n" .
               '<span style="font-family:\'Montserrat\',Arial,sans-serif; font-size:14px; color:#dc2626; font-weight:600;">Urgent Title</span><br/>' . "\n" .
               '<span style="font-family:\'Montserrat\',Arial,sans-serif; font-size:13px; color:#5a6070;">Urgent message here.</span>' . "\n" .
               '</td></tr></table>'],
    ['id' => 'course_card', 'label' => 'Course Card', 'icon' => 'fa-book',
     'html' => '{{> local_sentientia_emails/partials/course_info_box}}'],
    ['id' => 'divider', 'label' => 'Divider', 'icon' => 'fa-minus',
     'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0;">' . "\n" .
               '<tr><td style="border-top:1px solid #e2e6ef;">&nbsp;</td></tr></table>'],
    ['id' => 'heading', 'label' => 'Heading', 'icon' => 'fa-header',
     'html' => '<p style="margin:16px 0 8px; font-family:\'Montserrat\',Arial,sans-serif; font-size:18px; font-weight:700; color:#1a1a2e;">Section Heading</p>'],
    ['id' => 'paragraph', 'label' => 'Paragraph', 'icon' => 'fa-paragraph',
     'html' => '<p style="margin:0 0 16px; font-family:\'Montserrat\',Arial,sans-serif; font-size:15px; line-height:24px; color:#1a1a2e;">Your text here. Use {{firstname}} for personalization.</p>'],
    ['id' => 'footer_note', 'label' => 'Support Footer', 'icon' => 'fa-life-ring',
     'html' => '{{> local_sentientia_emails/partials/footer_note}}'],
    ['id' => 'two_column', 'label' => 'Two Columns', 'icon' => 'fa-columns',
     'html' => '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;">' . "\n" .
               '<tr><td width="48%" style="vertical-align:top; padding-right:8px;">Left column content</td>' . "\n" .
               '<td width="4%">&nbsp;</td>' . "\n" .
               '<td width="48%" style="vertical-align:top; padding-left:8px;">Right column content</td></tr></table>'],
    ['id' => 'badge_list', 'label' => 'Status Badge', 'icon' => 'fa-tag',
     'html' => '<span style="display:inline-block; padding:4px 12px; border-radius:12px; font-family:\'Montserrat\',Arial,sans-serif; font-size:12px; font-weight:600; background:#dcfce7; color:#16a34a;">Completed</span>'],
];

// Tenants for the selector.
$tenants = [
    ['id' => 0,   'name' => 'Global (all tenants)', 'selected' => ($tenantid == 0)],
    ['id' => 1,   'name' => 'Airpay',               'selected' => ($tenantid == 1)],
    ['id' => 77,  'name' => 'Public',                'selected' => ($tenantid == 77)],
    ['id' => 177, 'name' => 'ZEEA',                  'selected' => ($tenantid == 177)],
];

// Check for existing overrides across tenants.
$overridesummary = [];
foreach ([0, 1, 77, 177] as $tid) {
    $ov = \local_sentientia_emails\template_manager::get_override($templatekey, $tid);
    if ($ov) {
        $tname = match($tid) { 0 => 'Global', 1 => 'Airpay', 77 => 'Public', 177 => 'ZEEA', default => 'Tenant ' . $tid };
        $overridesummary[] = [
            'tenant_name' => $tname,
            'tenant_id'   => $tid,
            'modified'    => userdate($ov->timemodified, '%d %b %Y %I:%M %p'),
            'is_current'  => ($tid === $tenantid),
        ];
    }
}

$editordata = [
    'template_key'     => $templatekey,
    'template_label'   => $label,
    'category'         => $category,
    'tenant_id'        => $tenantid,
    'tenants'          => $tenants,
    'source'           => $source,
    'current_subject'  => s($currentsubject),
    'current_body'     => s($currentbody),
    'current_body_raw' => $currentbody,
    'preview_html'     => $previewhtml,
    'placeholders'     => $placeholdersdata,
    'smartblocks'      => $smartblocks,
    'overrides'        => $overridesummary,
    'has_overrides'    => !empty($overridesummary),
    'is_override'      => ($source !== 'file'),
    'sesskey'          => sesskey(),
    'save_url'         => (new moodle_url('/local/sentientia_emails/editor.php',
                            ['template' => $templatekey, 'tenant' => $tenantid]))->out(false),
    'back_url'         => (new moodle_url('/local/sentientia_emails/manage.php', ['tab' => 'templates']))->out(false),
    'preview_url'      => (new moodle_url('/local/sentientia_emails/preview.php',
                            ['template' => $templatekey, 'tenant' => $tenantid]))->out(false),
    'just_saved'       => optional_param('saved', 0, PARAM_INT),
    'just_reverted'    => optional_param('reverted', 0, PARAM_INT),
    'wwwroot'          => $CFG->wwwroot,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_emails/manage/editor', $editordata);
echo $OUTPUT->footer();
