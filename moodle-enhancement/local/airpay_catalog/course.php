<?php
/**
 * Public Course Detail — accessible to guests (no login required).
 * Shows course info with "Add to Cart" / "Enroll Free" / "Login to Access" CTAs.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $CFG, $OUTPUT, $PAGE, $USER, $SESSION;

$id = optional_param('id', 0, PARAM_INT);
if (!$id) {
    redirect(new moodle_url('/local/airpay_catalog/public.php'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_catalog/course.php', ['id' => $id]);
$PAGE->set_pagelayout('standard');

$course = $DB->get_record('course', ['id' => $id, 'visible' => 1], '*', MUST_EXIST);

// Tenant scoping (2026-05-29 broader-sweep follow-up to db5242c9a) —
// this page is reachable without login, so a guest could deep-link to
// `course.php?id=N` for ANY visible course on the site (Airpay-internal,
// ZEEA, etc.) and see name/summary/pricing. The catalog list (public.php)
// is hard-scoped to the public tenant; gate the detail page the same way.
//
// Two viewer classes:
//   - Guest / not-logged-in: ONLY Public-tenant courses, matching
//     public.php's scoping. Anything else -> redirect to public catalog.
//   - Logged-in learner: same tenant subtree as the rest of the catalog
//     (build_catalog_filter_sql_v2 via accesslib) OR a course they're
//     already enrolled in. The enrolment carve-out handles edge cases
//     like a user moved between tenants but still holds an active
//     enrolment in the original-tenant course.
$_is_loggedin_for_scope = isloggedin() && !isguestuser();
if (!$_is_loggedin_for_scope) {
    // Guest path: Public tenant only.
    $public_tid = (int) get_config('local_airpay_pages', 'public_tenant_id') ?: 77;
    $public_root = '/' . $public_tid;
    $course_path = $course->open_path ?? '';
    $_in_public = ($course_path === $public_root)
        || (strpos($course_path, $public_root . '/') === 0);
    if (!$_in_public) {
        redirect(new moodle_url('/local/airpay_catalog/public.php'),
            get_string('coursehidden', 'moodle', $course->fullname ?? ''),
            null, \core\output\notification::NOTIFY_ERROR);
    }
} else if (!is_siteadmin()) {
    // Logged-in path: accesslib tenant subtree OR an existing enrolment.
    $viewer_tenant_catid = \local_airpay_org\accesslib::get_tenant_category_id(
        (string) ($USER->open_path ?? ''));
    $viewer_tenant_catpath = $viewer_tenant_catid
        ? (string) $DB->get_field('course_categories', 'path',
            ['id' => $viewer_tenant_catid])
        : '';
    $_already_enrolled = is_enrolled(\context_course::instance($id), $USER);
    $_in_tenant = false;
    if ($viewer_tenant_catid && $viewer_tenant_catpath !== '') {
        $course_cat_path = (string) $DB->get_field('course_categories',
            'path', ['id' => $course->category]);
        $_in_tenant = ($course->category === (int) $viewer_tenant_catid)
            || ($course_cat_path !== ''
                && strpos($course_cat_path, $viewer_tenant_catpath . '/') === 0);
        // Borrowed-share carve-out (Sprint C/D parity with catalog v2).
        if (!$_in_tenant) {
            $parts = explode('/', trim((string) ($USER->open_path ?? ''), '/'));
            $tenant_root = isset($parts[0]) && ctype_digit($parts[0])
                ? (int) $parts[0] : 0;
            if ($tenant_root > 0
                    && \local_airpay_courses\sharing_manager::is_course_shared_to(
                        $id, $tenant_root)) {
                $_in_tenant = true;
            }
        }
    }
    if (!$_in_tenant && !$_already_enrolled) {
        redirect(new moodle_url('/local/airpay_catalog/index.php'),
            get_string('coursehidden', 'moodle', $course->fullname ?? ''),
            null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_title(format_string($course->fullname));

// Handle Add to Cart action.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'addtocart') {
    require_sesskey();
    \local_airpay_catalog\commerce::add_to_cart($id);
    redirect(new moodle_url('/local/airpay_catalog/course.php', ['id' => $id, 'added' => 1]),
        'Added to cart!', null, \core\output\notification::NOTIFY_SUCCESS);
}

$pricing = \local_airpay_catalog\commerce::get_course_price($id);
$cart_count = \local_airpay_catalog\commerce::get_cart_count();
$is_loggedin = isloggedin() && !isguestuser();
$added = optional_param('added', 0, PARAM_INT);

// Check if already enrolled (for logged-in users).
$is_enrolled = false;
if ($is_loggedin) {
    $coursecontext = context_course::instance($id);
    $is_enrolled = is_enrolled($coursecontext, $USER);
}

// Get enrollment count.
$enrolled_count = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
     JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :cid",
    ['cid' => $id]);

// Course summary.
$summary = format_text($course->summary, $course->summaryformat);

// Category name.
$categoryname = \local_airpay_catalog\category_manager::get_name((int)($course->open_categoryid ?? 0));
if (!empty($categoryname)) { $categoryname = format_string($categoryname); }

echo $OUTPUT->header();
?>

<div class="ap-public-course" style="max-width:900px; margin:0 auto;">

    <?php if ($added): ?>
    <div class="alert alert-success" style="border-radius:10px; display:flex; align-items:center; gap:10px;">
        <i class="fa fa-check-circle"></i>
        <span><strong><?php echo format_string($course->fullname); ?></strong> added to your cart.
        <a href="<?php echo (new moodle_url('/local/airpay_catalog/cart.php'))->out(); ?>">View Cart (<?php echo $cart_count; ?>)</a></span>
    </div>
    <?php endif; ?>

    <div style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:16px; overflow:hidden; margin-bottom:20px;">

        <!-- Hero -->
        <div style="background:linear-gradient(135deg,#0066A7 0%,#0f7a73 100%); padding:40px 32px; color:#fff;">
            <?php if ($categoryname): ?>
            <span style="background:rgba(255,255,255,0.2); padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600;"><?php echo s($categoryname); ?></span>
            <?php endif; ?>
            <h1 style="margin:12px 0 8px; font-size:28px; font-weight:800;"><?php echo format_string($course->fullname); ?></h1>
            <div style="display:flex; gap:16px; font-size:14px; opacity:0.9;">
                <span><i class="fa fa-users"></i> <?php echo $enrolled_count; ?> enrolled</span>
                <span><i class="fa fa-clock-o"></i> Self-paced</span>
            </div>
        </div>

        <!-- Body -->
        <div style="padding:28px 32px;">

            <!-- Pricing + CTA -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding:16px 20px; background:var(--ap-bg,#F2F4FB); border-radius:12px;">
                <div>
                    <span style="font-size:24px; font-weight:800; color:<?php echo $pricing['is_free'] ? '#16a34a' : 'var(--ap-primary,#0066A7)'; ?>;">
                        <?php echo $pricing['display']; ?>
                    </span>
                    <?php if (!$pricing['is_free']): ?>
                    <small style="color:var(--ap-text-muted); margin-left:4px;">one-time</small>
                    <?php endif; ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <?php if ($is_enrolled): ?>
                        <a href="<?php echo (new moodle_url('/course/view.php', ['id' => $id]))->out(); ?>"
                           style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                  background:linear-gradient(135deg,#0066A7,#0f7a73); color:#fff; text-decoration:none;">
                            <i class="fa fa-play-circle"></i> Continue Learning
                        </a>
                    <?php elseif ($pricing['is_free'] && $is_loggedin): ?>
                        <a href="<?php echo (new moodle_url('/enrol/index.php', ['id' => $id]))->out(); ?>"
                           style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                  background:linear-gradient(135deg,#0066A7,#0f7a73); color:#fff; text-decoration:none;">
                            <i class="fa fa-sign-in"></i> Enroll Now — Free
                        </a>
                    <?php elseif (!$is_loggedin): ?>
                        <form method="post" action="<?php echo (new moodle_url('/local/airpay_catalog/course.php', ['id' => $id]))->out(false); ?>" style="display:inline;">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="addtocart">
                            <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                    background:linear-gradient(135deg,#0066A7,#0f7a73); color:#fff; border:none; cursor:pointer;">
                                <i class="fa fa-cart-plus"></i> Add to Cart
                            </button>
                        </form>
                        <a href="<?php echo (new moodle_url('/login/index.php'))->out(); ?>"
                           style="padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600;
                                  background:var(--ap-surface,#fff); color:var(--ap-text,#1a1a2e); text-decoration:none;
                                  border:1px solid var(--ap-border,#e3eaf3);">
                            Login to Access
                        </a>
                    <?php else: ?>
                        <form method="post" action="<?php echo (new moodle_url('/local/airpay_catalog/course.php', ['id' => $id]))->out(false); ?>" style="display:inline;">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="addtocart">
                            <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                    background:linear-gradient(135deg,#0066A7,#0f7a73); color:#fff; border:none; cursor:pointer;">
                                <i class="fa fa-cart-plus"></i> Add to Cart — <?php echo $pricing['display']; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div style="margin-bottom:24px;">
                <h3 style="font-size:17px; font-weight:700; color:var(--ap-text,#1a1a2e); margin:0 0 12px;">About This Course</h3>
                <div style="font-size:14px; line-height:1.7; color:var(--ap-text-secondary,#607286);">
                    <?php echo $summary; ?>
                </div>
            </div>

            <!-- Back to catalog -->
            <div style="padding-top:16px; border-top:1px solid var(--ap-border,#e3eaf3);">
                <a href="<?php echo (new moodle_url('/local/airpay_catalog/public.php'))->out(); ?>"
                   style="font-size:13px; color:var(--ap-primary); text-decoration:none;">
                    <i class="fa fa-arrow-left"></i> Back to Course Catalog
                </a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
