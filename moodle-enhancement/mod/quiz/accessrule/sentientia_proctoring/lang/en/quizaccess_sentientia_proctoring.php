<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Proctoring';
$string['enable']     = 'Require proctoring';
// B1 (ADR-038 D2, 2026-11-20): the help and the learner notice say only what the code does.
$string['enable_help'] = 'If Yes, before each attempt the learner must agree to monitoring and confirm that they are taking the attempt themselves, in the quiz\'s pre-start check. While the attempt is open, its pages record when the learner switches to another tab or window, leaves fullscreen or pastes text. When the attempt is submitted or abandoned, these records are scored and an attempt that is not clean goes to the proctoring review queue. No camera, microphone or screen recording is used. Teachers\' previews are not proctored. The setting has no effect while proctoring is switched off for the learner\'s organisation.';
$string['notrunning']  = 'Proctoring runtime not loaded — refresh the page and ensure your browser allows webcam and microphone.';
$string['consent_required'] = 'You must complete the proctoring consent + identity step before starting this attempt.';

// Quiz front page and pre-start check (B1, 2026-11-20).
$string['description'] = 'This quiz is proctored. Before you start, you will be asked to agree to monitoring and to confirm that you are taking it yourself.';
$string['preflight_header'] = 'Proctored quiz';
$string['preflight_notice'] = '<p>While you answer, the quiz pages record each time:</p>
<ul>
<li>you switch to another tab or window;</li>
<li>you leave fullscreen;</li>
<li>you paste text into the page.</li>
</ul>
<p>The time of each record is taken from our server. No camera, microphone or screen recording is used.</p>
<p>When you submit, the records are scored. If they need a closer look, the attempt goes to a reviewer, who marks it clean, warning or fail. The records are kept with your attempt.</p>
<p>If you do not agree, select Cancel. Your attempt will not start; please contact your L&amp;D team.</p>';
$string['preflight_consent'] = 'I have read this notice and I agree to this monitoring for this attempt.';
$string['preflight_identity'] = 'I am {$a} and I will take this attempt myself, without help from anyone else.';
$string['preflight_consent_required'] = 'To start this proctored quiz you must agree to the monitoring.';
$string['preflight_identity_required'] = 'To start this proctored quiz you must confirm that you are taking it yourself.';

// B2 "Secure assessment mode" (ADR-038 D1, D3 and D6, 2026-11-20). Quiz settings, shown while
// sentientia.assessment.secure_mode.enabled is ON for the editor. The help says what the code does and what it cannot.
$string['securitylevel'] = 'Security level';
$string['securitylevel_help'] = 'How strongly the quiz pages are protected while a learner answers. It applies only while secure mode is switched on for the learner\'s organisation.

* **Off**: a normal quiz.
* **Standard**: before starting, the learner accepts the rules. The questions show only in fullscreen; if the learner leaves fullscreen they are hidden until a button takes the page back to fullscreen. Copy, cut, paste, right-click, printing and common keyboard shortcuts are blocked on the quiz pages. The learner\'s name and attempt number are shown faintly across the page. Leaving fullscreen and switching to another tab, window or application are recorded with the server\'s time and counted as violations. The page reports to the server every 15 seconds; a gap is recorded.
* **Secure**: Standard, plus Safe Exam Browser once the Safe Exam Browser preset is switched on (a later release). Until then it protects the page exactly as Standard does; to lock the computer down today, set "Require the use of Safe Exam Browser" in the Safe Exam Browser section of these settings.
* **Proctored**: Secure, plus the proctoring consent and the learner\'s confirmation that they are taking the attempt themselves. Webcam recording is not available yet. Needs proctoring to be switched on for your organisation.

What it cannot do: it cannot see a second device such as a phone, another person in the room, or a screenshot taken with the computer\'s own tools, and a modified browser can stop the page\'s script (the heartbeat gap is then the only trace). Good question design (question banks, random order, one attempt, no review straight after) remains the main protection.

A Secure or Proctored quiz does not start while secure mode (or, for Proctored, proctoring) is switched off for the learner\'s organisation: it never runs at a lower level without saying so. Teachers\' previews are not protected. Teachers see an integrity report on each learner\'s attempt review page.';
$string['securitylevel_off'] = 'Off (normal quiz)';
$string['securitylevel_standard'] = 'Standard';
$string['securitylevel_secure'] = 'Secure';
$string['securitylevel_proctored'] = 'Proctored';
$string['purpose'] = 'Assessment purpose';
$string['purpose_help'] = 'What the assessment is for. The purpose sets the lowest security level: Compliance or practice needs at least Standard, a Skill assessment at least Secure, a Skill certification or Hiring assessment Proctored. Only a proctoring manager can choose a lower level.

