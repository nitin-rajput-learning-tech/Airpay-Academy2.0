<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Proctoring';
$string['enable']     = 'Require proctoring';
$string['enable_help'] = 'Enable to require identity verification + live monitoring + AI review for every attempt on this quiz.';
$string['notrunning']  = 'Proctoring runtime not loaded — refresh the page and ensure your browser allows webcam and microphone.';
$string['consent_required'] = 'You must complete the proctoring consent + identity step before starting this attempt.';

// Privacy API (null provider).
$string['privacy:metadata'] = 'The Airpay Proctoring quiz access rule does not store any personal data. Its table holds per-quiz configuration only; proctoring evidence is stored and described by local_sentientia_proctoring.';
