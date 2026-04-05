<?php
/**
 * Airpay Academy — Front page layout.
 * Renders the custom homepage for non-logged-in users.
 * Logged-in users are redirected to their dashboard.
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 */

defined('MOODLE_INTERNAL') || die();

// Logged-in users go to dashboard.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

global $DB, $CFG, $OUTPUT, $PAGE;

// Get live stats.
$coursecount = $DB->count_records_select('course', 'visible = 1 AND id > 1');
$usercount = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');

// Get featured courses (most enrolled).
$featured = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.summary, c.summaryformat, COUNT(ue.id) as enrolcount
       FROM {course} c
       JOIN {enrol} e ON e.courseid = c.id
       JOIN {user_enrolments} ue ON ue.enrolid = e.id
      WHERE c.visible = 1 AND c.id > 1
   GROUP BY c.id, c.fullname, c.summary, c.summaryformat
   ORDER BY enrolcount DESC",
    [], 0, 6);

echo $OUTPUT->doctype();
echo $OUTPUT->htmlattributes();
?>
<head>
    <title>Airpay Academy — L&D Operating System</title>
    <?php echo $OUTPUT->standard_head_html(); ?>
    <link rel="manifest" href="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/manifest.json">
    <meta name="theme-color" content="#0066A7">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
    (function() {
        var theme = localStorage.getItem('airpay-theme');
        if (theme === 'dark') { document.documentElement.classList.add('dark-mode'); }
    })();
    </script>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<nav class="navbar fixed-top navbar-light navbar-expand airpay-nav">
    <a href="<?php echo $CFG->wwwroot; ?>/" class="navbar-brand d-flex align-items-center m-0 mr-3 p-0">
        <img src="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/academy-logo-350.png" class="logo mr-1" alt="Airpay Academy" style="height: 40px;">
    </a>
    <div class="airpay-nav__pills d-none d-md-flex">
        <a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php" class="airpay-nav__pill">Courses</a>
    </div>
    <div class="navbar-nav ml-auto airpay-nav__actions">
        <button class="airpay-nav__theme-toggle" onclick="var d=document.body.classList.toggle('dark-mode');document.documentElement.classList.toggle('dark-mode');localStorage.setItem('airpay-theme',d?'dark':'light');this.querySelector('i').className=d?'fa fa-sun-o':'fa fa-moon-o';" title="Toggle dark mode">
            <i class="fa fa-moon-o"></i>
        </button>
        <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="airpay-btn airpay-btn--outline airpay-btn--sm" style="margin-left: 8px;">Log In</a>
    </div>
</nav>

