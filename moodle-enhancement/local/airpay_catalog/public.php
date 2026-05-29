<?php
/**
 * Public Course Catalog — guest-accessible course browser.
 * No login required. Shows all Public tenant courses with pricing.
 * Guest can browse, search, view details, and add to cart.
 *
 * C4 (2026-05-29) — when the feature flag
 * sentientia.catalog.public_lxp.enabled is ON, this page renders with
 * the same LXP / Netflix card + carousel language as the logged-in
 * member catalog (index.php): a "Popular picks" scroll-snap rail
 * (hidden during search) above the searchable/sortable grid. When OFF
 * (default) it renders the legacy plain inline-styled grid, byte-for-
 * byte identical to production today. Commerce (pricing, add-to-cart,
 * cart pill) is preserved in both modes.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');

// NO require_login() — this page is accessible to guests.

global $DB, $CFG, $OUTPUT, $PAGE, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_catalog/public.php');
$PAGE->set_title(get_string('catalog', 'local_airpay_catalog') . ' — airpay academy');
$PAGE->set_heading(get_string('catalog', 'local_airpay_catalog'));
$PAGE->set_pagelayout('standard');

$search = optional_param('q', '', PARAM_TEXT);
$sort = optional_param('sort', 'popular', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$results = \local_airpay_catalog\commerce::get_public_catalog($search, $sort, $page, 12);
$cart_count = \local_airpay_catalog\commerce::get_cart_count();

// QA-walk P1 (2026-05-29) — does THIS viewer get one-click "Enrol now" on
// free courses (vs Add-to-cart)? Computed once for the whole grid: true only
// for a logged-in internal-tenant user with the flag ON. False (cart, exactly
// today's behaviour) for guests, the Public tenant, and when the flag is OFF.
$viewer_oneclick = \local_airpay_catalog\enrolment::should_offer_oneclick($USER, ['is_free' => true]);

// ── C4 feature flag ────────────────────────────────────────────────
$lxp_on = false;
if (class_exists('\\local_airpay_core\\feature_flags')) {
    $lxp_on = \local_airpay_core\feature_flags::is_enabled(
        'sentientia.catalog.public_lxp.enabled');
}

// Popular-picks rail data — only on the first, unsearched page.
$rail = [];
if ($lxp_on && $search === '' && $page === 0) {
    $rail = \local_airpay_catalog\commerce::get_public_catalog('', 'popular', 0, 8)['courses'];
}

// Carousel arrow-nav: CSP-safe inline AMD (no build step). Native
// horizontal scroll works without it; this just wires the arrows.
if ($lxp_on && !empty($rail)) {
    $PAGE->requires->js_amd_inline("
        require([], function() {
            document.querySelectorAll('[data-carousel]').forEach(function(sec) {
                var track = sec.querySelector('[data-carousel-track]');
                if (!track) { return; }
                sec.querySelectorAll('[data-dir]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        track.scrollBy({
                            left: btn.getAttribute('data-dir') === 'left' ? -640 : 640,
                            behavior: 'smooth'
                        });
                    });
                });
            });
        });
    ");
}

echo $OUTPUT->header();

if ($lxp_on) {
    // ════════════════════════════════════════════════════════════════
    // LXP / Netflix storefront — matches the member catalog (index.php)
    // by reusing the airpay-catalog__* BEM classes from styles.css.
    // ════════════════════════════════════════════════════════════════
    $carturl = (new moodle_url('/local/airpay_catalog/cart.php'))->out(false);
    $baseurl = (new moodle_url('/local/airpay_catalog/public.php'))->out(false);

    // Render one guest course card with a commerce footer, reusing the
    // member catalog's card classes so the look is identical.
    $render_card = function (array $course) use ($viewer_oneclick): string {
        $detail = $course['detailurl'];
        $isfree = !empty($course['is_free']);
        // Internal-tenant viewer + free course → one-click enrol; else cart.
        $oneclick = $viewer_oneclick && $isfree;
        $addurl = (new moodle_url('/local/airpay_catalog/course.php', [
            'id'      => $course['id'],
            'action'  => $oneclick ? 'enrolnow' : 'addtocart',
            'sesskey' => sesskey(),
        ]))->out(false);
        $cta = $oneclick
            ? get_string('enrol_now_free', 'local_airpay_catalog')
            : ($isfree
                ? get_string('public_enrolfree', 'local_airpay_catalog')
                : get_string('public_addtocart', 'local_airpay_catalog'));
        $pricecls = $isfree
            ? 'airpay-catalog__pubprice airpay-catalog__pubprice--free'
            : 'airpay-catalog__pubprice';

        $h  = '<article class="airpay-catalog__card">';
        $h .= '<a href="' . s($detail) . '" class="airpay-catalog__card-link" aria-label="'
            . s($course['fullname']) . '">';
        $variant = (int) ($course['id'] ?? 0) % 6;
        $h .= '<div class="airpay-catalog__card-thumb airpay-catalog__card-thumb--v' . $variant . '">';
        if (!empty($course['has_image'])) {
            $h .= '<img class="airpay-catalog__card-img" src="' . s($course['imageurl'])
                . '" alt="" loading="lazy" aria-hidden="true">';
        } else {
            $h .= '<span class="airpay-catalog__card-code" aria-hidden="true">'
                . s($course['shortname'] ?? '') . '</span>';
        }
        if ($isfree) {
            $h .= '<span class="airpay-catalog__badge airpay-catalog__badge--new">'
                . s(get_string('public_free', 'local_airpay_catalog')) . '</span>';
        }
        $h .= '<span class="airpay-catalog__card-type" aria-hidden="true">'
            . s(get_string('catalog', 'local_airpay_catalog')) . '</span>';
        $h .= '</div></a>';
        $h .= '<div class="airpay-catalog__card-body">';
        $h .= '<h4 class="airpay-catalog__card-title"><a href="' . s($detail) . '">'
            . format_string($course['fullname']) . '</a></h4>';
        if (!empty($course['summary_short'])) {
            $h .= '<p class="airpay-catalog__card-summary">' . s($course['summary_short']) . '</p>';
        }
        $h .= '<div class="airpay-catalog__card-meta">';
        $h .= '<span class="airpay-catalog__card-category"><i class="fa fa-users" aria-hidden="true"></i> '
            . (int) ($course['enrolled_count'] ?? 0) . '</span>';
        $h .= '</div>';
        $h .= '<div class="airpay-catalog__card-footer">';
        $h .= '<span class="' . $pricecls . '">' . s($course['display'] ?? '') . '</span>';
        $h .= '<a href="' . s($addurl) . '" class="airpay-catalog__btn airpay-catalog__btn--enroll">'
            . s($cta) . '</a>';
        $h .= '</div></div></article>';
        return $h;
    };

    echo '<div class="airpay-catalog airpay-catalog--public">';

    // ── Header ──────────────────────────────────────────────────────
    echo '<div class="airpay-catalog__pubhead">';
    echo '<div>';
    echo '<h2 class="airpay-catalog__pubtitle">'
        . '<i class="fa fa-th-large" aria-hidden="true"></i> '
        . s(get_string('catalog', 'local_airpay_catalog')) . '</h2>';
    echo '<p class="airpay-catalog__pubcount">'
        . s(get_string('public_coursesavailable', 'local_airpay_catalog', $results['total']))
        . '</p>';
    echo '</div>';
    if ($cart_count > 0) {
        echo '<a class="airpay-catalog__pubcart" href="' . s($carturl) . '">'
            . '<i class="fa fa-shopping-cart" aria-hidden="true"></i> '
            . s(get_string('public_cart', 'local_airpay_catalog', $cart_count)) . '</a>';
    }
    echo '</div>';

    // ── Search + sort ───────────────────────────────────────────────
    echo '<div class="airpay-catalog__pubcontrols">';
    echo '<form action="' . s($baseurl) . '" method="get" class="airpay-catalog__pubsearch" role="search">';
    echo '<i class="fa fa-search" aria-hidden="true"></i>';
    echo '<input type="text" name="q" value="' . s($search) . '" '
        . 'placeholder="' . s(get_string('search', 'local_airpay_catalog')) . '" '
        . 'aria-label="' . s(get_string('search', 'local_airpay_catalog')) . '">';
    echo '</form>';
    echo '<div class="airpay-catalog__pubsort">';
    foreach ([
        'popular' => 'public_sort_popular',
        'newest'  => 'public_sort_newest',
        'name'    => 'public_sort_name',
    ] as $key => $strkey) {
        $url = $baseurl . '?sort=' . $key . ($search !== '' ? '&q=' . urlencode($search) : '');
        $active = $sort === $key ? ' airpay-catalog__pubsort-pill--active' : '';
        echo '<a class="airpay-catalog__pubsort-pill' . $active . '" href="' . s($url) . '">'
            . s(get_string($strkey, 'local_airpay_catalog')) . '</a>';
    }
    echo '</div>';
    echo '</div>';

    // ── Popular-picks rail (carousel) — hidden during search ────────
    if (!empty($rail)) {
        echo '<section class="airpay-catalog__section" data-carousel>';
        echo '<h3 class="airpay-catalog__section-title"><i class="fa fa-fire" style="color:#d97706;" aria-hidden="true"></i> '
            . s(get_string('public_popularpicks', 'local_airpay_catalog')) . '</h3>';
        echo '<button class="airpay-catalog__carousel-nav airpay-catalog__carousel-nav--left" data-dir="left" '
            . 'aria-label="' . s(get_string('public_scrollleft', 'local_airpay_catalog')) . '">'
            . '<i class="fa fa-chevron-left" aria-hidden="true"></i></button>';
        echo '<div class="airpay-catalog__carousel" data-carousel-track>';
        foreach ($rail as $course) {
            echo $render_card($course);
        }
        echo '</div>';
        echo '<button class="airpay-catalog__carousel-nav airpay-catalog__carousel-nav--right" data-dir="right" '
            . 'aria-label="' . s(get_string('public_scrollright', 'local_airpay_catalog')) . '">'
            . '<i class="fa fa-chevron-right" aria-hidden="true"></i></button>';
        echo '</section>';
    }

    // ── Main grid ───────────────────────────────────────────────────
    if (!empty($results['courses'])) {
        if (!empty($rail)) {
            echo '<h3 class="airpay-catalog__section-title">'
                . s(get_string('public_browseall', 'local_airpay_catalog')) . '</h3>';
        }
        echo '<div class="airpay-catalog__grid">';
        foreach ($results['courses'] as $course) {
            echo $render_card($course);
        }
        echo '</div>';

        if (!empty($results['has_more'])) {
            $nexturl = $baseurl . '?page=' . ($page + 1) . '&sort=' . s($sort)
                . ($search !== '' ? '&q=' . urlencode($search) : '');
            echo '<div class="airpay-catalog__pubpager">';
            echo '<a class="airpay-catalog__btn airpay-catalog__btn--enroll" href="' . s($nexturl) . '">'
                . get_string('next') . ' <i class="fa fa-arrow-right" aria-hidden="true"></i></a>';
            echo '</div>';
        }
    } else {
        echo '<div class="ap-empty-state" style="padding:48px;">';
        echo '<i class="fa fa-search ap-empty-state__icon" aria-hidden="true"></i>';
        echo '<h4 class="ap-empty-state__title">' . s(get_string('public_nocourses', 'local_airpay_catalog')) . '</h4>';
        echo '<p class="ap-empty-state__message">' . s(get_string('public_nocourses_hint', 'local_airpay_catalog')) . '</p>';
        if ($search !== '') {
            echo '<a class="airpay-catalog__btn airpay-catalog__btn--enroll" href="' . s($baseurl) . '">'
                . s(get_string('public_clearsearch', 'local_airpay_catalog')) . '</a>';
        }
        echo '</div>';
    }

    echo '</div>'; // .airpay-catalog

    // Minimal additive styles for the public-only price + header bits.
    // The card/carousel/grid look comes entirely from styles.css's
    // airpay-catalog__* rules; this only adds the price pill + header
    // chrome that the member catalog doesn't have.
    echo '<style>
.airpay-catalog__pubhead { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; margin-bottom:var(--ap-space-4,24px); }
.airpay-catalog__pubtitle { margin:0; font-size:1.5rem; font-weight:800; color:var(--ap-text,#1a1a2e); }
.airpay-catalog__pubcount { margin:4px 0 0; font-size:0.875rem; color:var(--ap-text-secondary,#607286); }
.airpay-catalog__pubcart { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:var(--ap-radius-md,10px); background:var(--ap-primary,#0066A7); color:#fff; text-decoration:none; font-size:0.875rem; font-weight:600; }
.airpay-catalog__pubcontrols { display:flex; gap:12px; margin-bottom:var(--ap-space-4,24px); flex-wrap:wrap; align-items:center; }
.airpay-catalog__pubsearch { position:relative; flex:1; min-width:200px; }
.airpay-catalog__pubsearch i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#9ca3af; }
.airpay-catalog__pubsearch input { width:100%; padding:10px 14px 10px 40px; border:1px solid var(--ap-border,#e3eaf3); border-radius:var(--ap-radius-md,10px); font-size:0.875rem; font-family:inherit; }
.airpay-catalog__pubsort { display:flex; gap:4px; flex-wrap:wrap; }
.airpay-catalog__pubsort-pill { padding:8px 16px; border-radius:20px; font-size:0.8125rem; text-decoration:none; background:#f3f4f6; color:#5a6070; }
.airpay-catalog__pubsort-pill--active { background:var(--ap-primary,#0066A7); color:#fff; }
.airpay-catalog__pubprice { font-size:1.125rem; font-weight:800; color:var(--ap-primary,#0066A7); }
.airpay-catalog__pubprice--free { color:#16a34a; }
.airpay-catalog__pubpager { display:flex; justify-content:center; margin-top:var(--ap-space-4,24px); }
body.dark-mode .airpay-catalog__pubsort-pill { background:#252a36; color:#9ca3b4; }
body.dark-mode .airpay-catalog__pubsearch input { background:#1a1d27; border-color:#2d3140; color:#e8eaed; }
</style>';

} else {
    // ════════════════════════════════════════════════════════════════
    // LEGACY plain grid — preserved byte-for-byte (flag OFF = today).
    // ════════════════════════════════════════════════════════════════
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
                        <?php
                        // Internal-tenant viewer + free course → one-click enrol; else
                        // the legacy cart route (byte-for-byte unchanged when flag OFF).
                        $lg_oneclick = $viewer_oneclick && !empty($course['is_free']);
                        $lg_label = $lg_oneclick
                            ? get_string('enrol_now_free', 'local_airpay_catalog')
                            : ($course['is_free'] ? 'Enroll' : 'Add to Cart');
                        ?>
                        <a href="<?php echo s((new moodle_url('/local/airpay_catalog/course.php', ['id' => $course['id'], 'action' => $lg_oneclick ? 'enrolnow' : 'addtocart', 'sesskey' => sesskey()]))->out(false)); ?>"
                           style="padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600;
                                  background:var(--ap-primary,#0066A7); color:#fff; text-decoration:none;">
                            <?php echo s($lg_label); ?>
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
}

echo $OUTPUT->footer();