On a Hiring assessment the learner is warned at each violation and the attempt is submitted automatically when it reaches the number set below. On every other purpose the learner is warned at each violation and the attempt is flagged for a reviewer; it is never submitted for them.';
$string['purpose_none'] = 'Not set';
$string['purpose_compliance'] = 'Compliance or practice';
$string['purpose_skill'] = 'Skill assessment';
$string['purpose_certification'] = 'Skill certification';
$string['purpose_hiring'] = 'Hiring';
$string['violationlimit'] = 'Auto-submit after this many violations';
$string['violationlimit_help'] = 'Hiring assessments only. A violation is leaving fullscreen, or switching to another tab, window or application. Several signals within 2 seconds count once, and a lost focus in the first 10 seconds after a page opens is not counted. When the attempt reaches this number, the server submits it with the answers saved up to that moment; a reviewer then looks at it. 0 means never submit (warn and flag only). The learner is told the number before starting.';
$string['err_level_below_purpose'] = 'For this purpose the security level must be at least {$a}. Only a proctoring manager can choose a lower level.';
$string['err_proctored_needs_flag'] = 'Proctored needs proctoring to be switched on for your organisation. Choose Standard or Secure, or ask your L&D admin.';
$string['err_violationlimit'] = 'Enter a whole number from 0 to {$a}.';

// Fail closed (prevent_access) and the quiz front page.
$string['blocked_securemode_off'] = 'This assessment needs secure mode, which is switched off for your organisation, so it cannot be started. Please contact your L&D team.';
$string['blocked_proctoring_off'] = 'This assessment is proctored, and proctoring is switched off for your organisation, so it cannot be started. Please contact your L&D team.';
$string['blocked_desktop_required'] = 'Desktop required: this assessment runs in secure mode or is proctored, so it can only be taken in a web browser on a computer, not in the mobile app.';
$string['description_secure'] = 'This assessment runs in secure mode ({$a}): the questions show only in fullscreen, copying, pasting and printing are blocked, and leaving the page is recorded.';
$string['description_autosubmit'] = 'At {$a} violations the attempt is submitted automatically.';

// The pre-start check in secure mode. The notice is assembled from these parts in this order.
$string['secure_header'] = 'Secure assessment: {$a}';
$string['secure_notice_rules'] = '<p>This assessment runs in secure mode:</p>
<ul>
<li>The questions show only in fullscreen. If you leave fullscreen they are hidden until you return to it.</li>
<li>Copy, cut, paste, right-click, printing and common keyboard shortcuts are blocked on the quiz pages.</li>
<li>Your name and attempt number are shown faintly across the page.</li>
<li>Each time you leave fullscreen, or switch to another tab, window or application, our server records the time. This counts as a violation. Several signals within 2 seconds count once.</li>
<li>The page reports to our server every few seconds. If it stops reporting (for example because a browser extension blocks it), the gap is recorded.</li>
</ul>';
$string['secure_notice_warn'] = '<p>You will see a warning with your count at each violation. If there are any, your attempt is flagged for a reviewer, who marks it clean, warning or fail. Your attempt is never submitted for you.</p>';
$string['secure_notice_autosubmit'] = '<p>This is a hiring assessment. You will see a warning at each violation. At {$a} violations your attempt is submitted automatically, with the answers saved up to that moment, and a reviewer looks at it.</p>';
$string['secure_notice_proctored'] = '<p>This assessment is also proctored: below you agree to this monitoring and confirm that you are taking it yourself. No camera, microphone or screen recording is used.</p>';
$string['secure_notice_limits'] = '<p>What this page cannot do: it cannot see other devices such as a phone, or other people in the room, and it cannot stop screenshots taken with your computer\'s own tools. The records are kept with your attempt.</p>';
$string['secure_notice_cancel'] = '<p>If you do not accept these rules, select Cancel. Your attempt will not start; please contact your L&amp;D team.</p>';
$string['secure_ack'] = 'I have read these rules and I will follow them for this attempt.';
$string['secure_ack_required'] = 'To start this assessment you must accept the rules.';

// Privacy API (null provider).
$string['privacy:metadata'] = 'The Airpay Proctoring quiz access rule does not store any personal data. Its table holds per-quiz configuration only; proctoring evidence is stored and described by local_sentientia_proctoring.';
