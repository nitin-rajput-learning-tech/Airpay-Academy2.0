<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Style Guide — Phase A0.5 (2026-05-14).
 *
 * Visual reference of every design token in the platform. Lives in
 * production so designers, developers, and L&D admins can see "what good
 * looks like" without needing access to Storybook or Figma.
 *
 * URL: /local/airpay_core/admin/styleguide.php
 *
 * Access: site admin only. Future Phase Δ may open this to anyone with
 * the `local/airpay_core:viewstyleguide` capability so brand teams can
 * audit without full admin.
 *
 * Pairs with:
 *  - moodle-enhancement/theme/airpayux/scss/moodle/_tokens.scss (the source)
 *  - docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md (the spec)
 *
 * Every swatch / spacer / button on this page references the live CSS
 * custom property via `style="--ap-..."` — so it stays in sync with the
 * compiled theme automatically. No copy-pasted hex values here.
 *
 * @package local_airpay_core
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $OUTPUT, $PAGE;

$PAGE->set_url('/local/airpay_core/admin/styleguide.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Airpay Style Guide');
$PAGE->set_heading('Airpay Style Guide — design tokens');

echo $OUTPUT->header();

// Internal page styles. Kept inline so the page renders even if
// theme cache is busted. Only structure — every colour/spacing
// value references a token via var(--ap-...).
?>
<style>
.ap-sg { font-family: var(--ap-font-family); color: var(--ap-color-text-primary); }
.ap-sg__intro { background: var(--ap-color-bg-surface); border: 1px solid var(--ap-color-border); border-radius: var(--ap-radius-md); padding: var(--ap-space-6); margin-bottom: var(--ap-space-8); }
.ap-sg__intro p { margin: 0 0 var(--ap-space-2); color: var(--ap-color-text-secondary); }
.ap-sg__intro p:last-child { margin: 0; }
.ap-sg__section { margin-bottom: var(--ap-space-12); }
.ap-sg__section-title { font-size: var(--ap-text-2xl); font-weight: var(--ap-weight-bold); margin: 0 0 var(--ap-space-2); }
.ap-sg__section-desc { font-size: var(--ap-text-sm); color: var(--ap-color-text-secondary); margin: 0 0 var(--ap-space-6); max-width: 720px; }
.ap-sg__grid { display: grid; gap: var(--ap-space-4); grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
.ap-sg__swatch { background: var(--ap-color-bg-surface); border: 1px solid var(--ap-color-border); border-radius: var(--ap-radius-md); overflow: hidden; }
.ap-sg__swatch-fill { height: 96px; }
.ap-sg__swatch-meta { padding: var(--ap-space-3) var(--ap-space-4); }
.ap-sg__swatch-name { font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); color: var(--ap-color-text-primary); margin: 0 0 var(--ap-space-1); }
.ap-sg__swatch-value { font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); color: var(--ap-color-text-muted); margin: 0; }
.ap-sg__spacer-row { display: flex; align-items: center; gap: var(--ap-space-4); padding: var(--ap-space-2) 0; }
.ap-sg__spacer-bar { background: var(--ap-color-primary); height: 16px; border-radius: var(--ap-radius-xs); }
.ap-sg__spacer-label { font-family: var(--ap-font-family-mono); font-size: var(--ap-text-sm); color: var(--ap-color-text-secondary); min-width: 220px; }
.ap-sg__radius-row { display: flex; gap: var(--ap-space-4); align-items: flex-end; flex-wrap: wrap; }
.ap-sg__radius-box { width: 96px; height: 96px; background: var(--ap-color-primary-light); border: 2px solid var(--ap-color-primary); display: flex; align-items: center; justify-content: center; font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); color: var(--ap-color-primary-dark); text-align: center; }
.ap-sg__shadow-row { display: grid; gap: var(--ap-space-6); grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
.ap-sg__shadow-box { background: var(--ap-color-bg-surface); border-radius: var(--ap-radius-md); padding: var(--ap-space-6); text-align: center; font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); color: var(--ap-color-text-secondary); }
.ap-sg__type-sample { padding: var(--ap-space-2) 0; border-bottom: 1px solid var(--ap-color-divider); }
.ap-sg__type-sample:last-child { border-bottom: none; }
.ap-sg__type-meta { font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); color: var(--ap-color-text-muted); margin: 0 0 var(--ap-space-1); }
.ap-sg__motion-row { display: flex; gap: var(--ap-space-3); flex-wrap: wrap; }
.ap-sg__motion-btn { padding: var(--ap-space-3) var(--ap-space-4); background: var(--ap-color-primary); color: var(--ap-color-text-inverse); border: 0; border-radius: var(--ap-radius-sm); font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); cursor: pointer; min-height: var(--ap-tap-target-min); }
.ap-sg__motion-btn:hover { background: var(--ap-color-primary-hover); }
.ap-sg__motion-target { width: 80px; height: 80px; background: var(--ap-color-accent); border-radius: var(--ap-radius-md); }
.ap-sg__bp-row { display: flex; gap: var(--ap-space-3); padding: var(--ap-space-3); background: var(--ap-color-bg-surface-alt); border-radius: var(--ap-radius-sm); margin-bottom: var(--ap-space-2); font-family: var(--ap-font-family-mono); font-size: var(--ap-text-sm); }
.ap-sg__bp-row strong { min-width: 110px; }
.ap-sg__bp-row span { color: var(--ap-color-text-secondary); }
.ap-sg__bp-row em { color: var(--ap-color-text-muted); font-style: normal; }
.ap-sg__nav { position: sticky; top: var(--ap-space-4); background: var(--ap-color-bg-surface); border: 1px solid var(--ap-color-border); border-radius: var(--ap-radius-md); padding: var(--ap-space-4); margin-bottom: var(--ap-space-6); }
.ap-sg__nav a { display: inline-block; margin-right: var(--ap-space-4); color: var(--ap-color-primary); text-decoration: none; font-size: var(--ap-text-sm); font-weight: var(--ap-weight-medium); }
.ap-sg__nav a:hover { text-decoration: underline; }
</style>

