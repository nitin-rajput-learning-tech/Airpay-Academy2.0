<?php
/**
 * airpay academy Public Homepage.
 * Marketing landing page for non-logged-in visitors.
 * Shows hero banner, learning pillars, featured courses, and CTA.
 *
 * To use: Set as site home page via Site Admin → Front page settings → Front page
 * OR redirect root to this page.
 */
require_once(__DIR__ . '/../../config.php');
global $DB, $CFG, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_pages/homepage.php');
$PAGE->set_title('airpay academy — L&D Operating System');
$PAGE->set_heading('airpay academy');
$PAGE->set_pagelayout('standard');

// If already logged in, redirect to dashboard.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

echo $OUTPUT->header();

// Get live stats for the hero.
$coursecount = $DB->count_records_select('course', 'visible = 1 AND id > 1');
$usercount = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');

// Get featured courses (most enrolled, visible, with summaries).
$featured = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.summary, c.summaryformat, COUNT(ue.id) as enrolcount
       FROM {course} c
       JOIN {enrol} e ON e.courseid = c.id
       JOIN {user_enrolments} ue ON ue.enrolid = e.id
      WHERE c.visible = 1 AND c.id > 1
   GROUP BY c.id, c.fullname, c.summary, c.summaryformat
   ORDER BY enrolcount DESC",
    [], 0, 6);

echo '<div class="airpay-homepage">';

// ═══ HERO SECTION ═══
echo '<section class="airpay-homepage__hero" style="background-image: url(' . $CFG->wwwroot . '/theme/airpayux/pix/brand/bannerimg.png); background-size: cover; background-position: center;"
    <div class="airpay-homepage__hero-content">
        <span class="airpay-homepage__hero-badge">airpay academy</span>
        <h1 class="airpay-homepage__hero-title">Build Skills That<br>Drive Your Career Forward</h1>
        <p class="airpay-homepage__hero-subtitle">A comprehensive learning platform for employability, business training, and financial education. Empowering individuals and organisations with industry-relevant skills.</p>
        <div class="airpay-homepage__hero-actions">
            <a href="' . $CFG->wwwroot . '/local/search/allcourses.php" class="airpay-btn airpay-btn--primary airpay-btn--lg">Explore Courses</a>
            <a href="' . $CFG->wwwroot . '/login/index.php" class="airpay-btn airpay-btn--outline airpay-btn--lg">Sign In</a>
        </div>
        <div class="airpay-homepage__hero-stats">
            <div class="airpay-homepage__hero-stat"><strong>' . $coursecount . '+</strong><span>Courses</span></div>
            <div class="airpay-homepage__hero-stat"><strong>' . $usercount . '+</strong><span>Learners</span></div>
            <div class="airpay-homepage__hero-stat"><strong>3</strong><span>Organisations</span></div>
        </div>
    </div>
</section>';

// ═══ TRUST BAR ═══
echo '<section class="airpay-homepage__trust">
    <div class="airpay-homepage__trust-item"><i class="fa fa-shield"></i> RBI Compliant</div>
    <div class="airpay-homepage__trust-item"><i class="fa fa-lock"></i> DPDP 2023</div>
    <div class="airpay-homepage__trust-item"><i class="fa fa-certificate"></i> ISO Certified</div>
    <div class="airpay-homepage__trust-item"><i class="fa fa-headphones"></i> 24/7 Support</div>
</section>';

// ═══ LEARNING PILLARS ═══
echo '<section class="airpay-homepage__pillars">
    <h2>Three Pillars of Learning</h2>
    <div class="airpay-homepage__pillars-grid">';

$pillars = [
    ['icon' => 'briefcase', 'title' => 'Employability Skills', 'desc' => 'Digital literacy, communication, aptitude, and professional development to prepare you for the modern workplace.', 'color' => '#0066A7'],
    ['icon' => 'line-chart', 'title' => 'Business Acumen', 'desc' => 'Business correspondence, sales fundamentals, leadership essentials, and customer service excellence.', 'color' => '#0f7a73'],
    ['icon' => 'university', 'title' => 'Financial Education', 'desc' => 'Digital payments, anti-money laundering, POSH compliance, and financial wellness for the fintech industry.', 'color' => '#7c3aed'],
];

foreach ($pillars as $p) {
    echo '<div class="airpay-homepage__pillar" style="border-top: 3px solid ' . $p['color'] . '">
        <div class="airpay-homepage__pillar-icon" style="color: ' . $p['color'] . '"><i class="fa fa-' . $p['icon'] . '"></i></div>
        <h3>' . s($p['title']) . '</h3>
        <p>' . s($p['desc']) . '</p>
    </div>';
}

echo '  </div>
</section>';

// ═══ FEATURED COURSES ═══
if (!empty($featured)) {
    echo '<section class="airpay-homepage__courses">
        <h2>Featured Courses</h2>
        <div class="airpay-homepage__courses-grid">';

    foreach ($featured as $course) {
        $summary = shorten_text(strip_tags(format_string($course->summary)), 100);
        $url = new moodle_url('/local/search/coursedetails.php', ['id' => $course->id]);
        echo '<div class="airpay-homepage__course-card">
            <div class="airpay-homepage__course-header"></div>
            <div class="airpay-homepage__course-body">
                <h4>' . format_string($course->fullname) . '</h4>
                <p>' . $summary . '</p>
                <div class="airpay-homepage__course-meta">
                    <span><i class="fa fa-users"></i> ' . (int)$course->enrolcount . ' enrolled</span>
                </div>
                <a href="' . $url->out() . '" class="airpay-btn airpay-btn--outline airpay-btn--sm">View Details</a>
            </div>
        </div>';
    }

    echo '  </div>
    </section>';
}

// ═══ CTA SECTION ═══
echo '<section class="airpay-homepage__cta">
    <h2>Ready to Start Learning?</h2>
    <p>Join thousands of learners building skills for the digital economy.</p>
    <a href="' . $CFG->wwwroot . '/login/signup.php" class="airpay-btn airpay-btn--primary airpay-btn--lg">Get Started Free</a>
</section>';

echo '</div>'; // end .airpay-homepage

echo $OUTPUT->footer();
