<?php
/**
 * Airpay Academy — Certificate Gallery.
 * Visual card-based gallery of earned certificates with download + LinkedIn share.
 * Replaces the default table view at /admin/tool/certificate/my.php.
 *
 * @package    local_sentientia_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/sentientia_pages/certificates.php');
// Phase E (UI-NAV-AUDIT N-08): get_config('moodle','shortname') is not a real
// config key (site shortname lives on $SITE) — it returned empty and produced
// the "My Certificates | | airpay" double-separator title. Moodle appends the
// site name itself; pass only the page title.
// Surface walk 2026-11-18: every string on this page is a lang string now (it
// stayed English under ?lang=hi), and the count has a singular form.
$pagetitle = get_string('certs_title', 'local_sentientia_pages');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add($pagetitle);

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
// The verification page throws for a viewer without tool/certificate:verify,
// so the "Verify" button is only offered to viewers who can open it.
$canverify = \tool_certificate\permission::can_verify();

$count = count($certificates);
$countlabel = $count === 1
    ? get_string('certs_count_one', 'local_sentientia_pages')
    : get_string('certs_count', 'local_sentientia_pages', $count);

echo '<div class="airpay-cert-gallery">';
echo '<div class="airpay-cert-gallery__header">';
echo '<h2 class="airpay-cert-gallery__title"><i class="fa fa-certificate" aria-hidden="true"></i> ' . s($pagetitle) . '</h2>';
echo '<span class="airpay-cert-gallery__count">' . s($countlabel) . '</span>';
echo '</div>';

if (empty($certificates)) {
    echo '<div class="ap-empty-state">';
    echo '<i class="fa fa-certificate ap-empty-state__icon" aria-hidden="true"></i>';
    echo '<h4 class="ap-empty-state__title">' . s(get_string('certs_empty_title', 'local_sentientia_pages')) . '</h4>';
    echo '<p class="ap-empty-state__message">' . s(get_string('certs_empty_message', 'local_sentientia_pages')) . '</p>';
    $browseurl = new moodle_url('/local/sentientia_catalog/index.php');
    echo '<a href="' . $browseurl->out() . '" class="ap-empty-state__cta">';
    echo '<i class="fa fa-search" aria-hidden="true"></i> ' . s(get_string('certs_browse', 'local_sentientia_pages')) . '</a>';
    echo '</div>';
} else {
    $syscontext = context_system::instance();
    echo '<div class="airpay-cert-gallery__grid">';
    foreach ($certificates as $cert) {
        $rawname = (string) ($cert->coursename ?: ($cert->templatename ?: ''));
        if ($rawname === '') {
            $rawname = get_string('certs_fallback_name', 'local_sentientia_pages');
        }
        // format_string() output is already HTML-safe: print it as is (it was
        // s()-escaped a second time, so "Risk & Compliance" showed "&amp;").
        // The plain form is for URLs and aria labels, which s() escapes.
        $coursename = format_string($rawname, true, ['context' => $syscontext]);
        $plainname = format_string($rawname, true, ['context' => $syscontext, 'escape' => false]);
        $date = userdate($cert->timecreated, '%d %B %Y');
        // Surface walk 2026-11-18: "Download" pointed at the verification form
        // (index.php?code=), not the PDF. tool_certificate's own URL helpers:
        // view_url() is view.php?code=, which serves the issued PDF to its owner;
        // verification_url() is the public verify page, kept as "Verify" and as
        // the LinkedIn certificate URL.
        $downloadurl = \tool_certificate\template::view_url($cert->code);
        $verifyurl = \tool_certificate\template::verification_url($cert->code);
        $linkedinurl = 'https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&name=' .
            urlencode($plainname) . '&organizationName=Airpay%20Academy&certId=' .
            urlencode($cert->code) . '&certUrl=' . urlencode($verifyurl->out(false));

        echo '<div class="airpay-cert-gallery__card">';
        echo '<div class="airpay-cert-gallery__card-header">';
        echo '<i class="fa fa-certificate" aria-hidden="true"></i>';
        echo '</div>';
        echo '<div class="airpay-cert-gallery__card-body">';
        echo '<h4 class="airpay-cert-gallery__card-title">' . $coursename . '</h4>';
        echo '<p class="airpay-cert-gallery__card-date"><i class="fa fa-calendar" aria-hidden="true"></i> ' . s($date) . '</p>';
        echo '<p class="airpay-cert-gallery__card-code"><i class="fa fa-barcode" aria-hidden="true"></i> ' . s($cert->code) . '</p>';
        echo '<div class="airpay-cert-gallery__card-actions">';
        echo '<a href="' . $downloadurl->out() . '" class="airpay-btn airpay-btn--primary airpay-btn--sm" target="_blank"'
            . ' aria-label="' . s(get_string('certs_download_label', 'local_sentientia_pages', $plainname)) . '">';
        echo '<i class="fa fa-download" aria-hidden="true"></i> ' . s(get_string('certs_download', 'local_sentientia_pages')) . '</a>';
        if ($canverify) {
            echo '<a href="' . $verifyurl->out() . '" class="airpay-btn airpay-btn--outline airpay-btn--sm" target="_blank"'
                . ' aria-label="' . s(get_string('certs_verify_label', 'local_sentientia_pages', $plainname)) . '">';
            echo '<i class="fa fa-check-circle" aria-hidden="true"></i> ' . s(get_string('certs_verify', 'local_sentientia_pages')) . '</a>';
        }
        if ($showlinkedin) {
            echo '<a href="' . s($linkedinurl) . '" class="airpay-btn airpay-btn--outline airpay-btn--sm" target="_blank"'
                . ' aria-label="' . s(get_string('certs_linkedin_label', 'local_sentientia_pages', $plainname)) . '">';
            echo '<i class="fa fa-linkedin" aria-hidden="true"></i> ' . s(get_string('certs_linkedin', 'local_sentientia_pages')) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
