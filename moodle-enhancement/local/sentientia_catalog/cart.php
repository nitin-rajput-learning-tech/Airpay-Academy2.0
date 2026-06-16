<?php
/**
 * Shopping Cart — shows selected courses, total, and checkout CTA.
 * Guest users see "Login to Complete" which redirects to login then back.
 * Logged-in users see "Enroll" for free courses or "Proceed to Payment" for paid.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');

// NO require_login() — cart works for guests.

global $DB, $CFG, $OUTPUT, $PAGE, $USER, $SESSION;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/sentientia_catalog/cart.php');
$PAGE->set_title('Your Cart — airpay academy');
$PAGE->set_heading('Your Cart');
$PAGE->set_pagelayout('standard');

// Handle remove action.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'remove') {
    $removeid = required_param('courseid', PARAM_INT);
    require_sesskey();
    \local_sentientia_catalog\commerce::remove_from_cart($removeid);
    redirect(new moodle_url('/local/sentientia_catalog/cart.php'), 'Removed from cart.');
}

// Handle clear cart.
if ($action === 'clear') {
    require_sesskey();
    \local_sentientia_catalog\commerce::clear_cart();
    redirect(new moodle_url('/local/sentientia_catalog/cart.php'), 'Cart cleared.');
}

// Handle "enroll all free" for logged-in users.
// QA-walk P1 (2026-05-29) — the old loop called core enrol_self() (which
// silently no-ops on key-gated courses) yet incremented $enrolled anyway,
// so it reported "Enrolled in N free course(s)!" while enrolling no one.
// Now routed through enrolment::enrol_now() (manual enrol, bypasses the key)
// which returns whether the enrolment actually happened — count only real
// successes, keep failures in the cart, and report truthfully.
if ($action === 'enrollfree' && isloggedin() && !isguestuser()) {
    require_sesskey();
    $cart = \local_sentientia_catalog\commerce::get_cart();
    $enrolled = 0;
    foreach ($cart as $item) {
        if (empty($item['is_free'])) {
            continue;
        }
        if (\local_sentientia_catalog\enrolment::enrol_now((int) $item['courseid'])) {
            $enrolled++;
            \local_sentientia_catalog\commerce::remove_from_cart($item['courseid']);
        }
    }
    if ($enrolled > 0) {
        redirect(new moodle_url('/local/sentientia_catalog/mycourses.php'),
            get_string('enrolled_count', 'local_sentientia_catalog', $enrolled), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect(new moodle_url('/local/sentientia_catalog/cart.php'),
        get_string('enrolled_none', 'local_sentientia_catalog'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$cart = \local_sentientia_catalog\commerce::get_cart();
$totals = \local_sentientia_catalog\commerce::get_cart_total();
$is_loggedin = isloggedin() && !isguestuser();

echo $OUTPUT->header();
?>

<div class="ap-cart" style="max-width:700px; margin:0 auto;">

    <h2 style="font-size:22px; font-weight:800; color:var(--ap-text,#1a1a2e); margin-bottom:20px;">
        <i class="fa fa-shopping-cart" style="color:var(--ap-primary);"></i> Your Cart
        <?php if (!empty($cart)): ?>
        <span style="font-size:14px; font-weight:400; color:var(--ap-text-muted);">(<?php echo count($cart); ?> items)</span>
        <?php endif; ?>
    </h2>

    <?php if (empty($cart)): ?>
    <div style="text-align:center; padding:48px; background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px;">
        <i class="fa fa-shopping-cart" style="font-size:3rem; color:#d1d5db; display:block; margin-bottom:16px;"></i>
        <h4 style="color:#5a6070; margin:0 0 8px;">Your cart is empty</h4>
        <p style="color:#9ca3af; margin:0 0 16px;">Browse our course catalog to find courses you'd like to enroll in.</p>
        <a href="<?php echo (new moodle_url('/local/sentientia_catalog/public.php'))->out(); ?>"
           style="padding:10px 24px; border-radius:10px; background:var(--ap-primary,#0066A7); color:#fff; text-decoration:none; font-weight:600;">
            <i class="fa fa-search"></i> Browse Courses
        </a>
    </div>

    <?php else: ?>
    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
        <?php foreach ($cart as $item): ?>
        <div style="display:flex; align-items:center; gap:16px; padding:16px 20px;
                    background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px;">
            <div style="width:48px; height:48px; border-radius:10px; background:linear-gradient(135deg,#0066a7,#0d5da1);
                        display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; font-weight:700; flex-shrink:0;">
                <?php echo s(substr($item['shortname'], 0, 3)); ?>
            </div>
            <div style="flex:1; min-width:0;">
                <h4 style="margin:0 0 2px; font-size:15px; font-weight:600; color:var(--ap-text,#1a1a2e);
                           overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?php echo s($item['fullname']); ?>
                </h4>
                <small style="color:var(--ap-text-muted,#8896a6);">Added <?php echo userdate($item['added'], '%d %b %Y'); ?></small>
            </div>
            <span style="font-size:16px; font-weight:700; color:<?php echo $item['is_free'] ? '#16a34a' : 'var(--ap-primary)'; ?>; white-space:nowrap;">
                <?php echo $item['display']; ?>
            </span>
            <a href="<?php echo (new moodle_url('/local/sentientia_catalog/cart.php', ['action' => 'remove', 'courseid' => $item['courseid'], 'sesskey' => sesskey()]))->out(); ?>"
               style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;
                      color:#dc2626; text-decoration:none; flex-shrink:0;" title="Remove">
                <i class="fa fa-trash-o"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Cart Summary -->
    <div style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <span style="font-size:14px; color:var(--ap-text-secondary,#607286);">
                <?php echo $totals['count']; ?> course(s)
                <?php if ($totals['free_count'] > 0): ?> · <?php echo $totals['free_count']; ?> free<?php endif; ?>
            </span>
            <span style="font-size:22px; font-weight:800; color:var(--ap-text,#1a1a2e);">
                <?php echo $totals['display']; ?>
            </span>
        </div>

        <?php if (!$is_loggedin): ?>
        <!-- Guest: redirect to login -->
        <a href="<?php echo (new moodle_url('/login/index.php', ['wantsurl' => $CFG->wwwroot . '/local/sentientia_catalog/cart.php']))->out(); ?>"
           style="display:flex; align-items:center; justify-content:center; gap:8px;
                  padding:12px 24px; border-radius:10px; font-size:16px; font-weight:700;
                  background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; text-decoration:none; width:100%;">
            <i class="fa fa-sign-in"></i> Login to Complete Enrollment
        </a>
        <p style="text-align:center; font-size:12px; color:var(--ap-text-muted); margin:8px 0 0;">
            Don't have an account? <a href="<?php echo (new moodle_url('/local/sentientia_users/signup.php'))->out(); ?>" style="color:var(--ap-primary);">Register here</a>
        </p>

        <?php elseif ($totals['all_free']): ?>
        <!-- All free: enroll directly -->
        <form method="post" action="<?php echo (new moodle_url('/local/sentientia_catalog/cart.php'))->out(false); ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="enrollfree">
            <button type="submit" style="display:flex; align-items:center; justify-content:center; gap:8px;
                    padding:12px 24px; border-radius:10px; font-size:16px; font-weight:700;
                    background:linear-gradient(135deg,#16a34a,#1985DD); color:#fff; border:none; cursor:pointer; width:100%;">
                <i class="fa fa-check-circle"></i> Enroll in All (Free)
            </button>
        </form>

        <?php else: ?>
        <!-- Has paid courses: checkout -->
        <button disabled style="display:flex; align-items:center; justify-content:center; gap:8px;
                padding:12px 24px; border-radius:10px; font-size:16px; font-weight:700;
                background:#d1d5db; color:#6b7280; border:none; width:100%; cursor:not-allowed;">
            <i class="fa fa-lock"></i> Payment Coming Soon
        </button>
        <p style="text-align:center; font-size:12px; color:var(--ap-text-muted); margin:8px 0 0;">
            Payment integration is being configured. Free courses can be enrolled immediately.
        </p>
        <?php endif; ?>

        <div style="text-align:center; margin-top:12px;">
            <a href="<?php echo (new moodle_url('/local/sentientia_catalog/cart.php', ['action' => 'clear', 'sesskey' => sesskey()]))->out(); ?>"
               style="font-size:12px; color:#dc2626; text-decoration:none;">Clear Cart</a>
            <span style="color:var(--ap-text-muted); margin:0 8px;">·</span>
            <a href="<?php echo (new moodle_url('/local/sentientia_catalog/public.php'))->out(); ?>"
               style="font-size:12px; color:var(--ap-primary); text-decoration:none;">Continue Browsing</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
