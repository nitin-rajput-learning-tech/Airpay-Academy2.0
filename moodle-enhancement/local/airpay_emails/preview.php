<?php
/**
 * Email Template Preview — visual preview of all branded email templates.
 *
 * URL: /local/airpay_emails/preview.php?template=compliance/deadline_warning&tenant=1
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
// Siteadmins always have access; other users need the capability.
if (!is_siteadmin()) {
    require_capability('local/airpay_emails:preview', $context);
}

$templatekey = optional_param('template', '', PARAM_ALPHANUMEXT);
// Allow slashes in template key (e.g. compliance/deadline_warning).
$templatekey = str_replace('_', '/', optional_param('tpl', '', PARAM_RAW));
if (empty($templatekey)) {
    $templatekey = optional_param('template', '', PARAM_RAW);
}
$templatekey = clean_param($templatekey, PARAM_PATH);
// Sanitize: only allow alphanumeric, underscores, slashes.
$templatekey = preg_replace('/[^a-zA-Z0-9_\/]/', '', $templatekey);

$tenantid = optional_param('tenant', 1, PARAM_INT);
$viewmode = optional_param('view', 'visual', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/airpay_emails/preview.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('emailpreview', 'local_airpay_emails'));
$PAGE->set_heading(get_string('emailpreview', 'local_airpay_emails'));
$PAGE->set_pagelayout('standard');

// Get template categories.
$categories = \local_airpay_emails\email_renderer::get_template_list();
$tenants = [
    ['id' => 1,   'name' => get_string('tenant_airpay', 'local_airpay_emails'),  'selected' => ($tenantid == 1)],
    ['id' => 77,  'name' => get_string('tenant_public', 'local_airpay_emails'),   'selected' => ($tenantid == 77)],
    ['id' => 177, 'name' => get_string('tenant_zeea', 'local_airpay_emails'),     'selected' => ($tenantid == 177)],
];

// Render preview if template selected.
$previewhtml = '';
$previewsource = '';
$previewplain = '';
$selectedlabel = '';

if (!empty($templatekey)) {
    $fulltemplatename = 'local_airpay_emails/' . $templatekey;
    $samplecontext = \local_airpay_emails\email_context::get_sample($templatekey);

    try {
        $previewhtml = \local_airpay_emails\email_renderer::render_preview(
            $fulltemplatename, $samplecontext, $tenantid
        );
        $previewsource = s($previewhtml);
        $previewplain = html_to_text($previewhtml);
    } catch (\Exception $e) {
        $previewhtml = '<div style="padding:20px;color:#dc2626;font-family:monospace;">'
                     . 'Template error: ' . s($e->getMessage()) . '</div>';
    }

    // Find label.
    foreach ($categories as $cat) {
        foreach ($cat['templates'] as $tpl) {
            if ($tpl['key'] === $templatekey) {
                $selectedlabel = $tpl['label'];
                break 2;
            }
        }
    }
}

// Mark active template in sidebar.
foreach ($categories as &$cat) {
    foreach ($cat['templates'] as &$tpl) {
        $tpl['active'] = ($tpl['key'] === $templatekey);
        $tpl['url'] = (new moodle_url('/local/airpay_emails/preview.php', [
            'template' => $tpl['key'],
            'tenant'   => $tenantid,
            'view'     => $viewmode,
        ]))->out(false);
    }
}
unset($cat, $tpl);

echo $OUTPUT->header();
?>

<style>
.ap-email-preview-wrap {
    display: flex; gap: 24px; min-height: 80vh;
    font-family: 'Montserrat', -apple-system, sans-serif;
}
.ap-email-sidebar {
    width: 280px; min-width: 280px; background: #fff;
    border: 1px solid #e2e6ef; border-radius: 12px;
    padding: 16px; overflow-y: auto; max-height: 85vh;
}
.ap-email-sidebar h4 {
    font-size: 11px; text-transform: uppercase; letter-spacing: 1px;
    color: #5a6070; margin: 16px 0 8px; padding: 0;
}
.ap-email-sidebar h4:first-child { margin-top: 0; }
.ap-email-sidebar a {
    display: block; padding: 8px 12px; margin: 2px 0;
    border-radius: 8px; text-decoration: none;
    font-size: 13px; color: #1a1a2e; transition: all 0.15s;
}
.ap-email-sidebar a:hover { background: #e8f2f9; color: #0066A7; }
.ap-email-sidebar a.active {
    background: #0066A7; color: #fff; font-weight: 600;
}
.ap-email-main { flex: 1; min-width: 0; }
.ap-email-controls {
    display: flex; gap: 12px; align-items: center;
    margin-bottom: 16px; flex-wrap: wrap;
}
.ap-email-controls select {
    padding: 8px 12px; border: 1px solid #e2e6ef;
    border-radius: 8px; font-family: inherit; font-size: 13px;
    background: #fff; cursor: pointer;
}
.ap-email-tabs {
    display: flex; gap: 0; margin-bottom: 0;
    border-bottom: 2px solid #e2e6ef;
}
.ap-email-tab {
    padding: 10px 20px; font-size: 13px; font-weight: 500;
    color: #5a6070; cursor: pointer; border-bottom: 2px solid transparent;
    margin-bottom: -2px; transition: all 0.15s;
    background: none; border-top: none; border-left: none; border-right: none;
}
.ap-email-tab:hover { color: #0066A7; }
.ap-email-tab.active { color: #0066A7; border-bottom-color: #0066A7; font-weight: 600; }
.ap-email-viewport {
    background: #e2e6ef; border-radius: 0 0 12px 12px;
    padding: 24px; min-height: 400px; overflow: auto;
}
.ap-email-viewport.desktop { max-width: 100%; }
.ap-email-viewport.mobile { max-width: 400px; margin: 0 auto; }
.ap-email-iframe-wrap {
    background: #fff; border-radius: 4px; overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.ap-email-iframe-wrap iframe {
    width: 100%; border: none; min-height: 700px;
}
.ap-email-source {
    background: #1a1a2e; color: #e2e6ef; padding: 20px;
    border-radius: 0 0 12px 12px; overflow: auto;
    max-height: 600px; font-family: 'Courier New', monospace;
    font-size: 12px; line-height: 18px; white-space: pre-wrap;
    word-break: break-all;
}
.ap-email-plain {
    background: #fff; padding: 20px; border-radius: 0 0 12px 12px;
    overflow: auto; max-height: 600px;
    font-family: 'Courier New', monospace; font-size: 13px;
    line-height: 20px; white-space: pre-wrap; color: #1a1a2e;
    border: 1px solid #e2e6ef;
}
.ap-email-empty {
    display: flex; align-items: center; justify-content: center;
    min-height: 400px; color: #5a6070; font-size: 15px;
    background: #fff; border-radius: 12px; border: 1px solid #e2e6ef;
}
.ap-email-title {
    font-size: 18px; font-weight: 600; color: #1a1a2e; margin: 0 0 4px;
}
.ap-email-subtitle {
    font-size: 13px; color: #5a6070; margin: 0 0 16px;
}
.ap-viewport-toggle {
    display: flex; gap: 0; background: #fff; border: 1px solid #e2e6ef;
    border-radius: 8px; overflow: hidden;
}
.ap-viewport-toggle button {
    padding: 6px 14px; font-size: 12px; cursor: pointer;
    background: #fff; border: none; color: #5a6070;
    font-family: inherit; transition: all 0.15s;
}
.ap-viewport-toggle button.active { background: #0066A7; color: #fff; }
</style>

<div class="ap-email-preview-wrap">

    <!-- SIDEBAR -->
    <nav class="ap-email-sidebar">
        <?php foreach ($categories as $cat): ?>
            <h4><?php echo s($cat['category']); ?></h4>
            <?php foreach ($cat['templates'] as $tpl): ?>
                <a href="<?php echo s($tpl['url']); ?>"
                   class="<?php echo $tpl['active'] ? 'active' : ''; ?>">
                    <?php echo s($tpl['label']); ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- MAIN PREVIEW AREA -->
    <div class="ap-email-main">
        <?php if (!empty($templatekey) && !empty($previewhtml)): ?>

            <h2 class="ap-email-title"><?php echo s($selectedlabel); ?></h2>
            <p class="ap-email-subtitle">Template: <code>local_airpay_emails/<?php echo s($templatekey); ?></code></p>

            <div class="ap-email-controls">
                <label style="font-size:13px; color:#5a6070; font-weight:500;">Tenant:</label>
                <select onchange="window.location='<?php echo (new moodle_url('/local/airpay_emails/preview.php', ['template' => $templatekey]))->out(false); ?>&tenant='+this.value">
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $t['selected'] ? 'selected' : ''; ?>>
                            <?php echo s($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="ap-viewport-toggle">
                    <button id="btn-desktop" class="active" onclick="setViewport('desktop')">Desktop</button>
                    <button id="btn-mobile" onclick="setViewport('mobile')">Mobile</button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="ap-email-tabs">
                <button class="ap-email-tab <?php echo ($viewmode === 'visual') ? 'active' : ''; ?>"
                        onclick="switchTab('visual')">Visual Preview</button>
                <button class="ap-email-tab <?php echo ($viewmode === 'source') ? 'active' : ''; ?>"
                        onclick="switchTab('source')">HTML Source</button>
                <button class="ap-email-tab <?php echo ($viewmode === 'plain') ? 'active' : ''; ?>"
                        onclick="switchTab('plain')">Plain Text</button>
            </div>

            <!-- Visual preview (rendered in iframe for isolation) -->
            <div id="panel-visual" class="ap-email-viewport desktop"
                 style="<?php echo ($viewmode !== 'visual') ? 'display:none;' : ''; ?>">
                <div class="ap-email-iframe-wrap">
                    <iframe id="email-preview-frame" srcdoc="<?php echo s($previewhtml); ?>"
                            sandbox="allow-same-origin"
                            onload="this.style.height = this.contentDocument.body.scrollHeight + 40 + 'px';">
                    </iframe>
                </div>
            </div>

            <!-- HTML source -->
            <div id="panel-source" class="ap-email-source"
                 style="<?php echo ($viewmode !== 'source') ? 'display:none;' : ''; ?>"><?php echo $previewsource; ?></div>

            <!-- Plain text -->
            <div id="panel-plain" class="ap-email-plain"
                 style="<?php echo ($viewmode !== 'plain') ? 'display:none;' : ''; ?>"><?php echo s($previewplain); ?></div>

        <?php else: ?>
            <div class="ap-email-empty">
                <?php echo get_string('no_template_selected', 'local_airpay_emails'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.ap-email-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('[id^="panel-"]').forEach(function(p) { p.style.display = 'none'; });
    document.getElementById('panel-' + tab).style.display = '';
    event.target.classList.add('active');
}
function setViewport(mode) {
    var vp = document.getElementById('panel-visual');
    vp.classList.remove('desktop', 'mobile');
    vp.classList.add(mode);
    document.getElementById('btn-desktop').classList.toggle('active', mode === 'desktop');
    document.getElementById('btn-mobile').classList.toggle('active', mode === 'mobile');
}
</script>

<?php
echo $OUTPUT->footer();
