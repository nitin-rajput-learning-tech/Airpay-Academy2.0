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
$PAGE->set_url('/local/sentientia_pages/homepage.php');
$PAGE->set_title('airpay academy — L&D Operating System');
$PAGE->set_heading('airpay academy');
$PAGE->set_pagelayout('standard');

// If already logged in, redirect to dashboard.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

echo $OUTPUT->header();

// Get live stats for the hero — PUBLIC TENANT ONLY (external-facing).
// Public tenant ID is configurable (default: 77). NO FALLBACK to all-tenant data.
$public_costcenter_id = \local_sentientia_org\tenant_manager::get_public_tenant_id();
$publicpath = '/' . $public_costcenter_id . '%';
$public_descendants = \local_sentientia_org\org_manager::get_descendants('/' . $public_costcenter_id);
$public_category_ids = array_map(function($o) { return (int)$o->id; }, $public_descendants);
$public_category_ids[] = $public_costcenter_id;

// Count Public tenant courses (via open_path OR costcenter category).
$coursecount = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {course} WHERE visible = 1 AND id > 1 AND open_path LIKE :p",
    ['p' => $publicpath]);
if ($coursecount == 0 && !empty($public_category_ids)) {
    // Fallback: count courses in Public tenant's costcenter categories.
    [$insql, $params] = $DB->get_in_or_equal($public_category_ids, SQL_PARAMS_NAMED, 'cat');
    $coursecount = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course} WHERE visible = 1 AND id > 1 AND open_categoryid $insql",
        $params);
}

// Count Public tenant learners only.
$usercount = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND suspended = 0 AND id > 1 AND open_path LIKE :p",
    ['p' => $publicpath]);

// Get featured courses — STRICTLY Public tenant only. No fallback.
$featured = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.summary, c.summaryformat, COUNT(ue.id) as enrolcount
       FROM {course} c
       JOIN {enrol} e ON e.courseid = c.id
       JOIN {user_enrolments} ue ON ue.enrolid = e.id
      WHERE c.visible = 1 AND c.id > 1 AND c.open_path LIKE :pubpath
   GROUP BY c.id, c.fullname, c.summary, c.summaryformat
   ORDER BY enrolcount DESC",
    ['pubpath' => $publicpath], 0, 6);

// If open_path not populated, try costcenter category match.
if (empty($featured) && !empty($public_category_ids)) {
    [$insql, $params] = $DB->get_in_or_equal($public_category_ids, SQL_PARAMS_NAMED, 'cat');
    $featured = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.summary, c.summaryformat, COUNT(ue.id) as enrolcount
           FROM {course} c
           JOIN {enrol} e ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
          WHERE c.visible = 1 AND c.id > 1 AND c.open_categoryid $insql
       GROUP BY c.id, c.fullname, c.summary, c.summaryformat
       ORDER BY enrolcount DESC",
        $params, 0, 6);
}

echo '<div class="airpay-homepage">';

// ═══ HERO SECTION ═══
echo '<section class="airpay-homepage__hero" style="background-image: url(' . $CFG->wwwroot . '/theme/sentientia/pix/brand/bannerimg.png); background-size: cover; background-position: center;">
    <div class="airpay-homepage__hero-content">
        <span class="airpay-homepage__hero-badge">airpay academy</span>
        <h1 class="airpay-homepage__hero-title">Build Skills That<br>Drive Your Career Forward</h1>
        <p class="airpay-homepage__hero-subtitle">A comprehensive learning platform for employability, business training, and financial education. Empowering individuals and organisations with industry-relevant skills.</p>
        <div class="airpay-homepage__hero-actions">
            <a href="' . $CFG->wwwroot . '/local/sentientia_catalog/public.php" class="airpay-btn airpay-btn--primary airpay-btn--lg">Explore Courses</a>
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
echo '<section class="airpay-homepage__trust ap-scroll-animate">
    <div class="airpay-homepage__trust-item"><i class="fa fa-shield"></i> RBI Compliant</div>
    <div class="airpay-homepage__trust-item"><i class="fa fa-lock"></i> DPDP 2023</div>
    <div class="airpay-homepage__trust-item"><i class="fa fa-certificate"></i> ISO Certified</div>
    <div class="airpay-homepage__trust-item"><i class="fa fa-headphones"></i> 24/7 Support</div>
</section>';

