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
 * @package    theme_epsilon
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
function theme_epsilon_css_tree_post_processor($tree, $theme) {
    error_log('theme_epsilon_css_tree_post_processor() is deprecated. Required' .
        'prefixes for Bootstrap are now in theme/epsilon/scss/moodle/prefixes.scss');
    $prefixer = new theme_epsilon\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_epsilon_get_extra_scss($theme) {
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
function theme_epsilon_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
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
// function theme_epsilon_get_main_scss_content($theme) {
//     global $CFG;

//     $scss = '';
//     $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
//     $fs = get_file_storage();

//     $context = context_system::instance();
//     if ($filename == 'default.scss') {
//         $scss .= file_get_contents($CFG->dirroot . '/theme/epsilon/scss/preset/default.scss');
//     } else if ($filename == 'plain.scss') {
//         $scss .= file_get_contents($CFG->dirroot . '/theme/epsilon/scss/preset/plain.scss');
//     } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_epsilon', 'preset', 0, '/', $filename))) {
//         $scss .= $presetfile->get_content();
//     } else {
//         // Safety fallback - maybe new installs etc.
//         $scss .= file_get_contents($CFG->dirroot . '/theme/epsilon/scss/preset/default.scss');
//     }

//     $scheme_scss = '';
//     $theme_scheme = $theme->settings->theme_scheme;
//     if($theme_scheme){
//         if(file_exists($CFG->dirroot . '/theme/epsilon/scss/schemes/'.$theme_scheme.'.scss')){
//             $scheme_scss = file_get_contents($CFG->dirroot . '/theme/epsilon/scss/schemes/'.$theme_scheme.'.scss');
//         }
//     }

//     $scss .= $scheme_scss;

//     return $scss;
// }
function theme_epsilon_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/epsilon/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/epsilon/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_epsilon', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/epsilon/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_epsilon_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/epsilon/style/moodle.css');
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_epsilon_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        'brandcolor' => ['primary'],
        'bodybgcolor' => ['bodybgcolor'],
        'primarycolor' => ['primarycolor'],
        'secondarycolor' => ['secondarycolor'],
        'hovercolor' => ['hovercolor'],
    ];

    // Prepend variables first.
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
function get_css_for_costcenter_scss($costcenter_scheme = false){
    global $CFG;

    if(empty($costcenter_scheme)){
        return '';
    }

    if($costcenter_scheme){
        if(file_exists($CFG->dirroot . '/theme/epsilon/scss/schemes/'.$costcenter_scheme.'.scss')){
            $www_file = $CFG->wwwroot.'/theme/epsilon/style/'.$costcenter_scheme.'.css';
            $dir_file = $CFG->dirroot.'/theme/epsilon/style/'.$costcenter_scheme.'.css';

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

            $scheme_scss = file_get_contents($CFG->dirroot . '/theme/epsilon/scss/schemes/'.$costcenter_scheme.'.scss');
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

