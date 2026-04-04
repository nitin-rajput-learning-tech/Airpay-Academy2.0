<?php
/**
 * Airpay Academy static pages viewer.
 * Usage: /local/airpay_pages/index.php?page=privacy
 *        /local/airpay_pages/index.php?page=terms
 *        /local/airpay_pages/index.php?page=help
 *        /local/airpay_pages/index.php?page=contact
 */
require_once(__DIR__ . '/../../config.php');

$page = required_param('page', PARAM_ALPHA);

$validpages = [
    'privacy' => 'privacy_policy',
    'terms'   => 'terms_of_use',
    'help'    => 'help_center',
    'contact' => 'contact_us',
];

if (!isset($validpages[$page])) {
    throw new moodle_exception('invalidpage', 'error');
}

$stringkey = $validpages[$page];
$pagetitle = get_string($stringkey, 'local_airpay_pages');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/airpay_pages/index.php', ['page' => $page]);
$PAGE->set_title($pagetitle . ' | ' . get_config('moodle', 'shortname'));
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add($pagetitle);

echo $OUTPUT->header();

// Load content from the pages/ directory
$contentfile = __DIR__ . '/pages/' . $page . '.html';
if (file_exists($contentfile)) {
    echo '<div class="airpay-page">';
    echo '<div class="airpay-page__header">';
    echo '<h1 class="airpay-page__title">' . s($pagetitle) . '</h1>';
    echo '</div>';
    echo '<div class="airpay-page__content">';
    echo file_get_contents($contentfile);
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="alert alert-info">';
    echo 'This page is being prepared. Content will be available soon.';
    echo '</div>';
}

echo $OUTPUT->footer();
