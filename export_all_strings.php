<?php
/**
 * Export ALL English strings from Airpay plugins + key Moodle core strings
 * for Claude Cowork to translate.
 * Output: CSV with Plugin, Key, English, Hindi, Marathi, Swahili, Kannada
 */
define('CLI_SCRIPT', true);
require_once('C:\\xampp\\htdocs\\moodle\\config.php');

$output = fopen('D:\\Claude Local\\airpay-ld-os\\airpay_all_translations.csv', 'w');
fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM
fputcsv($output, ['Plugin', 'String Key', 'English', 'Hindi (hi)', 'Marathi (mr)', 'Swahili (sw)', 'Kannada (kn)', 'Notes']);

$totalrows = 0;

// ═══ AIRPAY PLUGIN STRINGS ═══
$sources = [
    ['type' => 'local', 'name' => 'airpay_catalog'],
    ['type' => 'local', 'name' => 'airpay_gamification'],
    ['type' => 'local', 'name' => 'airpay_compliance_report'],
    ['type' => 'local', 'name' => 'airpay_skills'],
    ['type' => 'local', 'name' => 'airpay_notifications'],
    ['type' => 'local', 'name' => 'airpay_privacy'],
    ['type' => 'local', 'name' => 'airpay_assistant'],
    ['type' => 'local', 'name' => 'airpay_analytics'],
    ['type' => 'local', 'name' => 'airpay_pages'],
    ['type' => 'local', 'name' => 'airpay_emails'],
    ['type' => 'theme', 'name' => 'airpayux'],
];

$langs = ['hi', 'mr', 'sw', 'kn'];

foreach ($sources as $src) {
    if ($src['type'] === 'local') {
        $component = 'local_' . $src['name'];
        $basepath = $CFG->dirroot . '/local/' . $src['name'] . '/lang';
    } else {
        $component = 'theme_' . $src['name'];
        $basepath = $CFG->dirroot . '/theme/' . $src['name'] . '/lang';
    }

    $enfile = $basepath . '/en/' . $component . '.php';
    if (!file_exists($enfile)) continue;

    $string = [];
    include($enfile);
    $en_strings = $string;

    $translations = [];
    foreach ($langs as $lang) {
        $string = [];
        $langfile = $basepath . '/' . $lang . '/' . $component . '.php';
        if (file_exists($langfile)) {
            include($langfile);
        }
        $translations[$lang] = $string;
    }

    foreach ($en_strings as $key => $value) {
        // Determine priority note
        $note = '';
        if (strpos($key, '_desc') !== false || strpos($key, 'privacy:') === 0) {
            $note = 'ADMIN-ONLY (low priority)';
        } elseif (strpos($key, 'region-') === 0 || strpos($key, 'slider') === 0) {
            $note = 'ADMIN SETTING (skip)';
        }

        fputcsv($output, [
            $component, $key, $value,
            $translations['hi'][$key] ?? '[NEEDS TRANSLATION]',
            $translations['mr'][$key] ?? '[NEEDS TRANSLATION]',
            $translations['sw'][$key] ?? '[NEEDS TRANSLATION]',
            $translations['kn'][$key] ?? '[NEEDS TRANSLATION]',
            $note,
        ]);
        $totalrows++;
    }
}

