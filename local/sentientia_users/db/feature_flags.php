<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_users.
 *
 * Read by local_sentientia_platform\feature_flags::load_registry(): a key that is not registered here (or in
 * another plugin's db/feature_flags.php) makes ::set() throw. Both flags are default OFF (CLAUDE.md section 5):
 * they gate the two places the imported BizLMS history becomes visible to a learner or a manager. The importer
 * itself does not depend on either (it is CLI-gated, ADR-032) and never flips one.
 *
 * @package local_sentientia_users
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── ADR-032 users: earlier training records on the profile ───────────────
    'sentientia.users.legacy_transcript' => [
        'default'     => false,
        'description' => 'Earlier training records (ADR-032, users). When ON, a profile shows an
                          "Earlier training records (imported)" section: the 2015-2016 transcript that
                          the BizLMS import copied into local_sentientia_users_transcript (title, type,
                          completion date, status, score and hours, with the status text as it was loaded).
                          The rows are history only: they are never added to the completed-course totals,
                          the grades widget or any report total. It appears only on a profile the viewer
                          is already allowed to open (own profile, same tenant, or cross-tenant), and only
                          for rows matched to that learner. BizLMS never showed this table to anyone, so
                          the section is new: do not turn it ON for a customer before the page has been
                          reviewed with screenshots.',
    ],

    // ─── ADR-032 users: position and domain labels on the profile ─────────────
    'sentientia.users.position_labels' => [
        'default'     => false,
        'description' => 'Position and domain labels (ADR-032, users, gap G4). When ON, the employee
                          profile detail grid shows the learner\'s position and domain by name, read
                          from the lookups the BizLMS import copied with their ids kept
                          (local_sentientia_users_position and _domain; user.open_positionid and
                          open_domainid hold the ids). The Sentientia profile showed neither before,
                          so the two lines are new: do not turn this ON for a customer before the page
                          has been reviewed with screenshots.',
    ],

];
