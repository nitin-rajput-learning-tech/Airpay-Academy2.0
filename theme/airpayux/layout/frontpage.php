<?php
/**
 * airpay academy — Front page layout.
 * Matches production homepage (airpay.academy) and C-suite prototype.
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

// Live stats.
$coursecount = $DB->count_records_select('course', 'visible = 1 AND id > 1');
$usercount = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');

// Featured courses (top 6 by enrolment).
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
    <title>airpay academy — A Comprehensive &amp; Hybrid Learning Platform</title>
    <?php echo $OUTPUT->standard_head_html(); ?>
    <link rel="manifest" href="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/manifest.json">
    <meta name="theme-color" content="#0066A7">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
    (function() {
        var t = localStorage.getItem('airpay-theme');
        if (t === 'dark') { document.documentElement.classList.add('dark-mode'); }
    })();
    </script>
    <style>
    /* Homepage-specific inline styles for prototype match */
    .ap-home * { font-family: 'Montserrat', sans-serif; box-sizing: border-box; }
    .ap-home { padding-top: 70px; }

    /* Navbar */
    .ap-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 999; background: #fff; padding: 0 32px; height: 70px; display: flex; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
    .ap-nav__logo img { height: 44px; }
    .ap-nav__links { display: flex; gap: 4px; margin-left: 24px; }
    .ap-nav__link { padding: 8px 16px; font-size: 0.9rem; font-weight: 600; color: #374151; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.2s; }
    .ap-nav__link:hover, .ap-nav__link--active { color: #0066A7; border-bottom-color: #0066A7; text-decoration: none; }
    .ap-nav__right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .ap-nav__icon { width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e2e6ef; display: flex; align-items: center; justify-content: center; color: #374151; background: #fff; cursor: pointer; transition: all 0.2s; text-decoration: none; }
    .ap-nav__icon:hover { background: #e8f2f9; border-color: #0066A7; color: #0066A7; }
    .ap-btn-login { padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; border: 2px solid #1a1a2e; color: #1a1a2e; background: #fff; text-decoration: none; transition: all 0.2s; }
    .ap-btn-login:hover { background: #f3f4f6; text-decoration: none; color: #1a1a2e; }
    .ap-btn-register { padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; border: 2px solid #0066A7; color: #fff; background: #0066A7; text-decoration: none; transition: all 0.2s; }
    .ap-btn-register:hover { background: #004d80; text-decoration: none; color: #fff; }

    /* Hero */
    .ap-hero { background: linear-gradient(135deg, #003d66 0%, #0a5c50 100%); padding: 80px 64px 60px; color: #fff; position: relative; overflow: hidden; }
    .ap-hero::before { content: ''; position: absolute; top: -40%; right: -15%; width: 500px; height: 500px; border-radius: 50%; background: rgba(255,255,255,0.03); }
    .ap-hero__badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: rgba(255,255,255,0.12); border-radius: 24px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px; backdrop-filter: blur(4px); }
    .ap-hero__badge i { font-size: 0.9rem; }
    .ap-hero__title { font-size: 3rem; font-weight: 800; line-height: 1.15; margin: 0 0 16px; max-width: 700px; }
    .ap-hero__subtitle { font-size: 1.05rem; opacity: 0.85; line-height: 1.6; margin: 0 0 32px; max-width: 600px; }
    .ap-hero__actions { display: flex; gap: 16px; margin-bottom: 48px; flex-wrap: wrap; }
    .ap-hero__btn-primary { padding: 14px 32px; border-radius: 30px; font-size: 0.95rem; font-weight: 700; background: #fff; color: #0066A7; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; }
    .ap-hero__btn-primary:hover { background: #e8f2f9; text-decoration: none; color: #004d80; }
    .ap-hero__btn-outline { padding: 14px 32px; border-radius: 30px; font-size: 0.95rem; font-weight: 600; background: transparent; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: 2px solid rgba(255,255,255,0.4); transition: all 0.2s; }
    .ap-hero__btn-outline:hover { background: rgba(255,255,255,0.1); text-decoration: none; color: #fff; }
    .ap-hero__stats { display: flex; gap: 0; }
    .ap-hero__stat { display: flex; align-items: center; gap: 10px; padding: 0 24px; border-right: 1px solid rgba(255,255,255,0.2); }
    .ap-hero__stat:last-child { border-right: none; }
    .ap-hero__stat:first-child { padding-left: 0; }
    .ap-hero__stat i { font-size: 1.2rem; opacity: 0.6; }
    .ap-hero__stat strong { font-size: 1.1rem; font-weight: 700; }
    .ap-hero__stat span { font-size: 0.85rem; opacity: 0.7; margin-left: 4px; }

    /* Trust bar */
    .ap-trust { display: flex; justify-content: center; gap: 40px; padding: 18px 32px; background: #fff; border-bottom: 1px solid #e2e6ef; flex-wrap: wrap; }
    .ap-trust__item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: #374151; }
    .ap-trust__item i { color: #0066A7; }

    /* Featured Courses */
    .ap-courses { padding: 60px 32px; background: #F2F4FB; }
    .ap-courses__header { text-align: center; margin-bottom: 36px; }
    .ap-courses__header h2 { font-size: 2rem; font-weight: 800; color: #1a1a2e; margin: 0 0 8px; }
    .ap-courses__header p { font-size: 0.95rem; color: #5a6070; }
    .ap-courses__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1200px; margin: 0 auto; }
    .ap-course { background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e6ef; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
    .ap-course:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,51,102,0.1); }
    .ap-course__bar { height: 6px; }
    .ap-course:nth-child(1) .ap-course__bar, .ap-course:nth-child(4) .ap-course__bar { background: linear-gradient(90deg, #0066A7, #0f7a73); }
    .ap-course:nth-child(2) .ap-course__bar, .ap-course:nth-child(5) .ap-course__bar { background: linear-gradient(90deg, #0f7a73, #059669); }
    .ap-course:nth-child(3) .ap-course__bar, .ap-course:nth-child(6) .ap-course__bar { background: linear-gradient(90deg, #0066A7, #1e40af); }
    .ap-course__body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
    .ap-course__badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: #e8f2f9; color: #0066A7; margin-bottom: 10px; width: fit-content; }
    .ap-course__title { font-size: 1.05rem; font-weight: 700; color: #1a1a2e; margin: 0 0 8px; }
    .ap-course__desc { font-size: 0.85rem; color: #5a6070; line-height: 1.5; margin: 0 0 16px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .ap-course__footer { margin-top: auto; padding-top: 14px; border-top: 1px solid #f3f4f6; display: flex; align-items: center; gap: 12px; }
    .ap-course__price { font-size: 1.1rem; font-weight: 800; color: #0f7a73; }
    .ap-course__btn { padding: 7px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: all 0.2s; }
    .ap-course__btn--outline { border: 1.5px solid #0066A7; color: #0066A7; background: #fff; }
    .ap-course__btn--outline:hover { background: #e8f2f9; text-decoration: none; }
    .ap-course__btn--fill { border: 1.5px solid #0066A7; color: #fff; background: #0066A7; }
    .ap-course__btn--fill:hover { background: #004d80; text-decoration: none; color: #fff; }

    /* Pillars */
    .ap-pillars { padding: 60px 32px; text-align: center; max-width: 1200px; margin: 0 auto; }
    .ap-pillars h2 { font-size: 2rem; font-weight: 800; margin-bottom: 32px; color: #1a1a2e; }
    .ap-pillars__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .ap-pillar { background: #fff; border-radius: 12px; padding: 32px 24px; text-align: left; border: 1px solid #e2e6ef; transition: transform 0.2s, box-shadow 0.2s; }
    .ap-pillar:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,51,102,0.08); }
    .ap-pillar__icon { font-size: 1.8rem; margin-bottom: 12px; }
    .ap-pillar h3 { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin: 0 0 8px; }
    .ap-pillar p { font-size: 0.9rem; color: #5a6070; line-height: 1.5; }

    /* FAQ */
    .ap-faq { padding: 60px 32px; background: #F2F4FB; }
    .ap-faq__header { text-align: center; margin-bottom: 32px; }
    .ap-faq__header h2 { font-size: 2rem; font-weight: 800; color: #1a1a2e; margin: 0 0 8px; }
    .ap-faq__header p { font-size: 0.95rem; color: #5a6070; }
    .ap-faq__list { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .ap-faq__item { background: #fff; border-radius: 12px; border: 1px solid #e2e6ef; overflow: hidden; }
    .ap-faq__q { padding: 18px 24px; font-size: 1rem; font-weight: 600; color: #1a1a2e; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
    .ap-faq__q i { color: #5a6070; transition: transform 0.2s; }
    .ap-faq__a { padding: 0 24px 18px; font-size: 0.9rem; color: #5a6070; line-height: 1.6; display: none; }
    .ap-faq__item.open .ap-faq__a { display: block; }
    .ap-faq__item.open .ap-faq__q i { transform: rotate(180deg); }

    /* CTA */
    .ap-cta { padding: 60px 32px; text-align: center; background: linear-gradient(135deg, #0066A7, #0f7a73); color: #fff; }
    .ap-cta h2 { font-size: 1.75rem; font-weight: 700; margin: 0 0 12px; }
    .ap-cta p { font-size: 1rem; opacity: 0.9; margin: 0 0 24px; }

    /* Footer */
    .ap-footer { background: #1a1d27; color: #9ca3b4; padding: 40px 32px 0; }
    .ap-footer__grid { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; max-width: 1200px; margin: 0 auto; padding-bottom: 24px; border-bottom: 1px solid #2d3140; }
    .ap-footer__brand img { height: 36px; margin-bottom: 8px; }
    .ap-footer__brand p { font-size: 0.85rem; line-height: 1.6; }
    .ap-footer__col h4 { color: #fff; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 14px; }
    .ap-footer__col ul { list-style: none; padding: 0; margin: 0; }
    .ap-footer__col li { margin-bottom: 8px; }
    .ap-footer__col a { color: #9ca3b4; font-size: 0.85rem; text-decoration: none; }
    .ap-footer__col a:hover { color: #fff; }
    .ap-footer__bottom { display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; max-width: 1200px; margin: 0 auto; font-size: 0.8rem; color: #6b7280; flex-wrap: wrap; gap: 8px; }
    .ap-footer__india { display: flex; justify-content: flex-end; padding: 8px 32px 16px; max-width: 1200px; margin: 0 auto; }

    /* Responsive */
    @media (max-width: 768px) {
        .ap-nav__links { display: none; }
        .ap-hero { padding: 48px 20px 40px; }
        .ap-hero__title { font-size: 1.75rem; }
        .ap-hero__stats { flex-wrap: wrap; gap: 12px; }
        .ap-hero__stat { border-right: none; padding: 0; }
        .ap-courses__grid { grid-template-columns: 1fr; }
        .ap-pillars__grid { grid-template-columns: 1fr; }
        .ap-footer__grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div style="display:none;"><?php echo $OUTPUT->main_content(); ?></div>

<!-- NAVBAR -->
<nav class="ap-nav">
    <a href="<?php echo $CFG->wwwroot; ?>/" class="ap-nav__logo">
        <img src="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/academy-logo-350.png" alt="airpay academy">
    </a>
    <div class="ap-nav__links">
        <a href="<?php echo $CFG->wwwroot; ?>/" class="ap-nav__link ap-nav__link--active">Home</a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php" class="ap-nav__link">Courses</a>
        <a href="#pillars" class="ap-nav__link">About</a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=contact" class="ap-nav__link">Contact</a>
    </div>
    <div class="ap-nav__right">
        <a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php" class="ap-nav__icon" title="Cart"><i class="fa fa-shopping-cart"></i></a>
        <button class="ap-nav__icon" onclick="var d=document.body.classList.toggle('dark-mode');document.documentElement.classList.toggle('dark-mode');localStorage.setItem('airpay-theme',d?'dark':'light');this.querySelector('i').className=d?'fa fa-sun-o':'fa fa-moon-o';" title="Toggle dark mode"><i class="fa fa-moon-o"></i></button>
        <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="ap-btn-login">Login</a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/users/signup.php" class="ap-btn-register">Register</a>
    </div>
</nav>

<div class="ap-home">

<!-- HERO -->
<section class="ap-hero">
    <div class="ap-hero__badge"><i class="fa fa-graduation-cap"></i> AIRPAY ACADEMY</div>
    <h1 class="ap-hero__title">A comprehensive &amp; hybrid<br>learning platform</h1>
    <p class="ap-hero__subtitle">An extensive training and development programme designed to improve your abilities in the financial services sector</p>
    <div class="ap-hero__actions">
        <a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php" class="ap-hero__btn-primary">Explore Courses <i class="fa fa-arrow-right"></i></a>
        <a href="#pillars" class="ap-hero__btn-outline"><i class="fa fa-info-circle"></i> Learn More</a>
    </div>
    <div class="ap-hero__stats">
        <div class="ap-hero__stat"><i class="fa fa-book"></i> <strong><?php echo $coursecount; ?>+</strong> <span>Courses</span></div>
        <div class="ap-hero__stat"><i class="fa fa-users"></i> <strong><?php echo $usercount; ?>+</strong> <span>Learners</span></div>
    </div>
</section>

<!-- TRUST BAR -->
<section class="ap-trust">
    <div class="ap-trust__item"><i class="fa fa-shield"></i> RBI Compliant</div>
    <div class="ap-trust__item"><i class="fa fa-lock"></i> DPDP 2023</div>
    <div class="ap-trust__item"><i class="fa fa-certificate"></i> ISO Certified</div>
    <div class="ap-trust__item"><i class="fa fa-headphones"></i> 24/7 Support</div>
</section>

<!-- FEATURED COURSES -->
<?php if (!empty($featured)): ?>
<section class="ap-courses">
    <div class="ap-courses__header">
        <h2>Featured Courses</h2>
        <p>Expert-led programs designed for real-world impact in the financial services sector</p>
    </div>
    <div class="ap-courses__grid">
        <?php foreach ($featured as $course):
            $summary = shorten_text(strip_tags(format_string($course->summary)), 120);
            $url = new moodle_url('/local/search/coursedetails.php', ['id' => $course->id]);
            $hasenddate = !empty($course->enddate) && $course->enddate > 0;
        ?>
        <div class="ap-course">
            <div class="ap-course__bar"></div>
            <div class="ap-course__body">
                <span class="ap-course__badge">E-Learning</span>
                <h4 class="ap-course__title"><?php echo format_string($course->fullname); ?></h4>
                <p class="ap-course__desc"><?php echo $summary; ?></p>
                <div class="ap-course__footer">
                    <span class="ap-course__price">Free</span>
                    <a href="<?php echo $url->out(); ?>" class="ap-course__btn ap-course__btn--outline">View Details</a>
                    <a href="<?php echo $url->out(); ?>" class="ap-course__btn ap-course__btn--fill">Add to Cart</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- LEARNING PILLARS -->
<section id="pillars" class="ap-pillars">
    <h2>Three Pillars of Learning</h2>
    <div class="ap-pillars__grid">
        <div class="ap-pillar" style="border-top: 3px solid #0066A7">
            <div class="ap-pillar__icon" style="color: #0066A7"><i class="fa fa-briefcase"></i></div>
            <h3>Employability Skills</h3>
            <p>Digital literacy, communication, aptitude, and professional development to prepare you for the modern workplace.</p>
        </div>
        <div class="ap-pillar" style="border-top: 3px solid #0f7a73">
            <div class="ap-pillar__icon" style="color: #0f7a73"><i class="fa fa-line-chart"></i></div>
            <h3>Business Acumen</h3>
            <p>Business correspondence, sales fundamentals, leadership essentials, and customer service excellence.</p>
        </div>
        <div class="ap-pillar" style="border-top: 3px solid #7c3aed">
            <div class="ap-pillar__icon" style="color: #7c3aed"><i class="fa fa-university"></i></div>
            <h3>Financial Education</h3>
            <p>Digital payments, anti-money laundering, POSH compliance, and financial wellness for the fintech industry.</p>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="ap-faq">
    <div class="ap-faq__header">
        <h2>Frequently Asked Questions</h2>
        <p>Everything you need to know about airpay academy</p>
    </div>
    <div class="ap-faq__list">
        <div class="ap-faq__item">
            <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">What is airpay academy? <i class="fa fa-chevron-down"></i></div>
            <div class="ap-faq__a">airpay academy is a comprehensive learning management system built by Airpay Payment Services for employability, business training, and financial education. It serves 3,500+ learners across internal employees, field staff, and external partners.</div>
        </div>
        <div class="ap-faq__item">
            <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">How do I enroll in a course? <i class="fa fa-chevron-down"></i></div>
            <div class="ap-faq__a">Click "Register" to create an account, then browse the course catalog. Click "Add to Cart" on any course and complete the enrollment. Many courses are free for Airpay employees.</div>
        </div>
        <div class="ap-faq__item">
            <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">Are certificates provided upon completion? <i class="fa fa-chevron-down"></i></div>
            <div class="ap-faq__a">Yes, digital certificates are automatically issued upon course completion. You can download them from your profile and share on LinkedIn.</div>
        </div>
        <div class="ap-faq__item">
            <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">What payment methods are accepted? <i class="fa fa-chevron-down"></i></div>
            <div class="ap-faq__a">airpay academy accepts payments through the Airpay payment gateway, supporting UPI, credit/debit cards, net banking, and wallets. Most internal training courses are free.</div>
        </div>
        <div class="ap-faq__item">
            <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">Can my organization get bulk access? <i class="fa fa-chevron-down"></i></div>
            <div class="ap-faq__a">Yes, airpay academy supports multi-tenant deployment. Organizations can get their own branded tenant with custom courses, user management, and reporting. Contact academy@airpay.co.in for enterprise plans.</div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="ap-cta">
    <h2>Unlock Success by Boosting Your Skills</h2>
    <p>Join airpay academy and access industry-relevant courses in financial services, business, and technology.</p>
    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="<?php echo $CFG->wwwroot; ?>/local/users/signup.php" class="ap-hero__btn-primary">Get Started Free</a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/search/allcourses.php" class="ap-hero__btn-outline">Browse Courses</a>
    </div>
</section>

</div>

<!-- FOOTER -->
<footer class="ap-footer">
    <div class="ap-footer__grid">
        <div class="ap-footer__brand">
            <img src="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/academy-logo-350.png" alt="airpay academy" style="filter: brightness(10);">
            <p>airpay academy is a comprehensive learning platform built for employability, business training, and financial education.</p>
        </div>
        <div class="ap-footer__col">
            <h4>Support</h4>
            <ul>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=help">Help Center</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=contact">Contact Us</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=privacy">Privacy Policy</a></li>
                <li><a href="<?php echo $CFG->wwwroot; ?>/local/airpay_pages/index.php?page=terms">Terms of Service</a></li>
            </ul>
        </div>
    </div>
    <div class="ap-footer__bottom">
        <span>&copy; <?php echo date('Y'); ?> airpay payment services. All rights reserved.</span>
        <span>Contact: academy@airpay.co.in</span>
    </div>
    <div class="ap-footer__india">
        <img src="<?php echo $CFG->wwwroot; ?>/theme/airpayux/pix/brand/made-in-india.jpg" alt="Made in India" style="height: 32px; opacity: 0.7;">
    </div>
    <?php echo $OUTPUT->standard_end_of_body_html(); ?>
</footer>
</body>
</html>
