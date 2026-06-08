<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\analyzer;

defined('MOODLE_INTERNAL') || die();

/**
 * Risk analyzer — computes a 0..100 risk score from the events table
 * after the session is finalized.
 *
 * Score thresholds:
 *   0-30  → clean (auto-pass review)
 *   31-60 → warn  (auto-flag for review, low priority)
 *   61+   → fail  (auto-flag for review, high priority)
 *
 * Event severity weights:
 *   face_lost                 → 5 per occurrence
 *   multiple_faces            → 25 per occurrence (high — strong cheat signal)
 *   tab_switch                → 8 per occurrence
 *   window_blur               → 4 per occurrence
 *   mic_noise                 → 2 per occurrence
 *   clipboard_paste           → 10 per occurrence
 *   fullscreen_exit           → 15 per occurrence
 *
 * Plus identity step result:
 *   identity match < threshold → +30 (very strong signal of impersonation)
 *
 * Score is capped at 100.
 */
class risk_analyzer {

    private const WEIGHTS = [
        'face_lost'        => 5.0,
        'multiple_faces'   => 25.0,
        'tab_switch'       => 8.0,
        'window_blur'      => 4.0,
        'mic_noise'        => 2.0,
        'clipboard_paste'  => 10.0,
        'fullscreen_exit'  => 15.0,
    ];

    public static function analyze(int $sessionid): array {
        global $DB;

        $events = $DB->get_records('local_sentientia_proctor_events',
            ['sessionid' => $sessionid], 'timecreated ASC');

        $counts = [];
        foreach ($events as $e) {
            $counts[$e->event_type] = ($counts[$e->event_type] ?? 0) + 1;
        }

        $score = 0.0;
        foreach ($counts as $type => $n) {
            $w = self::WEIGHTS[$type] ?? 0.0;
            $score += $w * $n;
        }

        // Identity step contribution.
        $session = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $sessionid]);
        if ($session && $session->identity_id) {
            $id_row = $DB->get_record('local_sentientia_proctor_identity',
                ['id' => $session->identity_id]);
            if ($id_row && !$id_row->passed) {
                $score += 30;
            }
        }

        $score = min(100.0, $score);

        if ($score >= 61) {
            $decision = 'fail';
        } else if ($score >= 31) {
            $decision = 'warn';
        } else {
            $decision = 'clean';
        }

        return [
            'risk_score' => $score,
            'decision'   => $decision,
            'event_counts' => $counts,
        ];
    }
}
