<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_recompletion.
 *
 * Per CLAUDE.md section 5 every new user-visible feature ships behind a
 * default-OFF flag. Both flags below came with the ADR-032 BizLMS import
 * (2026-09-30). Neither is ever flipped by the import itself.
 *
 * @package local_sentientia_recompletion
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.recompletion.evidence_view' => [
        'default'     => false,
        'description' => 'Recompletion evidence view (ADR-032, 2026-09-30). When OFF
                          (default) /local/sentientia_recompletion/history_detail.php
                          refuses to open and history.php shows no link to it. When ON,
                          a reader who holds local/sentientia_recompletion:view and whose
                          tenant covers the learner can open, for one reset, what it
                          ended: the course completion, criteria, activity completions,
                          quiz attempts and grades, SCORM tracking, LTI grades and
                          questionnaire answers that the BizLMS plugin archived (or the
                          Sentientia engine archived before it deleted them). Tenant
                          scope is the same as the history page. It also governs the
                          BizLMS resets on the history page itself (owner decision
                          recompletion.legacy_rows_on_history_page, 2026-10-07): while it
                          is OFF history.php lists only the resets the Sentientia engine
                          made, with no Legacy badge and no estimated (~) time, so the
                          page looks as it did before the import; the imported-rule
                          marker on the rules page stays visible. Flip it only after the
                          visual evidence has been reviewed.',
    ],

    'sentientia.recompletion.run_rules' => [
        'default'     => false,
        'description' => 'Daily recompletion task (ADR-032, 2026-09-30). When OFF
                          (default) the 03:15 scheduled task run_rules does nothing, so a
                          restored database that carries imported BizLMS rules can never
                          start resetting learners on its first night. When ON it runs
                          every ENABLED rule, exactly as before. Imported rules are
                          always created disabled; turning this flag on does not enable
                          them. The flag is read site-wide (a scheduled task has no
                          user, so only the global value counts: a customer or tenant
                          override of this flag does not switch the task on). Manual
                          resets and the CLI are not gated by it.',
    ],

];
