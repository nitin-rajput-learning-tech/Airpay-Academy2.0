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
 * Theme functions.
 *
 * @package    theme_airpayux
 * @copyright  2018 eAbyas Info Solutons Pvt Ltd, India
 * @author     eAbyas  <info@eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post process the CSS tree.
 *
 * @param string $tree The CSS tree.
 * @param theme_config $theme The theme config object.
 */
function theme_airpayux_css_tree_post_processor($tree, $theme) {
    error_log('theme_airpayux_css_tree_post_processor() is deprecated. Required' .
        'prefixes for Bootstrap are now in theme/epsilon/scss/moodle/prefixes.scss');
    $prefixer = new theme_airpayux\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_airpayux_get_extra_scss($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');

    // Sets the background image, and its settings.
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover;";
        $content .= ' } }';
    }

    $loginbg = $theme->setting_file_url('loginbg', 'loginbg');
    // Sets the background image, and its settings.
    if (!empty($loginbg)) {
        $content .= 'body#page-login-index { ';
        $content .= "background: #fff url('$loginbg') no-repeat right top !important;";
        $content .= "background-size: 100% 100% !important;";
        $content .= ' }';
    }

    // Include component library SCSS.
    $componentspath = $theme->dir . '/scss/moodle/components.scss';
    if (file_exists($componentspath)) {
        $content .= file_get_contents($componentspath);
    }

    // Include dark mode + high contrast CSS layers.
    $darkmodepath = $theme->dir . '/scss/moodle/dark_mode.scss';
    if (file_exists($darkmodepath)) {
        $content .= file_get_contents($darkmodepath);
    }

    // Always return the background image with the scss when we have it.
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_airpayux_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage'
         || $filearea === 'loginlogo' || $filearea === 'carousellogo' || $filearea === 'slider1' || $filearea === 'slider2' || $filearea === 'slider3'
         || $filearea === 'slider4' || $filearea === 'slider5') || $filearea === 'favicon') {
        $theme = theme_config::load('epsilon');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
// function theme_airpayux_get_main_scss_content($theme) {
//     global $CFG;

//     $scss = '';
//     $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
//     $fs = get_file_storage();

//     $context = context_system::instance();
//     if ($filename == 'default.scss') {
//         $scss .= file_get_contents($CFG->dirroot . '/theme/airpayux/scss/preset/default.scss');
//     } else if ($filename == 'plain.scss') {
//         $scss .= file_get_contents($CFG->dirroot . '/theme/airpayux/scss/preset/plain.scss');
//     } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_airpayux', 'preset', 0, '/', $filename))) {
//         $scss .= $presetfile->get_content();
//     } else {
//         // Safety fallback - maybe new installs etc.
//         $scss .= file_get_contents($CFG->dirroot . '/theme/airpayux/scss/preset/default.scss');
//     }

//     $scheme_scss = '';
//     $theme_scheme = $theme->settings->theme_scheme;
//     if($theme_scheme){
//         if(file_exists($CFG->dirroot . '/theme/airpayux/scss/schemes/'.$theme_scheme.'.scss')){
//             $scheme_scss = file_get_contents($CFG->dirroot . '/theme/airpayux/scss/schemes/'.$theme_scheme.'.scss');
//         }
//     }

//     $scss .= $scheme_scss;

//     return $scss;
// }
function theme_airpayux_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/airpayux/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/airpayux/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_airpayux', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/airpayux/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_airpayux_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/airpayux/style/moodle.css');
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_airpayux_get_pre_scss($theme) {
    global $CFG;

    $scss = '';

    // 1. Load centralized design tokens FIRST (single source of truth).
    $tokenpath = $theme->dir . '/scss/moodle/_tokens.scss';
    if (file_exists($tokenpath)) {
        $scss .= file_get_contents($tokenpath);
    }
    $darkpath = $theme->dir . '/scss/moodle/_tokens-dark.scss';
    if (file_exists($darkpath)) {
        $scss .= file_get_contents($darkpath);
    }

    // 2. Legacy SCSS variables from theme settings (backward compat).
    $configurable = [
        'brandcolor' => ['primary'],
        'bodybgcolor' => ['bodybgcolor'],
        'primarycolor' => ['primarycolor'],
        'secondarycolor' => ['secondarycolor'],
        'hovercolor' => ['hovercolor'],
    ];
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    if (!empty($theme->settings->fontsize)) {
        $scss .= '$font-size-base: ' . (1 / 100 * $theme->settings->fontsize) . "rem !default;\n";
    }

    return $scss;
}

/**
 * Returns the scheme css file to load in header for respective costcenters/orgs.
 *
 * @param costcenter_scheme selected costcenter/org scheme name.
 * @return url
 */