// ═══ HARDCODED STRINGS FROM TEMPLATES (not in lang files) ═══
// These are English strings hardcoded in templates/PHP that learners see.
$hardcoded = [
    ['source' => 'dashboard.php', 'key' => 'total_users_label', 'en' => 'Total Users'],
    ['source' => 'dashboard.php', 'key' => 'active_courses_label', 'en' => 'Active Courses'],
    ['source' => 'dashboard.php', 'key' => 'enrolments_label', 'en' => 'Enrolments'],
    ['source' => 'dashboard.php', 'key' => 'completion_rate_label', 'en' => 'Completion Rate'],
    ['source' => 'dashboard.php', 'key' => 'quick_navigation', 'en' => 'Quick Navigation'],
    ['source' => 'dashboard.php', 'key' => 'welcome_back', 'en' => 'Welcome back'],
    ['source' => 'dashboard.php', 'key' => 'your_courses', 'en' => 'Your Courses'],
    ['source' => 'dashboard.php', 'key' => 'upcoming_deadlines', 'en' => 'Upcoming Deadlines'],
    ['source' => 'dashboard.php', 'key' => 'recent_achievements', 'en' => 'Recent Achievements'],
    ['source' => 'homepage.php', 'key' => 'hero_title', 'en' => 'Build Skills That Drive Your Career Forward'],
    ['source' => 'homepage.php', 'key' => 'hero_subtitle', 'en' => 'A comprehensive learning platform for employability, business training, and financial education.'],
    ['source' => 'homepage.php', 'key' => 'explore_courses', 'en' => 'Explore Courses'],
    ['source' => 'homepage.php', 'key' => 'sign_in', 'en' => 'Sign In'],
    ['source' => 'homepage.php', 'key' => 'featured_courses', 'en' => 'Featured Courses'],
    ['source' => 'homepage.php', 'key' => 'ready_to_start', 'en' => 'Ready to Start Learning?'],
    ['source' => 'homepage.php', 'key' => 'get_started_free', 'en' => 'Get Started Free'],
    ['source' => 'homepage.php', 'key' => 'three_pillars', 'en' => 'Three Pillars of Learning'],
    ['source' => 'homepage.php', 'key' => 'pillar_employability', 'en' => 'Employability Skills'],
    ['source' => 'homepage.php', 'key' => 'pillar_business', 'en' => 'Business Acumen'],
    ['source' => 'homepage.php', 'key' => 'pillar_financial', 'en' => 'Financial Education'],
    ['source' => 'loginform.mustache', 'key' => 'welcome_back_login', 'en' => 'Welcome back'],
    ['source' => 'loginform.mustache', 'key' => 'sign_in_continue', 'en' => 'Sign in to continue your learning journey'],
    ['source' => 'loginform.mustache', 'key' => 'forgot_password', 'en' => 'Forgot Password?'],
    ['source' => 'loginform.mustache', 'key' => 'create_account', 'en' => 'Create an Account'],
    ['source' => 'loginform.mustache', 'key' => 'access_guest', 'en' => 'Access as a guest'],
    ['source' => 'coursedetail.mustache', 'key' => 'about_course', 'en' => 'About This Course'],
    ['source' => 'coursedetail.mustache', 'key' => 'course_content', 'en' => 'Course Content'],
    ['source' => 'coursedetail.mustache', 'key' => 'what_youll_learn', 'en' => 'What You\'ll Learn'],
    ['source' => 'coursedetail.mustache', 'key' => 'share_course', 'en' => 'Share This Course'],
    ['source' => 'coursedetail.mustache', 'key' => 'related_courses', 'en' => 'Related Courses'],
    ['source' => 'coursedetail.mustache', 'key' => 'continue_learning', 'en' => 'Continue Learning'],
    ['source' => 'coursedetail.mustache', 'key' => 'enroll_now', 'en' => 'Enroll Now — Free'],
    ['source' => 'coursedetail.mustache', 'key' => 'view_details', 'en' => 'View Details'],
    ['source' => 'course.mustache', 'key' => 'course_content_sidebar', 'en' => 'Course Content'],
    ['source' => 'course.mustache', 'key' => 'percent_complete', 'en' => '% complete'],
    ['source' => 'navbar.mustache', 'key' => 'nav_home', 'en' => 'Home'],
    ['source' => 'navbar.mustache', 'key' => 'nav_explore', 'en' => 'Explore'],
    ['source' => 'navbar.mustache', 'key' => 'nav_learning', 'en' => 'Learning'],
    ['source' => 'navbar.mustache', 'key' => 'nav_alerts', 'en' => 'Alerts'],
    ['source' => 'navbar.mustache', 'key' => 'nav_profile', 'en' => 'Profile'],
    ['source' => 'mycourses.mustache', 'key' => 'all_courses', 'en' => 'All Courses'],
    ['source' => 'mycourses.mustache', 'key' => 'in_progress', 'en' => 'In Progress'],
    ['source' => 'mycourses.mustache', 'key' => 'completed', 'en' => 'Completed'],
    ['source' => 'mycourses.mustache', 'key' => 'not_started', 'en' => 'Not Started'],
    ['source' => 'mycourses.mustache', 'key' => 'no_courses_found', 'en' => 'No courses found'],
    ['source' => 'mycourses.mustache', 'key' => 'browse_catalog', 'en' => 'Browse Catalog'],
    ['source' => 'certificates.php', 'key' => 'my_certificates', 'en' => 'My Certificates'],
    ['source' => 'certificates.php', 'key' => 'certificates_earned', 'en' => 'certificates earned'],
    ['source' => 'certificates.php', 'key' => 'no_certificates', 'en' => 'No certificates yet'],
];

foreach ($hardcoded as $h) {
    fputcsv($output, [
        'HARDCODED:' . $h['source'], $h['key'], $h['en'],
        '[NEEDS TRANSLATION]', '[NEEDS TRANSLATION]', '[NEEDS TRANSLATION]', '[NEEDS TRANSLATION]',
        'HARDCODED — not in lang file yet, translate for future migration',
    ]);
    $totalrows++;
}

fclose($output);
echo "Exported $totalrows strings to airpay_all_translations.csv\n";