<div class="ap-sg">

<div class="ap-sg__intro">
    <p><strong>This is the canonical token reference for Airpay Academy.</strong> Every colour, spacing, radius, shadow, type, and motion value in the platform must reference a token from this page. New components that introduce hex literals or magic numbers will be flagged at PR review.</p>
    <p>Source: <code>moodle-enhancement/theme/airpayux/scss/moodle/_tokens.scss</code> &middot; Spec: <code>docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md</code></p>
</div>

<nav class="ap-sg__nav">
    <a href="#colour">Colour</a>
    <a href="#typography">Typography</a>
    <a href="#spacing">Spacing</a>
    <a href="#radius">Radius</a>
    <a href="#shadow">Shadow</a>
    <a href="#motion">Motion</a>
    <a href="#breakpoints">Breakpoints</a>
    <a href="#a11y">A11y &amp; Touch</a>
    <a href="#components">Components</a>
</nav>

<!-- ─────────────────────────── COLOUR ─────────────────────────── -->
<section class="ap-sg__section" id="colour">
    <h2 class="ap-sg__section-title">Colour</h2>
    <p class="ap-sg__section-desc">Semantic tokens reference the colour primitives. Tenant branding overrides ONLY the semantic tokens (<code>--ap-color-primary</code> etc.) — primitives stay fixed so the palette remains coherent.</p>

    <h3>Semantic — brand</h3>
    <div class="ap-sg__grid">
        <?php
        $semantic = [
            'primary' => 'Default CTAs, links, active nav',
            'primary-hover' => 'Hover/pressed state',
            'primary-light' => 'Tinted backgrounds, hover bg',
            'primary-dark' => 'Pressed state on light bg',
            'accent' => 'Secondary actions, success-adjacent',
            'accent-hover' => 'Accent hover state',
            'accent-light' => 'Accent tinted background',
        ];
        foreach ($semantic as $name => $desc) {
            echo '<div class="ap-sg__swatch">';
            echo '<div class="ap-sg__swatch-fill" style="background: var(--ap-color-' . s($name) . ');"></div>';
            echo '<div class="ap-sg__swatch-meta">';
            echo '<p class="ap-sg__swatch-name">--ap-color-' . s($name) . '</p>';
            echo '<p class="ap-sg__swatch-value">' . s($desc) . '</p>';
            echo '</div></div>';
        }
        ?>
    </div>

    <h3 style="margin-top: var(--ap-space-8);">Semantic — surface &amp; text</h3>
    <div class="ap-sg__grid">
        <?php
        $surfaces = [
            'bg-body' => 'Page background',
            'bg-surface' => 'Card/panel background',
            'bg-surface-alt' => 'Nested card, zebra rows',
            'bg-elevated' => 'Modal/popover background',
            'text-primary' => 'Headlines + body text',
            'text-secondary' => 'Labels (WCAG AA 6.4:1)',
            'text-muted' => 'Captions, hints',
            'border' => 'Card borders, dividers',
            'border-strong' => 'Inputs, separators',
        ];
        foreach ($surfaces as $name => $desc) {
            echo '<div class="ap-sg__swatch">';
            echo '<div class="ap-sg__swatch-fill" style="background: var(--ap-color-' . s($name) . '); border-bottom: 1px solid var(--ap-color-border);"></div>';
            echo '<div class="ap-sg__swatch-meta">';
            echo '<p class="ap-sg__swatch-name">--ap-color-' . s($name) . '</p>';
            echo '<p class="ap-sg__swatch-value">' . s($desc) . '</p>';
            echo '</div></div>';
        }
        ?>
    </div>

    <h3 style="margin-top: var(--ap-space-8);">Semantic — status</h3>
    <div class="ap-sg__grid">
        <?php
        $status = [
            'success' => 'Success messages (#15803d — WCAG AA 4.5:1)',
            'success-light' => 'Success tinted bg',
            'warning' => 'Warnings (#b45309 — WCAG AA 4.5:1)',
            'warning-light' => 'Warning tinted bg',
            'danger' => 'Errors, destructive actions',
            'danger-light' => 'Error tinted bg',
        ];
        foreach ($status as $name => $desc) {
            echo '<div class="ap-sg__swatch">';
            echo '<div class="ap-sg__swatch-fill" style="background: var(--ap-color-' . s($name) . ');"></div>';
            echo '<div class="ap-sg__swatch-meta">';
            echo '<p class="ap-sg__swatch-name">--ap-color-' . s($name) . '</p>';
            echo '<p class="ap-sg__swatch-value">' . s($desc) . '</p>';
            echo '</div></div>';
        }
        ?>
    </div>
