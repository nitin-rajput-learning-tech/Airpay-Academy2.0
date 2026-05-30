<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia Core is a library/service layer (the tenant-identity seam) and
 * has no learner-facing UI. This page is an admin-only signpost to its
 * settings + the design ADR, satisfying the local-plugin file checklist.
 *
 * @package   local_sentientia_core
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/local/sentientia_core/index.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_sentientia_core'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_core'));

echo $OUTPUT->header();
echo $OUTPUT->notification(
    'Sentientia Core is a library / service layer (the ADR-019 tenant-identity '
    . 'seam). It has no learner-facing UI. Configure it under Site administration '
    . '→ Plugins → Local plugins → Sentientia Core. Design: docs/adr/ADR-019.',
    'info'
);
echo $OUTPUT->footer();
