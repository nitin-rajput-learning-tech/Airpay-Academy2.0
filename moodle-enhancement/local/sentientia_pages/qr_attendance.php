<?php
/**
 * QR Attendance — Generate QR code for classroom session.
 * Trainer displays this page on projector. Employees scan to mark attendance.
 *
 * Uses Moodle's built-in QR library (no external API dependency).
 * Includes countdown timer + fullscreen mode for projector display.
 *
 * Usage: /local/sentientia_pages/qr_attendance.php?sessionid=123
 *
 * @package    local_sentientia_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$sessionid = required_param('sessionid', PARAM_INT);
$context = context_system::instance();

// The classroom plugin owns the QR token and the session/tenant rules; without it this
// page could not show a QR that records anything. (Checked before the capability below
// because that capability is declared by the same plugin.)
if (!class_exists('\local_sentientia_classroom\session_manager')) {
    throw new moodle_exception('invalidaccess');
}

// Who may show the QR: whoever may take attendance in the Sentientia classroom UI
// (attendance.php and the bulk_mark_attendance web service check the same capability).
// It is declared by local_sentientia_classroom (manager and editingteacher by default) and
// site admins always pass. This used to be the pre-ADR-025 BizLMS capability
// local/classroom:takesessionattendance, which a Sentientia-only install does not
// declare, so there only site admins could open this page. The tenant scope is checked
// below by require_session_access() (ADR-031): holding the capability is not enough to
// show a QR for another tenant's classroom.
require_capability('local/sentientia_classroom:attendance', $context);

global $DB, $CFG, $OUTPUT, $PAGE;

$PAGE->set_context($context);
$PAGE->set_url('/local/sentientia_pages/qr_attendance.php', ['sessionid' => $sessionid]);
$PAGE->set_title(get_string('qr_show_title', 'local_sentientia_pages'));
$PAGE->set_heading(get_string('qr_show_title', 'local_sentientia_pages'));
$PAGE->set_pagelayout('standard');

// Get session info from the Sentientia classroom tables, the same ones qr_scan.php
// records attendance in and the attendance grid reads. This page used to read the
// BizLMS {local_classroom_sessions} / {local_classroom} tables, which a Sentientia
// install does not have.
//
// require_session_access() also applies the ADR-031 tenant guard: a holder of the
// capability above who is not a site admin can only show a QR for a classroom in
// their own tenant. A session that does not exist (or whose classroom is gone) gets
// core's 'invalidaccess', because a QR for it could never record anything.
try {
    [$session, $classroom] = \local_sentientia_classroom\session_manager::require_session_access($sessionid);
} catch (\dml_missing_record_exception $e) {
    throw new moodle_exception('invalidaccess');
}
$sessionname = format_string($classroom->name);
if (trim((string) $session->title) !== '') {
    $sessionname .= ' - ' . format_string($session->title);
}

// Generate a time-limited token (rotates hourly). session_manager signs it with a per-site
// secret; this page never reads $CFG->passwordsaltmain (a new Moodle install has none, and
// the token was then guessable).
$token = \local_sentientia_classroom\session_manager::qr_token($sessionid, time());
$scanurl = $CFG->wwwroot . '/local/sentientia_pages/qr_scan.php?sessionid=' . $sessionid . '&token=' . $token;

// Generate the QR with core_qrcode (TCPDF's 2D barcode, in Moodle core since 3.9; no
// Google Charts dependency). This page used to require lib/phpqrcode/qrlib.php, a
// library Moodle 5.x no longer ships, so on a Sentientia install it stopped with a
// missing-file error before it showed anything (found 2026-09-30 while taking the
// screenshots for this page). getBarcodePngData() returns false when PHP has
// neither the GD nor the Imagick extension.
$qrpng = (new \core_qrcode($scanurl))->getBarcodePngData(8, 8);
$qrbase64 = ($qrpng === false || $qrpng === null || $qrpng === '') ? '' : base64_encode($qrpng);

// Calculate minutes until token expires (top of next hour).
$now = time();
$nextrotation = strtotime(date('Y-m-d H:00:00', $now + 3600));
$minutesremaining = max(1, round(($nextrotation - $now) / 60));

echo $OUTPUT->header();
?>

<div class="airpay-qr" id="airpay-qr-container">
    <div class="airpay-qr__header">
        <h2 class="airpay-qr__title"><i class="fa fa-qrcode"></i> <?php echo s(get_string('qr_show_heading', 'local_sentientia_pages')); ?></h2>
        <p class="airpay-qr__session"><?php echo $sessionname; // Already escaped by format_string(). ?></p>
    </div>

    <div class="airpay-qr__code-wrap">
        <?php if ($qrbase64 !== '') { ?>
        <img src="data:image/png;base64,<?php echo $qrbase64; ?>" alt="<?php echo s(get_string('qr_show_alt', 'local_sentientia_pages')); ?>" class="airpay-qr__code" width="400" height="400">
        <?php } else { ?>
        <p class="alert alert-danger"><?php echo s(get_string('qr_show_nogd', 'local_sentientia_pages')); ?></p>
        <?php } ?>
    </div>

    <div class="airpay-qr__timer" id="airpay-qr-timer">
        <i class="fa fa-clock-o"></i>
        <?php // The lang string has no HTML; the number is wrapped here so the countdown script can update it. ?>
        <?php echo get_string('qr_show_refreshes', 'local_sentientia_pages',
            '<strong id="ap-qr-countdown">' . (int) $minutesremaining . '</strong>'); ?>
    </div>

    <div class="airpay-qr__actions">
        <button onclick="toggleFullscreen()" class="airpay-qr__btn" title="<?php echo s(get_string('qr_show_fullscreen_hint', 'local_sentientia_pages')); ?>">
            <i class="fa fa-expand"></i> <?php echo s(get_string('qr_show_fullscreen', 'local_sentientia_pages')); ?>
        </button>
        <button onclick="window.location.reload()" class="airpay-qr__btn airpay-qr__btn--refresh">
            <i class="fa fa-refresh"></i> <?php echo s(get_string('qr_show_refresh', 'local_sentientia_pages')); ?>
        </button>
    </div>

    <p class="airpay-qr__meta">
        <?php echo s(get_string('qr_show_meta', 'local_sentientia_pages', (object) [
            'id' => $sessionid,
            'time' => userdate(time(), '%d %b %Y %I:%M %p'),
        ])); ?>
    </p>
</div>

<style>
.airpay-qr { text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto; }
.airpay-qr__title { font-size: 24px; font-weight: 800; color: var(--ap-text, #1a1a2e); margin: 0 0 4px; }
.airpay-qr__session { font-size: 16px; color: var(--ap-text-secondary, #607286); margin: 0 0 24px; }
.airpay-qr__code-wrap {
    display: inline-block; padding: 24px; background: #fff;
    border-radius: 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    border: 2px solid var(--ap-border, #e3eaf3); margin-bottom: 20px;
}
.airpay-qr__code { display: block; border-radius: 8px; }
.airpay-qr__timer {
    font-size: 14px; color: var(--ap-text-secondary, #607286);
    margin-bottom: 16px;
}
.airpay-qr__timer strong { color: var(--ap-primary, #0066A7); font-size: 16px; }
.airpay-qr__timer--danger strong { color: #dc2626 !important; }
.airpay-qr__actions { display: flex; gap: 8px; justify-content: center; margin-bottom: 16px; }
.airpay-qr__btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 10px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    border: 1px solid var(--ap-border, #e3eaf3);
    background: var(--ap-surface, #fff); color: var(--ap-text, #1a1a2e);
    transition: all 0.15s; font-family: inherit;
}
.airpay-qr__btn:hover { border-color: var(--ap-primary); color: var(--ap-primary); }
.airpay-qr__btn--refresh {
    background: var(--ap-gradient, linear-gradient(135deg, #0066A7, #0d5da1));
    color: #fff; border-color: transparent;
}
.airpay-qr__btn--refresh:hover { opacity: 0.9; color: #fff; }
.airpay-qr__meta { font-size: 12px; color: var(--ap-text-muted, #8896a6); }

/* Fullscreen mode */
.airpay-qr--fullscreen {
    position: fixed; inset: 0; z-index: 9999;
    background: #fff; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    max-width: none; padding: 0;
}
.airpay-qr--fullscreen .airpay-qr__code { width: 60vmin; height: 60vmin; }