</section>

<!-- ─────────────────────────── TYPOGRAPHY ─────────────────────────── -->
<section class="ap-sg__section" id="typography">
    <h2 class="ap-sg__section-title">Typography</h2>
    <p class="ap-sg__section-desc">Montserrat 400-800. Eight size steps, four weight steps. Line-height comes from <code>--ap-leading-*</code> tokens.</p>

    <?php
    $types = [
        '4xl' => ['label' => '--ap-text-4xl · 2.25rem · 36px', 'sample' => 'Build the future of L&amp;D'],
        '3xl' => ['label' => '--ap-text-3xl · 1.875rem · 30px', 'sample' => 'Major page headings'],
        '2xl' => ['label' => '--ap-text-2xl · 1.5rem · 24px', 'sample' => 'Section titles'],
        'xl'  => ['label' => '--ap-text-xl · 1.25rem · 20px', 'sample' => 'Card heads, subhead'],
        'lg'  => ['label' => '--ap-text-lg · 1.125rem · 18px', 'sample' => 'Emphasised body, nav items'],
        'base' => ['label' => '--ap-text-base · 1rem · 16px', 'sample' => 'Default body copy. Used for paragraphs and table rows.'],
        'sm'  => ['label' => '--ap-text-sm · 0.875rem · 14px', 'sample' => 'Helper text, table cells, secondary content.'],
        'xs'  => ['label' => '--ap-text-xs · 0.75rem · 12px', 'sample' => 'Badges, captions, breadcrumbs.'],
    ];
    foreach ($types as $size => $row) {
        echo '<div class="ap-sg__type-sample">';
        echo '<p class="ap-sg__type-meta">' . $row['label'] . '</p>';
        echo '<p style="font-size: var(--ap-text-' . s($size) . '); margin: 0; font-weight: var(--ap-weight-semibold);">' . $row['sample'] . '</p>';
        echo '</div>';
    }
    ?>

    <h3 style="margin-top: var(--ap-space-6);">Weight</h3>
    <?php foreach (['normal' => 400, 'medium' => 500, 'semibold' => 600, 'bold' => 700, 'extrabold' => 800] as $name => $val) {
        echo '<p style="font-weight: var(--ap-weight-' . s($name) . '); margin: var(--ap-space-1) 0; font-size: var(--ap-text-lg);">';
        echo 'Weight ' . $val . ' · --ap-weight-' . s($name) . ' · Designs that ship</p>';
    } ?>
</section>

<!-- ─────────────────────────── SPACING ─────────────────────────── -->
<section class="ap-sg__section" id="spacing">
    <h2 class="ap-sg__section-title">Spacing — 4pt + 8pt grid</h2>
    <p class="ap-sg__section-desc">Thirteen spacing tokens on a 4px-base/8px-grid scale. <code>--ap-space-4</code> (16px) is the default content padding. Use <code>--ap-space-1</code> only for sub-icon adjustments — anything larger should snap to the grid.</p>

    <?php
    $spaces = [
        '0' => 0, '1' => 4, '2' => 8, '3' => 12, '4' => 16, '5' => 20,
        '6' => 24, '8' => 32, '10' => 40, '12' => 48, '16' => 64, '20' => 80,
    ];
    foreach ($spaces as $name => $px) {
        echo '<div class="ap-sg__spacer-row">';
        echo '<span class="ap-sg__spacer-label">--ap-space-' . s($name) . ' · ' . $px . 'px</span>';
        echo '<div class="ap-sg__spacer-bar" style="width: var(--ap-space-' . s($name) . ');"></div>';
        echo '</div>';
    }
    ?>
</section>

<!-- ─────────────────────────── RADIUS ─────────────────────────── -->
<section class="ap-sg__section" id="radius">
    <h2 class="ap-sg__section-title">Border radius</h2>
    <p class="ap-sg__section-desc">Soft but not playful. Cards default to <code>--ap-radius-md</code>; inputs use <code>--ap-radius-sm</code>; pills use <code>--ap-radius-pill</code>.</p>

    <div class="ap-sg__radius-row">
        <?php foreach (['xs' => '4px', 'sm' => '8px', 'md' => '12px', 'lg' => '16px', 'xl' => '20px', 'pill' => '999px'] as $name => $val) {
            echo '<div class="ap-sg__radius-box" style="border-radius: var(--ap-radius-' . s($name) . ');">--ap-radius-' . s($name) . '<br>' . s($val) . '</div>';
        } ?>
    </div>
