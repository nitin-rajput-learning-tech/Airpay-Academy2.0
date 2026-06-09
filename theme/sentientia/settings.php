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
 * @package   theme_sentientia
 * @copyright  2026 Airpay Payment Services - Sentientia LMS
 * @author     Sentientia LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // SENTIENTIA-CORE-MOD: section id was the leftover parent-theme name 'themesettingepsilon'
    // (forked from epsilon). Moodle's theme-selector auto-links to 'themesetting<themename>'
    // = 'themesettingsentientia', so the admin "Settings" link 404'd with sectionerror. Renamed to
    // match the convention (Boost→themesettingboost, Classic→themesettingclassic). Stored settings
    // are keyed by 'theme_sentientia/*' and are unaffected by this admin-tree node rename.
    $settings = new theme_sentientia_admin_settingspage_tabs('themesettingsentientia', get_string('configtitle', 'theme_sentientia'));
    $page = new admin_settingpage('theme_sentientia_general', get_string('generalsettings', 'theme_sentientia'));

    // Unaddable blocks.
    // Blocks to be excluded when this theme is enabled in the "Add a block" list: Administration, Navigation, Courses and
    // Section links.
    $default = 'navigation,settings,course_list,section_links';
    $setting = new admin_setting_configtext('theme_sentientia/unaddableblocks',
        get_string('unaddableblocks', 'theme_sentientia'), get_string('unaddableblocks_desc', 'theme_sentientia'), $default, PARAM_TEXT);
    $page->add($setting);

    // Preset.
    $name = 'theme_sentientia/preset';
    $title = get_string('preset', 'theme_sentientia');
    $description = get_string('preset_desc', 'theme_sentientia');
    $default = 'default.scss';

    $context = context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'theme_sentientia', 'preset', 0, 'itemid, filepath, filename', false);

    $choices = [];
    foreach ($files as $file) {
        $choices[$file->get_filename()] = $file->get_filename();
    }
    // These are the built in presets.
    $choices['default.scss'] = 'default.scss';
    $choices['plain.scss'] = 'plain.scss';

    $setting = new admin_setting_configthemepreset($name, $title, $description, $default, $choices, 'sentientia');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Preset files setting.
    $name = 'theme_sentientia/presetfiles';
    $title = get_string('presetfiles','theme_sentientia');
    $description = get_string('presetfiles_desc', 'theme_sentientia');

    $setting = new admin_setting_configstoredfile($name, $title, $description, 'preset', 0,
        array('maxfiles' => 20, 'accepted_types' => array('.scss')));
    $page->add($setting);

    // Background image setting.
    $name = 'theme_sentientia/backgroundimage';
    $title = get_string('backgroundimage', 'theme_sentientia');
    $description = get_string('backgroundimage_desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Login Background image setting.
    $name = 'theme_sentientia/loginbackgroundimage';
    $title = get_string('loginbackgroundimage', 'theme_sentientia');
    $description = get_string('loginbackgroundimage_desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbackgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Variable $body-color.
    // We use an empty default value because the default colour should come from the preset.
    $name = 'theme_sentientia/brandcolor';
    $title = get_string('brandcolor', 'theme_sentientia');
    $description = get_string('brandcolor_desc', 'theme_sentientia');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Must add the page after definiting all the settings!
    $settings->add($page);

    // Advanced settings.
    $page = new admin_settingpage('theme_sentientia_advanced', get_string('advancedsettings', 'theme_sentientia'));

    // Raw SCSS to include before the content.
    $setting = new admin_setting_scsscode('theme_sentientia/scsspre',
        get_string('rawscsspre', 'theme_sentientia'), get_string('rawscsspre_desc', 'theme_sentientia'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS to include after the content.
    $setting = new admin_setting_scsscode('theme_sentientia/scss', get_string('rawscss', 'theme_sentientia'),
        get_string('rawscss_desc', 'theme_sentientia'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
    
    $page = new admin_settingpage('theme_sentientia_custom', get_string('customsettings', 'theme_sentientia'));

    //Logo setting over site
    $name = 'theme_sentientia/logo';
    $title = get_string('logo', 'theme_sentientia');
    $description = get_string('logodesc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    
    // custom favicon
    $name = 'theme_sentientia/favicon';
    $title = get_string('favicon', 'theme_sentientia');
    $description = get_string('favicondesc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Login Page Logo.
    $name = 'theme_sentientia/loginlogo';
    $title = get_string('loginlogo', 'theme_sentientia');
    $description = get_string('loginlogo_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginlogo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);


    //Welcome Text
    $name = 'theme_sentientia/welcometext';
    $title = get_string('welcometext', 'theme_sentientia');
    $description = get_string('welcometext_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //Login Page Logo Caption
    $name = 'theme_sentientia/logocaption';
    $title = get_string('logocaption', 'theme_sentientia');
    $description = get_string('logocaptiondesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    
    $page->add($setting);
    

    //loginordering setting
    $name = 'theme_sentientia/loginorder';
    $title = get_string('loginorder', 'theme_sentientia');
    $description = get_string('loginorder_desc', 'theme_sentientia');
    $default = 0;
    $choices = array('left', 'right');
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    //Login Page Slider Logo
    $name = 'theme_sentientia/carousellogo';
    $title = get_string('carousellogo', 'theme_sentientia');
    $description = get_string('carousellogo_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'carousellogo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Description under Login Page Logo.
    $name = 'theme_sentientia/logindesc';
    $title = get_string('logindesc', 'theme_sentientia');
    $description = get_string('logindesc_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Description for buttons on Login Page.
    $name = 'theme_sentientia/helpdesc';
    $title = get_string('helpdesc', 'theme_sentientia');
    $description = get_string('helpdesc_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Description for buttons on Login Page.
    $name = 'theme_sentientia/helpdesc';
    $title = get_string('helpdesc', 'theme_sentientia');
    $description = get_string('helpdesc_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/contact';
    $title = get_string('contact', 'theme_sentientia');
    $description = get_string('contact_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/aboutus';
    $title = get_string('aboutus', 'theme_sentientia');
    $description = get_string('aboutus_desc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Must add the page after definiting all the settings!
    //login page slider image1 
    $name = 'theme_sentientia/slider1';
    $title = get_string('slider1', 'theme_sentientia');
    $description = get_string('slider1desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider1');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image2 
    $name = 'theme_sentientia/slider2';
    $title = get_string('slider2', 'theme_sentientia');
    $description = get_string('slider2desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider2');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image3 
    $name = 'theme_sentientia/slider3';
    $title = get_string('slider3', 'theme_sentientia');
    $description = get_string('slider3desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider3');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image4 
    $name = 'theme_sentientia/slider4';
    $title = get_string('slider4', 'theme_sentientia');
    $description = get_string('slider4desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider4');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image5 
    $name = 'theme_sentientia/slider5';
    $title = get_string('slider5', 'theme_sentientia');
    $description = get_string('slider5desc', 'theme_sentientia');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider5');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //fonts setting
    $name = 'theme_sentientia/font';
    $title = get_string('font', 'theme_sentientia');
    $description = get_string('font_desc', 'theme_sentientia');
    $default = 3;
    $choices = array('Lato', 'Open Sans', 'PT Sans', 'Roboto', 'Maven Pro', 'Comfortaa');
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    //==== footer settings =====
    // Footnote setting.
    $name = 'theme_sentientia/copyright';
    $title = get_string('copyright', 'theme_sentientia');
    $description = get_string('copyrightdesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/facebook';
    $title = get_string('facebook', 'theme_sentientia');
    $description = get_string('facebookdesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/twitter';
    $title = get_string('twitter', 'theme_sentientia');
    $description = get_string('twitterdesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/linkedin';
    $title = get_string('linkedin', 'theme_sentientia');
    $description = get_string('linkedindesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/youtube';
    $title = get_string('youtube', 'theme_sentientia');
    $description = get_string('youtubedesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/instagram';
    $title = get_string('instagram', 'theme_sentientia');
    $description = get_string('instagramdesc', 'theme_sentientia');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);


    $name = 'theme_sentientia/quickinfo';
    $title = get_string('quickinfo', 'theme_sentientia');
    $description = get_string('quickinfodesc', 'theme_sentientia');
    $default = 'no';
    $choices = array('no' => get_string('disable', 'theme_sentientia'),
                     'yes' => get_string('enable', 'theme_sentientia')
                 );
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_sentientia/quickinfo1';
    $title = get_string('quickinfo1', 'theme_sentientia');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_sentientia/quickinfo2';
    $title = get_string('quickinfo2', 'theme_sentientia');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_sentientia/quickinfo3';
    $title = get_string('quickinfo3', 'theme_sentientia');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_sentientia/quickinfo4';
    $title = get_string('quickinfo4', 'theme_sentientia');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_sentientia/quickinfo5';
    $title = get_string('quickinfo5', 'theme_sentientia');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
    
    // Costcenter Scheme Settings (per-tenant branding).
    $page = new admin_settingpage('theme_sentientia_color', get_string('colorsettings', 'theme_sentientia'));

    $name = 'theme_sentientia/theme_scheme';
    $title = get_string('theme_scheme', 'theme_sentientia');
    $description = get_string('theme_scheme_desc', 'theme_sentientia');
    $default = 'airpay_internal';
    $choices = array(
        'airpay_internal' => 'Airpay Internal',
        'marketplace'     => 'Public Marketplace',
        'zeea_whitelabel' => 'ZEEA Whitelabel',
    );
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // color settings.
    $page = new admin_settingpage('theme_sentientia_color', get_string('colorsettings', 'theme_sentientia'));

    // Site buttons color
    $name = 'theme_sentientia/primarycolor';
    $title = get_string('primarycolor', 'theme_sentientia');
    $description = get_string('primarycolor_desc', 'theme_sentientia');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#25467a');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Site brand color
    $name = 'theme_sentientia/secondarycolor';
    $title = get_string('secondarycolor', 'theme_sentientia');
    $description = get_string('secondarycolor_desc', 'theme_sentientia');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#006699');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    

    // Hover color
    $name = 'theme_sentientia/hovercolor';
    $title = get_string('hovercolor', 'theme_sentientia');
    $description = get_string('hovercolor_desc', 'theme_sentientia');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#006699');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
