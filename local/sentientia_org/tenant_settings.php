<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-tenant settings — branding, email-from, hero, custom CSS.
 *
 * One page per tenant root. Site admins manage any; tenant admins
 * manage only their own tenant (capability-gated).
 *
 * @package local_sentientia_org
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE;

$tenantid = optional_param('id', 0, PARAM_INT);
if (!$tenantid) {
    // Default to current user's tenant root.
    $tenantid = \local_sentientia_org\tenant_manager::get_tenant_id($USER);
}
if ($tenantid <= 0) {
    throw new \moodle_exception('invalidtenant', 'local_sentientia_org');
}

$tenant = $DB->get_record('local_sentientia_org', ['id' => $tenantid], '*', MUST_EXIST);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_org/tenant_settings.php', ['id' => $tenantid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Tenant settings — ' . format_string($tenant->fullname));
$PAGE->set_heading('Tenant settings — ' . format_string($tenant->fullname));

// Authorisation:
// - site admin → any tenant
// - has local/sentientia_org:managetenant cap → own tenant only
require_capability('local/sentientia_org:view', $ctx);
$is_admin = is_siteadmin();
$can_manage = $is_admin
    || has_capability('local/sentientia_org:managetenant', $ctx);

if (!$can_manage) {
    throw new \moodle_exception('nopermissions', 'error', '',
        'Manage tenant settings');
}

// Non-siteadmin can only edit own tenant.
if (!$is_admin) {
    $user_tenant = \local_sentientia_org\tenant_manager::get_tenant_id($USER);
    if ((int) $user_tenant !== (int) $tenantid) {
        throw new \moodle_exception('outoftenant', 'local_sentientia_org');
    }
}

/**
 * The settings form.
 */
class tenant_settings_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $tenant = $this->_customdata['tenant'];

        $mform->addElement('hidden', 'id', $tenant->id);
        $mform->setType('id', PARAM_INT);

        // ── Branding ──────────────────────────────────────────────────
        $mform->addElement('header', 'h_brand', 'Branding');

        $mform->addElement('filemanager', 'org_logo', 'Logo', null, [
            'maxfiles' => 1, 'accepted_types' => ['web_image'], 'subdirs' => 0,
        ]);
        $mform->addHelpButton('org_logo', 'org_logo', 'local_sentientia_org');

        $mform->addElement('filemanager', 'favicon', 'Favicon (16×16 ICO/PNG)', null, [
            'maxfiles' => 1, 'accepted_types' => ['.ico', '.png'], 'subdirs' => 0,
        ]);

        $mform->addElement('text', 'brand_color', 'Primary brand color (#HEX)',
            ['size' => 10, 'placeholder' => '#0066A7']);
        $mform->setType('brand_color', PARAM_TEXT);

        $mform->addElement('text', 'button_color', 'Button color (#HEX)',
            ['size' => 10, 'placeholder' => '#0066A7']);
        $mform->setType('button_color', PARAM_TEXT);

        $mform->addElement('text', 'hover_color', 'Hover color (#HEX)',
            ['size' => 10, 'placeholder' => '#004d80']);
        $mform->setType('hover_color', PARAM_TEXT);

        $mform->addElement('text', 'theme_scheme', 'Theme variant (optional)',
            ['size' => 30, 'placeholder' => 'airpay | public | zeea']);
        $mform->setType('theme_scheme', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('theme_scheme', 'theme_scheme', 'local_sentientia_org');

        // ── Email identity ────────────────────────────────────────────
        $mform->addElement('header', 'h_email', 'Email identity');
        $mform->setExpanded('h_email', false);

        $mform->addElement('text', 'email_from_name', 'From name',
            ['size' => 60, 'placeholder' => 'Airpay Academy']);
        $mform->setType('email_from_name', PARAM_TEXT);

        $mform->addElement('text', 'email_from_addr', 'From email',
            ['size' => 60, 'placeholder' => 'noreply@airpay.academy']);
        $mform->setType('email_from_addr', PARAM_EMAIL);

        $mform->addElement('text', 'support_email', 'Support email',
            ['size' => 60, 'placeholder' => 'support@airpay.academy']);
        $mform->setType('support_email', PARAM_EMAIL);

        $mform->addElement('text', 'help_url', 'Help URL',
            ['size' => 60, 'placeholder' => 'https://help.airpay.academy']);
        $mform->setType('help_url', PARAM_URL);

        // ── Footer ────────────────────────────────────────────────────
        $mform->addElement('header', 'h_footer', 'Footer');
        $mform->setExpanded('h_footer', false);

        $mform->addElement('editor', 'footer_text', 'Footer HTML', null, [
            'maxfiles' => 0, 'noclean' => false, 'subdirs' => 0,
        ]);
        $mform->setType('footer_text', PARAM_RAW);

        // ── Login page hero ───────────────────────────────────────────
        $mform->addElement('header', 'h_hero', 'Login page hero (visible on /login)');
        $mform->setExpanded('h_hero', false);

        $mform->addElement('text', 'hero_title', 'Hero title',
            ['size' => 60, 'placeholder' => 'Welcome to the academy']);
        $mform->setType('hero_title', PARAM_TEXT);

        $mform->addElement('textarea', 'hero_subtitle', 'Hero subtitle',
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('hero_subtitle', PARAM_TEXT);

        // ── Advanced: custom CSS ──────────────────────────────────────
        $mform->addElement('header', 'h_css', 'Advanced — Custom CSS');
        $mform->setExpanded('h_css', false);

        $mform->addElement('textarea', 'custom_css',
            'Custom CSS (injected into <head>)',
            ['rows' => 12, 'cols' => 80, 'style' => 'font-family:monospace;font-size:0.85em;']);
        $mform->setType('custom_css', PARAM_RAW);

        $this->add_action_buttons(true, 'Save settings');
    }
}

