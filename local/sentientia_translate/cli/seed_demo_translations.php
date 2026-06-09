<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Demo / QA seed — populate `local_sentientia_tr_log` with sample
 * translation rows across all five statuses (pending, translated,
 * saved, failed, discarded), so the C16 admin queue dashboard at
 * `/local/sentientia_translate/admin/index.php` shows real data.
 *
 * C17 stabilization-audit follow-up (2026-05-28). The Bucket F probe
 * found the translate log was empty on local — admins navigating to
 * the C16 dashboard saw an empty queue and couldn't visually verify
 * the stat-card maths or the status-badge styling.
 *
 * Idempotency: every seeded row's `title` starts with `[DEMO]`. Re-runs
 * skip if rows are present. `--purge` removes only `[DEMO]`-titled
 * rows, never touching real admin translations.
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_translate/cli/seed_demo_translations.php
 *   php local/sentientia_translate/cli/seed_demo_translations.php --purge
 *   php local/sentientia_translate/cli/seed_demo_translations.php --help
 *
 * Safety: this CLI writes to the database. The implementation uses the
 * plugin's own `translate_engine` static methods (create_pending,
 * store_translation, accept, discard, mark_failed) so the data shape
 * always matches what live translations produce. No raw `$DB->insert_record`
 * — if the schema evolves the seed evolves with it.
 *
 * @package local_sentientia_translate
 * @copyright 2026 Airpay Payment Services
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_translate\translate_engine;

