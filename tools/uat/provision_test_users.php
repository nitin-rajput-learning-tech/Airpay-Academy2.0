<?php
// provision_test_users.php - UAT shared test-account set for Sentientia LMS (Moodle 5.2).
//
// Creates, IDEMPOTENTLY, everything Nitin's team needs to try every persona on a FRESH install:
//   schema   - fresh-install gaps (user_type/profile tables, org branding columns)
//   roles    - administrator(id 9) / trainer / sentientiaauthor / complianceofficer / linemanager / employee
//   tenants  - local_sentientia_org rows with production ids (1 airpay, 77 public, 177 ZEEA) + departments
//   config   - public tenant id, compliance auto-enrol, completion/badges on
//   flags    - mock-mode feature flags (no *.live_api flag is ever touched)
//   content  - categories, certificate template, skills, 14 courses (Page + Quiz), tags, prices
//   users    - one account per persona (open_path/designation/supervisor), roles, user types
//   enrol    - enrolments, completions (via the completion API), certificates
//   objects  - learning paths, program, classrooms, exam, evaluation, compliance, leaderboard, live session
//   verify   - role_detector tier + capability assertions per persona
//
// Run ON the UAT box as the web user:
//   sudo -u www-data env CREDS_OUT=/tmp/uat-creds.csv php provision_test_users.php [--only=stage,stage] [--reset-passwords] [--dry-run]
// Passwords are generated here and written ONLY to the CSV (never printed, never in git). On re-run the
// CSV at CREDS_OUT is read first so existing passwords are kept unless --reset-passwords is given.
//
// Sentientia LMS / Airpay Payment Services 2026 - GPL v3 or later.

define('CLI_SCRIPT', true);

$configfile = getenv('MOODLE_CONFIG') ?: '/var/www/html/moodle5.2/config.php';
require($configfile);
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false, 'dry-run' => false, 'only' => '', 'reset-passwords' => false, 'creds-out' => '',
], ['h' => 'help']);
if ($options['help']) {
    cli_writeln("See the header comment of this file for usage.");
    exit(0);
}
$DRY = !empty($options['dry-run']);
$ONLY = array_filter(array_map('trim', explode(',', (string) $options['only'])));
$CREDSOUT = $options['creds-out'] ?: (getenv('CREDS_OUT') ?: '');
if ($CREDSOUT === '' && !$DRY) {
    cli_error('Set --creds-out=<path> or env CREDS_OUT (the ONLY place passwords are written).');
}
$RESETPW = !empty($options['reset-passwords']);

\core\session\manager::set_user(get_admin());
$ADMIN = get_admin();
$SYS = context_system::instance();
$NOW = time();
$DAY = 86400;
$LOG = [];
function say(string $m): void { cli_writeln($m); }
function warn(string $m): void { global $LOG; $LOG[] = $m; cli_writeln('  WARN ' . $m); }
function stage(string $s): bool { global $ONLY; return empty($ONLY) || in_array($s, $ONLY, true); }

// ======================================================================================
// Spec (kept in one place so the credentials doc and this seed never drift)
// ======================================================================================
$ORGS = [ // id => [fullname, shortname, parentid, path, depth, sortorder]
    1   => ['AIRPAY PAYMENT SERVICES PRIVATE LIMITED', 'AirPay', 0, '/1', 1, 1],
    2   => ['Airpay Payment Services (Acquiring)', 'AirPay_Acquiring', 1, '/1/2', 2, 101],
    79  => ['Airpay Vyaapaar Fintech', 'AirPay_Vyaapaar', 1, '/1/79', 2, 102],
    114 => ['Airpay Financial Technologies Pvt Ltd', 'AirPay_AFT', 1, '/1/114', 2, 103],
    77  => ['Public', 'external', 0, '/77', 1, 2],
    256 => ['HIC Bank', 'external_HIC', 77, '/77/256', 2, 201],
    257 => ['Merchant Partners', 'external_MERCHANT', 77, '/77/257', 2, 202],
    177 => ['ZEEA', 'ZEEA01', 0, '/177', 1, 6],
    178 => ['ZANZIBAR', 'ZEEA01_TZ01', 177, '/177/178', 2, 601],
    179 => ['Dar es Salaam', 'ZEEA01_DSM', 177, '/177/179', 2, 602],
];
$CATEGORIES = [ // idnumber => name  (idnumber == org shortname at depth 1: tenant->category resolution)
    'AirPay' => 'AIRPAY PAYMENT SERVICES PRIVATE LIMITED', 'external' => 'Public', 'ZEEA01' => 'ZEEA',
];
$TENANTCAT = [1 => 'AirPay', 77 => 'external', 177 => 'ZEEA01'];

$ROLES = [ // shortname => [name, archetype, contextlevels, extra caps]
    'administrator' => ['Administrator', 'manager', [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE], []],
    'trainer' => ['Trainer', 'teacher', [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE],
        ['local/sentientia_classroom:view', 'local/sentientia_classroom:attendance', 'local/sentientia_evaluation:manage', 'local/sentientia_exams:view']],
    'sentientiaauthor' => ['Sentientia Author', '', [CONTEXT_SYSTEM],
        ['local/sentientia_authoring:generate', 'local/sentientia_authoring:review', 'local/sentientia_authoring:managetemplates',
         'local/sentientia_skillsai:extract', 'local/sentientia_skillsai:review',
         'local/sentientia_aiquiz:generate', 'local/sentientia_aiquiz:review']],
    'complianceofficer' => ['Compliance Officer', '', [CONTEXT_SYSTEM],
        ['moodle/site:viewreports', 'local/sentientia_compliance_report:export', 'local/sentientia_reports:view',
         'local/sentientia_recompletion:view', 'local/sentientia_notifications:viewlogs']],
    'linemanager' => ['Line Manager (approvals)', '', [CONTEXT_SYSTEM],
        ['local/sentientia_manager:approve', 'local/sentientia_manager:allocate', 'local/sentientia_request:approve']],
    'employee' => ['Employee', 'student', [CONTEXT_SYSTEM, CONTEXT_COURSE, CONTEXT_MODULE], []],
];

$COURSES = [ // shortname => [tenant root, fullname, mandatory?, compliance days (0 = none), cert?, price, summary]
    'UAT-AP-POSH' => [1, 'Prevention of Sexual Harassment (POSH) 2026', true, 30, true, 0,
        'Mandatory annual awareness programme on the POSH Act 2013: what constitutes harassment, the Internal Committee, how to raise a complaint and the employer duties.'],
    'UAT-AP-AML-KYC' => [1, 'AML & KYC Essentials', true, 30, true, 0,
        'Anti-money-laundering and Know-Your-Customer obligations for a payments company: red flags, CDD/EDD, STR filing and record keeping under PMLA.'],
    'UAT-AP-INFOSEC' => [1, 'Information Security Awareness', true, 15, false, 0,
        'Phishing, password hygiene, device security, data classification and incident reporting. Short deadline: 15 days from enrolment.'],
    'UAT-AP-PRODUCT' => [1, 'Airpay Product Suite Fundamentals', false, 0, true, 0,
        'The Airpay product family end to end: payment gateway, POS, payouts, BBPS and value-added services, with positioning for each merchant segment.'],
    'UAT-AP-LEADERSHIP' => [1, 'Leading High-Performance Teams', false, 0, false, 0,
        'Manager track: setting expectations, coaching conversations, feedback and running effective one-to-ones.'],
    'UAT-AP-SALES-FUND' => [1, 'Consultative Sales Fundamentals', false, 0, false, 0,
        'Discovery questions, need mapping, objection handling and closing for merchant-facing teams. Ends with the Sales Fundamentals exam.'],
    'UAT-PUB-DIGITAL-PAY' => [77, 'Digital Payments in India: An Introduction', false, 0, true, 0,
        'Free public course: how UPI, cards, wallets and BBPS work, and how to pay and get paid safely.'],
    'UAT-PUB-UPI-BASICS' => [77, 'UPI Basics for Merchants', false, 0, false, 0,
        'Free public course for small merchants: QR onboarding, settlement cycles, disputes and refunds.'],
    'UAT-PUB-FINTECH-101' => [77, 'Fintech 101: Payments, Lending & Compliance', false, 0, false, 499.0,
        'Paid public course (INR 499): the Indian fintech landscape, regulation, business models and career paths.'],
    'UAT-PUB-MERCHANT-ONB' => [77, 'Merchant Onboarding Masterclass', false, 0, false, 0,
        'Free public course: documents, KYC, risk categories and go-live checklist for onboarding a merchant.'],
    'UAT-ZEEA-POSH-TZ' => [177, 'Workplace Conduct & Harassment Prevention (Tanzania)', true, 30, true, 0,
        'Mandatory ZEEA programme aligned with the Employment and Labour Relations Act: respectful workplace, reporting channels and protection from retaliation.'],
    'UAT-ZEEA-AGENT-BANKING' => [177, 'Agent Banking Operations', false, 0, false, 0,
        'Running a ZEEA agent point: float management, cash-in/cash-out, reconciliation and fraud prevention.'],
    'UAT-ZEEA-MOBILE-MONEY' => [177, 'Mobile Money & Wallet Services', false, 0, false, 0,
        'M-Pesa, Tigo Pesa and Airtel Money integrations, interoperability and customer support scenarios.'],
    'UAT-ZEEA-CUST-SERVICE' => [177, 'Customer Service Excellence', false, 0, false, 0,
        'Blended course with a classroom session: service standards, complaint handling and escalation for ZEEA agents.'],
];

