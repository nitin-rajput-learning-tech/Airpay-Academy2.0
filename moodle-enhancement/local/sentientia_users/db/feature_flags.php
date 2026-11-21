<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_users.
 *
 * Read by local_sentientia_platform\feature_flags::load_registry(): a key that is not registered here (or in
 * another plugin's db/feature_flags.php) makes ::set() throw. All three flags are default OFF (CLAUDE.md section 5):
 * they gate the three places the imported BizLMS history becomes visible to a learner or a manager. The importer
 * itself does not depend on any (it is CLI-gated, ADR-032) and never flips one.
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

    // ─── ADR-032 users: the imported HRMS sync history on the admin pages ─────
    'sentientia.users.imported_sync_history' => [
        'default'     => false,
        'description' => 'Imported HRMS sync history (ADR-032, users; XC-IMPORTED-HISTORY-READERS, 2026-10-07). When
                          ON, the HRMS history page (sync_runs.php) lists the upload runs the BizLMS import copied
                          (source "Imported from BizLMS"), and a run\'s detail page (sync_run_detail.php) opens for
                          them. When OFF those runs are left out of the list and their detail page shows a notice
                          instead, so the imported history stays out of sight until it has been reviewed. Native
                          runs are never behind this flag. Either way a run\'s rejected lines (the e-mail address,
                          employee code and name of a prospective employee) are shown only to the person who
                          uploaded the run and to platform administrators, imported or native; the run counts stay
                          visible to everyone who may open the page for their own tenant. Do not turn this ON for a
                          customer before the pages have been reviewed with screenshots.',
    ],

    // ─── ADR-036 D9: external trainer accounts (session 5) ───────────────────
    'sentientia.users.external_trainer.enabled' => [
        'default'     => false,
        'description' => 'External trainer accounts (ADR-036 D9, plan C6, session 5). When OFF (default) the
                          page /local/sentientia_users/external_trainers.php refuses everyone, the Manage
                          Users page shows no "External trainers" button, and no invite can be sent:
                          the platform behaves as before. When ON, a holder of
                          local/sentientia_users:inviteexternaltrainer (the tenant administrator role; site
                          admins) can invite an external trainer by e-mail into their own tenant: a
                          manual-login account with a forced password change, the restricted role
                          sentientia_externaltrainer (classroom view and attendance only), names-only
                          rosters and their own classrooms only. The account expires 30 days after the
                          last session they train (invite date plus 30 days before any assignment); the
                          daily task suspends it then, reminds the admins 7 days before, and never
                          deletes it. Every invite, extension, reactivation, expiry and revoke is a
                          logged event. Accounts that already exist keep their restrictions and expiry
                          if the flag is turned OFF again; the expiry task runs either way. Do not turn
                          this ON on a shared site before the platform part (user-type registration,
                          audit allow-list) has landed and the pages have been reviewed with screenshots.',
    ],

];
