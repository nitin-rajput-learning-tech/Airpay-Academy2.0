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

// Navigation bar between static pages.
echo '<div class="ap-static-page-nav">';
foreach ($validpages as $slug => $title) {
    $activeclass = ($slug === $page) ? 'ap-static-page-nav__link--active' : '';
    $url = new moodle_url('/local/airpay_pages/index.php', ['page' => $slug]);
    echo '<a href="' . $url->out() . '" class="ap-static-page-nav__link ' . $activeclass . '">' . s($title) . '</a>';
}
echo '</div>';

// Load content from the pages/ directory.
$contentfile = __DIR__ . '/pages/' . $page . '.html';
if (file_exists($contentfile)) {
    $content = file_get_contents($contentfile);
    // Replace hardcoded /moodle/ paths with dynamic wwwroot.
    $content = str_replace('/moodle/', $CFG->wwwroot . '/', $content);
    $content = str_replace('href="/', 'href="' . $CFG->wwwroot . '/', $content);

    echo '<div class="ap-static-page">';
    echo '<div class="ap-static-page__content">';
    echo $content;
    echo '</div>';
    // Last updated badge + back-to-top.
    echo '<div class="ap-static-page__footer">';
    echo '<span class="ap-static-page__updated"><i class="fa fa-clock-o"></i> Last updated: April 2026</span>';
    echo '<a href="#" class="ap-static-page__back-top" onclick="window.scrollTo({top:0,behavior:\'smooth\'});return false;">';
    echo '<i class="fa fa-arrow-up"></i> Back to top</a>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="ap-empty-state">';
    echo '<i class="fa fa-file-text-o ap-empty-state__icon"></i>';
    echo '<h4 class="ap-empty-state__title">Page coming soon</h4>';
    echo '<p class="ap-empty-state__message">This content is being prepared and will be available shortly.</p>';
    echo '</div>';
}

echo $OUTPUT->footer();