$USERS = [ // username => spec
    'uat_ldadmin_airpay' => ['Meera', 'Iyer', 'L&D / tenant admin (Airpay)', 1, '/1', 'Head of L&D', null, 'IN', 'Asia/Kolkata', 'en', 'employee',
        [['administrator', 'system'], ['administrator', 'category:AirPay']], [], [], true],
    'uat_manager_airpay' => ['Vikram', 'Sharma', 'Manager with direct reports (Airpay)', 1, '/1/79', 'Cluster Head', null, 'IN', 'Asia/Kolkata', 'en', 'employee',
        [['employee', 'system'], ['linemanager', 'system']], ['UAT-AP-LEADERSHIP', 'UAT-AP-POSH', 'UAT-AP-AML-KYC'], ['UAT-AP-POSH'], true],
    'uat_learner_airpay' => ['Priya', 'Nair', 'Employee learner (reports to the manager)', 1, '/1/79', 'Area Sales Manager', 'uat_manager_airpay', 'IN', 'Asia/Kolkata', 'en', 'employee',
        [['employee', 'system']], ['UAT-AP-POSH', 'UAT-AP-AML-KYC', 'UAT-AP-INFOSEC', 'UAT-AP-PRODUCT', 'UAT-AP-LEADERSHIP', 'UAT-AP-SALES-FUND'], ['UAT-AP-POSH', 'UAT-AP-PRODUCT'], true],
    'uat_learner2_airpay' => ['Rahul', 'Deshmukh', 'Employee learner 2 (overdue case, Hindi UI, onboarding pending)', 1, '/1/2', 'Sales Officer', 'uat_manager_airpay', 'IN', 'Asia/Kolkata', 'hi', 'employee',
        [['employee', 'system']], ['UAT-AP-POSH', 'UAT-AP-AML-KYC', 'UAT-AP-INFOSEC', 'UAT-AP-SALES-FUND'], ['UAT-AP-POSH'], false],
    'uat_trainer_airpay' => ['Arjun', 'Mehta', 'Trainer / subject-matter expert', 1, '/1/2', 'Sales Trainer Manager', null, 'IN', 'Asia/Kolkata', 'en', 'employee',
        [['trainer', 'system'], ['employee', 'system']], ['UAT-AP-SALES-FUND:editingteacher', 'UAT-AP-POSH:editingteacher'], [], true],
    'uat_author_airpay' => ['Sneha', 'Kulkarni', 'Course author / instructional designer', 1, '/1/114', 'Instructional Designer', 'uat_manager_airpay', 'IN', 'Asia/Kolkata', 'en', 'employee',
        [['sentientiaauthor', 'system'], ['employee', 'system'], ['coursecreator', 'category:AirPay']], ['UAT-AP-PRODUCT:editingteacher', 'UAT-AP-SALES-FUND:editingteacher'], [], true],
    'uat_compliance_airpay' => ['Joseph', 'Fernandes', 'Compliance officer', 1, '/1', 'Compliance Manager', null, 'IN', 'Asia/Kolkata', 'en', 'employee',
        [['complianceofficer', 'system'], ['employee', 'system']], ['UAT-AP-POSH'], ['UAT-AP-POSH'], true],
    'uat_learner_public' => ['Deepa', 'Menon', 'Public / external learner', 77, '/77', '', null, 'IN', 'Asia/Kolkata', 'en', 'consumer',
        [['employee', 'system']], ['UAT-PUB-DIGITAL-PAY', 'UAT-PUB-UPI-BASICS'], ['UAT-PUB-DIGITAL-PAY'], true],
    'uat_learner_zeea' => ['Fatma', 'Khamis', 'ZEEA tenant learner (Tanzania)', 177, '/177/178', 'Agent Support Officer', null, 'TZ', 'Africa/Dar_es_Salaam', 'en', 'partner_employee',
        [['employee', 'system']], ['UAT-ZEEA-POSH-TZ', 'UAT-ZEEA-AGENT-BANKING', 'UAT-ZEEA-MOBILE-MONEY', 'UAT-ZEEA-CUST-SERVICE'], ['UAT-ZEEA-POSH-TZ'], true],
    'uat_admin_zeea' => ['Juma', 'Mwakalinga', 'ZEEA tenant / L&D admin', 177, '/177', 'Country L&D Lead', null, 'TZ', 'Africa/Dar_es_Salaam', 'en', 'partner_employee',
        [['administrator', 'system'], ['administrator', 'category:ZEEA01']], [], [], true],
];
// index: 0 first,1 last,2 persona,3 tenant root,4 open_path,5 designation,6 supervisor username,7 country,8 timezone,9 lang,
//        10 user_type,11 roles,12 enrol (SHORT or SHORT:role),13 complete,14 onboarding done?

$FLAGS_GLOBAL = ['sentientia.ai.gateway.enabled', 'sentientia.assistant.agentic.enabled', 'sentientia.aiquiz.enabled',
    'sentientia.authoring.enabled', 'sentientia.authoring.publish.enabled', 'sentientia.skillsai.enabled',
    'sentientia.skillsai.gap_engine', 'sentientia.skillsai.impact_map', 'sentientia.recommendations.enabled',
    'ai.recommendations.enabled', 'sentientia.translate.enabled', 'live.enabled', 'live.questiontype.multichoice',
    'live.questiontype.wordcloud', 'live.questiontype.openended', 'live.questiontype.rating', 'live.questiontype.quiz',
    'live.questiontype.ranking', 'live.allow_anonymous', 'sentientia.leaderboards.enabled',
    'sentientia.leaderboards.type.completion', 'sentientia.leaderboards.type.quiz', 'sentientia.dashboard.skillsrecs.enabled',
    'sentientia.analytics.predictive.enabled', 'sentientia.analytics.roi.enabled', 'sentientia.calendar_sync.enabled',
    'sentientia.xapi.enabled', 'sentientia.xapi.emit_login', 'sentientia.xapi.emit_module_view', 'sentientia.pwa.install.enabled',
    'engagement.gamification.enabled'];
$FLAGS_TENANT = ['sentientia.lifecycle.autoenrol.enabled' => [1, 177]];

// ======================================================================================
// Helpers
// ======================================================================================
$WORDS = ['Amber', 'River', 'Cedar', 'Falcon', 'Harbor', 'Indigo', 'Juniper', 'Kestrel', 'Lotus', 'Maple', 'Nimbus', 'Orchid',
    'Pebble', 'Quartz', 'Raven', 'Saffron', 'Tidal', 'Umber', 'Velvet', 'Willow', 'Zephyr', 'Beacon', 'Comet', 'Delta',
    'Ember', 'Fjord', 'Glacier', 'Horizon', 'Island', 'Jasper', 'Lantern', 'Meadow', 'Nectar', 'Oasis', 'Prism', 'Ridge',
    'Summit', 'Tundra', 'Valley', 'Wander'];
