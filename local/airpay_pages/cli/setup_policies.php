<?php
/**
 * Phase 8B: Configure DPDP Privacy/Consent using Moodle's policy tool.
 * Creates Privacy Policy and Terms of Use as Moodle managed policies.
 * Users must accept before accessing the platform.
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/admin/tool/policy/classes/api.php');

global $DB;

echo "=== PHASE 8B: DPDP Privacy/Consent Setup ===\n\n";

// 1. Enable the policy tool
echo "1. Enabling site policies handler:\n";
$currenthandler = get_config('core', 'sitepolicyhandler');
if ($currenthandler !== 'tool_policy') {
    set_config('sitepolicyhandler', 'tool_policy');
    echo "   Set sitepolicyhandler = tool_policy\n";
} else {
    echo "   Already set to tool_policy\n";
}

// 2. Check if policies already exist
$existing = $DB->get_records('tool_policy', [], '', 'id,sortorder,currentversionid');
if (!empty($existing)) {
    echo "\n2. Policies already exist:\n";
    foreach ($existing as $p) {
        $vname = $p->currentversionid ? $DB->get_field('tool_policy_versions', 'name', ['id' => $p->currentversionid]) : 'no version';
        echo "   - {$vname} (id={$p->id})\n";
    }
    echo "\nSkipping creation. Delete existing policies via Site Admin to re-create.\n";
} else {
    echo "\n2. Creating policies:\n";

    // Privacy Policy
    $privacypolicy = new stdClass();
    $privacypolicy->sortorder = 1;
    $privacypolicy->currentversionid = null;
    $policyid1 = $DB->insert_record('tool_policy', $privacypolicy);

    // Create version for privacy policy
    $version1 = new stdClass();
    $version1->name = 'Privacy Policy';
    $version1->policyid = $policyid1;
    $version1->revision = 'v1.0';
    $version1->summary = 'Airpay Academy Privacy Policy — describes how we collect, use, and protect your personal information under DPDP 2023.';
    $version1->summaryformat = FORMAT_HTML;
    $version1->content = file_get_contents($CFG->dirroot . '/local/airpay_pages/pages/privacy.html');
    $version1->contentformat = FORMAT_HTML;
    $version1->type = 0; // site
    $version1->audience = 0; // all users
    $version1->optional = 0; // compulsory
    $version1->archived = 0;
    $version1->timecreated = time();
    $version1->timemodified = time();
    $version1->usermodified = 2; // admin
    $versionid1 = $DB->insert_record('tool_policy_versions', $version1);
    $DB->set_field('tool_policy', 'currentversionid', $versionid1, ['id' => $policyid1]);
    echo "   Created: Privacy Policy (id=$policyid1, version=$versionid1)\n";

    // Terms of Use
    $terms = new stdClass();
    $terms->sortorder = 2;
    $terms->currentversionid = null;
    $policyid2 = $DB->insert_record('tool_policy', $terms);

    $version2 = new stdClass();
    $version2->name = 'Terms of Use';
    $version2->policyid = $policyid2;
    $version2->revision = 'v1.0';
    $version2->summary = 'Airpay Academy Terms of Use — governs your use of the learning platform, intellectual property, and user responsibilities.';
    $version2->summaryformat = FORMAT_HTML;
    $version2->content = file_get_contents($CFG->dirroot . '/local/airpay_pages/pages/terms.html');
    $version2->contentformat = FORMAT_HTML;
    $version2->type = 0;
    $version2->audience = 0;
    $version2->optional = 0; // compulsory
    $version2->archived = 0;
    $version2->timecreated = time();
    $version2->timemodified = time();
    $version2->usermodified = 2;
    $versionid2 = $DB->insert_record('tool_policy_versions', $version2);
    $DB->set_field('tool_policy', 'currentversionid', $versionid2, ['id' => $policyid2]);
    echo "   Created: Terms of Use (id=$policyid2, version=$versionid2)\n";
}

// 3. Auto-accept for existing users (superadmin + test users)
echo "\n3. Auto-accepting policies for existing users:\n";
$users = $DB->get_records_select('user', 'deleted = 0 AND id > 1', [], '', 'id,username');
$policies = $DB->get_records('tool_policy', [], '', 'id,currentversionid');

foreach ($users as $user) {
    foreach ($policies as $policy) {
        if (empty($policy->currentversionid)) continue;
        $exists = $DB->record_exists('tool_policy_acceptances', [
            'policyversionid' => $policy->currentversionid,
            'userid' => $user->id,
        ]);
        if (!$exists) {
            $acceptance = new stdClass();
            $acceptance->policyversionid = $policy->currentversionid;
            $acceptance->userid = $user->id;
            $acceptance->status = 1; // accepted
            $acceptance->lang = 'en';
            $acceptance->usermodified = $user->id;
            $acceptance->timecreated = time();
            $acceptance->timemodified = time();
            $DB->insert_record('tool_policy_acceptances', $acceptance);
        }
    }
    echo "   Accepted for: {$user->username}\n";
}

echo "\n=== DPDP CONSENT READY ===\n";
echo "New users will be prompted to accept Privacy Policy + Terms of Use before accessing the platform.\n";
echo "Manage at: Site Admin > Users > Privacy and policies > Manage policies\n";