// Prefill draft areas for filemanager fields.
$draftid_logo = file_get_submitted_draft_itemid('org_logo');
file_prepare_draft_area($draftid_logo, $ctx->id, 'local_sentientia_org',
    'org_logo', (int) ($tenant->org_logo ?? 0), ['subdirs' => 0, 'maxfiles' => 1]);

$draftid_favicon = file_get_submitted_draft_itemid('favicon');
file_prepare_draft_area($draftid_favicon, $ctx->id, 'local_sentientia_org',
    'favicon', (int) ($tenant->favicon ?? 0), ['subdirs' => 0, 'maxfiles' => 1]);

$prefill = [
    'id'              => $tenant->id,
    'org_logo'        => $draftid_logo,
    'favicon'         => $draftid_favicon,
    'brand_color'     => $tenant->brand_color ?? '',
    'button_color'    => $tenant->button_color ?? '',
    'hover_color'     => $tenant->hover_color ?? '',
    'theme_scheme'    => $tenant->theme_scheme ?? '',
    'email_from_name' => $tenant->email_from_name ?? '',
    'email_from_addr' => $tenant->email_from_addr ?? '',
    'support_email'   => $tenant->support_email ?? '',
    'help_url'        => $tenant->help_url ?? '',
    'footer_text'     => ['text' => $tenant->footer_text ?? '', 'format' => FORMAT_HTML],
    'hero_title'      => $tenant->hero_title ?? '',
    'hero_subtitle'   => $tenant->hero_subtitle ?? '',
    'custom_css'      => $tenant->custom_css ?? '',
];

$form = new tenant_settings_form(null, ['tenant' => $tenant]);
$form->set_data($prefill);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/sentientia_org/admin.php'));
}

if ($data = $form->get_data()) {
    require_sesskey();
    $update = (object) [
        'id'              => $tenant->id,
        'brand_color'     => trim($data->brand_color ?: ''),
        'button_color'    => trim($data->button_color ?: ''),
        'hover_color'     => trim($data->hover_color ?: ''),
        'theme_scheme'    => trim($data->theme_scheme ?: ''),
        'email_from_name' => trim($data->email_from_name ?: ''),
        'email_from_addr' => trim($data->email_from_addr ?: ''),
        'support_email'   => trim($data->support_email ?: ''),
        'help_url'        => trim($data->help_url ?: ''),
        'footer_text'     => $data->footer_text['text'] ?? '',
        'hero_title'      => trim($data->hero_title ?: ''),
        'hero_subtitle'   => $data->hero_subtitle ?? '',
        'custom_css'      => $data->custom_css ?? '',
        'timemodified'    => time(),
    ];

    // Persist scalar fields.
    $DB->update_record('local_sentientia_org', $update);

    // Persist filemanager uploads (logo + favicon).
    file_save_draft_area_files((int) $data->org_logo, $ctx->id,
        'local_sentientia_org', 'org_logo', $tenant->id,
        ['subdirs' => 0, 'maxfiles' => 1]);
    $DB->set_field('local_sentientia_org', 'org_logo', (int) $data->org_logo,
        ['id' => $tenant->id]);

    file_save_draft_area_files((int) $data->favicon, $ctx->id,
        'local_sentientia_org', 'favicon', $tenant->id,
        ['subdirs' => 0, 'maxfiles' => 1]);
    $DB->set_field('local_sentientia_org', 'favicon', (int) $data->favicon,
        ['id' => $tenant->id]);

    // Purge theme cache so colour changes take effect immediately.
    theme_reset_all_caches();

    \core\notification::success('Tenant settings saved.');
    redirect(new moodle_url('/local/sentientia_org/tenant_settings.php',
        ['id' => $tenant->id]));
}

echo $OUTPUT->header();
echo '<div class="container py-3">';
echo '<div class="alert alert-info small mb-3">'
    . '<i class="fa fa-info-circle"></i> '
    . 'Editing branding for: <strong>' . format_string($tenant->fullname) . '</strong>'
    . ' (path: <code>' . s($tenant->path) . '</code>)'
    . '</div>';
$form->display();
echo '</div>';
echo $OUTPUT->footer();
