<?php
/**
 * Airpay Academy static pages viewer.
 * Usage: /local/airpay_pages/index.php?page=privacy
 *        /local/airpay_pages/index.php?page=terms
 *        /local/airpay_pages/index.php?page=help
 *        /local/airpay_pages/index.php?page=contact
 *
 * @package   local_airpay_pages
 * @copyright 2026 Airpay Payment Services
 */
require_once(__DIR__ . '/../../config.php');

$page = required_param('page', PARAM_ALPHA);

$validpages = [
    'privacy' => 'Privacy Policy',
    'terms'   => 'Terms of Service',
    'help'    => 'Help Center',
    'contact' => 'Contact Us',
];

if (!isset($validpages[$page])) {
    throw new moodle_exception('invalidpage', 'error');
}

$pagetitle = $validpages[$page];

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/airpay_pages/index.php', ['page' => $page]);
$PAGE->set_title($pagetitle . ' | airpay academy');
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add($pagetitle);

echo $OUTPUT->header();

// Load content from the pages/ directory.
$contentfile = __DIR__ . '/pages/' . $page . '.html';
if (file_exists($contentfile)) {
    echo '<div class="ap-static-page">';
    echo '<div class="ap-static-page__content">';
    echo file_get_contents($contentfile);
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="alert alert-info">';
    echo 'This page is being prepared. Content will be available soon.';
    echo '</div>';
}

echo $OUTPUT->footer();