// ═══ LEARNING PILLARS ═══
echo '<section class="airpay-homepage__pillars ap-scroll-animate">
    <h2>Three Pillars of Learning</h2>
    <div class="airpay-homepage__pillars-grid">';

$pillars = [
    ['icon' => 'briefcase', 'title' => 'Employability Skills', 'desc' => 'Digital literacy, communication, aptitude, and professional development to prepare you for the modern workplace.', 'color' => '#0066A7'],
    ['icon' => 'line-chart', 'title' => 'Business Acumen', 'desc' => 'Business correspondence, sales fundamentals, leadership essentials, and customer service excellence.', 'color' => '#1985DD'],
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
    echo '<section class="airpay-homepage__courses ap-scroll-animate">
        <h2>Featured Courses</h2>
        <div class="airpay-homepage__courses-grid">';

    foreach ($featured as $course) {
        $summary = shorten_text(strip_tags(format_string($course->summary)), 100);
        $detailurl = new moodle_url('/local/sentientia_catalog/course.php', ['id' => $course->id]);
        $pricing = \local_sentientia_catalog\commerce::get_course_price($course->id);
        echo '<div class="airpay-homepage__course-card">
            <div class="airpay-homepage__course-header"></div>
            <div class="airpay-homepage__course-body">
                <h4>' . format_string($course->fullname) . '</h4>
                <p>' . $summary . '</p>
                <div class="airpay-homepage__course-meta">
                    <span><i class="fa fa-users"></i> ' . (int)$course->enrolcount . ' enrolled</span>
                    <span><i class="fa fa-clock-o"></i> Self-paced</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:10px; border-top:1px solid var(--ap-border,#e3eaf3);">
                    <span style="font-size:16px; font-weight:700; color:' . ($pricing['is_free'] ? '#16a34a' : '#0066A7') . ';">' . $pricing['display'] . '</span>
                    <div style="display:flex; gap:6px;">
                        <a href="' . $detailurl->out() . '" class="airpay-btn airpay-btn--outline airpay-btn--sm">Details</a>
                        <a href="' . $detailurl->out() . '?action=addtocart&sesskey=' . sesskey() . '" class="airpay-btn airpay-btn--primary airpay-btn--sm">' . ($pricing['is_free'] ? 'Enroll' : 'Add to Cart') . '</a>
                    </div>
                </div>
            </div>
        </div>';
    }

    echo '  </div>';

    // "View All Courses" button if more than 6 exist.
    echo '<div style="text-align:center; margin-top:20px;">
        <a href="' . (new moodle_url('/local/sentientia_catalog/public.php'))->out() . '"
           class="airpay-btn airpay-btn--outline airpay-btn--lg">
            View All Courses <i class="fa fa-arrow-right"></i>
        </a>
    </div>';

    echo '</section>';
}

// ═══ CTA SECTION ═══
echo '<section class="airpay-homepage__cta ap-scroll-animate">
    <h2>Ready to Start Learning?</h2>
    <p>Join thousands of learners building skills for the digital economy.</p>
    <a href="' . $CFG->wwwroot . '/login/signup.php" class="airpay-btn airpay-btn--primary airpay-btn--lg">Get Started Free</a>
</section>';

echo '</div>'; // end .airpay-homepage

// ═══ Scroll Animation + Stats Counter ═══
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Scroll-triggered fade-in for sections with .ap-scroll-animate
    if ("IntersectionObserver" in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("ap-scroll-animate--visible");
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        document.querySelectorAll(".ap-scroll-animate").forEach(function(el) {
            observer.observe(el);
        });
    } else {
        // Fallback: show all immediately.
        document.querySelectorAll(".ap-scroll-animate").forEach(function(el) {
            el.classList.add("ap-scroll-animate--visible");
        });
    }

    // Animated stat counters in hero.
    document.querySelectorAll(".airpay-homepage__hero-stat strong").forEach(function(el) {
        var text = el.textContent.trim();
        var match = text.match(/^(\d+)/);
        if (!match) return;
        var target = parseInt(match[1], 10);
        var suffix = text.replace(/^\d+/, "");
        var duration = 1500;
        var start = 0;
        var startTime = null;
        function animate(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = Math.round(eased * target) + suffix;
            if (progress < 1) requestAnimationFrame(animate);
        }
        requestAnimationFrame(animate);
    });
});
</script>';

echo $OUTPUT->footer();
