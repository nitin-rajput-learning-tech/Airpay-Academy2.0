<?php
/**
 * airpay academy — Enterprise front page layout.
 *
 * Self-contained premium landing page with 10 sections:
 * Navbar → Hero → Trust → Capabilities → Courses → Pillars → Testimonials → FAQ → CTA → Footer
 *
 * Live data from DB: course count, learner count, featured courses.
 * Scroll fade-in animations via IntersectionObserver.
 * Full dark mode support via .dark-mode class.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 */

defined('MOODLE_INTERNAL') || die();

// Logged-in users go to dashboard.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

global $DB, $CFG, $OUTPUT, $PAGE;

// ── Live stats — PUBLIC TENANT ONLY ─────────────────────────
// Guest-facing page must ONLY show Public tenant data. No all-tenant fallback.
$public_id = \local_sentientia_org\tenant_manager::get_public_tenant_id();
$pubpath = '/' . $public_id . '%';

// SENTIENTIA: open_path is a BizLMS-injected tenant column, ABSENT on a vanilla /
// non-Airpay deployment. Detect it once; fall back to site-wide counts when missing
// so the guest landing page renders on ANY Sentientia install. (Front-page 500 fix.)
$dbman = $DB->get_manager();
$hastenant = $dbman->field_exists('user', 'open_path') && $dbman->field_exists('course', 'open_path');
$tenantc = $hastenant ? 'AND c.open_path LIKE :p' : '';
$tenantp = $hastenant ? ['p' => $pubpath] : [];

if ($hastenant) {
    $coursecount = (int)$DB->count_records_sql(
        "SELECT COUNT(*) FROM {course} WHERE visible = 1 AND id > 1 AND open_path LIKE :p",
        ['p' => $pubpath]);
    $usercount  = (int)$DB->count_records_sql(
        "SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND suspended = 0 AND id > 1 AND open_path LIKE :p",
        ['p' => $pubpath]);
} else {
    $coursecount = (int)$DB->count_records_select('course', 'visible = 1 AND id > 1');
    $usercount   = (int)$DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');
}
try {
    $completioncount = (int)$DB->count_records_sql(
        "SELECT COUNT(cc.id) FROM {course_completions} cc
           JOIN {course} c ON c.id = cc.course
          WHERE cc.timecompleted > 0 {$tenantc}",
        $tenantp);
} catch (\Throwable $e) {
    $completioncount = 0;
}
$completionrate = ($usercount > 0 && $completioncount > 0) ? min(99, round(($completioncount / max(1, $usercount)) * 100)) : 96;

// ── Featured courses (top 6 by enrolment) — PUBLIC TENANT ONLY
try {
    $featured = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.summary, c.summaryformat, COUNT(ue.id) AS enrolcount
           FROM {course} c
           JOIN {enrol} e ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
          WHERE c.visible = 1 AND c.id > 1 {$tenantc}
       GROUP BY c.id, c.fullname, c.summary, c.summaryformat
       ORDER BY enrolcount DESC",
        $tenantp, 0, 6);
} catch (\Throwable $e) {
    $featured = [];
}