</section>

<!-- ─────────────────────────── SHADOW ─────────────────────────── -->
<section class="ap-sg__section" id="shadow">
    <h2 class="ap-sg__section-title">Elevation (shadow)</h2>
    <p class="ap-sg__section-desc">Five elevation steps. <code>--ap-shadow-sm</code> for resting cards, <code>--ap-shadow-md</code> for popovers, <code>--ap-shadow-lg</code> for modals, <code>--ap-shadow-hover</code> for interactive lift on hover.</p>

    <div class="ap-sg__shadow-row">
        <?php foreach (['xs', 'sm', 'md', 'lg', 'hover'] as $name) {
            echo '<div class="ap-sg__shadow-box" style="box-shadow: var(--ap-shadow-' . s($name) . ');">--ap-shadow-' . s($name) . '</div>';
        } ?>
    </div>
</section>

<!-- ─────────────────────────── MOTION ─────────────────────────── -->
<section class="ap-sg__section" id="motion">
    <h2 class="ap-sg__section-title">Motion</h2>
    <p class="ap-sg__section-desc">Five duration tokens, four easing curves. Tokens automatically collapse to 0ms under <code>prefers-reduced-motion: reduce</code>. Click each button to see the motion applied to the target on the right.</p>

    <div class="ap-sg__motion-row" style="align-items: center;">
        <div>
            <p class="ap-sg__swatch-name" style="margin-bottom: var(--ap-space-3);">Duration tokens</p>
            <button class="ap-sg__motion-btn" data-duration="quick" data-ease="out">quick · 150ms · ease-out</button>
            <button class="ap-sg__motion-btn" data-duration="default" data-ease="out">default · 250ms · ease-out</button>
            <button class="ap-sg__motion-btn" data-duration="slow" data-ease="in-out">slow · 400ms · ease-in-out</button>
            <button class="ap-sg__motion-btn" data-duration="deliberate" data-ease="spring">deliberate · 700ms · spring</button>
        </div>
        <div id="ap-sg-motion-target" class="ap-sg__motion-target" style="margin-left: var(--ap-space-6);"></div>
    </div>

    <h3 style="margin-top: var(--ap-space-8);">Easing curves</h3>
    <ul style="font-family: var(--ap-font-family-mono); font-size: var(--ap-text-sm); line-height: var(--ap-leading-relaxed); color: var(--ap-color-text-secondary);">
        <li><code>--ap-ease-out</code> &mdash; cubic(0.16, 1, 0.3, 1) &mdash; incoming elements</li>
        <li><code>--ap-ease-in</code> &mdash; cubic(0.7, 0, 0.84, 0) &mdash; outgoing elements</li>
        <li><code>--ap-ease-in-out</code> &mdash; cubic(0.5, 0, 0.5, 1) &mdash; bidirectional</li>
        <li><code>--ap-ease-spring</code> &mdash; cubic(0.34, 1.56, 0.64, 1) &mdash; emphatic incoming</li>
    </ul>
</section>

<!-- ─────────────────────────── BREAKPOINTS ─────────────────────────── -->
<section class="ap-sg__section" id="breakpoints">
    <h2 class="ap-sg__section-title">Responsive breakpoints</h2>
    <p class="ap-sg__section-desc">Six device-class breakpoints. SCSS <code>@media</code> queries must reference these via the Sass variables — never inline px literals. CSS-only contexts use container queries against the matching widths.</p>

    <?php
    $bps = [
        ['mobile-s', '$ap-bp-mobile-s', '< 380px', 'Galaxy S series, iPhone SE'],
        ['mobile',   '$ap-bp-mobile',   '< 590px', 'iPhone Pro, Pixel'],
        ['tablet-s', '$ap-bp-tablet-s', '< 768px', 'iPad mini portrait, foldable inner'],
        ['tablet',   '$ap-bp-tablet',   '< 992px', 'iPad portrait, iPad Air landscape'],
        ['laptop',   '$ap-bp-laptop',   '< 1280px', '13" laptops at 100%/125% zoom'],
        ['desktop',  '$ap-bp-desktop',  '< 1600px', 'External monitors, 14-16" at 100%'],
    ];
    foreach ($bps as [$slug, $var, $range, $device]) {
        echo '<div class="ap-sg__bp-row">';
        echo '<strong>' . s($slug) . '</strong>';
        echo '<span>' . s($var) . '</span>';
        echo '<span>' . s($range) . '</span>';
        echo '<em>' . s($device) . '</em>';
        echo '</div>';
    }
    ?>
</section>