function theme_airpayux_get_css_for_costcenter_scss($costcenter_scheme = false){
    global $CFG;

    if(empty($costcenter_scheme)){
        return '';
    }

    if($costcenter_scheme){
        if(file_exists($CFG->dirroot . '/theme/airpayux/scss/schemes/'.$costcenter_scheme.'.scss')){
            $www_file = $CFG->wwwroot.'/theme/airpayux/style/'.$costcenter_scheme.'.css';
            $dir_file = $CFG->dirroot.'/theme/airpayux/style/'.$costcenter_scheme.'.css';

            $current_css = file_get_contents($dir_file);
            $current_css_count = strlen($current_css);

            $time = time();
            $themerev_diff = $time - $CFG->themerev;

            if($themerev_diff < 15){
                $fo = fopen($dir_file, 'w');
                if($fo){
                    fwrite($fo, '');
                    fclose($fo);
                }
            }
            
            if($current_css_count > 0){
                return $www_file;
            }

            $scheme_scss = file_get_contents($CFG->dirroot . '/theme/airpayux/scss/schemes/'.$costcenter_scheme.'.scss');
            $scss = $scheme_scss;

            $compiler = new core_scss();
            $rawscss = $compiler->append_raw_scss($scss);
            $scss_content = implode(';', $rawscss);

            $compiled_css = $compiler->to_css();
            $minified_css = core_minify::css($compiled_css);

            // chmod($dir_file, 0777);
            $fp = fopen($dir_file, 'w');
            if($fp){
                fwrite($fp, $minified_css);
                fclose($fp);
            }else{
                fclose($fp);
                return '';
            }
            return $www_file;
        }
    }
    return '';
}

/**
 * P0 borrow #10 (Moodle 5.2, 2026-05-23) — inject suspended-user data
 * into pages that show user lists so the AMD decorator can paint
 * inline "Suspended" / "Deleted" badges next to user names.
 *
 * Triggers on pagetypes that surface user lists:
 *   - grade-report-*       (gradebook)
 *   - report-*             (activity/log/completion reports)
 *   - user-index           (course participants)
 *   - course-user          (per-user activity report)
 *
 * Server-side prefetch — one DB hit per request, scoped to the current
 * user's tenant via $USER->open_path. Avoids the WS round-trip and the
 * tenant + capability surface that would come with one. Failure is
 * silent (returns empty string) — never blocks the page.
 *
 * On Moodle 5.2 upgrade: replace this whole function with the upstream
 * `\core\hook\output\before_standard_top_of_body_html_generation` hook
 * + 5.2's built-in suspended-row marker.
 *
 * @return string HTML — a single hidden <script type="application/json"> blob
 *                consumed by AMD theme_airpayux/user_status_badge.
 */
function theme_airpayux_before_standard_top_of_body_html() {
    global $PAGE, $USER, $DB;

    // Anonymous / unset session — nothing to render.
    if (empty($USER->id) || isguestuser()) {
        return '';
    }
    if (empty($PAGE) || empty($PAGE->pagetype)) {
        return '';
    }

    $pt = $PAGE->pagetype;
    $needs = (
        str_starts_with($pt, 'grade-report-')
        || str_starts_with($pt, 'report-')
        || $pt === 'user-index'
        || $pt === 'course-user'
    );
    if (!$needs) {
        return '';
    }

    // Tenant scope — BizLMS open_path. If column missing (test fixture
    // without local_costcenter), silently no-op.
    $tenantpath = $USER->open_path ?? '';
    if (!$tenantpath) {
        return '';
    }

    try {
        // Fetch all suspended OR deleted users in the same tenant subtree.
        // Suspended-user count in a 3500-user tenant is typically 50-300
        // rows = a couple of KB on the wire. Faster than per-row lookups
        // and cheaper than a WS round-trip with auth+cap on every page.
        $sql = "SELECT id, suspended, deleted
                  FROM {user}
                 WHERE (suspended = 1 OR deleted = 1)
                   AND open_path LIKE :path";
        $rows = $DB->get_records_sql($sql, ['path' => $tenantpath . '%']);
    } catch (\Throwable $e) {
        // PHPUnit fixture without open_path, or DB hiccup.
        // Never block a real report page on a status-badge prefetch.
        return '';
    }

    if (empty($rows)) {
        return '';
    }

    // Build compact userid → status map.
    $data = [];
    foreach ($rows as $r) {
        $data[(int)$r->id] = !empty($r->deleted) ? 'deleted' : 'suspended';
    }

    // Queue the AMD decorator. It picks up the JSON blob on init.
    $PAGE->requires->js_call_amd('theme_airpayux/user_status_badge', 'init');

    // Embed the data inline. Hidden from screen readers via aria-hidden;
    // type=application/json so it's never executed as JS, only parsed.
    return '<script id="airpay-user-status-data" type="application/json" aria-hidden="true">'
        . json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        . '</script>';
}

