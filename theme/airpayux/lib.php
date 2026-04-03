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

    // Phase 6B: Component overrides — Airpay design system applied.
    $scss .= '
// ═══════════════════════════════════════════════════════════
// AIRPAY UX COMPONENT OVERRIDES — Phase 6B
// ═══════════════════════════════════════════════════════════

// ── Global ──
.card {
    border-radius: $airpay-radius-md;
    border: 1px solid $airpay-border;
    box-shadow: $airpay-shadow-sm;
}

.btn {
    border-radius: $airpay-radius-sm;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.btn-primary {
    background: $airpay-primary;
    border-color: $airpay-primary;
    &:hover, &:focus {
        background: $airpay-primary-dk;
        border-color: $airpay-primary-dk;
        box-shadow: 0 4px 12px rgba(0, 102, 167, 0.3);
    }
}

// ── Navbar (B7) ──
.airpay-navbar {
    background: $airpay-bg-surface;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
    padding: 0 $airpay-space-lg;
    min-height: 60px;
    border-bottom: none;
    z-index: 1030;
}

.airpay-navbar__brand {
    font-weight: 800;
    font-size: 0.95rem;
    color: $airpay-primary;
    letter-spacing: -0.01em;
}

.airpay-navbar__greeting {
    margin-left: $airpay-space-md;
    border-left: 1px solid $airpay-border;
    padding-left: $airpay-space-md;
}

.airpay-navbar__hello {
    font-size: 0.9rem;
    font-weight: 600;
    color: $airpay-text;
}

// Navbar icon buttons — circular with border
.airpay-navbar .navbar-nav .nav-link,
.airpay-navbar #usernavigation .popover-region .nav-link {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 1px solid $airpay-border;
    margin: 0 3px;
    color: $airpay-text-sec;
    font-size: 1rem;
    transition: all 0.2s;
    padding: 0;

    &:hover {
        background: rgba(0, 102, 167, 0.08);
        color: $airpay-primary;
        border-color: $airpay-primary;
    }
}

// Notification badge styling
.airpay-navbar .popover-region .count-container {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid $airpay-bg-surface;
}

// Quick Access button — match icon button style
.airpay-navbar .quickaccess-popover-container .nav-link {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1px solid $airpay-border;
}

// User avatar in navbar
.airpay-navbar .usermenu-container .usermenu .dropdown-toggle {
    border-radius: $airpay-radius-pill;
    border: 1px solid $airpay-border;
    padding: 2px 10px 2px 2px;
    transition: all 0.2s;

    &:hover {
        border-color: $airpay-primary;
        box-shadow: 0 0 0 3px rgba(0, 102, 167, 0.08);
    }
}

// Edit mode toggle — cleaner pill shape
.airpay-navbar .editmode-switch-form {
    .custom-control-input:checked ~ .custom-control-label::before {
        background-color: $airpay-primary;
        border-color: $airpay-primary;
    }
}

// Hide motivational quote on all screens (cleanup)
.airpay-navbar .usermsg span.text-muted {
    display: none;
}

// Mobile navbar adjustments
@media (max-width: 767px) {
    .airpay-navbar {
        padding: 0 $airpay-space-md;
        min-height: 52px;
    }
    .airpay-navbar .navbar-nav .nav-link,
    .airpay-navbar #usernavigation .popover-region .nav-link {
        width: 34px;
        height: 34px;
        font-size: 0.875rem;
        margin: 0 2px;
    }
    .airpay-navbar .usermenu-container .usermenu .dropdown-toggle {
        padding: 2px 6px 2px 2px;
    }
}

// ── Footer (B8) ──
.airpay-footer {
    margin-top: auto;
    background: #1a1d27;
    color: #9ba4b4;
    font-size: 0.8125rem;
}

.airpay-footer__main {
    border-top: 3px solid $airpay-primary;
}

.airpay-footer__inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: $airpay-space-lg $airpay-space-xl;
}

.airpay-footer__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: $airpay-space-md;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    flex-wrap: wrap;
    gap: $airpay-space-md;
}

.airpay-footer__links {
    display: flex;
    align-items: center;
    gap: $airpay-space-sm;
    flex-wrap: wrap;

    a {
        color: #9ba4b4;
        font-size: 0.8125rem;
        font-weight: 500;
        transition: color 0.2s;
        &:hover {
            color: #fff;
            text-decoration: none;
        }
    }
}

.airpay-footer__sep {
    display: inline-block;
    width: 4px;
    height: 4px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
}

.airpay-footer__social {
    a {
        color: #9ba4b4;
        margin-left: $airpay-space-sm;
        font-size: 1rem;
        transition: color 0.2s;
        &:hover { color: #fff; }
    }
}

.airpay-footer__bottom {
    padding-top: $airpay-space-md;
    text-align: center;
}

.airpay-footer__copy {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0;
}

.airpay-footer__debug {
    background: #0f1117;
    padding: $airpay-space-sm $airpay-space-lg;
    font-size: 0.75rem;
    a { color: $airpay-accent; }
}

.airpay-footer__back2top {
    display: none;
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 40px;
    height: 40px;
    background: $airpay-primary;
    color: #fff;
    border-radius: 50%;
    text-align: center;
    line-height: 40px;
    font-size: 14px;
    z-index: 100;
    box-shadow: $airpay-shadow-md;
    transition: all 0.2s;
    &:hover {
        background: $airpay-primary-dk;
        color: #fff;
        text-decoration: none;
        transform: translateY(-2px);
    }
}

// Hide epsilon default footer elements that we replaced
#page-footer > .footer_datacourse > .row,
#page-footer > .footer > .foot-copyright {
    display: none !important;
}

// ── Responsive footer ──
@media (max-width: 575px) {
    .airpay-footer__top {
        flex-direction: column;
        align-items: flex-start;
    }
    .airpay-footer__inner {
        padding: $airpay-space-md $airpay-space-md;
    }
}
';

    return $scss;
}