<div style="display:none;"><?php echo $OUTPUT->main_content(); ?></div>
<div class="airpay-homepage" style="padding-top: 64px;">

    <!-- HERO -->
    <section class="airpay-homepage__hero" style="background-image: url('<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/bannerimg.png'); background-size: cover; background-position: center;">
        <div class="airpay-homepage__hero-content">
            <span class="airpay-homepage__hero-badge">Airpay Academy</span>
            <h1 class="airpay-homepage__hero-title">Build Skills That<br>Drive Your Career Forward</h1>
            <p class="airpay-homepage__hero-subtitle">A comprehensive learning platform for employability, business training, and financial education.</p>
            <div class="airpay-homepage__hero-actions">
                <a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php" class="airpay-btn airpay-btn--primary airpay-btn--lg">Explore Courses</a>
                <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="airpay-btn airpay-btn--outline airpay-btn--lg" style="border-color: #fff; color: #fff;">Sign In</a>
            </div>
            <div class="airpay-homepage__hero-stats">
                <div class="airpay-homepage__hero-stat"><strong><?php echo $coursecount; ?>+</strong><span>Courses</span></div>
                <div class="airpay-homepage__hero-stat"><strong><?php echo $usercount; ?>+</strong><span>Learners</span></div>
                <div class="airpay-homepage__hero-stat"><strong>3</strong><span>Organisations</span></div>
            </div>
        </div>
    </section>

    <!-- TRUST BAR -->
    <section class="airpay-homepage__trust">
        <div class="airpay-homepage__trust-item"><i class="fa fa-shield"></i> RBI Compliant</div>
        <div class="airpay-homepage__trust-item"><i class="fa fa-lock"></i> DPDP 2023</div>
        <div class="airpay-homepage__trust-item"><i class="fa fa-certificate"></i> ISO Certified</div>
        <div class="airpay-homepage__trust-item"><i class="fa fa-headphones"></i> 24/7 Support</div>
    </section>

    <!-- LEARNING PILLARS -->
    <section class="airpay-homepage__pillars">
        <h2>Three Pillars of Learning</h2>
        <div class="airpay-homepage__pillars-grid">
            <div class="airpay-homepage__pillar" style="border-top: 3px solid #0066A7">
                <div class="airpay-homepage__pillar-icon" style="color: #0066A7"><i class="fa fa-briefcase"></i></div>
                <h3>Employability Skills</h3>
                <p>Digital literacy, communication, aptitude, and professional development to prepare you for the modern workplace.</p>
            </div>
            <div class="airpay-homepage__pillar" style="border-top: 3px solid #0f7a73">
                <div class="airpay-homepage__pillar-icon" style="color: #0f7a73"><i class="fa fa-line-chart"></i></div>
                <h3>Business Acumen</h3>
                <p>Business correspondence, sales fundamentals, leadership essentials, and customer service excellence.</p>
            </div>
            <div class="airpay-homepage__pillar" style="border-top: 3px solid #7c3aed">
                <div class="airpay-homepage__pillar-icon" style="color: #7c3aed"><i class="fa fa-university"></i></div>
                <h3>Financial Education</h3>
                <p>Digital payments, anti-money laundering, POSH compliance, and financial wellness for the fintech industry.</p>
            </div>
        </div>
    </section>

    <!-- FEATURED COURSES -->
    <?php if (!empty($featured)): ?>
    <section class="airpay-homepage__courses">
        <h2>Featured Courses</h2>
        <div class="airpay-homepage__courses-grid">
            <?php $i = 0; foreach ($featured as $course): $i++;
                $summary = shorten_text(strip_tags(format_string($course->summary)), 100);
                $url = new moodle_url('/local/search/coursedetails.php', ['id' => $course->id]);
            ?>
            <div class="airpay-homepage__course-card">
                <div class="airpay-homepage__course-header"></div>
                <div class="airpay-homepage__course-body">
                    <h4><?php echo format_string($course->fullname); ?></h4>
                    <p><?php echo $summary; ?></p>
                    <div class="airpay-homepage__course-meta">
                        <span><i class="fa fa-users"></i> <?php echo (int)$course->enrolcount; ?> enrolled</span>
                    </div>
                    <a href="<?php echo $url->out(); ?>" class="airpay-btn airpay-btn--outline airpay-btn--sm">View Details</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA -->
    <section class="airpay-homepage__cta">
        <h2>Ready to Start Learning?</h2>
        <p>Join thousands of learners building skills for the digital economy.</p>
        <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="airpay-btn airpay-btn--primary airpay-btn--lg">Get Started Free</a>
    </section>

</div>

<!-- FOOTER -->
<footer id="page-footer" class="airpay-footer">
    <div class="airpay-footer__grid">
        <div class="airpay-footer__col">
            <img src="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/academy-logo-350.png" alt="Airpay Academy" style="height: 40px; margin-bottom: 8px;">
            <p class="airpay-footer__brand-desc">Airpay Academy is a comprehensive learning platform built for employability, business training, and financial education.</p>
        </div>
        <div class="airpay-footer__col">
            <h4 class="airpay-footer__heading">Learn</h4>
            <ul class="airpay-footer__links">
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php">All Courses</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php">E-Learning</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/classroom/index.php">Classroom</a></li>
            </ul>
        </div>
        <div class="airpay-footer__col">
            <h4 class="airpay-footer__heading">Support</h4>
            <ul class="airpay-footer__links">
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=help">Help Center</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=contact">Contact Us</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=privacy">Privacy Policy</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=terms">Terms of Service</a></li>
            </ul>
        </div>
    </div>
    <div class="airpay-footer__bottom">
        <span>&copy; <?php echo date('Y'); ?> Airpay Payment Services. All rights reserved.</span>
        <span>Contact: academy@airpay.co.in</span>
    </div>
    <div style="display: flex; justify-content: flex-end; padding: 12px 32px 16px; max-width: 1200px; margin: 0 auto;">
        <img src="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/made-in-india.jpg" alt="Made in India" style="height: 32px; opacity: 0.7;">
    </div>
    <?php echo $OUTPUT->standard_end_of_body_html(); ?>
</footer>
</body>
</html>
<?php