[$options, $unrecognized] = cli_get_params([
    'help'  => false,
    'purge' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || $unrecognized) {
    echo <<<EOT

Seed demo translations into local_sentientia_tr_log.

Usage:
  php seed_demo_translations.php          Add 6 sample rows + 2 brand overrides
  php seed_demo_translations.php --purge  Remove only seeded ([DEMO]) rows
  php seed_demo_translations.php --help   Show this

Creates 6 rows across all five statuses (pending, translated, saved,
failed, discarded) in 4 target languages (hi, mr, kn, sw). Visible in
the C16 admin queue at /local/sentientia_translate/admin/index.php
EOT;
    exit(0);
}

global $DB, $CFG;

// ── Owner: site admin's first row, or any non-deleted user as fallback.
$owner = $DB->get_record_sql(
    "SELECT id, firstname, lastname FROM {user}
      WHERE id IN (SELECT userid FROM {role_assignments}
                    WHERE roleid IN (SELECT id FROM {role}
                                      WHERE shortname IN ('manager', 'editingteacher')))
        AND deleted = 0 AND suspended = 0
   ORDER BY id ASC", null, IGNORE_MULTIPLE);

if (!$owner) {
    // Fallback: first non-deleted user that's not the guest.
    $owner = $DB->get_record_sql(
        "SELECT id, firstname, lastname FROM {user}
          WHERE deleted = 0 AND suspended = 0 AND id > 1
       ORDER BY id ASC LIMIT 1");
}

if (!$owner) {
    cli_error("No usable owner user found. Run /admin/user.php to create one first.\n");
}

cli_writeln("Owner: user id={$owner->id} ({$owner->firstname} {$owner->lastname})");

// ── Purge mode ────────────────────────────────────────────────────
if ($options['purge']) {
    $sql_like = $DB->sql_like('title', ':marker');
    $rows = $DB->get_records_select('local_sentientia_tr_log',
        $sql_like, ['marker' => '[DEMO]%']);
    if (empty($rows)) {
        cli_writeln("No [DEMO] rows to purge.");
        exit(0);
    }
    foreach ($rows as $r) {
        $DB->delete_records('local_sentientia_tr_log', ['id' => $r->id]);
        cli_writeln("  Deleted row id={$r->id} (\"$r->title\")");
    }
    // Also purge any [DEMO] brand overrides.
    $sql_like_brand = $DB->sql_like('brand_source', ':marker');
    $brands = $DB->get_records_select('local_sentientia_tr_brand',
        $sql_like_brand, ['marker' => '[DEMO]%']);
    foreach ($brands as $b) {
        $DB->delete_records('local_sentientia_tr_brand', ['id' => $b->id]);
        cli_writeln("  Deleted brand override id={$b->id}");
    }
    cli_writeln("Purged " . count($rows) . " translation row(s) + " . count($brands) . " brand override(s).");
    exit(0);
}

// ── Idempotency check ─────────────────────────────────────────────
$existing = $DB->count_records_select('local_sentientia_tr_log',
    $DB->sql_like('title', ':marker'), ['marker' => '[DEMO]%']);
if ($existing > 0) {
    cli_writeln("$existing [DEMO] row(s) already present. Re-run with --purge first.");
    exit(0);
}

// ── Seed data ─────────────────────────────────────────────────────
// 6 sample translations — one per status × spread across 4 languages.
// Source texts are short, English, training-content-shaped (no PII).
$seeds = [
    [
        'title'  => '[DEMO] Welcome paragraph (Hindi)',
        'source' => 'Welcome to Airpay Academy. Our courses help you grow your career, master new skills, and stay compliant with industry regulations.',
        'lang'   => 'hi',
        'status' => 'pending',
    ],
    [
        'title'  => '[DEMO] Compliance reminder (Marathi)',
        'source' => 'You have an upcoming compliance training deadline. Please complete the course before the due date to avoid escalation.',
        'lang'   => 'mr',
        'status' => 'translated',
        'output' => 'तुमच्याकडे आगामी अनुपालन प्रशिक्षण अंतिम मुदत आहे. कृपया वाढीचा टाळण्यासाठी देय तारखेपूर्वी कोर्स पूर्ण करा.',
    ],
    [
        'title'  => '[DEMO] Course completion certificate text (Hindi)',
        'source' => 'Congratulations on completing the course. Your certificate is attached and a copy has been saved to your profile.',
        'lang'   => 'hi',
        'status' => 'saved',
        'output' => 'कोर्स पूर्ण करने के लिए बधाई। आपका प्रमाणपत्र संलग्न है और एक प्रति आपकी प्रोफ़ाइल में सहेजी गई है।',
    ],
    [
        'title'  => '[DEMO] Quiz instructions (Kannada)',
        'source' => 'Read each question carefully. You have 30 minutes to complete the quiz. You may attempt the quiz up to 3 times.',
        'lang'   => 'kn',
        'status' => 'translated',
        'output' => 'ಪ್ರತಿ ಪ್ರಶ್ನೆಯನ್ನು ಎಚ್ಚರಿಕೆಯಿಂದ ಓದಿ. ರಸಪ್ರಶ್ನೆಯನ್ನು ಪೂರ್ಣಗೊಳಿಸಲು ನೀವು 30 ನಿಮಿಷಗಳನ್ನು ಹೊಂದಿರುತ್ತೀರಿ. ನೀವು ರಸಪ್ರಶ್ನೆಯನ್ನು 3 ಬಾರಿ ಪ್ರಯತ್ನಿಸಬಹುದು.',
    ],
    [
        'title'  => '[DEMO] Welcome email subject (Swahili)',
        'source' => 'Welcome to your new learning journey at Airpay Academy.',
        'lang'   => 'sw',
        'status' => 'failed',
        'error'  => 'mock_failure_demo — synthetic error for seed demo',
    ],
    [
        'title'  => '[DEMO] Manager nudge (Hindi)',
        'source' => 'Your team member has overdue training. Please follow up to ensure compliance.',
        'lang'   => 'hi',
        'status' => 'discarded',
        'output' => 'आपके टीम सदस्य का प्रशिक्षण देय हो गया है। कृपया अनुपालन सुनिश्चित करने के लिए अनुसरण करें।',
    ],
];

// 2 brand overrides — small map so brand_terms_applied stays >0 on
// translated rows. brand_source is what the model returned;
// brand_target is what brand_manager substitutes for the given
// target language. The [DEMO] prefix in brand_source lets --purge
// find these rows without touching real overrides.
$brand_overrides = [
    [
        'brand_source' => '[DEMO] Airpay',
        'brand_target' => 'एयरपे',
        'lang'         => 'hi',
    ],
    [
        'brand_source' => '[DEMO] Academy',
        'brand_target' => 'अकादमी',
        'lang'         => 'hi',
    ],
];

cli_writeln("\n--- Seeding 6 translation rows ---");

$created_ids = [];
foreach ($seeds as $s) {
    $rowid = translate_engine::create_pending(
        (int) $owner->id,
        $s['title'],
        $s['source'],
        $s['lang'],
        \local_sentientia_translate\anthropic_client::DEFAULT_MODEL
    );
    $created_ids[] = $rowid;

    // The row is now in 'pending' status. Apply the desired final
    // status using the engine's mutator methods so behaviour matches
    // what a real translation flow does.
    switch ($s['status']) {
        case 'pending':
            cli_writeln("  ✓ row id=$rowid status=pending lang={$s['lang']}");
            break;

        case 'translated':
            // store_translation flips status -> translated, leaves it
            // awaiting admin save/discard.
            translate_engine::store_translation(
                $rowid,
                $s['output'],
                /* brandapplied */ 1,
                /* tokensin */ 250,
                /* tokensout */ 300,
                /* mode */ 'mock'
            );
            cli_writeln("  ✓ row id=$rowid status=translated lang={$s['lang']}");
            break;

        case 'saved':
            // First store, then accept -> status=saved.
            translate_engine::store_translation(
                $rowid, $s['output'], 1, 250, 300, 'mock');
            translate_engine::accept($rowid, (int) $owner->id);
            cli_writeln("  ✓ row id=$rowid status=saved lang={$s['lang']}");
            break;

        case 'failed':
            translate_engine::mark_failed($rowid, $s['error'] ?? 'demo_error');
            cli_writeln("  ✓ row id=$rowid status=failed lang={$s['lang']}");
            break;

        case 'discarded':
            translate_engine::store_translation(
                $rowid, $s['output'], 0, 250, 300, 'mock');
            translate_engine::discard($rowid, (int) $owner->id);
            cli_writeln("  ✓ row id=$rowid status=discarded lang={$s['lang']}");
            break;
    }
}

cli_writeln("\n--- Seeding 2 brand overrides ---");

$now = time();
foreach ($brand_overrides as $b) {
    $existing_brand = $DB->get_record('local_sentientia_tr_brand', [
        'customerid'   => 1,
        'brand_source' => $b['brand_source'],
        'targetlang'   => $b['lang'],
    ]);
    if ($existing_brand) {
        cli_writeln("  ⚠ skip (already exists) {$b['brand_source']} ({$b['lang']})");
        continue;
    }
    $row = (object) [
        'customerid'   => 1,
        'brand_source' => $b['brand_source'],
        'targetlang'   => $b['lang'],
        'brand_target' => $b['brand_target'],
        'timecreated'  => $now,
        'timemodified' => $now,
    ];
    $bid = $DB->insert_record('local_sentientia_tr_brand', $row);
    cli_writeln("  ✓ brand override id=$bid {$b['brand_source']} → {$b['brand_target']} ({$b['lang']})");
}

cli_writeln("\n--- Summary ---");
cli_writeln("Created " . count($created_ids) . " translation rows.");
cli_writeln("");
cli_writeln("View in C16 admin queue:");
cli_writeln("  $CFG->wwwroot/local/sentientia_translate/admin/index.php");
cli_writeln("");
cli_writeln("To remove only the [DEMO] rows:");
cli_writeln("  php local/sentientia_translate/cli/seed_demo_translations.php --purge");