function make_password(): string {
    global $WORDS;
    $a = $WORDS[random_int(0, count($WORDS) - 1)];
    $b = $WORDS[random_int(0, count($WORDS) - 1)];
    return sprintf('%s-%s%02d!', $a, $b, random_int(10, 99)); // upper+lower+digit+non-alnum, >= 12 chars
}
function read_creds(string $path): array {
    $map = [];
    if ($path !== '' && is_readable($path)) {
        foreach (array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1) as $line) {
            $c = str_getcsv($line);
            if (count($c) >= 3) { $map[$c[0]] = $c[2]; }
        }
    }
    return $map;
}
function ensure_cap_row(int $roleid, string $cap, context $ctx): void {
    global $DB;
    if (!$DB->record_exists('capabilities', ['name' => $cap])) { warn("capability $cap not registered on this site - skipped"); return; }
    if (!$DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $ctx->id])) {
        assign_capability($cap, CAP_ALLOW, $roleid, $ctx->id, true);
    }
}
function role_id(string $shortname): int {
    global $DB;
    return (int) $DB->get_field('role', 'id', ['shortname' => $shortname]);
}
function category_id(string $idnumber): int {
    global $DB;
    return (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber]);
}
function course_by_short(string $short): ?stdClass {
    global $DB;
    $c = $DB->get_record('course', ['shortname' => $short]);
    return $c ?: null;
}
function user_by_name(string $u): ?stdClass {
    global $DB;
    $r = $DB->get_record('user', ['username' => $u, 'deleted' => 0]);
    return $r ?: null;
}
function manual_instance(stdClass $course): stdClass {
    global $DB;
    $inst = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
    if (!$inst) {
        $id = enrol_get_plugin('manual')->add_default_instance($course);
        $inst = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }
    return $inst;
}
function cm_by_idnumber(int $courseid, string $idnumber): ?stdClass {
    global $DB;
    $cm = $DB->get_record('course_modules', ['course' => $courseid, 'idnumber' => $idnumber]);
    return $cm ?: null;
}
function set_flag(string $key, int $tenant, bool $value): void {
    global $ADMIN;
    if (!class_exists('\local_sentientia_platform\feature_flags')) { warn('feature_flags class missing'); return; }
    try {
        \local_sentientia_platform\feature_flags::set($key, $tenant, $value, $ADMIN->id, 'UAT persona seed', 0);
    } catch (\Throwable $e) {
        warn("flag $key (tenant $tenant): " . $e->getMessage());
    }
}

say("=== Sentientia UAT test-user provisioning " . date('c') . ($DRY ? ' (DRY RUN - read-only)' : '') . " ===");
say("site: {$CFG->wwwroot}  stages: " . ($ONLY ? implode(',', $ONLY) : 'all'));