<!-- ─────────────────────────── A11Y ─────────────────────────── -->
<section class="ap-sg__section" id="a11y">
    <h2 class="ap-sg__section-title">A11y &amp; touch</h2>
    <p class="ap-sg__section-desc">WCAG 2.2 AA is the floor. Touch targets minimum 44×44pt (manifesto §9). Focus rings visible on every interactive element via <code>:focus-visible</code>.</p>

    <h3>Touch targets</h3>
    <div style="display: flex; gap: var(--ap-space-4); align-items: center; flex-wrap: wrap; margin-bottom: var(--ap-space-6);">
        <button style="min-width: var(--ap-tap-target-min); min-height: var(--ap-tap-target-min); border-radius: var(--ap-radius-sm); border: 1px solid var(--ap-color-border-strong); background: var(--ap-color-bg-surface); padding: 0 var(--ap-space-4); cursor: pointer;">--ap-tap-target-min (44×44)</button>
        <button style="min-width: var(--ap-tap-target-cozy); min-height: var(--ap-tap-target-cozy); border-radius: var(--ap-radius-sm); border: 1px solid var(--ap-color-border-strong); background: var(--ap-color-bg-surface); padding: 0 var(--ap-space-4); cursor: pointer;">--ap-tap-target-cozy (40×40, admin tables)</button>
    </div>

    <h3>Control heights — vertical rhythm</h3>
    <div style="display: flex; gap: var(--ap-space-3); align-items: flex-end; margin-bottom: var(--ap-space-6); flex-wrap: wrap;">
        <?php foreach (['sm' => '32px', 'md' => '40px', 'lg' => '48px', 'xl' => '56px'] as $name => $val) {
            echo '<button style="height: var(--ap-control-height-' . s($name) . '); padding: 0 var(--ap-space-4); border-radius: var(--ap-radius-sm); border: 0; background: var(--ap-color-primary); color: var(--ap-color-text-inverse); font-family: var(--ap-font-family-mono); font-size: var(--ap-text-xs); cursor: pointer;">--ap-control-height-' . s($name) . ' · ' . s($val) . '</button>';
        } ?>
    </div>

    <h3>Focus ring</h3>
    <p style="font-size: var(--ap-text-sm); color: var(--ap-color-text-secondary); margin-bottom: var(--ap-space-3);">Tab into each control below to see the focus ring. 3px width, 2px offset.</p>
    <div style="display: flex; gap: var(--ap-space-3); flex-wrap: wrap;">
        <button style="padding: var(--ap-space-3) var(--ap-space-4); border-radius: var(--ap-radius-sm); border: 1px solid var(--ap-color-border-strong); background: var(--ap-color-bg-surface);">Button</button>
        <input type="text" placeholder="Input" style="padding: var(--ap-space-3) var(--ap-space-4); border-radius: var(--ap-radius-sm); border: 1px solid var(--ap-color-border-strong); background: var(--ap-color-bg-surface);">
        <a href="#a11y" style="padding: var(--ap-space-3) var(--ap-space-4); color: var(--ap-color-primary);">Link</a>
    </div>
</section>