/* Dark mode */
body.dark-mode .airpay-qr__title { color: #e8eaed; }
body.dark-mode .airpay-qr__code-wrap { background: #fff; border-color: #2d3140; }
body.dark-mode .airpay-qr__btn { background: #1a1d27; border-color: #2d3140; color: #e8eaed; }
body.dark-mode .airpay-qr--fullscreen { background: #14161e; }
</style>

<script>
// Countdown timer (refresh page when token expires).
(function() {
    var remaining = <?php echo (int)$minutesremaining; ?> * 60; // seconds
    var el = document.getElementById('ap-qr-countdown');
    var timerWrap = document.getElementById('airpay-qr-timer');
    setInterval(function() {
        remaining--;
        if (remaining <= 0) { window.location.reload(); return; }
        var mins = Math.ceil(remaining / 60);
        el.textContent = mins;
        if (remaining < 300) { // < 5 min: turn red
            timerWrap.classList.add('airpay-qr__timer--danger');
        }
    }, 1000);
})();

function toggleFullscreen() {
    var container = document.getElementById('airpay-qr-container');
    container.classList.toggle('airpay-qr--fullscreen');
    // Also try native fullscreen API.
    if (!document.fullscreenElement) {
        container.requestFullscreen && container.requestFullscreen();
    } else {
        document.exitFullscreen && document.exitFullscreen();
    }
}
</script>

<?php
echo $OUTPUT->footer();
