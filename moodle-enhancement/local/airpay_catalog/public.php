<?php
/**
 * Public Course Catalog — guest-accessible course browser.
 * No login required. Shows all Public tenant courses with pricing.
 * Guest can browse, search, view details, and add to cart.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');

// NO require_login() — this page is accessible to guests.

global $DB, $CFG, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_catalog/public.php');
$PAGE->set_title('Course Catalog — airpay academy');
$PAGE->set_heading('Course Catalog');
$PAGE->set_pagelayout('standard');

$search = optional_param('q', '', PARAM_TEXT);
$sort = optional_param('sort', 'popular', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$results = \local_airpay_catalog\commerce::get_public_catalog($search, $sort, $page, 12);
$cart_count = \local_airpay_catalog\commerce::get_cart_count();

echo $OUTPUT->header();
?>

<div class="ap-public-catalog" style="max-width:1200px; margin:0 auto; padding:0 20px;">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0; font-size:24px; font-weight:800; color:var(--ap-text,#1a1a2e);">
                <i class="fa fa-th-large" style="color:var(--ap-primary);"></i> Course Catalog
            </h2>
            <p style="margin:4px 0 0; font-size:14px; color:var(--ap-text-secondary,#607286);">
                <?php echo $results['total']; ?> courses available
            </p>
        </div>
        <?php if ($cart_count > 0): ?>
        <a href="<?php echo (new moodle_url('/local/airpay_catalog/cart.php'))->out(); ?>"
           style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:10px;
                  background:var(--ap-primary,#0066A7); color:#fff; text-decoration:none; font-size:14px; font-weight:600;">
            <i class="fa fa-shopping-cart"></i> Cart (<?php echo $cart_count; ?>)
        </a>
        <?php endif; ?>
    </div>

    <!-- Search + Sort -->
    <div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
        <form action="<?php echo (new moodle_url('/local/airpay_catalog/public.php'))->out(false); ?>" method="get" style="flex:1; min-width:200px;">
            <div style="position:relative;">
                <i class="fa fa-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#9ca3af;"></i>
                <input type="text" name="q" value="<?php echo s($search); ?>"
                       placeholder="Search courses..."
                       style="width:100%; padding:10px 14px 10px 40px; border:1px solid var(--ap-border,#e3eaf3);
                              border-radius:10px; font-size:14px; font-family:inherit;">
            </div>
        </form>
        <div style="display:flex; gap:4px;">
            <a href="?sort=popular<?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
               style="padding:8px 16px; border-radius:20px; font-size:13px; text-decoration:none;
                      <?php echo $sort === 'popular' ? 'background:var(--ap-primary,#0066A7); color:#fff;' : 'background:#f3f4f6; color:#5a6070;'; ?>">Popular</a>
            <a href="?sort=newest<?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
               style="padding:8px 16px; border-radius:20px; font-size:13px; text-decoration:none;
                      <?php echo $sort === 'newest' ? 'background:var(--ap-primary,#0066A7); color:#fff;' : 'background:#f3f4f6; color:#5a6070;'; ?>">Newest</a>
            <a href="?sort=name<?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
               style="padding:8px 16px; border-radius:20px; font-size:13px; text-decoration:none;
                      <?php echo $sort === 'name' ? 'background:var(--ap-primary,#0066A7); color:#fff;' : 'background:#f3f4f6; color:#5a6070;'; ?>">A-Z</a>
        </div>
    </div>

    <!-- Course Grid -->
    <?php if (!empty($results['courses'])): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php foreach ($results['courses'] as $course): ?>
        <div class="ap-public-card" style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3);
             border-radius:12px; overflow:hidden; transition:transform 0.2s, box-shadow 0.2s;">

            <!-- Card header with gradient -->
            <div style="height:8px; background:linear-gradient(90deg, #0066A7, #0f7a73);"></div>

            <div style="padding:20px;">
                <!-- Category badge -->
                <?php if (!empty($course['categoryname'])): ?>
                <span style="display:inline-block; font-size:11px; font-weight:600; color:#0066A7;
                             background:#e8f2f9; padding:2px 10px; border-radius:10px; margin-bottom:8px;">
                    <?php echo s($course['categoryname'] ?? ''); ?>
                </span>
                <?php endif; ?>

                <h3 style="margin:0 0 8px; font-size:16px; font-weight:700; color:var(--ap-text,#1a1a2e);
                           display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:44px;">
                    <a href="<?php echo s($course['detailurl']); ?>" style="color:inherit; text-decoration:none;">
                        <?php echo format_string($course['fullname']); ?>
                    </a>
                </h3>

                <p style="font-size:13px; color:var(--ap-text-secondary,#607286); margin:0 0 12px; line-height:1.4;
                          display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                    <?php echo s($course['summary_short']); ?>
                </p>

                <div style="display:flex; gap:12px; font-size:12px; color:var(--ap-text-muted,#8896a6); margin-bottom:14px;">
                    <span><i class="fa fa-users"></i> <?php echo $course['enrolled_count']; ?> enrolled</span>
                    <span><i class="fa fa-clock-o"></i> Self-paced</span>
                </div>

                <!-- Price + Actions -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:12px;
                            border-top:1px solid var(--ap-border,#e3eaf3);">
                    <span style="font-size:18px; font-weight:800; color:<?php echo $course['is_free'] ? '#16a34a' : 'var(--ap-primary)'; ?>;">
                        <?php echo $course['display']; ?>
                    </span>
                    <div style="display:flex; gap:6px;">
                        <a href="<?php echo s($course['detailurl']); ?>"
                           style="padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600;
                                  border:1px solid var(--ap-border,#e3eaf3); color:var(--ap-text,#1a1a2e);
                                  text-decoration:none;">Details</a>
                        <a href="<?php echo s($course['detailurl']); ?>?action=addtocart&sesskey=<?php echo sesskey(); ?>"
                           style="padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600;
                                  background:var(--ap-primary,#0066A7); color:#fff; text-decoration:none;">
                            <?php echo $course['is_free'] ? 'Enroll' : 'Add to Cart'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($results['has_more']): ?>
    <div style="display:flex; justify-content:center; margin-top:24px; padding-top:16px; border-top:1px solid var(--ap-border,#e3eaf3);">
        <span style="font-size:13px; color:var(--ap-text-muted); margin-right:12px;">
            Page <?php echo $page + 1; ?> of <?php echo $results['pages']; ?>
        </span>
        <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo s($sort); ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
           style="padding:8px 20px; border-radius:10px; font-size:13px; font-weight:600;
                  background:var(--ap-primary,#0066A7); color:#fff; text-decoration:none;">
            Next Page <i class="fa fa-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="ap-empty-state" style="padding:48px;">
        <i class="fa fa-search" style="font-size:3rem; color:#d1d5db; display:block; margin-bottom:16px;"></i>
        <h4 style="color:#5a6070;">No courses found</h4>
        <p style="color:#9ca3af;">Try a different search term or browse all courses.</p>
        <?php if ($search): ?>
        <a href="<?php echo (new moodle_url('/local/airpay_catalog/public.php'))->out(); ?>"
           style="padding:8px 16px; border-radius:8px; background:var(--ap-primary); color:#fff; text-decoration:none; font-size:13px;">
            Clear Search
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.ap-public-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
body.dark-mode .ap-public-card { background: #1a1d27 !important; border-color: #2d3140 !important; }
body.dark-mode .ap-public-card h3 a { color: #e8eaed !important; }
body.dark-mode .ap-public-card p { color: #9ca3b4 !important; }
@media (max-width: 590px) {
    .ap-public-catalog [style*="grid-template"] { grid-template-columns: 1fr !important; }
}
</style>

<?php
echo $OUTPUT->footer();
