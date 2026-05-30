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
 * @copyright  2018 eAbyas Info Solutons Pvt Ltd, India; 2026 Airpay Payment Services (Sentientia white-label fork)
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

    // 3. Moodle 5.2 component-scoped variable adoption (Phase B.3.e+).
    //    Loaded LAST in the pre-scss chain so its `!default` declarations
    //    only kick in when nothing above (our tokens, dark tokens, theme
    //    settings, customer scsspre) has set the variable. This makes
    //    every new 5.2 boost variable available to component SCSS without
    //    clobbering customer brand overrides.
    $tokens52path = $theme->dir . '/scss/moodle/_tokens-52.scss';
    if (file_exists($tokens52path)) {
        $scss .= file_get_contents($tokens52path);
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

// Legacy `before_standard_top_of_body_html` callback — Moodle 5.1 only.
//
// Moodle 5.2 introduced
// `\core\hook\output\before_standard_top_of_body_html_generation` and
// scans every plugin's lib.php for `<component>_<oldcallback>` function
// names; if it finds one, it fires it AND prints a deprecation notice
// (independent of whether a new-style hook subscription exists).
//
// To stay clean on 5.2 we CONDITIONALLY DEFINE the legacy function:
// only when the new hook class is not present (i.e. we're on 5.1).
// On 5.2 the canonical entry point is
// `\theme_airpayux\hook_callbacks::before_standard_top_of_body_html_generation()`
// wired via `db/hooks.php`.
if (!class_exists('\\core\\hook\\output\\before_standard_top_of_body_html_generation')) {
    /**
     * Inject suspended-user data into pages that show user lists so the
     * AMD decorator can paint inline "Suspended" / "Deleted" badges next
     * to user names (P0 borrow #10).
     *
     * @deprecated since Moodle 5.2 — see classes/hook_callbacks.php +
     *   db/hooks.php. This function is conditionally compiled out on 5.2
     *   to suppress the `process_legacy_callbacks()` deprecation notice.
     *
     * @return string HTML — a single hidden <script type="application/json">
     *                blob consumed by AMD theme_airpayux/user_status_badge.
     *                Empty string when no badges are needed.
     */
    function theme_airpayux_before_standard_top_of_body_html(): string {
        // Delegate to the same builder the hook class uses — single
        // source of truth across 5.1 and 5.2.
        if (class_exists('\\theme_airpayux\\hook_callbacks')) {
            return \theme_airpayux\hook_callbacks::build_user_status_html();
        }
        return '';
    }
}

