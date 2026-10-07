<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_classroom.
 *
 * Per CLAUDE.md section 5 every new user-visible feature ships behind a
 * default-OFF flag whose OFF state matches today's behaviour.
 *
 * @package local_sentientia_classroom
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.classroom.qr_attendance' => [
        'default'     => false,
        'description' => 'Entry point to QR attendance (2026-09-30 review). When OFF
                          (default), the trainer\'s attendance page
                          (/local/sentientia_classroom/attendance.php) has no link to
                          the QR page, as before. When ON, a
                          "Show QR for this session" link to
                          /local/sentientia_pages/qr_attendance.php appears for
                          users holding local/sentientia_classroom:attendance. The
                          flag only controls the link: the QR page, the scan page
                          and their own checks (capability, tenant scope, session
                          window, signed token) are unchanged.',
    ],

    'sentientia.classroom.bulk_enrol_audience' => [
        'default'     => false,
        'description' => 'Bulk enrolment of a classroom by target audience (owner decision XC-CLS-ENROL,
                          2026-10-07). The button "Bulk enrol by target audience" on a classroom\'s Users
                          tab, its form, and the two web services behind it
                          (local_sentientia_classroom_preview_audience and _bulk_enrol_by_audience) gated
                          on local/sentientia_classroom:enrol, which no db/access.php declares, so the
                          surface refused everyone, site admins included. It now needs
                          local/sentientia_classroom:manage (the manager archetype, and any role the
                          signed capability allow-list grants it to) AND this flag. When OFF (default)
                          the button is not shown and the form and both web services refuse. When ON it
                          still stays inside the caller\'s own tenant (ADR-031), refuses an empty filter
                          (a filter must name at least one criterion; a whole-tenant enrolment names the
                          tenant\'s org_path) and enrols at most 2,000 users per call. Flip it only after
                          the visual evidence has been reviewed.',
    ],

    'sentientia.classroom.import_history' => [
        'default'     => false,
        'description' => 'Readers for the classroom history the BizLMS import brings in
                          (ADR-032, classroom code fix 12). When OFF (default) nothing
                          changes on screen: the classroom overview, the roster table and
                          the pages are as before, and /local/sentientia_classroom/my.php
                          does not exist. When ON: the overview shows the training dates,
                          the date completed, every trainer, the linked courses and the
                          classroom logo; the roster table gains Completion, Completed on
                          and Hours columns; the classroom logo is served; and a learner
                          can open "My classrooms" (my.php) to see their own classrooms,
                          sessions, attendance and completion. The flag only controls what
                          is shown: the import, the tenant rules (ADR-031) and the
                          protection of imported history do not depend on it.',
    ],

];
