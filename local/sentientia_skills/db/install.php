<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Seed fintech industry skill taxonomy on install.
 */
function xmldb_local_sentientia_skills_install() {
    global $DB;

    if ($DB->count_records('local_sentientia_skill_cats') > 0) {
        return;
    }

    $now = time();

    // Skill categories.
    $categories = [
        ['name' => 'Compliance & Risk',    'icon' => 'fa-shield',         'color' => '#dc2626', 'sort_order' => 1],
        ['name' => 'Financial Literacy',   'icon' => 'fa-inr',            'color' => '#0f7a73', 'sort_order' => 2],
        ['name' => 'Technical',            'icon' => 'fa-code',           'color' => '#7c3aed', 'sort_order' => 3],
        ['name' => 'Sales & Business',     'icon' => 'fa-briefcase',      'color' => '#d97706', 'sort_order' => 4],
        ['name' => 'Leadership',           'icon' => 'fa-users',          'color' => '#0066A7', 'sort_order' => 5],
        ['name' => 'Communication',        'icon' => 'fa-comments',       'color' => '#16a34a', 'sort_order' => 6],
        ['name' => 'Product Knowledge',    'icon' => 'fa-cube',           'color' => '#ea580c', 'sort_order' => 7],
        ['name' => 'Operations',           'icon' => 'fa-cog',            'color' => '#6b7280', 'sort_order' => 8],
    ];

    $catids = [];
    foreach ($categories as $cat) {
        $cat['description'] = '';
        $cat['timecreated'] = $now;
        $catids[$cat['name']] = $DB->insert_record('local_sentientia_skill_cats', (object)$cat);
    }

    // Skills per category.
    $skills = [
        'Compliance & Risk' => [
            'Anti-Money Laundering (AML/KYC)',
            'Prevention of Sexual Harassment (POSH)',
            'Data Privacy & GDPR',
            'RBI Regulations',
            'Fraud Detection & Prevention',
            'Information Security Awareness',
        ],
        'Financial Literacy' => [
            'Banking Fundamentals',
            'Insurance & Risk Management',
            'Investment & Capital Markets',
            'Tax Planning & Compliance',
            'Credit & Lending',
            'Digital Payments Ecosystem',
        ],
        'Technical' => [
            'JIRA & Project Tools',
            'API Integration',
            'Database Management',
            'Cybersecurity',
            'Cloud Infrastructure',
            'Software Development',
        ],
        'Sales & Business' => [
            'Negotiation Skills',
            'Sales Pitching',
            'Client Relationship Management',
            'CRM Tools',
            'Business Development',
            'Market Analysis',
        ],
        'Leadership' => [
            'Team Management',
            'Strategic Thinking',
            'Decision Making',
            'Change Management',
            'Coaching & Mentoring',
            'Conflict Resolution',
        ],
        'Communication' => [
            'Written Communication',
            'Verbal Communication',
            'Presentation Skills',
            'Cross-cultural Communication',
            'Email Etiquette',
            'Active Listening',
        ],
        'Product Knowledge' => [
            'Payment Gateway (PG) Products',
            'ERP & Business Solutions',
            'Acquiring & Settlement',
            'Money Transfer & Wallets',
            'Tanzania Products',
            'Business Correspondent Products',
        ],
        'Operations' => [
            'Process Management',
            'Quality Assurance',
            'Service Delivery',
            'Dispute & Fraud Operations',
            'Reconciliation',
            'Vendor Management',
        ],
    ];

    foreach ($skills as $catname => $skilllist) {
        $catid = $catids[$catname];
        $order = 1;
        foreach ($skilllist as $skillname) {
            $DB->insert_record('local_sentientia_skills', (object)[
                'categoryid'  => $catid,
                'name'        => $skillname,
                'description' => '',
                'max_level'   => 5,
                'sort_order'  => $order++,
                'timecreated' => $now,
            ]);
        }
    }
}