// ======================================================================================
// 0. schema - fresh-install gaps that block persona rendering
// ======================================================================================
if (stage('schema')) {
    say("--- schema ---");
    $dbman = $DB->get_manager();
    $missing = [];
    foreach (['local_sentientia_user_type', 'local_sentientia_consumer_profile', 'local_sentientia_employee_profile',
              'local_sentientia_partner_employee_profile', 'local_sentientia_operator_profile'] as $t) {
        if (!$dbman->table_exists($t)) { $missing[] = $t; }
    }
    if ($missing && !$DRY) {
        if (class_exists('\local_sentientia_platform\schema\user_type_tables')) {
            \local_sentientia_platform\schema\user_type_tables::ensure($dbman);
        } else {
            // Replay of local/sentientia_platform/db/upgrade.php step 2026052801 (created there only on UPGRADE).
            $t = new xmldb_table('local_sentientia_user_type');
            if (!$dbman->table_exists($t)) {
                $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $t->add_field('user_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
                $t->add_field('provisioning_source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'unknown');
                $t->add_field('provisioned_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
                $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
                $t->add_index('idx_type', XMLDB_INDEX_NOTUNIQUE, ['user_type']);
                $dbman->create_table($t);
            }
            $t = new xmldb_table('local_sentientia_employee_profile');
            if (!$dbman->table_exists($t)) {
                $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $t->add_field('employee_id', XMLDB_TYPE_CHAR, '40', null, null, null, null);
                $t->add_field('department', XMLDB_TYPE_CHAR, '80', null, null, null, null);
                $t->add_field('job_title', XMLDB_TYPE_CHAR, '80', null, null, null, null);
                $t->add_field('manager_userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $t->add_field('hire_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $t->add_field('cost_center_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
                $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
                $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
                $t->add_key('fk_manager', XMLDB_KEY_FOREIGN, ['manager_userid'], 'user', ['id']);
                $t->add_index('idx_dept', XMLDB_INDEX_NOTUNIQUE, ['department']);
                $dbman->create_table($t);
            }
            $t = new xmldb_table('local_sentientia_consumer_profile');
            if (!$dbman->table_exists($t)) {
                $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $t->add_field('interests_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
                $t->add_field('weekly_goal', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
                $t->add_field('referral_source', XMLDB_TYPE_CHAR, '40', null, null, null, null);
                $t->add_field('consent_marketing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('consent_leaderboard', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('payment_history_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
                $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
                $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
                $dbman->create_table($t);
            }
            $t = new xmldb_table('local_sentientia_partner_employee_profile');
            if (!$dbman->table_exists($t)) {
                $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $t->add_field('customer_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $t->add_field('partner_employee_id', XMLDB_TYPE_CHAR, '40', null, null, null, null);
                $t->add_field('partner_department', XMLDB_TYPE_CHAR, '80', null, null, null, null);
                $t->add_field('partner_job_title', XMLDB_TYPE_CHAR, '80', null, null, null, null);
                $t->add_field('partner_manager_userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $t->add_field('partner_hire_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $t->add_field('cost_center_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
                $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
                $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
                $t->add_key('fk_manager', XMLDB_KEY_FOREIGN, ['partner_manager_userid'], 'user', ['id']);
                $t->add_index('idx_customer', XMLDB_INDEX_NOTUNIQUE, ['customer_id']);
                $dbman->create_table($t);
            }
            $t = new xmldb_table('local_sentientia_operator_profile');
            if (!$dbman->table_exists($t)) {
                $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $t->add_field('operator_role', XMLDB_TYPE_CHAR, '40', null, null, null, null);
                $t->add_field('contact_phone', XMLDB_TYPE_CHAR, '40', null, null, null, null);
                $t->add_field('oncall_for_customer_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
                $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
                $dbman->create_table($t);
            }
        }
        say("  user-type tables created: " . implode(', ', $missing));
    } else {
        say("  user-type tables: " . ($missing ? 'MISSING ' . implode(',', $missing) : 'present'));
    }
    // local_sentientia_org branding columns (upgrade step 2026051100 only).
    $orgtable = new xmldb_table('local_sentientia_org');
    $orgfields = [
        ['favicon', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'org_logo'],
        ['footer_text', XMLDB_TYPE_TEXT, null, null, null, null, null, 'theme_scheme'],
        ['email_from_name', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'footer_text'],
        ['email_from_addr', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'email_from_name'],
        ['support_email', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'email_from_addr'],
        ['help_url', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'support_email'],
        ['hero_title', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'help_url'],
        ['hero_subtitle', XMLDB_TYPE_TEXT, null, null, null, null, null, 'hero_title'],
        ['custom_css', XMLDB_TYPE_TEXT, null, null, null, null, null, 'hero_subtitle'],
    ];
    $added = [];
    if ($dbman->table_exists($orgtable)) {
        foreach ($orgfields as $fdef) {
            $field = new xmldb_field(...$fdef);
            if (!$dbman->field_exists($orgtable, $field)) {
                if (!$DRY) { $dbman->add_field($orgtable, $field); }
                $added[] = $fdef[0];
            }
        }
    } else {
        cli_error('local_sentientia_org table missing - the org plugin is not installed; the landing page would fatal.');
    }
    say("  org branding columns " . ($DRY ? 'missing' : 'added') . ": " . ($added ? implode(',', $added) : 'none'));
}

// ======================================================================================
// 1. roles
// ======================================================================================
if (stage('roles') && !$DRY) {
    say("--- roles ---");
    foreach ($ROLES as $short => [$name, $arch, $levels, $caps]) {
        $id = role_id($short);
        $created = false;
        if (!$id) {
            if ($short === 'administrator' && !$DB->record_exists('role', ['id' => 9])) {
                // Production's BizLMS administrator role is id 9 and three pages hard-code it.
                $sort = (int) $DB->get_field_sql("SELECT COALESCE(MAX(sortorder),0)+1 FROM {role}");
                $DB->import_record('role', (object) ['id' => 9, 'name' => $name, 'shortname' => $short,
                    'description' => 'UAT: mirrors the production BizLMS administrator role (L&D / tenant admin).',
                    'sortorder' => $sort, 'archetype' => $arch]);
                $DB->get_manager()->reset_sequence('role');
                $id = 9;
            } else {
                $id = create_role($name, $short, 'UAT persona role (' . $short . ').', $arch);
            }
            $created = true;
            if ($arch !== '') { reset_role_capabilities($id); }
            say("  created role $short id=$id" . ($arch ? " (archetype $arch)" : ''));
        } else {
            say("  role $short exists id=$id");
        }
        if ($short === 'administrator' && $id !== 9) {
            warn("administrator role is id $id, NOT 9 - Analytics / compliance admin branch will not recognise it");
        }
        $current = get_role_contextlevels($id);
        sort($current); $want = $levels; sort($want);
        if ($current !== $want) { set_role_contextlevels($id, $levels); }
        foreach ($caps as $cap) { ensure_cap_row($id, $cap, $SYS); }
    }
    // Let the L&D admin and core manager assign the UAT roles in the UI.
    $assignables = array_filter([role_id('trainer'), role_id('sentientiaauthor'), role_id('complianceofficer'), role_id('linemanager'),
        role_id('employee'), role_id('student'), role_id('editingteacher'), role_id('teacher')]);
    foreach ([role_id('administrator'), role_id('manager')] as $assigner) {
        if (!$assigner) { continue; }
        foreach ($assignables as $target) {
            if (!$DB->record_exists('role_allow_assign', ['roleid' => $assigner, 'allowassign' => $target])) {
                core_role_set_assign_allowed($assigner, $target);
            }
        }
    }
    $SYS->mark_dirty();
}

// ======================================================================================
// 2. tenants (local_sentientia_org rows with production ids) + categories
// ======================================================================================
if (stage('tenants') && !$DRY) {
    say("--- tenants ---");
    foreach ($ORGS as $id => [$full, $short, $parent, $path, $depth, $sort]) {
        if ($DB->record_exists('local_sentientia_org', ['id' => $id])) { continue; }
        $DB->import_record('local_sentientia_org', (object) ['id' => $id, 'fullname' => $full, 'shortname' => $short,
            'description' => '', 'parentid' => $parent, 'path' => $path, 'depth' => $depth, 'visible' => 1,
            'sortorder' => $sort, 'timecreated' => $NOW, 'timemodified' => $NOW]);
        say("  org $id $short $path");
    }
    $DB->get_manager()->reset_sequence('local_sentientia_org');
    foreach ($CATEGORIES as $idnumber => $name) {
        if (!category_id($idnumber)) {
            $cat = core_course_category::create((object) ['name' => $name, 'idnumber' => $idnumber, 'parent' => 0]);
            say("  category $idnumber id={$cat->id}");
        }
    }
    if (class_exists('\local_sentientia_core\tenant_registry') && file_exists($CFG->dirroot . '/local/sentientia_core/cli/seed_tenants.php')) {
        say("  (core registry rows: run local/sentientia_core/cli/seed_tenants.php if the registry is ever switched on)");
    }
}

// ======================================================================================
// 3. config
// ======================================================================================
if (stage('config') && !$DRY) {
    say("--- config ---");
    set_config('public_tenant_id', 77, 'local_sentientia_org');
    set_config('public_tenant_id', 77, 'local_sentientia_pages');
    set_config('auto_enrol', 1, 'local_sentientia_compliance_report');
    foreach (['enablecompletion' => 1, 'enablebadges' => 1, 'forcelogin' => 0, 'enablemyhome' => 1] as $k => $v) {
        if ((string) get_config('core', $k) !== (string) $v) { set_config($k, $v); say("  $k=$v"); }
    }
    say("  public tenant 77, compliance auto-enrol on, completion/badges on");
}

// ======================================================================================
// 4. flags (mock mode only)
// ======================================================================================
if (stage('flags') && !$DRY) {
    say("--- flags ---");
    $adaptiveok = $DB->get_manager()->table_exists('local_sentientia_lp_adaptive_log');
    foreach ($FLAGS_GLOBAL as $key) { set_flag($key, 0, true); }
    if ($adaptiveok) { set_flag('sentientia.learningpath.adaptive.enabled', 0, true); } else { say("  adaptive LP tables absent - flag left OFF"); }
    foreach ($FLAGS_TENANT as $key => $tenants) { foreach ($tenants as $t) { set_flag($key, $t, true); } }
    say("  " . count($FLAGS_GLOBAL) . " global flags on, lifecycle autoenrol on for tenants 1 + 177; every *.live_api flag untouched (OFF)");
}

// ======================================================================================
// 5. content - certificate template, skills, courses (Page + Quiz), tags, prices
// ======================================================================================
$TEMPLATEID = 0;
if (stage('content') && !$DRY) {
    say("--- content ---");
    // 5.1 certificate template
    if (class_exists('\tool_certificate\template')) {
        try {
            $tpl = \tool_certificate\template::find_by_name('UAT Course Completion');
            if (!$tpl) {
                $tpl = \tool_certificate\template::create((object) ['name' => 'UAT Course Completion', 'shared' => 1, 'contextid' => $SYS->id]);
                $page = $tpl->new_page();
                $page->save((object) []);
                $pageid = $page->get_id();
                $els = [
                    ['text', ['name' => 'Title', 'text' => 'Certificate of Completion', 'font' => 'freesans', 'fontsize' => 28, 'colour' => '#0066A7', 'posx' => 20, 'posy' => 50, 'width' => 257, 'refpoint' => 1]],
                    ['userfield', ['name' => 'Learner', 'userfield' => 'fullname', 'font' => 'freesans', 'fontsize' => 20, 'colour' => '#1a1a2e', 'posx' => 20, 'posy' => 90, 'width' => 257, 'refpoint' => 1]],
                    ['date', ['name' => 'Date', 'dateitem' => -1, 'dateformat' => 'strftimedate', 'font' => 'freesans', 'fontsize' => 12, 'colour' => '#5a6070', 'posx' => 20, 'posy' => 150, 'width' => 257, 'refpoint' => 1]],
                    ['code', ['name' => 'Code', 'display' => 1, 'font' => 'freesans', 'fontsize' => 10, 'colour' => '#5a6070', 'posx' => 20, 'posy' => 180, 'width' => 257, 'refpoint' => 1]],
                ];
                foreach ($els as [$type, $data]) {
                    try {
                        $el = \tool_certificate\element::instance(0, (object) ['pageid' => $pageid, 'element' => $type]);
                        $el->save_form_data((object) $data);
                    } catch (\Throwable $e) { warn("certificate element $type: " . $e->getMessage()); }
                }
                say("  certificate template created id=" . $tpl->get_id());
            }
            $TEMPLATEID = (int) $tpl->get_id();
        } catch (\Throwable $e) { warn('certificate template: ' . $e->getMessage()); }
    } else { say("  tool_certificate not installed - no certificates"); }

    // 5.2 courses with Page + Quiz
    $pagemodule = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
    $quizmodule = $DB->get_record('modules', ['name' => 'quiz']);
    foreach ($COURSES as $short => [$root, $full, $mandatory, $days, $cert, $price, $summary]) {
        $course = course_by_short($short);
        if (!$course) {
            $data = (object) ['category' => category_id($TENANTCAT[$root]), 'shortname' => $short, 'idnumber' => $short,
                'fullname' => $full, 'summary' => '<p>' . s($summary) . '</p>', 'summaryformat' => FORMAT_HTML, 'format' => 'topics',
                'numsections' => 2, 'visible' => 1, 'enablecompletion' => 1, 'startdate' => $NOW - 30 * $DAY, 'enddate' => 0,
                'open_path' => '/' . $root, 'open_points' => 100, 'open_coursetype' => 0,
                'open_coursecompletiondays' => $days ?: 30, 'open_certificateid' => $cert ? $TEMPLATEID : 0];
            $course = create_course($data);
            say("  course $short id={$course->id}");
        } else {
            // Keep substrate columns correct on re-run (they are not part of the shortname lookup).
            $upd = (object) ['id' => $course->id, 'open_path' => '/' . $root, 'open_certificateid' => $cert ? $TEMPLATEID : 0];
            $DB->update_record('course', $upd);
        }
        manual_instance($course);
        $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);

        // Page
        $pagecm = cm_by_idnumber($course->id, "$short-PAGE");
        if (!$pagecm) {
            $mi = (object) ['modulename' => 'page', 'module' => $pagemodule->id, 'course' => $course->id, 'section' => 1,
                'visible' => 1, 'visibleoncoursepage' => 1, 'name' => 'Read: ' . $full,
                'intro' => '<p>Start here. Read the summary and continue to the knowledge check.</p>', 'introformat' => FORMAT_HTML,
                'page' => ['text' => '<h3>' . s($full) . '</h3><p>' . s($summary) . '</p><p>This is UAT sample content. When you have read it, take the knowledge check to complete the course.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
                'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 1, 'cmidnumber' => "$short-PAGE",
                'groupmode' => 0, 'groupingid' => 0, 'availabilityconditionsjson' => '',
                'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1, 'completionexpected' => 0, 'completiongradeitemnumber' => ''];
            $mi = add_moduleinfo($mi, $course);
            $pagecm = $DB->get_record('course_modules', ['id' => $mi->coursemodule], '*', MUST_EXIST);
            // mod_page reads $data->page['text'] in some builds and $data->content in others - make sure content is stored.
            $p = $DB->get_record('page', ['id' => $pagecm->instance]);
            if ($p && trim((string) $p->content) === '') {
                $DB->set_field('page', 'content', $mi->page['text'], ['id' => $p->id]);
                $DB->set_field('page', 'contentformat', FORMAT_HTML, ['id' => $p->id]);
            }
        }

        // Quiz + 3 questions
        $quizcm = cm_by_idnumber($course->id, "$short-QUIZ");
        if (!$quizcm && $quizmodule) {
            try {
                $mi = (object) ['modulename' => 'quiz', 'module' => $quizmodule->id, 'course' => $course->id, 'section' => 1,
                    'visible' => 1, 'visibleoncoursepage' => 1, 'name' => 'Knowledge check: ' . $full,
                    'intro' => '<p>Three questions. Pass mark 70%.</p>', 'introformat' => FORMAT_HTML, 'cmidnumber' => "$short-QUIZ",
                    'groupmode' => 0, 'groupingid' => 0, 'availabilityconditionsjson' => '',
                    'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0, 'overduehandling' => 'autosubmit', 'graceperiod' => 86400,
                    'preferredbehaviour' => 'deferredfeedback', 'canredoquestions' => 0, 'attempts' => 0, 'attemptonlast' => 0,
                    'grademethod' => QUIZ_GRADEHIGHEST, 'decimalpoints' => 2, 'questiondecimalpoints' => -1,
                    'questionsperpage' => 1, 'navmethod' => QUIZ_NAVMETHOD_FREE, 'shuffleanswers' => 1, 'sumgrades' => 0, 'grade' => 100,
                    'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '-', 'delay1' => 0, 'delay2' => 0,
                    'showuserpicture' => 0, 'showblocks' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionusegrade' => 1, 'completiongradeitemnumber' => 0, 'completionpassgrade' => 0, 'completionview' => 0,
                    'completionexpected' => 0, 'completionattemptsexhausted' => 0, 'completionminattempts' => 0,
                    'gradepass' => 70];
                foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $r) {
                    foreach (['during', 'immediately', 'open', 'closed'] as $w) {
                        $mi->{$r . $w} = ($r === 'overallfeedback' && $w === 'during') ? 0 : 1;
                    }
                }
                $mi = add_moduleinfo($mi, $course);
                $quizcm = $DB->get_record('course_modules', ['id' => $mi->coursemodule], '*', MUST_EXIST);
            } catch (\Throwable $e) {
                warn("quiz for $short: " . $e->getMessage());
                $quizcm = cm_by_idnumber($course->id, "$short-QUIZ");
            }
        }
        // Populate questions whenever the quiz exists but has no slots (also repairs a half-created quiz).
        if ($quizcm && !$DB->record_exists('quiz_slots', ['quizid' => $quizcm->instance])) {
            try {
                $quiz = $DB->get_record('quiz', ['id' => $quizcm->instance], '*', MUST_EXIST);
                // Questions live in a course-level standard question bank (Moodle 5.x: module contexts only).
                $bankcm = \core_question\local\bank\question_bank_helper::create_default_open_instance($course, 'UAT question bank',
                    \core_question\local\bank\question_bank_helper::TYPE_STANDARD);
                $bankcmid = is_object($bankcm) ? (int) ($bankcm->id ?? $bankcm->coursemodule ?? 0) : (int) $bankcm;
                $cat = question_get_default_category(context_module::instance($bankcmid)->id, true);
                $qs = [
                    ['multichoice', 'Which statement best describes the purpose of "' . $full . '"?', [
                        [$summary, 1.0], ['It is an optional social event for the team.', 0.0], ['It replaces the annual appraisal.', 0.0], ['It is only for new joiners.', 0.0]]],
                    ['multichoice', 'Who is responsible for applying what this course teaches?', [
                        ['Every employee in scope, every day.', 1.0], ['Only the compliance team.', 0.0], ['Only senior management.', 0.0], ['Nobody - it is informational.', 0.0]]],
                    ['truefalse', 'Completing this knowledge check with 70% or more completes the course.', true],
                ];
                $n = 0;
                foreach ($qs as [$type, $text, $answers]) {
                    $n++;
                    $idn = "$short-Q$n";
                    $existing = $DB->get_record_sql("SELECT q.id FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id
                        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid WHERE qbe.idnumber = :idn AND qbe.questioncategoryid = :cat",
                        ['idn' => $idn, 'cat' => $cat->id]);
                    if (!$existing) {
                        $form = new stdClass();
                        $form->qtype = $type;
                        $form->category = "{$cat->id},{$cat->contextid}";
                        $form->name = "$short Q$n";
                        $form->questiontext = ['text' => '<p>' . s($text) . '</p>', 'format' => FORMAT_HTML];
                        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
                        $form->defaultmark = 1;
                        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
                        $form->idnumber = $idn;
                        if ($type === 'multichoice') {
                            $form->penalty = 0.3333333;
                            $form->single = '1'; $form->shuffleanswers = 1; $form->answernumbering = 'abc'; $form->showstandardinstruction = 0;
                            $form->correctfeedback = ['text' => 'Correct.', 'format' => FORMAT_HTML];
                            $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                            $form->incorrectfeedback = ['text' => 'Not quite - re-read the page.', 'format' => FORMAT_HTML];
                            $form->shownumcorrect = 1;
                            $form->fraction = []; $form->answer = []; $form->feedback = [];
                            foreach ($answers as [$atext, $frac]) {
                                $form->fraction[] = (string) $frac;
                                $form->answer[] = ['text' => s($atext), 'format' => FORMAT_HTML];
                                $form->feedback[] = ['text' => '', 'format' => FORMAT_HTML];
                            }
                            $form->noanswers = count($answers); $form->numhints = 0;
                        } else {
                            $form->penalty = 1;
                            $form->correctanswer = $answers ? '1' : '0';
                            $form->feedbacktrue = ['text' => 'Correct.', 'format' => FORMAT_HTML];
                            $form->feedbackfalse = ['text' => 'Incorrect.', 'format' => FORMAT_HTML];
                        }
                        $q = question_bank::get_qtype($type)->save_question((object) ['qtype' => $type], $form);
                        $qid = (int) $q->id;
                    } else {
                        $qid = (int) $existing->id;
                    }
                    quiz_add_quiz_question($qid, $quiz, 0, 1);
                }
                \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
                $gi = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id, 'itemnumber' => 0]);
                if ($gi && (float) $gi->gradepass < 70) { $gi->gradepass = 70; $gi->update(); }
                say("  quiz for $short: 3 questions, pass 70");
            } catch (\Throwable $e) {
                warn("quiz for $short: " . $e->getMessage());
                $quizcm = cm_by_idnumber($course->id, "$short-QUIZ");
            }
        }

        // Completion criteria: Page (view) + Quiz (grade) with aggregation ALL.
        $crit = new completion_criteria_activity();
        $wanted = [$pagecm->id => 1];
        if ($quizcm) { $wanted[$quizcm->id] = 1; }
        $todo = [];
        foreach ($wanted as $cmid => $one) {
            if (!$DB->record_exists('course_completion_criteria', ['course' => $course->id, 'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY, 'moduleinstance' => $cmid])) {
                $todo[$cmid] = 1;
            }
        }
        if ($todo) {
            $critdata = (object) ['id' => $course->id, 'criteria_activity' => $todo];
            $crit->update_config($critdata); // by-reference parameter
        }
        foreach ([null, COMPLETION_CRITERIA_TYPE_ACTIVITY] as $ct) {
            $agg = new completion_aggregation(['course' => $course->id, 'criteriatype' => $ct]);
            if (empty($agg->id)) { $agg->course = $course->id; $agg->criteriatype = $ct; }
            $agg->setMethod(COMPLETION_AGGREGATION_ALL);
            $agg->save();
        }
        // Mandatory tag (lifecycle auto-enrol + compliance), price (storefront).
        if ($mandatory) {
            $tag = get_config('local_sentientia_lifecycle', 'mandatory_tag') ?: 'mandatory';
            core_tag_tag::set_item_tags('core', 'course', $course->id, context_course::instance($course->id), [$tag]);
        }
        if ($price > 0 && class_exists('\local_sentientia_catalog\commerce')) {
            try { \local_sentientia_catalog\commerce::set_course_price($course->id, (float) $price); } catch (\Throwable $e) { warn("price $short: " . $e->getMessage()); }
        }
    }
    rebuild_course_cache(0, true);

    // 5.3 skills (before completions so the course->skill observer has something to update)
    if (class_exists('\local_sentientia_skills\skills_manager')) {
        try {
            $sm = '\local_sentientia_skills\skills_manager';
            $cats = ['Payments Domain' => ['fa-credit-card', '#0066A7'], 'Leadership' => ['fa-users', '#1985DD']];
            $catid = [];
            foreach ($cats as $name => [$icon, $color]) {
                $catid[$name] = (int) $DB->get_field('local_sentientia_skill_cats', 'id', ['name' => $name]);
                if (!$catid[$name]) { $catid[$name] = $sm::create_category((object) ['name' => $name, 'icon' => $icon, 'color' => $color]); }
            }
            $skills = ['UPI & Digital Payments' => 'Payments Domain', 'AML & KYC Compliance' => 'Payments Domain', 'Consultative Selling' => 'Payments Domain',
                'People Leadership' => 'Leadership', 'Agent Network Management' => 'Payments Domain'];
            $skillid = [];
            $labels = [1 => 'Novice', 2 => 'Beginner', 3 => 'Competent', 4 => 'Proficient', 5 => 'Expert'];
            foreach ($skills as $name => $cat) {
                $skillid[$name] = (int) $DB->get_field('local_sentientia_skills', 'id', ['name' => $name]);
                if (!$skillid[$name]) {
                    $skillid[$name] = $sm::create_skill((object) ['categoryid' => $catid[$cat], 'name' => $name, 'max_level' => 5]);
                    foreach ($labels as $lvl => $label) { $sm::save_skill_level($skillid[$name], $lvl, $label); }
                }
            }
            $desig = ['Area Sales Manager' => ['UPI & Digital Payments' => 3, 'AML & KYC Compliance' => 3, 'Consultative Selling' => 4],
                'Sales Officer' => ['UPI & Digital Payments' => 2, 'AML & KYC Compliance' => 2, 'Consultative Selling' => 3],
                'Cluster Head' => ['People Leadership' => 4, 'Consultative Selling' => 4],
                'Agent Support Officer' => ['Agent Network Management' => 3, 'AML & KYC Compliance' => 2]];
            foreach ($desig as $d => $map) { foreach ($map as $s => $lvl) { $sm::save_designation_skill($d, $skillid[$s], $lvl); } }
            $cmap = ['UAT-AP-PRODUCT' => ['UPI & Digital Payments', 3], 'UAT-AP-AML-KYC' => ['AML & KYC Compliance', 3],
                'UAT-AP-SALES-FUND' => ['Consultative Selling', 3], 'UAT-AP-LEADERSHIP' => ['People Leadership', 3],
                'UAT-ZEEA-AGENT-BANKING' => ['Agent Network Management', 3], 'UAT-ZEEA-MOBILE-MONEY' => ['UPI & Digital Payments', 2]];
            foreach ($cmap as $short => [$s, $lvl]) { if ($c = course_by_short($short)) { $sm::save_course_skill($c->id, $skillid[$s], $lvl); } }
            say("  skills: " . count($skills) . " skills, 4 designation matrices, 6 course mappings");
        } catch (\Throwable $e) { warn('skills: ' . $e->getMessage()); }
    }
}

// ======================================================================================
// 6. users
// ======================================================================================
$CREDS = read_creds($CREDSOUT);
$NEWCREDS = [];
if (stage('users') && !$DRY) {
    say("--- users ---");
    $hasusertype = $DB->get_manager()->table_exists('local_sentientia_user_type');
    // Site admin: give it a tenant path so tenant labels resolve.
    if (empty($ADMIN->open_path)) { $DB->set_field('user', 'open_path', '/1', ['id' => $ADMIN->id]); }
    $n = 0;
    foreach ($USERS as $username => $u) {
        [$first, $last, $persona, $root, $path, $desig, $sup, $country, $tz, $lang, $utype, $roles, $enrols, $complete, $onboarded] = $u;
        $n++;
        $user = user_by_name($username);
        $password = $CREDS[$username] ?? null;
        if (!$user) {
            $password = make_password();
            $rec = (object) ['username' => $username, 'password' => $password, 'firstname' => $first, 'lastname' => $last,
                'email' => $username . '@uat.example.com', 'auth' => 'manual', 'confirmed' => 1, 'policyagreed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id, 'lang' => $lang, 'country' => $country, 'timezone' => $tz,
                'description' => 'UAT shared test account - ' . $persona, 'descriptionformat' => FORMAT_HTML,
                'open_path' => $path, 'open_designation' => $desig, 'open_employeeid' => sprintf('UAT-%03d', $n)];
            $uid = user_create_user($rec, true, true); // event fires -> lifecycle auto-enrol for mandatory courses
            $user = $DB->get_record('user', ['id' => $uid], '*', MUST_EXIST);
            say("  created $username ($persona)");
        } else {
            if ($RESETPW || $password === null) {
                $password = make_password();
                update_internal_user_password($user, $password, false);
                say("  $username exists - password " . ($RESETPW ? 'reset' : 'set (no prior CSV value)'));
            }
            $DB->update_record('user', (object) ['id' => $user->id, 'open_path' => $path, 'open_designation' => $desig, 'lang' => $lang]);
        }
        $NEWCREDS[$username] = [$persona, $password, $root];
        // Preferences.
        set_user_preference('airpay_onboarding_complete', $onboarded ? 1 : 0, $user->id);
        // User type row.
        if ($hasusertype && !$DB->record_exists('local_sentientia_user_type', ['userid' => $user->id])) {
            $DB->insert_record('local_sentientia_user_type', (object) ['userid' => $user->id, 'user_type' => $utype,
                'provisioning_source' => 'uat_seed', 'provisioned_at' => $NOW, 'timecreated' => $NOW, 'timemodified' => $NOW]);
            if ($utype === 'consumer' && $DB->get_manager()->table_exists('local_sentientia_consumer_profile')
                    && !$DB->record_exists('local_sentientia_consumer_profile', ['userid' => $user->id])) {
                $DB->insert_record('local_sentientia_consumer_profile', (object) ['userid' => $user->id, 'consent_marketing' => 0,
                    'consent_leaderboard' => 1, 'timecreated' => $NOW, 'timemodified' => $NOW]);
            }
        }
        // Roles.
        foreach ($roles as [$rshort, $where]) {
            $rid = role_id($rshort);
            if (!$rid) { warn("role $rshort missing for $username"); continue; }
            if ($where === 'system') {
                $ctx = $SYS;
            } else {
                $catid = category_id(substr($where, strlen('category:')));
                if (!$catid) { warn("category $where missing for $username"); continue; }
                $ctx = context_coursecat::instance($catid);
            }
            if (!$DB->record_exists('role_assignments', ['roleid' => $rid, 'userid' => $user->id, 'contextid' => $ctx->id])) {
                role_assign($rid, $user->id, $ctx->id);
            }
        }
    }
    // Supervisors (second pass, same-tenant only).
    foreach ($USERS as $username => $u) {
        if (empty($u[6])) { continue; }
        $me = user_by_name($username); $boss = user_by_name($u[6]);
        if ($me && $boss && (int) $me->open_supervisorid !== (int) $boss->id) {
            $DB->set_field('user', 'open_supervisorid', $boss->id, ['id' => $me->id]);
        }
    }
    if ($hasusertype && !$DB->record_exists('local_sentientia_user_type', ['userid' => $ADMIN->id])) {
        $DB->insert_record('local_sentientia_user_type', (object) ['userid' => $ADMIN->id, 'user_type' => 'operator',
            'provisioning_source' => 'uat_seed', 'provisioned_at' => $NOW, 'timecreated' => $NOW, 'timemodified' => $NOW]);
    }
    // Credentials CSV (the only place passwords are written).
    $fh = fopen($CREDSOUT, 'w');
    fputcsv($fh, ['username', 'persona', 'password', 'tenant', 'roles', 'landing_url']);
    fputcsv($fh, ['admin', 'Site administrator (existing)', $CREDS['admin'] ?? '(vaulted separately)', 'airpay /1', 'siteadmin', $CFG->wwwroot . '/admin/search.php']);
    foreach ($USERS as $username => $u) {
        [$persona, $password, $root] = $NEWCREDS[$username];
        $rolestr = implode('; ', array_map(fn($r) => $r[0] . '@' . $r[1], $u[11]));
        $tenant = [1 => 'airpay /1', 77 => 'public /77', 177 => 'ZEEA /177'][$root];
        fputcsv($fh, [$username, $persona, $password, $tenant, $rolestr, $CFG->wwwroot . '/my/']);
    }
    fclose($fh);
    @chmod($CREDSOUT, 0600);
    say("  credentials written to $CREDSOUT (" . count($USERS) . " accounts; passwords never printed)");
}

// ======================================================================================
// 7. enrol - enrolments, completions, certificates
// ======================================================================================
if (stage('enrol') && !$DRY) {
    say("--- enrolments + completions ---");
    $manual = enrol_get_plugin('manual');
    $studentid = role_id('student');
    $editingid = role_id('editingteacher');
    foreach ($USERS as $username => $u) {
        $user = user_by_name($username);
        if (!$user) { continue; }
        foreach ($u[12] as $spec) {
            [$short, $rolename] = array_pad(explode(':', $spec), 2, 'student');
            $course = course_by_short($short);
            if (!$course) { warn("course $short missing for $username"); continue; }
            $rid = $rolename === 'editingteacher' ? $editingid : $studentid;
            $inst = manual_instance($course);
            if (!$DB->record_exists('user_enrolments', ['enrolid' => $inst->id, 'userid' => $user->id])) {
                // learner2's INFOSEC enrolment is back-dated 45 days -> overdue against the 15-day compliance deadline.
                $start = ($username === 'uat_learner2_airpay' && $short === 'UAT-AP-INFOSEC') ? $NOW - 45 * $DAY : $NOW - 10 * $DAY;
                $manual->enrol_user($inst, $user->id, $rid, $start, 0);
            }
        }
        // Completions via the completion API (fires course_completed -> gamification / skills / xapi observers).
        foreach ($u[13] as $short) {
            $course = course_by_short($short);
            if (!$course) { continue; }
            try {
                $cc = new completion_completion(['userid' => $user->id, 'course' => $course->id]);
                if (!$cc->is_complete()) {
                    $ci = new completion_info($course);
                    foreach ($ci->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
                        $cm = get_coursemodule_from_id('', $criterion->moduleinstance);
                        if ($cm) { $ci->update_state($cm, COMPLETION_COMPLETE, $user->id, true); }
                        $ccc = new completion_criteria_completion(['userid' => $user->id, 'course' => $course->id, 'criteriaid' => $criterion->id]);
                        $ccc->mark_complete($NOW - 3 * $DAY);
                    }
                    $cc->mark_complete($NOW - 3 * $DAY);
                }
                // Certificate for cert-bearing courses (also on re-run, so a fixed certificate tool back-fills).
                $tplid = (int) ($course->open_certificateid ?? 0);
                if ($tplid && class_exists('\tool_certificate\template')
                        && !$DB->record_exists('tool_certificate_issues', ['userid' => $user->id, 'courseid' => $course->id, 'templateid' => $tplid, 'archived' => 0])) {
                    \tool_certificate\template::instance($tplid)->issue_certificate($user->id, null, [], 'tool_certificate', $course->id);
                }
            } catch (\Throwable $e) { warn("completion $username/$short: " . $e->getMessage()); }
        }
    }
    // In-progress realism: Priya has viewed the AML-KYC page; Fatma the Agent-Banking page; Deepa the UPI page.
    foreach ([['uat_learner_airpay', 'UAT-AP-AML-KYC'], ['uat_learner_zeea', 'UAT-ZEEA-AGENT-BANKING'], ['uat_learner_public', 'UAT-PUB-UPI-BASICS']] as [$un, $short]) {
        $user = user_by_name($un); $course = course_by_short($short);
        if ($user && $course && ($cm = cm_by_idnumber($course->id, "$short-PAGE"))) {
            try {
                $ci = new completion_info($course);
                $ci->update_state(get_coursemodule_from_id('page', $cm->id), COMPLETION_COMPLETE, $user->id, true);
            } catch (\Throwable $e) { warn("in-progress $un/$short: " . $e->getMessage()); }
        }
    }
    // Skills self-ratings for Priya.
    if (class_exists('\local_sentientia_skills\skills_manager') && ($priya = user_by_name('uat_learner_airpay'))) {
        foreach (['UPI & Digital Payments' => 2, 'AML & KYC Compliance' => 1, 'Consultative Selling' => 2] as $s => $lvl) {
            $sid = (int) $DB->get_field('local_sentientia_skills', 'id', ['name' => $s]);
            if ($sid && !$DB->record_exists('local_sentientia_user_skills', ['userid' => $priya->id, 'skillid' => $sid])) {
                try { \local_sentientia_skills\skills_manager::self_rate_skill($priya->id, $sid, $lvl, $priya->id); } catch (\Throwable $e) { warn("self-rate $s: " . $e->getMessage()); }
            }
        }
    }
    say("  done");
}

// ======================================================================================
// 8. objects - learning paths, program, classrooms, exam, evaluation, compliance, leaderboard, live
// ======================================================================================
if (stage('objects') && !$DRY) {
    say("--- plugin objects ---");
    $uid = fn(string $u) => (int) (user_by_name($u)->id ?? 0);
    $cid = fn(string $s) => (int) (course_by_short($s)->id ?? 0);

    // Learning paths
    if (class_exists('\local_sentientia_learningpath\path_manager')) {
        try {
            $pm = '\local_sentientia_learningpath\path_manager';
            foreach ([
                ['Sales Onboarding Path', 1, ['UAT-AP-PRODUCT', 'UAT-AP-SALES-FUND', 'UAT-AP-LEADERSHIP'], ['uat_learner_airpay', 'uat_learner2_airpay']],
                ['Agent Fundamentals', 177, ['UAT-ZEEA-AGENT-BANKING', 'UAT-ZEEA-MOBILE-MONEY', 'UAT-ZEEA-CUST-SERVICE'], ['uat_learner_zeea']],
            ] as [$name, $cc, $courses, $users]) {
                $pid = (int) $DB->get_field('local_sentientia_learningpath', 'id', ['name' => $name]);
                if (!$pid) {
                    $pid = $pm::create((object) ['name' => $name, 'description' => 'UAT sample learning path.', 'costcenterid' => $cc, 'status' => 1, 'visible' => 1]);
                    $pm::assign_courses($pid, array_values(array_filter(array_map($cid, $courses))));
                }
                $pm::enrol_users($pid, array_values(array_filter(array_map($uid, $users))));
            }
            say("  learning paths ok");
        } catch (\Throwable $e) { warn('learning paths: ' . $e->getMessage()); }
    }
    // Program
    if (class_exists('\local_sentientia_programs\program_manager')) {
        try {
            $pg = '\local_sentientia_programs\program_manager';
            $pid = (int) $DB->get_field('local_sentientia_programs', 'id', ['name' => 'Airpay Certified Professional']);
            if (!$pid) {
                $pid = $pg::create((object) ['name' => 'Airpay Certified Professional', 'description' => 'Two-level certification programme (UAT sample).',
                    'costcenterid' => 1, 'status' => 1, 'visible' => 1, 'completion_required' => 1]);
                foreach ([['Level 1 - Product', 'UAT-AP-PRODUCT'], ['Level 2 - Leadership', 'UAT-AP-LEADERSHIP']] as [$lname, $short]) {
                    $lid = $pg::create_level($pid, (object) ['name' => $lname]);
                    $pg::assign_courses_to_level($lid, [$cid($short)]);
                }
            }
            $pg::enrol_users($pid, array_values(array_filter([$uid('uat_learner_airpay'), $uid('uat_learner2_airpay'), $uid('uat_manager_airpay')])));
            say("  program ok");
        } catch (\Throwable $e) { warn('program: ' . $e->getMessage()); }
    }
    // Classrooms
    if (class_exists('\local_sentientia_classroom\session_manager')) {
        try {
            $cm = '\local_sentientia_classroom\session_manager';
            foreach ([
                ['Sales Fundamentals Bootcamp', 1, 'uat_trainer_airpay', 'Mumbai HQ, Training Room 2', [['uat_learner_airpay', $cm::ATT_PRESENT], ['uat_learner2_airpay', $cm::ATT_ABSENT]]],
                ['Customer Service Bootcamp - Zanzibar', 177, 'uat_admin_zeea', 'Zanzibar office', [['uat_learner_zeea', $cm::ATT_PRESENT]]],
            ] as [$name, $cc, $trainer, $loc, $roster]) {
                $clid = (int) $DB->get_field('local_sentientia_classroom', 'id', ['name' => $name]);
                if (!$clid) {
                    $clid = $cm::create((object) ['name' => $name, 'description' => 'UAT sample classroom.', 'costcenterid' => $cc,
                        'trainerid' => $uid($trainer), 'location' => $loc, 'capacity' => 20, 'status' => 1]);
                }
                $past = strtotime('10:00', $NOW - 7 * $DAY); $future = strtotime('10:00', $NOW + 7 * $DAY);
                $pastid = (int) $DB->get_field('local_sentientia_classroom_sessions', 'id', ['classroomid' => $clid, 'starttime' => $past]);
                if (!$pastid) {
                    $pastid = $cm::create_session($clid, (object) ['title' => 'Session 1 (held)', 'sessiondate' => $past, 'starttime' => $past,
                        'endtime' => $past + 7200, 'location' => $loc, 'trainerid' => $uid($trainer)]);
                }
                if (!$DB->record_exists('local_sentientia_classroom_sessions', ['classroomid' => $clid, 'starttime' => $future])) {
                    $cm::create_session($clid, (object) ['title' => 'Session 2 (upcoming)', 'sessiondate' => $future, 'starttime' => $future,
                        'endtime' => $future + 7200, 'location' => $loc, 'trainerid' => $uid($trainer)]);
                }
                $cm::enrol_users($clid, array_values(array_filter(array_map(fn($r) => $uid($r[0]), $roster))));
                foreach ($roster as [$un, $att]) {
                    if ($uid($un) && !$DB->record_exists('local_sentientia_classroom_attendance', ['sessionid' => $pastid, 'userid' => $uid($un)])) {
                        $cm::mark_attendance($pastid, $uid($un), $att);
                    }
                }
            }
            say("  classrooms ok");
        } catch (\Throwable $e) { warn('classrooms: ' . $e->getMessage()); }
    }
    // Exam
    if (class_exists('\local_sentientia_exams\exam_manager')) {
        try {
            $c = course_by_short('UAT-AP-SALES-FUND');
            $qcm = $c ? cm_by_idnumber($c->id, 'UAT-AP-SALES-FUND-QUIZ') : null;
            if ($qcm && !$DB->record_exists('local_sentientia_exams', ['quizid' => $qcm->instance])) {
                \local_sentientia_exams\exam_manager::create((object) ['name' => 'UAT Sales Fundamentals Exam', 'quizid' => (int) $qcm->instance,
                    'costcenterid' => 1, 'categoryid' => 0, 'duration' => 30, 'passinggrade' => 70, 'status' => 1, 'visible' => 1]);
            }
            say("  exam ok");
        } catch (\Throwable $e) { warn('exam: ' . $e->getMessage()); }
    }
    // Evaluation
    if (class_exists('\local_sentientia_evaluation\evaluation_manager')) {
        try {
            $em = '\local_sentientia_evaluation\evaluation_manager';
            $eid = (int) $DB->get_field('local_sentientia_evaluation', 'id', ['name' => 'Post-course feedback (Level 1)']);
            if (!$eid) {
                $eid = $em::create((object) ['name' => 'Post-course feedback (Level 1)', 'description' => 'Kirkpatrick level 1 reaction survey (UAT sample).',
                    'kirkpatrick_level' => 1, 'trigger_event' => 'course_completion', 'days_after' => 0, 'costcenterid' => 1, 'status' => 1, 'anonymous' => 0]);
                foreach ([['rating', 'How would you rate the overall quality of this course?'], ['nps', 'How likely are you to recommend this course to a colleague?'],
                          ['yesno', 'Did the course match its description?'], ['text', 'What would you improve?']] as [$type, $text]) {
                    $em::create_question((object) ['evaluationid' => $eid, 'questiontype' => $type, 'questiontext' => $text, 'required' => 1]);
                }
            }
            if ($p = $uid('uat_learner_airpay')) { $em::ensure_assignment($eid, $p, 'manual', $cid('UAT-AP-POSH'), $ADMIN->id); }
            say("  evaluation ok");
        } catch (\Throwable $e) { warn('evaluation: ' . $e->getMessage()); }
    }
    // Compliance
    if (class_exists('\local_sentientia_compliance_report\compliance_engine')) {
        try {
            $ce = '\local_sentientia_compliance_report\compliance_engine';
            foreach ([['UAT-AP-POSH', 1, 30], ['UAT-AP-AML-KYC', 1, 30], ['UAT-AP-INFOSEC', 1, 15], ['UAT-ZEEA-POSH-TZ', 177, 30]] as [$short, $ent, $days]) {
                if ($id = $cid($short)) { $ce::add_compliance_course($id, $ent, $days); }
            }
            if (($a = $uid('uat_author_airpay')) && !$DB->record_exists('local_compliance_exemptions', ['userid' => $a, 'courseid' => 0, 'is_active' => 1])) {
                $ce::exclude_user($a, 'UAT exemption sample (instructional designer, not in scope)');
            }
            $ce::rebuild_snapshot();
            say("  compliance ok (4 tracked courses, snapshot rebuilt)");
        } catch (\Throwable $e) { warn('compliance: ' . $e->getMessage()); }
    }
    // Leaderboard
    if (class_exists('\local_sentientia_leaderboard\board_manager')) {
        try {
            $bm = '\local_sentientia_leaderboard\board_manager';
            $bid = (int) $DB->get_field('local_sentientia_lb_boards', 'id', ['name' => '[UAT] Top completers - Airpay']);
            if (!$bid) {
                $bid = $bm::create(['name' => '[UAT] Top completers - Airpay', 'type' => $bm::TYPE_COMPLETION, 'scope' => $bm::SCOPE_COURSE,
                    'courseid' => $cid('UAT-AP-POSH'), 'tenantid' => 1, 'ownerid' => $ADMIN->id, 'recompute_seconds' => 600,
                    'settings' => ['top_n' => 10, 'show_full_name' => true]]);
            }
            \local_sentientia_leaderboard\ranking_engine::recompute($bid);
            say("  leaderboard ok");
        } catch (\Throwable $e) { warn('leaderboard: ' . $e->getMessage()); }
    }
    // Live session (owner = trainer, tenant from owner's open_path)
    if (class_exists('\local_sentientia_live\session_manager')) {
        try {
            $trainer = $uid('uat_trainer_airpay');
            $exists = false;
            foreach (\local_sentientia_live\session_manager::list_owned_by($trainer) as $s) { if (($s->title ?? '') === 'UAT Town-hall poll') { $exists = $s; } }
            if (!$exists && $trainer) {
                $sid = \local_sentientia_live\session_manager::create($trainer, 'UAT Town-hall poll', ['allow_anonymous' => 1]);
                $slides = [['multichoice', 'Which product line will grow fastest next quarter?', ['options' => ['Payment gateway', 'POS', 'Payouts', 'BBPS']]],
                    ['wordcloud', 'One word that describes our culture', []], ['openended', 'What should leadership start doing?', []],
                    ['rating', 'Rate today\'s town hall', ['max' => 5]], ['quiz', 'Which regulator supervises payment aggregators in India?', ['options' => ['RBI', 'SEBI', 'IRDAI', 'NPCI'], 'correct' => 0]],
                    ['ranking', 'Rank these priorities', ['items' => ['Compliance', 'Growth', 'Customer experience', 'Cost']]]];
                $first = 0;
                foreach ($slides as [$type, $title, $settings]) {
                    try { $slid = \local_sentientia_live\slide_manager::add($sid, $type, $title, $settings); if (!$first) { $first = $slid; } }
                    catch (\Throwable $e) { warn("live slide $type: " . $e->getMessage()); }
                }
                \local_sentientia_live\session_manager::start_session($sid);
                if ($first) { \local_sentientia_live\session_manager::set_current_slide($sid, $first); }
            }
            say("  live session ok");
        } catch (\Throwable $e) { warn('live: ' . $e->getMessage()); }
    }
    purge_all_caches();
}

// ======================================================================================
// 9. verify
// ======================================================================================
if (stage('verify')) {
    say("--- verify ---");
    $rows = [];
    foreach (array_merge(['admin' => null], $USERS) as $username => $u) {
        $user = user_by_name($username);
        if (!$user) { $rows[] = "$username: MISSING"; continue; }
        $tier = 'n/a';
        if (class_exists('\theme_sentientia\role_detector')) {
            try {
                $d = \theme_sentientia\role_detector::detect($user->id);
                $tier = implode(',', array_keys(array_filter($d, fn($v) => $v === true)));
            } catch (\Throwable $e) { $tier = 'detect error: ' . $e->getMessage(); }
        }
        $caps = [];
        foreach (['local/sentientia_skills:view', 'moodle/site:viewreports', 'local/sentientia_courses:manage', 'local/sentientia_manager:approve', 'local/sentientia_live:create', 'local/sentientia_authoring:generate'] as $cap) {
            if ($DB->record_exists('capabilities', ['name' => $cap]) && has_capability($cap, $SYS, $user)) { $caps[] = substr($cap, strrpos($cap, '/') + 1); }
        }
        $enrols = $DB->count_records_sql("SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = :u", ['u' => $user->id]);
        $done = $DB->count_records_select('course_completions', 'userid = :u AND timecompleted > 0', ['u' => $user->id]);
        $reports = $DB->count_records('user', ['open_supervisorid' => $user->id, 'deleted' => 0]);
        $rows[] = sprintf("%-24s path=%-9s tier=[%s] caps=[%s] enrol=%d done=%d reports=%d", $username, $user->open_path ?? '-', $tier, implode(',', $caps), $enrols, $done, $reports);
    }
    foreach ($rows as $r) { say("  $r"); }
    say("  courses: " . $DB->count_records_select('course', 'id > 1') . "  orgs: " . $DB->count_records('local_sentientia_org')
        . "  roles: " . implode(',', array_filter(array_map(fn($s) => role_id($s) ? "$s=" . role_id($s) : '', array_keys($ROLES)))));
    $adminrole = role_id('administrator');
    say("  administrator role id " . ($adminrole === 9 ? '9 OK' : "$adminrole (WARNING: expected 9)"));
}

if ($LOG) { say("=== " . count($LOG) . " warning(s) ==="); foreach ($LOG as $l) { say("  - $l"); } }
say("=== done " . date('c') . " ===");