<!-- ─────────────────────────── COMPONENTS ─────────────────────────── -->
<section class="ap-sg__section" id="components">
    <h2 class="ap-sg__section-title">Components</h2>
    <p class="ap-sg__section-desc">Reusable components composed from the tokens above. Every component is available as a Mustache partial under <code>theme_airpayux/components/*</code>. Source SCSS lives in <code>scss/moodle/partials/_components-*.scss</code>.</p>

    <h3>Stat card &mdash; <code>theme_airpayux/components/stat_card</code></h3>
    <p class="ap-sg__section-desc">Canonical KPI/metric tile. Used on every dashboard, report, analytics, and admin landing page. Mobile-first: 4 columns desktop &rarr; 2 columns tablet &rarr; 1 column mobile via <code>.airpay-stat-grid</code>.</p>

    <h4 style="margin-top: var(--ap-space-6);">Six colour variants</h4>
    <div class="airpay-stat-grid" style="margin-bottom: var(--ap-space-8);">
        <?php
        $variants = [
            ['primary', 'users',         'Active Users',     '2,847', '+12% vs last month',     'up'],
            ['accent',  'book',          'Courses',          '412',   '+3 new this week',       'up'],
            ['success', 'check-circle',  'Completions',      '1,238', '78% completion rate',    'up'],
            ['warning', 'clock-o',       'Overdue',          '47',    'attention needed',       'flat'],
            ['danger',  'exclamation-triangle', 'Failed Logins', '23','+5 vs yesterday',        'down'],
            ['info',    'line-chart',    'Avg Session',      '14m',   'steady',                 'flat'],
        ];
        foreach ($variants as [$color, $icon, $label, $value, $trend, $dir]) {
            echo '<div class="airpay-stat-card airpay-stat-card--' . s($color) . '" role="group" aria-label="' . s($label . ': ' . $value . ', ' . $trend) . '">';
            echo '<div class="airpay-stat-card__icon" aria-hidden="true"><i class="fa fa-' . s($icon) . '"></i></div>';
            echo '<div class="airpay-stat-card__body">';
            echo '<span class="airpay-stat-card__value">' . s($value) . '</span>';
            echo '<span class="airpay-stat-card__label">' . s($label) . '</span>';
            echo '<span class="airpay-stat-card__trend airpay-stat-card__trend--' . s($dir) . '">';
            echo '<i class="fa fa-arrow-' . s($dir) . '" aria-hidden="true"></i>';
            echo '<span>' . s($trend) . '</span></span>';
            echo '</div></div>';
        }
        ?>
    </div>

    <h4 style="margin-top: var(--ap-space-6);">Linked variant &mdash; hover + focus states</h4>
    <p style="font-size: var(--ap-text-sm); color: var(--ap-color-text-secondary); margin-bottom: var(--ap-space-3);">Hover lifts the tile by 2px with shadow upgrade. Tab into them to see the focus ring.</p>
    <div class="airpay-stat-grid" style="grid-template-columns: repeat(3, 1fr); max-width: 800px; margin-bottom: var(--ap-space-8);">
        <a href="#components" class="airpay-stat-card airpay-stat-card--primary airpay-stat-card--linked" aria-label="Click through to user management">
            <div class="airpay-stat-card__icon" aria-hidden="true"><i class="fa fa-users"></i></div>
            <div class="airpay-stat-card__body">
                <span class="airpay-stat-card__value">2,847</span>
                <span class="airpay-stat-card__label">Active Users</span>
            </div>
        </a>
        <a href="#components" class="airpay-stat-card airpay-stat-card--success airpay-stat-card--linked" aria-label="Click through to completion report">
            <div class="airpay-stat-card__icon" aria-hidden="true"><i class="fa fa-check-circle"></i></div>
            <div class="airpay-stat-card__body">
                <span class="airpay-stat-card__value">1,238</span>
                <span class="airpay-stat-card__label">Completions</span>
            </div>
        </a>
        <a href="#components" class="airpay-stat-card airpay-stat-card--warning airpay-stat-card--linked" aria-label="Click through to overdue list">
            <div class="airpay-stat-card__icon" aria-hidden="true"><i class="fa fa-clock-o"></i></div>
            <div class="airpay-stat-card__body">
                <span class="airpay-stat-card__value">47</span>
                <span class="airpay-stat-card__label">Overdue items</span>
            </div>
        </a>
    </div>

    <h4 style="margin-top: var(--ap-space-6);">Mustache usage</h4>
    <pre style="background: var(--ap-color-bg-surface-alt); padding: var(--ap-space-4); border-radius: var(--ap-radius-sm); font-size: var(--ap-text-xs); overflow-x: auto;">{{&gt; theme_airpayux/components/stat_card }}
{
    "value":    "1,238",
    "label":    "Completions",
    "icon":     "check-circle",
    "color":    "success",
    "trend":    "78% rate",
    "trenddir": "up",
    "href":     "/local/airpay_reports/index.php"
}</pre>

    <h3 style="margin-top: var(--ap-space-10);">Course progress card &mdash; <code>theme_airpayux/components/course_progress_card</code></h3>
    <p class="ap-sg__section-desc">Canonical learner-facing course tile. Used on Dashboard "Continue Learning", My Learning, and Manager team drilldown. Mobile-first via <code>.airpay-course-grid</code> (3 &rarr; 2 &rarr; 1 cols).</p>

    <h4 style="margin-top: var(--ap-space-6);">Four status variants</h4>
    <div class="airpay-course-grid" style="margin-bottom: var(--ap-space-6);">
        <?php
        $courses = [
            ['fullname' => 'Anti-Money Laundering 2026', 'shortname' => 'AML-26', 'progress' => 0,   'status' => 'not_started', 'statuslabel' => 'Not started',   'subtitle' => 'Mandatory · Compliance'],
            ['fullname' => 'Customer Onboarding KYC', 'shortname' => 'KYC',    'progress' => 42,  'status' => 'in_progress', 'statuslabel' => 'In progress',  'subtitle' => 'Ops · 45 min',                'duration' => '45 min'],
            ['fullname' => 'Information Security Annual Refresh', 'shortname' => 'INFOSEC', 'progress' => 100, 'status' => 'completed',  'statuslabel' => 'Completed',    'subtitle' => 'Mandatory · Security'],
            ['fullname' => 'GDPR / DPDP Refresher (overdue)', 'shortname' => 'DPDP', 'progress' => 18,  'status' => 'overdue',    'statuslabel' => 'Overdue',      'subtitle' => 'Mandatory · was due 2026-04-30'],
        ];
        foreach ($courses as $c) {
            echo '<a class="airpay-course-card airpay-course-card--' . s($c['status']) . '" href="#components" aria-label="' . s($c['fullname'] . ', ' . $c['progress'] . '% complete') . '">';
            echo '<div class="airpay-course-card__thumb">';
            echo '<span class="airpay-course-card__initial" aria-hidden="true">' . s($c['shortname']) . '</span>';
            echo '<span class="airpay-course-card__badge airpay-course-card__badge--' . s($c['status']) . '">' . s($c['statuslabel']) . '</span>';
            echo '</div>';
            echo '<div class="airpay-course-card__body">';
            echo '<h4 class="airpay-course-card__title">' . s($c['fullname']) . '</h4>';
            echo '<p class="airpay-course-card__subtitle">' . s($c['subtitle']) . '</p>';
            echo '<div class="airpay-course-card__progress">';
            echo '<div class="airpay-course-card__track" role="progressbar" aria-valuenow="' . (int) $c['progress'] . '" aria-valuemin="0" aria-valuemax="100">';
            echo '<div class="airpay-course-card__fill" style="width: ' . (int) $c['progress'] . '%;"></div>';
            echo '</div>';
            echo '<span class="airpay-course-card__progress-text">' . (int) $c['progress'] . '%</span>';
            echo '</div>';
            if (!empty($c['duration'])) {
                echo '<span class="airpay-course-card__duration"><i class="fa fa-clock-o" aria-hidden="true"></i> ' . s($c['duration']) . '</span>';
            }
            echo '</div></a>';
        }
        ?>
    </div>

    <h4 style="margin-top: var(--ap-space-6);">Mustache usage</h4>
    <pre style="background: var(--ap-color-bg-surface-alt); padding: var(--ap-space-4); border-radius: var(--ap-radius-sm); font-size: var(--ap-text-xs); overflow-x: auto;">{{&gt; theme_airpayux/components/course_progress_card }}
{
    "fullname":    "Anti-Money Laundering 2026",
    "shortname":   "AML-26",
    "progress":    42,
    "viewurl":     "/course/view.php?id=123",
    "subtitle":    "Mandatory · 45 min",
    "status":      "in_progress",
    "statuslabel": "In progress",
    "duration":    "45 min"
}</pre>

    <h3 style="margin-top: var(--ap-space-10);">Activity item &mdash; <code>theme_airpayux/components/activity_item</code></h3>
    <p class="ap-sg__section-desc">Canonical row for any chronological-event list. Two layouts (<code>inline</code> default, <code>timeline</code> with dot+line) and seven semantic variants. Used on Dashboard "Recent Activity" (admin), "Activity Timeline" (learner), and the Manager team drilldown.</p>

    <h4 style="margin-top: var(--ap-space-6);">Inline layout &mdash; admin Recent Activity</h4>
    <div style="max-width: 560px; background: var(--ap-color-bg-surface); border: 1px solid var(--ap-color-border); border-radius: var(--ap-radius-md); padding: var(--ap-space-2); margin-bottom: var(--ap-space-8);">
        <ul class="airpay-activity-list">
            <?php
            $activities = [
                ['completion', 'check-circle',  'Sarah Khan completed Anti-Money Laundering 2026', '14 May, 10:32 AM'],
                ['enrolment',  'plus-circle',   'Arjun Mehta enrolled in Customer Onboarding KYC', '14 May, 09:48 AM'],
                ['badge',      'trophy',        'Priya Singh earned the 30-day streak badge',      '13 May, 06:12 PM'],
                ['quiz',       'question-circle','Vikas Rao scored 100% on InfoSec Refresh quiz',   '13 May, 03:21 PM'],
                ['submission', 'file-text-o',   'Rahul Das submitted Quiz 3 in KYC',               '12 May, 11:05 AM'],
                ['alert',      'exclamation-triangle', '3 mandatory courses became overdue today', '14 May, 00:01 AM'],
            ];
            foreach ($activities as [$variant, $icon, $text, $subtext]) {
                echo '<li class="airpay-activity airpay-activity--inline airpay-activity--' . s($variant) . '">';
                echo '<div class="airpay-activity__marker" aria-hidden="true"><i class="fa fa-' . s($icon) . '"></i></div>';
                echo '<div class="airpay-activity__body">';
                echo '<p class="airpay-activity__text">' . s($text) . '</p>';
                echo '<p class="airpay-activity__subtext">' . s($subtext) . '</p>';
                echo '</div></li>';
            }
            ?>
        </ul>
    </div>

    <h4 style="margin-top: var(--ap-space-6);">Timeline layout &mdash; learner Activity Timeline</h4>
    <div style="max-width: 480px; background: var(--ap-color-bg-surface); border: 1px solid var(--ap-color-border); border-radius: var(--ap-radius-md); padding: var(--ap-space-3); margin-bottom: var(--ap-space-8);">
        <ul class="airpay-activity-list">
            <?php
            $timeline = [
                ['completion', 'Completed Customer Onboarding KYC',           'Today',     true],
                ['quiz',       'Submitted quiz in InfoSec Refresh',           '13 May 2026', false],
                ['enrolment',  'Enrolled in Anti-Money Laundering 2026',      '10 May 2026', false],
                ['badge',      'Earned the 7-day learning streak badge',     '07 May 2026', false],
                ['submission', 'Submitted quiz in DPDP Compliance',           '05 May 2026', false],
            ];
            foreach ($timeline as [$variant, $text, $subtext, $istoday]) {
                $cls = 'airpay-activity airpay-activity--timeline airpay-activity--' . $variant;
                if ($istoday) {
                    $cls .= ' airpay-activity--today';
                }
                echo '<li class="' . s($cls) . '">';
                echo '<div class="airpay-activity__marker" aria-hidden="true"></div>';
                echo '<div class="airpay-activity__body">';
                echo '<p class="airpay-activity__text">' . s($text) . '</p>';
                echo '<p class="airpay-activity__subtext">' . s($subtext) . '</p>';
                echo '</div></li>';
            }
            ?>
        </ul>
    </div>

    <h4 style="margin-top: var(--ap-space-6);">Mustache usage</h4>
    <pre style="background: var(--ap-color-bg-surface-alt); padding: var(--ap-space-4); border-radius: var(--ap-radius-sm); font-size: var(--ap-text-xs); overflow-x: auto;">{{&gt; theme_airpayux/components/activity_item }}
{
    "text":    "Sarah completed Anti-Money Laundering 2026",
    "subtext": "14 May, 10:32 AM",
    "icon":    "check-circle",
    "variant": "completion",
    "layout":  "inline",
    "href":    "/course/view.php?id=42"
}</pre>

    <h3 style="margin-top: var(--ap-space-10);">Deadline tile &mdash; <code>theme_airpayux/components/deadline_tile</code></h3>
    <p class="ap-sg__section-desc">Four urgency states. The "urgent" variant icon pulses on render to draw the eye; "overdue" gets a thick left border. Used on the dashboard's Upcoming Deadlines, manager team compliance, and notification email templates.</p>

    <div style="max-width: 640px; margin-bottom: var(--ap-space-8);">
        <ul class="airpay-deadline-list">
            <?php
            $deadlines = [
                ['normal',  'calendar',              'Diversity & Inclusion 2026',       'Due: 30 May 2026'],
                ['soon',    'clock-o',               'KYC Customer Onboarding',          'Due in 3 days'],
                ['urgent',  'exclamation-circle',    'Information Security Annual',      'Due tomorrow'],
                ['overdue', 'exclamation-triangle',  'GDPR/DPDP Compliance Refresh',     'Overdue by 4 days'],
            ];
            foreach ($deadlines as [$urgency, $icon, $name, $when]) {
                echo '<li class="airpay-deadline airpay-deadline--' . s($urgency) . '">';
                echo '<div class="airpay-deadline__icon" aria-hidden="true"><i class="fa fa-' . s($icon) . '"></i></div>';
                echo '<div class="airpay-deadline__body">';
                echo '<span class="airpay-deadline__name">' . s($name) . '</span>';
                echo '<span class="airpay-deadline__date">' . s($when) . '</span>';
                echo '</div>';
                echo '<a href="#components" class="airpay-deadline__action airpay-btn airpay-btn--outline airpay-btn--sm" aria-label="View ' . s($name) . '">View</a>';
                echo '</li>';
            }
            ?>
        </ul>
    </div>

    <h4 style="margin-top: var(--ap-space-6);">Mustache usage</h4>
    <pre style="background: var(--ap-color-bg-surface-alt); padding: var(--ap-space-4); border-radius: var(--ap-radius-sm); font-size: var(--ap-text-xs); overflow-x: auto;">{{&gt; theme_airpayux/components/deadline_tile }}
{
    "coursename": "KYC Customer Onboarding",
    "duedate":    "17 May 2026",
    "urgency":    "soon",
    "icon":       "clock-o",
    "relative":   "Due in 3 days",
    "viewurl":    "/course/view.php?id=42"
}</pre>
</section>

</div>

<script>
// Motion preview — uses textContent only, no innerHTML.
(function() {
    var target = document.getElementById('ap-sg-motion-target');
    if (!target) return;
    var buttons = document.querySelectorAll('.ap-sg__motion-btn');
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var duration = btn.getAttribute('data-duration');
            var ease     = btn.getAttribute('data-ease');
            // Reset, force reflow, then animate.
            target.style.transition = 'none';
            target.style.transform  = 'translateX(0)';
            // eslint-disable-next-line no-unused-expressions
            target.offsetWidth;
            target.style.transition = 'transform var(--ap-duration-' + duration + ') var(--ap-ease-' + ease + ')';
            target.style.transform  = 'translateX(120px) rotate(8deg)';
            setTimeout(function() {
                target.style.transition = 'transform var(--ap-duration-' + duration + ') var(--ap-ease-' + ease + ')';
                target.style.transform  = 'translateX(0) rotate(0)';
            }, 800);
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();
