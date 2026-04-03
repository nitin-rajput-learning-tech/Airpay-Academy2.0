<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Airpay Academy UX theme functions.
 *
 * Provides SCSS callback functions for the theme. The pre-SCSS callback
 * injects our design system tokens, and the extra-SCSS callback layers
 * our component overrides after epsilon's styles.
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Pre-SCSS callback: inject design system tokens.
 *
 * These SCSS variables are compiled BEFORE epsilon's main SCSS,
 * allowing us to override Bootstrap and epsilon defaults.
 *
 * @param theme_config $theme The theme config object.
 * @return string SCSS content to prepend.
 */
function theme_airpayux_get_pre_scss($theme) {
    $scss = '';

    // Airpay Academy Design System — Phase 6A tokens.
    // These override epsilon's defaults (primary: #25467a, secondary: #006699).
    $scss .= '
// ═══════════════════════════════════════════════════════════
// AIRPAY ACADEMY DESIGN SYSTEM — Phase 6A
// ═══════════════════════════════════════════════════════════

// Brand Colors
$airpay-primary:     #0066A7;   // Airpay blue
$airpay-primary-dk:  #004d7a;   // Darker blue (hover states)
$airpay-accent:      #0f7a73;   // Teal accent
$airpay-accent-lt:   #e6f5f3;   // Light teal (backgrounds)

// Semantic Colors
$airpay-success:     #2e7d32;   // Green (completion, pass)
$airpay-warning:     #f57c00;   // Orange (approaching deadline)
$airpay-danger:      #c62828;   // Red (overdue, fail)
$airpay-info:        #0288d1;   // Blue (informational)

// Neutrals
$airpay-text:        #1a1a2e;   // Primary text
$airpay-text-sec:    #607286;   // Secondary text
$airpay-text-muted:  #8896a6;   // Muted text
$airpay-border:      #e3eaf3;   // Borders
$airpay-bg-body:     #F2F4FB;   // Page background
$airpay-bg-surface:  #FFFFFF;   // Card/surface background

// Typography
$font-family-sans-serif: "Montserrat", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;

// Spacing Scale (8px base)
$airpay-space-xs:    4px;
$airpay-space-sm:    8px;
$airpay-space-md:    16px;
$airpay-space-lg:    24px;
$airpay-space-xl:    32px;
$airpay-space-2xl:   48px;

// Border Radius
$airpay-radius-sm:   8px;
$airpay-radius-md:   12px;
$airpay-radius-lg:   16px;
$airpay-radius-xl:   20px;
$airpay-radius-pill: 999px;

// Shadows
$airpay-shadow-sm:   0 4px 20px rgba(0, 51, 102, 0.06);
$airpay-shadow-md:   0 8px 32px rgba(0, 51, 102, 0.08);
$airpay-shadow-lg:   0 12px 48px rgba(0, 51, 102, 0.12);
$airpay-shadow-hover: 0 8px 32px rgba(0, 51, 102, 0.14);

// ═══════════════════════════════════════════════════════════
// Map to Bootstrap/Epsilon variables
// ═══════════════════════════════════════════════════════════
$primary:   $airpay-primary;
$body-bg:   $airpay-bg-body;
$body-color: $airpay-text;
';

    // Also include epsilon's own pre-SCSS (to preserve its settings).
    $scss .= theme_epsilon_get_pre_scss($theme);

    return $scss;
}

/**
 * Extra SCSS callback: inject component overrides.
 *
 * These styles are compiled AFTER epsilon's main SCSS,
 * allowing us to override specific components with higher specificity.
 *
 * @param theme_config $theme The theme config object.
 * @return string SCSS content to append.
 */
function theme_airpayux_get_extra_scss($theme) {
    $scss = '';

    // Include epsilon's extra SCSS (background images, admin custom SCSS).
    $scss .= theme_epsilon_get_extra_scss($theme);

    // Phase 6A: Minimal overrides — just enough to verify the cascade works.
    // More component overrides will be added in Phase 6B.
    $scss .= '
// ═══════════════════════════════════════════════════════════
// AIRPAY UX COMPONENT OVERRIDES — Phase 6A
// ═══════════════════════════════════════════════════════════

// Verify cascade: override card border-radius globally.
.card {
    border-radius: $airpay-radius-md;
}

// Verify cascade: override button border-radius.
.btn {
    border-radius: $airpay-radius-sm;
}
';

    return $scss;
}
