<?php
/**
 * Airpay Academy — Certificate Gallery.
 * Visual card-based gallery of earned certificates with download + LinkedIn share.
 * Replaces the default table view at /admin/tool/certificate/my.php.
 *
 * @package    local_airpay_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_pages/certificates.php');
$PAGE->set_title('My Certificates | ' . get_config('moodle', 'shortname'));
$PAGE->set_heading('My Certificates');
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add('My Certificates');

echo $OUTPUT->header();

// Get user's certificates.
$certificates = $DB->get_records_sql(
    "SELECT ci.id, ci.code, ci.timecreated, ci.courseid,
            ct.name as templatename,
            c.fullname as coursename, c.shortname as courseshort
       FROM {tool_certificate_issues} ci
  LEFT JOIN {tool_certificate_templates} ct ON ct.id = ci.templateid
  LEFT JOIN {course} c ON c.id = ci.courseid
      WHERE ci.userid = :uid AND ci.archived = 0
   ORDER BY ci.timecreated DESC",
    ['uid' => $USER->id]
);

// Check if LinkedIn sharing is enabled.
$showlinkedin = get_config('tool_certificate', 'show_shareonlinkedin');

echo '<div class="airpay-cert-gallery">';
echo '<div class="airpay-cert-gallery__header">';
echo '<h2 class="airpay-cert-gallery__title"><i class="fa fa-certificate"></i> My Certificates</h2>';
echo '<span class="airpay-cert-gallery__count">' . count($certificates) . ' certificates earned</span>';
echo '</div>';

if (empty($certificates)) {
    echo '<div class="ap-empty-state">';
    echo '<i class="fa fa-certificate ap-empty-state__icon"></i>';
    echo '<h4 class="ap-empty-state__title">No certificates yet</h4>';
    echo '<p class="ap-empty-state__message">Complete courses with certificates enabled to earn your credentials.</p>';
    echo '<a href="' . $CFG->wwwroot . '/local/airpay_catalog/index.php" class="ap-empty-state__cta">';
    echo '<i class="fa fa-search"></i> Browse Courses</a>';
    echo '</div>';
} else {
    echo '<div class="airpay-cert-gallery__grid">';
    foreach ($certificates as $cert) {
        $coursename = format_string($cert->coursename ?? $cert->templatename ?? 'Certificate');
        $date = userdate($cert->timecreated, '%d %B %Y');
        $downloadurl = new moodle_url('/admin/tool/certificate/index.php', ['code' => $cert->code]);
        $verifyurl = $CFG->wwwroot . '/admin/tool/certificate/index.php?code=' . urlencode($cert->code);
        $linkedinurl = 'https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name=' .
            urlencode($coursename) . '&organizationName=Airpay%20Academy&certId=' .
            urlencode($cert->code) . '&certUrl=' . urlencode($verifyurl);

        echo '<div class="airpay-cert-gallery__card">';
        echo '<div class="airpay-cert-gallery__card-header">';
        echo '<i class="fa fa-certificate"></i>';
        echo '</div>';
        echo '<div class="airpay-cert-gallery__card-body">';
        echo '<h4 class="airpay-cert-gallery__card-title">' . s($coursename) . '</h4>';
        echo '<p class="airpay-cert-gallery__card-date"><i class="fa fa-calendar"></i> ' . s($date) . '</p>';
        echo '<p class="airpay-cert-gallery__card-code"><i class="fa fa-barcode"></i> ' . s($cert->code) . '</p>';
        echo '<div class="airpay-cert-gallery__card-actions">';
        echo '<a href="' . $downloadurl->out() . '" class="airpay-btn airpay-btn--primary airpay-btn--sm" target="_blank">';
        echo '<i class="fa fa-download"></i> Download</a>';
        if ($showlinkedin) {
            echo '<a href="' . s($linkedinurl) . '" class="airpay-btn airpay-btn--outline airpay-btn--sm" target="_blank">';
            echo '<i class="fa fa-linkedin"></i> LinkedIn</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