// ── Course category badges ──────────────────────────────────
$categorybadges = ['Compliance', 'Finance', 'Leadership', 'Technology', 'Business', 'Digital'];
$gradients = [
    'linear-gradient(135deg, #0066A7, #0f7a73)',
    'linear-gradient(135deg, #0f7a73, #059669)',
    'linear-gradient(135deg, #1e40af, #0066A7)',
    'linear-gradient(135deg, #7c3aed, #4f46e5)',
    'linear-gradient(135deg, #d97706, #ea580c)',
    'linear-gradient(135deg, #0066A7, #1e40af)',
];

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title>airpay academy — Enterprise Learning &amp; Development Platform</title>
    <?php echo $OUTPUT->standard_head_html(); ?>
    <link rel="manifest" href="<?php echo $CFG->wwwroot; ?>/theme/sentientia/pix/brand/manifest.json">
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
    /* ================================================================
       AIRPAY ACADEMY — ENTERPRISE HOMEPAGE
       Self-contained inline styles. 10 sections. Full dark mode.
       ================================================================ */

    :root {
        --ap-primary: #0066A7;
        --ap-primary-dark: #004d80;
        --ap-primary-light: #e8f4fd;
        --ap-accent: #0f7a73;
        --ap-accent-light: #e5f4f3;
        --ap-bg: #F2F4FB;
        --ap-surface: #ffffff;
        --ap-surface-alt: #f8fafc;
        --ap-border: #e2e8f0;
        --ap-text: #0f172a;
        --ap-text-secondary: #475569;
        --ap-text-muted: #94a3b8;
        --ap-hero-start: #003052;
        --ap-hero-end: #0a4a42;
        --ap-footer-bg: #0f172a;
        --ap-footer-text: #94a3b8;
        --ap-card-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
        --ap-card-shadow-hover: 0 20px 40px rgba(0,0,0,0.08);
        --ap-nav-height: 72px;
    }

    /* ── Dark mode vars ─────────────────────────────── */
    .dark-mode {
        --ap-bg: #0f172a;
        --ap-surface: #1e293b;
        --ap-surface-alt: #1a2332;
        --ap-border: #334155;
        --ap-text: #f1f5f9;
        --ap-text-secondary: #cbd5e1;
        --ap-text-muted: #64748b;
        --ap-primary-light: #172554;
        --ap-accent-light: #134e4a;
        --ap-card-shadow: 0 1px 3px rgba(0,0,0,0.3);
        --ap-card-shadow-hover: 0 20px 40px rgba(0,0,0,0.4);
    }

    /* ── Base ────────────────────────────────────────── */
    .ap-home *, .ap-home *::before, .ap-home *::after { box-sizing: border-box; }
    .ap-home { font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--ap-text); background: var(--ap-bg); padding-top: var(--ap-nav-height); -webkit-font-smoothing: antialiased; }
    .ap-home img { max-width: 100%; }
    .ap-home a { text-decoration: none; }
    .ap-section-wrap { max-width: 1200px; margin: 0 auto; padding: 0 32px; }

    /* ── Scroll fade-in ─────────────────────────────── */
    .ap-reveal { opacity: 0; transform: translateY(30px); transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
    .ap-reveal.ap-visible { opacity: 1; transform: translateY(0); }

    /* ================================================================
       1. NAVBAR
       ================================================================ */
    .ap-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 999; background: var(--ap-surface); height: var(--ap-nav-height); display: flex; align-items: center; padding: 0 40px; border-bottom: 1px solid var(--ap-border); transition: box-shadow 0.3s, background 0.3s; }
    .ap-nav.ap-nav--scrolled { box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
    .dark-mode .ap-nav.ap-nav--scrolled { box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .ap-nav__logo img { height: 42px; }
    .ap-nav__links { display: flex; gap: 4px; margin-left: 32px; }
    .ap-nav__link { padding: 8px 18px; font-size: 0.875rem; font-weight: 600; color: var(--ap-text-secondary); border-radius: 8px; transition: all 0.2s; }
    .ap-nav__link:hover { color: var(--ap-primary); background: var(--ap-primary-light); }
    .ap-nav__link--active { color: var(--ap-primary); background: var(--ap-primary-light); }
    .ap-nav__right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .ap-nav__icon { width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--ap-border); display: flex; align-items: center; justify-content: center; color: var(--ap-text-secondary); background: var(--ap-surface); cursor: pointer; transition: all 0.2s; font-size: 0.95rem; }
    .ap-nav__icon:hover { background: var(--ap-primary-light); border-color: var(--ap-primary); color: var(--ap-primary); }
    .ap-btn-login { padding: 9px 22px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; border: 2px solid var(--ap-border); color: var(--ap-text); background: var(--ap-surface); transition: all 0.2s; display: inline-flex; align-items: center; }
    .ap-btn-login:hover { border-color: var(--ap-primary); color: var(--ap-primary); }
    .ap-btn-register { padding: 9px 22px; border-radius: 10px; font-size: 0.85rem; font-weight: 700; border: 2px solid var(--ap-primary); color: #fff; background: var(--ap-primary); transition: all 0.2s; display: inline-flex; align-items: center; }
    .ap-btn-register:hover { background: var(--ap-primary-dark); border-color: var(--ap-primary-dark); color: #fff; }

    /* ================================================================
       2. HERO
       ================================================================ */
    .ap-hero { background: linear-gradient(135deg, var(--ap-hero-start) 0%, var(--ap-hero-end) 100%); padding: 88px 0 72px; color: #fff; position: relative; overflow: hidden; }
    .ap-hero::before { content: ''; position: absolute; top: -50%; right: -20%; width: 700px; height: 700px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%); pointer-events: none; }
    .ap-hero::after { content: ''; position: absolute; bottom: -30%; left: -10%; width: 500px; height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(15,122,115,0.15) 0%, transparent 70%); pointer-events: none; }
    .ap-hero__inner { max-width: 1200px; margin: 0 auto; padding: 0 40px; position: relative; z-index: 1; }
    .ap-hero__badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 24px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 28px; backdrop-filter: blur(8px); }
    .ap-hero__title { font-size: 3.25rem; font-weight: 800; line-height: 1.12; margin: 0 0 20px; max-width: 680px; letter-spacing: -0.02em; }
    .ap-hero__subtitle { font-size: 1.1rem; opacity: 0.8; line-height: 1.7; margin: 0 0 36px; max-width: 560px; font-weight: 400; }
    .ap-hero__actions { display: flex; gap: 14px; margin-bottom: 56px; flex-wrap: wrap; }
    .ap-hero__btn-primary { padding: 15px 36px; border-radius: 50px; font-size: 0.95rem; font-weight: 700; background: #fff; color: var(--ap-primary); display: inline-flex; align-items: center; gap: 10px; transition: all 0.25s; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
    .ap-hero__btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); color: var(--ap-primary-dark); }
    .ap-hero__btn-outline { padding: 15px 36px; border-radius: 50px; font-size: 0.95rem; font-weight: 600; background: transparent; color: #fff; display: inline-flex; align-items: center; gap: 10px; border: 2px solid rgba(255,255,255,0.3); transition: all 0.25s; }
    .ap-hero__btn-outline:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.5); color: #fff; }
    .ap-hero__stats { display: flex; align-items: center; }
    .ap-hero__stat { display: flex; align-items: center; gap: 12px; padding: 0 28px; }
    .ap-hero__stat:first-child { padding-left: 0; }
    .ap-hero__stat-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.2); }
    .ap-hero__stat-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .ap-hero__stat-text strong { display: block; font-size: 1.4rem; font-weight: 800; line-height: 1; }
    .ap-hero__stat-text span { font-size: 0.8rem; opacity: 0.65; font-weight: 500; }

    /* ================================================================
       3. TRUST BAR
       ================================================================ */
    .ap-trust { background: var(--ap-surface); border-bottom: 1px solid var(--ap-border); padding: 20px 0; }
    .ap-trust__inner { max-width: 1200px; margin: 0 auto; padding: 0 40px; display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; }
    .ap-trust__item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 600; color: var(--ap-text-secondary); }
    .ap-trust__item i { font-size: 1.1rem; color: var(--ap-primary); }

    /* ================================================================
       4. WHY AIRPAY ACADEMY
       ================================================================ */
    .ap-why { padding: 88px 0; background: var(--ap-surface); }
    .ap-why__header { text-align: center; margin-bottom: 56px; }
    .ap-why__label { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--ap-primary); background: var(--ap-primary-light); margin-bottom: 16px; }
    .ap-why__header h2 { font-size: 2.25rem; font-weight: 800; color: var(--ap-text); margin: 0 0 12px; letter-spacing: -0.01em; }
    .ap-why__header p { font-size: 1.05rem; color: var(--ap-text-secondary); max-width: 600px; margin: 0 auto; line-height: 1.6; }
    .ap-why__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
    .ap-why__card { background: var(--ap-surface-alt); border: 1px solid var(--ap-border); border-radius: 16px; padding: 32px 24px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); position: relative; overflow: hidden; }
    .ap-why__card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--ap-primary); opacity: 0; transition: opacity 0.3s; }
    .ap-why__card:hover { transform: translateY(-4px); box-shadow: var(--ap-card-shadow-hover); }
    .ap-why__card:hover::before { opacity: 1; }
    .ap-why__card-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 20px; }
    .ap-why__card h3 { font-size: 1.05rem; font-weight: 700; color: var(--ap-text); margin: 0 0 10px; }
    .ap-why__card p { font-size: 0.875rem; color: var(--ap-text-secondary); line-height: 1.6; margin: 0; }

    /* ================================================================
       5. FEATURED COURSES
       ================================================================ */
    .ap-courses { padding: 88px 0; background: var(--ap-bg); }
    .ap-courses__header { text-align: center; margin-bottom: 48px; }
    .ap-courses__label { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--ap-accent); background: var(--ap-accent-light); margin-bottom: 16px; }
    .ap-courses__header h2 { font-size: 2.25rem; font-weight: 800; color: var(--ap-text); margin: 0 0 12px; }
    .ap-courses__header p { font-size: 1.05rem; color: var(--ap-text-secondary); max-width: 560px; margin: 0 auto; }
    .ap-courses__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .ap-course { background: var(--ap-surface); border-radius: 16px; overflow: hidden; border: 1px solid var(--ap-border); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
    .ap-course:hover { transform: translateY(-4px); box-shadow: var(--ap-card-shadow-hover); }
    .ap-course__poster { display: flex; align-items: center; justify-content: center; height: 150px; position: relative; overflow: hidden; }
    .ap-course__poster-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
    .ap-course:hover .ap-course__poster-img { transform: scale(1.06); }
    .ap-course__poster-code { color: #fff; font-weight: 800; font-size: 1.05rem; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.92; text-shadow: 0 1px 8px rgba(0,0,0,0.25); }
    .ap-course__header { padding: 20px 24px 16px; position: relative; }
    .ap-course__header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
    .ap-course__badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; margin-bottom: 12px; }
    .ap-course__title { font-size: 1rem; font-weight: 700; color: var(--ap-text); margin: 0 0 8px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ap-course__desc { font-size: 0.85rem; color: var(--ap-text-secondary); line-height: 1.55; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .ap-course__meta { padding: 0 24px; display: flex; align-items: center; gap: 16px; font-size: 0.8rem; color: var(--ap-text-muted); }
    .ap-course__meta i { margin-right: 4px; }
    .ap-course__footer { margin-top: auto; padding: 16px 24px; border-top: 1px solid var(--ap-border); display: flex; align-items: center; justify-content: space-between; }
    .ap-course__price { font-size: 1.1rem; font-weight: 800; color: var(--ap-accent); }
    .ap-course__actions { display: flex; gap: 8px; }
    .ap-course__btn { padding: 8px 18px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; }
    .ap-course__btn--outline { border: 1.5px solid var(--ap-border); color: var(--ap-text-secondary); background: var(--ap-surface); }
    .ap-course__btn--outline:hover { border-color: var(--ap-primary); color: var(--ap-primary); }
    .ap-course__btn--fill { border: 1.5px solid var(--ap-primary); color: #fff; background: var(--ap-primary); }
    .ap-course__btn--fill:hover { background: var(--ap-primary-dark); color: #fff; }

    /* ================================================================
       6. LEARNING PILLARS
       ================================================================ */
    .ap-pillars { padding: 88px 0; background: var(--ap-surface); }
    .ap-pillars__header { text-align: center; margin-bottom: 56px; }
    .ap-pillars__header h2 { font-size: 2.25rem; font-weight: 800; color: var(--ap-text); margin: 0 0 12px; }
    .ap-pillars__header p { font-size: 1.05rem; color: var(--ap-text-secondary); max-width: 560px; margin: 0 auto; }
    .ap-pillars__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
    .ap-pillar { background: var(--ap-surface-alt); border: 1px solid var(--ap-border); border-radius: 16px; padding: 36px 28px; text-align: left; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); position: relative; }
    .ap-pillar:hover { transform: translateY(-4px); box-shadow: var(--ap-card-shadow-hover); }
    .ap-pillar__icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 20px; }
    .ap-pillar h3 { font-size: 1.15rem; font-weight: 700; color: var(--ap-text); margin: 0 0 10px; }
    .ap-pillar p { font-size: 0.9rem; color: var(--ap-text-secondary); line-height: 1.6; margin: 0 0 16px; }
    .ap-pillar__tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .ap-pillar__tag { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: var(--ap-primary-light); color: var(--ap-primary); }

    /* ================================================================
       7. TESTIMONIALS
       ================================================================ */
    .ap-testimonials { padding: 88px 0; background: var(--ap-bg); }
    .ap-testimonials__header { text-align: center; margin-bottom: 56px; }
    .ap-testimonials__header h2 { font-size: 2.25rem; font-weight: 800; color: var(--ap-text); margin: 0 0 12px; }
    .ap-testimonials__header p { font-size: 1.05rem; color: var(--ap-text-secondary); }
    .ap-testimonials__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .ap-testimonial { background: var(--ap-surface); border: 1px solid var(--ap-border); border-radius: 16px; padding: 32px 28px; transition: all 0.3s; }
    .ap-testimonial:hover { box-shadow: var(--ap-card-shadow-hover); }
    .ap-testimonial__stars { color: #f59e0b; font-size: 0.9rem; margin-bottom: 16px; letter-spacing: 2px; }
    .ap-testimonial__quote { font-size: 0.95rem; color: var(--ap-text-secondary); line-height: 1.7; margin: 0 0 20px; font-style: italic; }
    .ap-testimonial__author { display: flex; align-items: center; gap: 12px; }
    .ap-testimonial__avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; color: #fff; }
    .ap-testimonial__info strong { display: block; font-size: 0.9rem; font-weight: 700; color: var(--ap-text); }
    .ap-testimonial__info span { font-size: 0.8rem; color: var(--ap-text-muted); }

    /* ================================================================
       8. FAQ
       ================================================================ */
    .ap-faq { padding: 88px 0; background: var(--ap-surface); }
    .ap-faq__header { text-align: center; margin-bottom: 48px; }
    .ap-faq__header h2 { font-size: 2.25rem; font-weight: 800; color: var(--ap-text); margin: 0 0 12px; }
    .ap-faq__header p { font-size: 1.05rem; color: var(--ap-text-secondary); }
    .ap-faq__list { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .ap-faq__item { background: var(--ap-surface-alt); border: 1px solid var(--ap-border); border-radius: 12px; overflow: hidden; transition: border-color 0.2s; }
    .ap-faq__item:hover { border-color: var(--ap-primary); }
    .ap-faq__q { padding: 20px 24px; font-size: 0.95rem; font-weight: 600; color: var(--ap-text); cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 16px; user-select: none; }
    .ap-faq__q i { color: var(--ap-text-muted); transition: transform 0.3s; flex-shrink: 0; }
    .ap-faq__a { padding: 0 24px 20px; font-size: 0.9rem; color: var(--ap-text-secondary); line-height: 1.7; display: none; }
    .ap-faq__item.open .ap-faq__a { display: block; }
    .ap-faq__item.open .ap-faq__q i { transform: rotate(180deg); }
    .ap-faq__item.open { border-color: var(--ap-primary); background: var(--ap-primary-light); }

    /* ================================================================
       9. CTA BANNER
       ================================================================ */
    .ap-cta { padding: 80px 0; text-align: center; background: linear-gradient(135deg, var(--ap-hero-start) 0%, var(--ap-hero-end) 100%); color: #fff; position: relative; overflow: hidden; }
    .ap-cta::before { content: ''; position: absolute; top: -50%; right: -30%; width: 600px; height: 600px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%); }
    .ap-cta h2 { font-size: 2.25rem; font-weight: 800; margin: 0 0 14px; position: relative; z-index: 1; }
    .ap-cta p { font-size: 1.1rem; opacity: 0.8; margin: 0 0 32px; max-width: 520px; margin-left: auto; margin-right: auto; position: relative; z-index: 1; }
    .ap-cta__actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; }

    /* ================================================================
       10. FOOTER
       ================================================================ */
    .ap-footer { background: var(--ap-footer-bg); color: var(--ap-footer-text); padding: 0; }
    .ap-footer__inner { max-width: 1200px; margin: 0 auto; padding: 0 40px; }
    .ap-footer__compact { display: flex; align-items: center; gap: 24px; padding: 20px 0; flex-wrap: wrap; }
    .ap-footer__logo img { height: 32px; filter: brightness(10); }
    .ap-footer__links { display: flex; gap: 20px; margin-left: auto; }
    .ap-footer__links a { color: var(--ap-footer-text); font-size: 0.8rem; font-weight: 500; transition: color 0.2s; }
    .ap-footer__links a:hover { color: #fff; }
    .ap-footer__copy { font-size: 0.75rem; color: #475569; white-space: nowrap; }
    .ap-footer__india-badge { height: 24px; opacity: 0.5; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .ap-why__grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .ap-nav { padding: 0 20px; }
        .ap-nav__links { display: none; }
        .ap-hero { padding: 56px 0 48px; }
        .ap-hero__inner { padding: 0 20px; }
        .ap-hero__title { font-size: 2rem; }
        .ap-hero__subtitle { font-size: 0.95rem; }
        .ap-hero__stats { flex-wrap: wrap; gap: 16px; }
        .ap-hero__stat { padding: 0 0 0 0; }
        .ap-hero__stat-divider { display: none; }
        .ap-section-wrap { padding: 0 20px; }
        .ap-why, .ap-courses, .ap-pillars, .ap-testimonials, .ap-faq, .ap-cta { padding: 56px 0; }
        .ap-why__grid { grid-template-columns: 1fr; }
        .ap-courses__grid { grid-template-columns: 1fr; }
        .ap-pillars__grid { grid-template-columns: 1fr; }
        .ap-testimonials__grid { grid-template-columns: 1fr; }
        .ap-footer__compact { flex-direction: column; align-items: flex-start; gap: 12px; }
        .ap-footer__links { margin-left: 0; flex-wrap: wrap; }
        .ap-why__header h2, .ap-courses__header h2, .ap-pillars__header h2, .ap-testimonials__header h2, .ap-faq__header h2, .ap-cta h2 { font-size: 1.75rem; }
        .ap-footer__inner { padding: 0 20px; }
        .ap-trust__inner { gap: 20px; justify-content: flex-start; }
        .ap-btn-login { display: none; }
    }
    @media (max-width: 480px) {
        .ap-hero__title { font-size: 1.6rem; }
        .ap-hero__actions { flex-direction: column; }
        .ap-hero__btn-primary, .ap-hero__btn-outline { width: 100%; justify-content: center; }
        .ap-course__footer { flex-direction: column; align-items: flex-start; gap: 12px; }
        .ap-course__actions { width: 100%; }
        .ap-course__btn { flex: 1; justify-content: center; }
    }
    </style>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div style="display:none;"><?php echo $OUTPUT->main_content(); ?></div>

<div class="ap-home">

<!-- ═══════════════════════════════════════════════════════════════
     1. NAVBAR
     ═══════════════════════════════════════════════════════════════ -->
<nav class="ap-nav" id="ap-nav">
    <a href="<?php echo $CFG->wwwroot; ?>/" class="ap-nav__logo">
        <img src="<?php echo $CFG->wwwroot; ?>/theme/sentientia/pix/brand/academy-logo-350.png" alt="airpay academy">
    </a>
    <div class="ap-nav__links">
        <a href="<?php echo $CFG->wwwroot; ?>/" class="ap-nav__link ap-nav__link--active">Home</a>
        <a href="#ap-courses" class="ap-nav__link">Courses</a>
        <a href="#ap-why" class="ap-nav__link">About</a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_pages/index.php?page=contact" class="ap-nav__link">Contact</a>
    </div>
    <div class="ap-nav__right">
        <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="ap-nav__icon" title="Login to browse courses"><i class="fa fa-shopping-cart"></i></a>
        <button class="ap-nav__icon" id="ap-dark-toggle" title="Toggle dark mode"><i class="fa fa-moon-o"></i></button>
        <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="ap-btn-login">Login</a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_users/signup.php" class="ap-btn-register">Register</a>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════════
     2. HERO
     ═══════════════════════════════════════════════════════════════ -->
<section class="ap-hero">
    <div class="ap-hero__inner">
        <div class="ap-hero__badge"><i class="fa fa-graduation-cap"></i> ENTERPRISE LEARNING PLATFORM</div>
        <h1 class="ap-hero__title">Build a skilled, compliant<br>workforce at scale</h1>
        <p class="ap-hero__subtitle">airpay academy is an enterprise learning and development platform for the financial services industry. From regulatory compliance to business skills and financial literacy &mdash; train, track, and certify your teams across every location.</p>
        <div class="ap-hero__actions">
            <a href="#ap-courses" class="ap-hero__btn-primary">Explore Courses <i class="fa fa-arrow-right"></i></a>
            <a href="<?php echo $CFG->wwwroot; ?>/login/index.php" class="ap-hero__btn-outline"><i class="fa fa-lock"></i> Sign In</a>
        </div>
        <div class="ap-hero__stats">
            <div class="ap-hero__stat">
                <div class="ap-hero__stat-icon"><i class="fa fa-book"></i></div>
                <div class="ap-hero__stat-text">
                    <strong><?php echo $coursecount; ?>+</strong>
                    <span>Courses</span>
                </div>
            </div>
            <div class="ap-hero__stat-divider"></div>
            <div class="ap-hero__stat">
                <div class="ap-hero__stat-icon"><i class="fa fa-users"></i></div>
                <div class="ap-hero__stat-text">
                    <strong><?php echo number_format($usercount); ?>+</strong>
                    <span>Learners</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     3. TRUST BAR
     ═══════════════════════════════════════════════════════════════ -->
<section class="ap-trust">
    <div class="ap-trust__inner">
        <div class="ap-trust__item"><i class="fa fa-shield"></i> RBI Compliant</div>
        <div class="ap-trust__item"><i class="fa fa-lock"></i> DPDP 2023 Ready</div>
        <div class="ap-trust__item"><i class="fa fa-certificate"></i> SCORM &amp; xAPI</div>
        <div class="ap-trust__item"><i class="fa fa-sitemap"></i> Multi-Tenant</div>
        <div class="ap-trust__item"><i class="fa fa-headphones"></i> 24/7 Support</div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     4. WHY AIRPAY ACADEMY
     ═══════════════════════════════════════════════════════════════ -->
<section class="ap-why ap-reveal" id="ap-why">
    <div class="ap-section-wrap">
        <div class="ap-why__header">
            <span class="ap-why__label">Why Choose Us</span>
            <h2>Built for enterprise learning</h2>
            <p>Purpose-built capabilities that set airpay academy apart from generic LMS platforms.</p>
        </div>
        <div class="ap-why__grid">
            <div class="ap-why__card">
                <div class="ap-why__card-icon" style="background: var(--ap-primary-light); color: var(--ap-primary);"><i class="fa fa-shield"></i></div>
                <h3>Compliance-First</h3>
                <p>RBI-mandated training with automated tracking, deadline escalation, and audit-ready export for financial services.</p>
            </div>
            <div class="ap-why__card">
                <div class="ap-why__card-icon" style="background: var(--ap-accent-light); color: var(--ap-accent);"><i class="fa fa-sitemap"></i></div>
                <h3>Multi-Tenant</h3>
                <p>Isolated environments for each business unit with white-label branding, separate user pools, and independent reporting.</p>
            </div>
            <div class="ap-why__card">
                <div class="ap-why__card-icon" style="background: #fef3c7; color: #d97706;"><i class="fa fa-mobile"></i></div>
                <h3>Mobile-Ready</h3>
                <p>Progressive web app with offline support, responsive dashboards, and SCORM content that works on any device.</p>
            </div>
            <div class="ap-why__card">
                <div class="ap-why__card-icon" style="background: #ede9fe; color: #7c3aed;"><i class="fa fa-bar-chart"></i></div>
                <h3>Data-Driven</h3>
                <p>Role-based analytics, manager team views, compliance RAG matrices, and real-time completion tracking dashboards.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     5. FEATURED COURSES
     ═══════════════════════════════════════════════════════════════ -->
<?php if (!empty($featured)): ?>
<section class="ap-courses ap-reveal" id="ap-courses">
    <div class="ap-section-wrap">
        <div class="ap-courses__header">
            <span class="ap-courses__label">Catalog</span>
            <h2>Featured Courses</h2>
            <p>Expert-led programs designed for real-world impact in financial services.</p>
        </div>
        <div class="ap-courses__grid">
            <?php $i = 0; foreach ($featured as $course):
                $summary = shorten_text(strip_tags(format_string($course->summary)), 100);
                $url = new moodle_url('/local/search/coursedetails.php', ['id' => $course->id]);
                $badge = $categorybadges[$i % count($categorybadges)];
                $grad  = $gradients[$i % count($gradients)];
                // Poster image — real course overview image, else the per-card
                // gradient ($grad) shows through with the category badge text.
                $poster = class_exists('\\local_sentientia_catalog\\catalog_manager')
                    ? \local_sentientia_catalog\catalog_manager::course_poster((int) $course->id)
                    : ['imageurl' => '', 'has_image' => false];
            ?>
            <div class="ap-course">
                <a class="ap-course__poster" href="<?php echo $url->out(); ?>" style="background: <?php echo $grad; ?>;" aria-hidden="true" tabindex="-1">
                    <?php if (!empty($poster['has_image'])): ?>
                    <img class="ap-course__poster-img" src="<?php echo s($poster['imageurl']); ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <span class="ap-course__poster-code"><?php echo s($badge); ?></span>
                    <?php endif; ?>
                </a>
                <div class="ap-course__header">
                    <span class="ap-course__badge" style="background: var(--ap-primary-light); color: var(--ap-primary);"><?php echo s($badge); ?></span>
                    <h4 class="ap-course__title"><?php echo format_string($course->fullname); ?></h4>
                    <p class="ap-course__desc"><?php echo $summary; ?></p>
                </div>
                <div class="ap-course__meta">
                    <span><i class="fa fa-users"></i> <?php echo (int)$course->enrolcount; ?> enrolled</span>
                    <span><i class="fa fa-clock-o"></i> Self-paced</span>
                </div>
                <div class="ap-course__footer">
                    <span class="ap-course__price">Free</span>
                    <div class="ap-course__actions">
                        <a href="<?php echo $url->out(); ?>" class="ap-course__btn ap-course__btn--outline">Details</a>
                        <a href="<?php echo $url->out(); ?>" class="ap-course__btn ap-course__btn--fill">Enroll</a>
                    </div>
                </div>
            </div>
            <?php $i++; endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════
     6. LEARNING PILLARS
     ═══════════════════════════════════════════════════════════════ -->
<section id="pillars" class="ap-pillars ap-reveal">
    <div class="ap-section-wrap">
        <div class="ap-pillars__header">
            <h2>Three Pillars of Learning</h2>
            <p>A structured curriculum framework spanning employability, business, and finance.</p>
        </div>
        <div class="ap-pillars__grid">
            <div class="ap-pillar" style="border-top: 3px solid var(--ap-primary);">
                <div class="ap-pillar__icon" style="background: var(--ap-primary-light); color: var(--ap-primary);"><i class="fa fa-briefcase"></i></div>
                <h3>Employability Skills</h3>
                <p>Digital literacy, communication, aptitude, and professional development to prepare for the modern workplace.</p>
                <div class="ap-pillar__tags">
                    <span class="ap-pillar__tag">Digital Literacy</span>
                    <span class="ap-pillar__tag">Communication</span>
                    <span class="ap-pillar__tag">Aptitude</span>
                    <span class="ap-pillar__tag">Interview Prep</span>
                </div>
            </div>
            <div class="ap-pillar" style="border-top: 3px solid var(--ap-accent);">
                <div class="ap-pillar__icon" style="background: var(--ap-accent-light); color: var(--ap-accent);"><i class="fa fa-line-chart"></i></div>
                <h3>Business Acumen</h3>
                <p>Business correspondence, sales fundamentals, leadership essentials, and customer service excellence.</p>
                <div class="ap-pillar__tags">
                    <span class="ap-pillar__tag" style="background: var(--ap-accent-light); color: var(--ap-accent);">Sales</span>
                    <span class="ap-pillar__tag" style="background: var(--ap-accent-light); color: var(--ap-accent);">Leadership</span>
                    <span class="ap-pillar__tag" style="background: var(--ap-accent-light); color: var(--ap-accent);">CX</span>
                    <span class="ap-pillar__tag" style="background: var(--ap-accent-light); color: var(--ap-accent);">Strategy</span>
                </div>
            </div>
            <div class="ap-pillar" style="border-top: 3px solid #7c3aed;">
                <div class="ap-pillar__icon" style="background: #ede9fe; color: #7c3aed;"><i class="fa fa-university"></i></div>
                <h3>Financial Education</h3>
                <p>Digital payments, anti-money laundering, POSH compliance, and financial wellness for fintech.</p>
                <div class="ap-pillar__tags">
                    <span class="ap-pillar__tag" style="background: #ede9fe; color: #7c3aed;">AML/KYC</span>
                    <span class="ap-pillar__tag" style="background: #ede9fe; color: #7c3aed;">POSH</span>
                    <span class="ap-pillar__tag" style="background: #ede9fe; color: #7c3aed;">UPI</span>
                    <span class="ap-pillar__tag" style="background: #ede9fe; color: #7c3aed;">Compliance</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     7. TESTIMONIALS
     ═══════════════════════════════════════════════════════════════ -->
<section class="ap-testimonials ap-reveal">
    <div class="ap-section-wrap">
        <div class="ap-testimonials__header">
            <h2>What Our Learners Say</h2>
            <p>Voices from the airpay academy community.</p>
        </div>
        <div class="ap-testimonials__grid">
            <div class="ap-testimonial">
                <div class="ap-testimonial__stars"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></div>
                <p class="ap-testimonial__quote">The compliance training modules helped me understand RBI regulations in a practical way. The dashboard makes it easy to track my progress across all mandatory courses.</p>
                <div class="ap-testimonial__author">
                    <div class="ap-testimonial__avatar" style="background: linear-gradient(135deg, #0066A7, #0f7a73);">PR</div>
                    <div class="ap-testimonial__info">
                        <strong>Priya Sharma</strong>
                        <span>Operations Analyst, Airpay</span>
                    </div>
                </div>
            </div>
            <div class="ap-testimonial">
                <div class="ap-testimonial__stars"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i></div>
                <p class="ap-testimonial__quote">As a team manager, the team analytics dashboard gives me complete visibility into my team's learning progress. The compliance alerts ensure nobody misses a deadline.</p>
                <div class="ap-testimonial__author">
                    <div class="ap-testimonial__avatar" style="background: linear-gradient(135deg, #0f7a73, #059669);">RK</div>
                    <div class="ap-testimonial__info">
                        <strong>Rahul Khanna</strong>
                        <span>Regional Manager, Airpay</span>
                    </div>
                </div>
            </div>
            <div class="ap-testimonial">
                <div class="ap-testimonial__stars"><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star-half-o"></i></div>
                <p class="ap-testimonial__quote">The financial education courses on digital payments and AML were exactly what I needed for my role. The certificates are recognized and I could share them on LinkedIn instantly.</p>
                <div class="ap-testimonial__author">
                    <div class="ap-testimonial__avatar" style="background: linear-gradient(135deg, #7c3aed, #4f46e5);">AM</div>
                    <div class="ap-testimonial__info">
                        <strong>Anita Mehta</strong>
                        <span>Compliance Officer, ZEEA</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     8. FAQ
     ═══════════════════════════════════════════════════════════════ -->
<section class="ap-faq ap-reveal">
    <div class="ap-section-wrap">
        <div class="ap-faq__header">
            <h2>Frequently Asked Questions</h2>
            <p>Everything you need to know about airpay academy.</p>
        </div>
        <div class="ap-faq__list">
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">What is airpay academy? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">airpay academy is an enterprise learning and development platform purpose-built for financial services. It combines regulatory compliance training, business skill development, and financial literacy across <?php echo $coursecount; ?>+ courses &mdash; with multi-tenant isolation, AI-powered learning, role-based dashboards, and full SCORM/xAPI support. The platform is built and operated by Airpay Payment Services.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">Who is the platform designed for? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">airpay academy serves three audiences: <strong>enterprise organisations</strong> that need to train and certify distributed teams at scale; <strong>individual professionals</strong> looking to upskill in compliance, fintech, and business skills; and <strong>external partners</strong> such as business correspondents, field agents, and channel partners who require product and regulatory training.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">What compliance training is covered? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">The platform includes RBI-mandated courses on Anti-Money Laundering (AML), Know Your Customer (KYC), Prevention of Sexual Harassment (POSH), IT &amp; Information Security Awareness, DPDP Act 2023, and financial fraud prevention. Compliance training features automated enrolment, deadline tracking, escalation emails to managers, and audit-ready CSV/Excel exports.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">What content formats does the platform support? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">SCORM 1.2 and xAPI (Tin Can) e-learning packages, H5P interactive modules, video-based training, instructor-led virtual classrooms, online assessments with proctoring, and blended learning programs that combine self-paced and classroom components. Courses can be uploaded, authored in-platform, or sourced from third-party content providers.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">Can we get our own branded instance? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">Yes. Each enterprise customer gets a dedicated tenant with their own logo, brand colours, custom domain, user pool, course catalog, and analytics &mdash; fully isolated from other tenants. Administrators manage their own users, assign courses, track compliance, and export reports independently. Contact <a href="mailto:academy@airpay.co.in">academy@airpay.co.in</a> for enterprise plans.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">Does the platform integrate with our existing systems? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">airpay academy integrates with HRMS platforms (KeKa, Darwinbox) for automated user provisioning, supports SSO via SAML/OAuth2 for single sign-on, provides REST APIs for custom integrations, and offers webhook notifications for events like course completions and compliance deadlines. CSV bulk import/export is available for all data.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">Are certificates provided upon completion? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">Yes. Digital certificates with unique verification codes are automatically issued upon course completion. Certificates can be downloaded as PDF, shared to LinkedIn, and verified by third parties via a public verification URL. For enterprise customers, certificate templates can be customised with your organisation&rsquo;s branding.</div>
            </div>
            <div class="ap-faq__item">
                <div class="ap-faq__q" onclick="this.parentElement.classList.toggle('open')">How is pricing structured? <i class="fa fa-chevron-down"></i></div>
                <div class="ap-faq__a">Individual courses are available for free or at per-course pricing. Enterprise plans are based on active user count with annual billing, and include dedicated tenant setup, priority support, custom branding, and HRMS integration. Contact <a href="mailto:academy@airpay.co.in">academy@airpay.co.in</a> for a tailored quote.</div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     9. CTA BANNER
     ═══════════════════════════════════════════════════════════════ -->
<section class="ap-cta ap-reveal">
    <div class="ap-section-wrap">
        <h2>Ready to transform your workforce training?</h2>
        <p>Join <?php echo number_format($usercount); ?>+ professionals across financial services who are already learning on airpay academy.</p>
        <div class="ap-cta__actions">
            <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_users/signup.php" class="ap-hero__btn-primary">Get Started Free <i class="fa fa-arrow-right"></i></a>
            <a href="mailto:academy@airpay.co.in?subject=Enterprise%20Demo%20Request%20%E2%80%94%20airpay%20academy&amp;body=Hi%20Airpay%20Academy%20Team%2C%0A%0AI%27d%20like%20to%20request%20an%20enterprise%20demo%20of%20airpay%20academy.%0A%0AOrganisation%3A%20%0ANo.%20of%20employees%3A%20%0AIndustry%3A%20%0AKey%20requirements%3A%20%0A%0ALooking%20forward%20to%20hearing%20from%20you.%0A%0ARegards" class="ap-hero__btn-outline"><i class="fa fa-briefcase"></i> Request Enterprise Demo</a>
        </div>
    </div>
</section>

</div><!-- .ap-home -->

<!-- ═══════════════════════════════════════════════════════════════
     10. FOOTER
     ═══════════════════════════════════════════════════════════════ -->
<footer class="ap-footer">
    <div class="ap-footer__inner">
        <div class="ap-footer__compact">
            <a href="<?php echo $CFG->wwwroot; ?>/" class="ap-footer__logo">
                <img src="<?php echo $CFG->wwwroot; ?>/theme/sentientia/pix/brand/academy-logo-350.png" alt="airpay academy">
            </a>
            <nav class="ap-footer__links">
                <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_pages/index.php?page=privacy">Privacy</a>
                <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_pages/index.php?page=terms">Terms</a>
                <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_pages/index.php?page=help">Help</a>
                <a href="<?php echo $CFG->wwwroot; ?>/local/sentientia_pages/index.php?page=contact">Contact</a>
            </nav>
            <span class="ap-footer__copy">&copy; <?php echo date('Y'); ?> airpay payment services pvt. ltd.</span>
            <img src="<?php echo $CFG->wwwroot; ?>/theme/sentientia/pix/brand/made-in-india.jpg" alt="Made in India" class="ap-footer__india-badge">
        </div>
    </div>
    <?php echo $OUTPUT->standard_footer_html(); ?>
    <?php echo $OUTPUT->standard_end_of_body_html(); ?>
</footer>

<!-- ═══════════════════════════════════════════════════════════════
     JAVASCRIPT — Scroll animations + Dark mode + Navbar shadow
     ═══════════════════════════════════════════════════════════════ -->
<script>
(function() {
    'use strict';

    // ── Scroll fade-in via IntersectionObserver ───────────────
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('ap-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.ap-reveal').forEach(function(el) {
            observer.observe(el);
        });
    } else {
        // Fallback: show all immediately.
        document.querySelectorAll('.ap-reveal').forEach(function(el) {
            el.classList.add('ap-visible');
        });
    }

    // ── Navbar shadow on scroll ──────────────────────────────
    var nav = document.getElementById('ap-nav');
    if (nav) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 10) {
                nav.classList.add('ap-nav--scrolled');
            } else {
                nav.classList.remove('ap-nav--scrolled');
            }
        }, { passive: true });
    }

    // ── Dark mode toggle ─────────────────────────────────────
    var toggle = document.getElementById('ap-dark-toggle');
    if (toggle) {
        // Set initial icon state.
        var isDark = document.documentElement.classList.contains('dark-mode');
        toggle.querySelector('i').className = isDark ? 'fa fa-sun-o' : 'fa fa-moon-o';

        toggle.addEventListener('click', function() {
            var active = document.body.classList.toggle('dark-mode');
            document.documentElement.classList.toggle('dark-mode');
            localStorage.setItem('airpay-theme', active ? 'dark' : 'light');
            this.querySelector('i').className = active ? 'fa fa-sun-o' : 'fa fa-moon-o';
        });
    }

    // ── Smooth scroll for anchor links ───────────────────────
    document.querySelectorAll('.ap-home a[href^="#"]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
</script>
</body>
</html>
