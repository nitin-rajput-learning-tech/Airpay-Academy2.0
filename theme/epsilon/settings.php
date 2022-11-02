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
 * @package   theme_epsilon
 * @copyright  2018 eAbyas Info Solutons Pvt Ltd, India
 * @author     eAbyas  <info@eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_epsilon_admin_settingspage_tabs('themesettingepsilon', get_string('configtitle', 'theme_epsilon'));
    $page = new admin_settingpage('theme_epsilon_general', get_string('generalsettings', 'theme_epsilon'));

    // Unaddable blocks.
    // Blocks to be excluded when this theme is enabled in the "Add a block" list: Administration, Navigation, Courses and
    // Section links.
    $default = 'navigation,settings,course_list,section_links';
    $setting = new admin_setting_configtext('theme_epsilon/unaddableblocks',
        get_string('unaddableblocks', 'theme_epsilon'), get_string('unaddableblocks_desc', 'theme_epsilon'), $default, PARAM_TEXT);
    $page->add($setting);

    // Preset.
    $name = 'theme_epsilon/preset';
    $title = get_string('preset', 'theme_epsilon');
    $description = get_string('preset_desc', 'theme_epsilon');
    $default = 'default.scss';

    $context = context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'theme_epsilon', 'preset', 0, 'itemid, filepath, filename', false);

    $choices = [];
    foreach ($files as $file) {
        $choices[$file->get_filename()] = $file->get_filename();
    }
    // These are the built in presets.
    $choices['default.scss'] = 'default.scss';
    $choices['plain.scss'] = 'plain.scss';

    $setting = new admin_setting_configthemepreset($name, $title, $description, $default, $choices, 'epsilon');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Preset files setting.
    $name = 'theme_epsilon/presetfiles';
    $title = get_string('presetfiles','theme_epsilon');
    $description = get_string('presetfiles_desc', 'theme_epsilon');

    $setting = new admin_setting_configstoredfile($name, $title, $description, 'preset', 0,
        array('maxfiles' => 20, 'accepted_types' => array('.scss')));
    $page->add($setting);

    // Background image setting.
    $name = 'theme_epsilon/backgroundimage';
    $title = get_string('backgroundimage', 'theme_epsilon');
    $description = get_string('backgroundimage_desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Login Background image setting.
    $name = 'theme_epsilon/loginbackgroundimage';
    $title = get_string('loginbackgroundimage', 'theme_epsilon');
    $description = get_string('loginbackgroundimage_desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbackgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Variable $body-color.
    // We use an empty default value because the default colour should come from the preset.
    $name = 'theme_epsilon/brandcolor';
    $title = get_string('brandcolor', 'theme_epsilon');
    $description = get_string('brandcolor_desc', 'theme_epsilon');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Must add the page after definiting all the settings!
    $settings->add($page);

    // Advanced settings.
    $page = new admin_settingpage('theme_epsilon_advanced', get_string('advancedsettings', 'theme_epsilon'));

    // Raw SCSS to include before the content.
    $setting = new admin_setting_scsscode('theme_epsilon/scsspre',
        get_string('rawscsspre', 'theme_epsilon'), get_string('rawscsspre_desc', 'theme_epsilon'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS to include after the content.
    $setting = new admin_setting_scsscode('theme_epsilon/scss', get_string('rawscss', 'theme_epsilon'),
        get_string('rawscss_desc', 'theme_epsilon'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
    
    $page = new admin_settingpage('theme_epsilon_custom', get_string('customsettings', 'theme_epsilon'));

    //Logo setting over site
    $name = 'theme_epsilon/logo';
    $title = get_string('logo', 'theme_epsilon');
    $description = get_string('logodesc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    
    // custom favicon
    $name = 'theme_epsilon/favicon';
    $title = get_string('favicon', 'theme_epsilon');
    $description = get_string('favicondesc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Login Page Logo.
    $name = 'theme_epsilon/loginlogo';
    $title = get_string('loginlogo', 'theme_epsilon');
    $description = get_string('loginlogo_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginlogo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);


    //Welcome Text
    $name = 'theme_epsilon/welcometext';
    $title = get_string('welcometext', 'theme_epsilon');
    $description = get_string('welcometext_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //Login Page Logo Caption
    $name = 'theme_epsilon/logocaption';
    $title = get_string('logocaption', 'theme_epsilon');
    $description = get_string('logocaptiondesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    
    $page->add($setting);
    

    //loginordering setting
    $name = 'theme_epsilon/loginorder';
    $title = get_string('loginorder', 'theme_epsilon');
    $description = get_string('loginorder_desc', 'theme_epsilon');
    $default = 0;
    $choices = array('left', 'right');
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    //Login Page Slider Logo
    $name = 'theme_epsilon/carousellogo';
    $title = get_string('carousellogo', 'theme_epsilon');
    $description = get_string('carousellogo_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'carousellogo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Description under Login Page Logo.
    $name = 'theme_epsilon/logindesc';
    $title = get_string('logindesc', 'theme_epsilon');
    $description = get_string('logindesc_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Description for buttons on Login Page.
    $name = 'theme_epsilon/helpdesc';
    $title = get_string('helpdesc', 'theme_epsilon');
    $description = get_string('helpdesc_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Description for buttons on Login Page.
    $name = 'theme_epsilon/helpdesc';
    $title = get_string('helpdesc', 'theme_epsilon');
    $description = get_string('helpdesc_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/contact';
    $title = get_string('contact', 'theme_epsilon');
    $description = get_string('contact_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/aboutus';
    $title = get_string('aboutus', 'theme_epsilon');
    $description = get_string('aboutus_desc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    // Must add the page after definiting all the settings!
    //login page slider image1 
    $name = 'theme_epsilon/slider1';
    $title = get_string('slider1', 'theme_epsilon');
    $description = get_string('slider1desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider1');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image2 
    $name = 'theme_epsilon/slider2';
    $title = get_string('slider2', 'theme_epsilon');
    $description = get_string('slider2desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider2');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image3 
    $name = 'theme_epsilon/slider3';
    $title = get_string('slider3', 'theme_epsilon');
    $description = get_string('slider3desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider3');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image4 
    $name = 'theme_epsilon/slider4';
    $title = get_string('slider4', 'theme_epsilon');
    $description = get_string('slider4desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider4');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //login page slider image5 
    $name = 'theme_epsilon/slider5';
    $title = get_string('slider5', 'theme_epsilon');
    $description = get_string('slider5desc', 'theme_epsilon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slider5');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    //fonts setting
    $name = 'theme_epsilon/font';
    $title = get_string('font', 'theme_epsilon');
    $description = get_string('font_desc', 'theme_epsilon');
    $default = 3;
    $choices = array('Lato', 'Open Sans', 'PT Sans', 'Roboto', 'Maven Pro', 'Comfortaa');
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    //==== footer settings =====
    // Footnote setting.
    $name = 'theme_epsilon/copyright';
    $title = get_string('copyright', 'theme_epsilon');
    $description = get_string('copyrightdesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/facebook';
    $title = get_string('facebook', 'theme_epsilon');
    $description = get_string('facebookdesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/twitter';
    $title = get_string('twitter', 'theme_epsilon');
    $description = get_string('twitterdesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/linkedin';
    $title = get_string('linkedin', 'theme_epsilon');
    $description = get_string('linkedindesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/youtube';
    $title = get_string('youtube', 'theme_epsilon');
    $description = get_string('youtubedesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/instagram';
    $title = get_string('instagram', 'theme_epsilon');
    $description = get_string('instagramdesc', 'theme_epsilon');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);


    $name = 'theme_epsilon/quickinfo';
    $title = get_string('quickinfo', 'theme_epsilon');
    $description = get_string('quickinfodesc', 'theme_epsilon');
    $default = 'no';
    $choices = array('no' => get_string('disable', 'theme_epsilon'),
                     'yes' => get_string('enable', 'theme_epsilon')
                 );
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);
    
    $name = 'theme_epsilon/quickinfo1';
    $title = get_string('quickinfo1', 'theme_epsilon');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_epsilon/quickinfo2';
    $title = get_string('quickinfo2', 'theme_epsilon');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_epsilon/quickinfo3';
    $title = get_string('quickinfo3', 'theme_epsilon');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_epsilon/quickinfo4';
    $title = get_string('quickinfo4', 'theme_epsilon');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_epsilon/quickinfo5';
    $title = get_string('quickinfo5', 'theme_epsilon');
    $description = '';
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
    
    // $page = new admin_settingpage('theme_epsilon_color', get_string('colorsettings', 'theme_epsilon'));

    // $name = 'theme_epsilon/theme_scheme';
    // $title = get_string('theme_scheme', 'theme_epsilon');
    // $description = get_string('theme_scheme_desc', 'theme_epsilon');
    // $default = 'scheme1';
    // $choices = array('scheme1' => get_string('scheme_1', 'theme_epsilon'),
    //                  'scheme2' => get_string('scheme_2', 'theme_epsilon'),
    //                  'scheme3' => get_string('scheme_3', 'theme_epsilon'),
    //                  'scheme4' => get_string('scheme_4', 'theme_epsilon'),
    //                  'scheme5' => get_string('scheme_5', 'theme_epsilon'),
    //                  'scheme6' => get_string('scheme_6', 'theme_epsilon')
  
    //              );
    
    // $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    // $setting->set_updatedcallback('theme_reset_all_caches');
    // $page->add($setting);

    //Custom SCSS to change the Body bg color
    
    // $settings->add($page);

    // color settings.
    $page = new admin_settingpage('theme_epsilon_color', get_string('colorsettings', 'theme_epsilon'));


    // Site brand color
    $name = 'theme_epsilon/secondarycolor';
    $title = get_string('secondarycolor', 'theme_epsilon');
    $description = get_string('secondarycolor_desc', 'theme_epsilon');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#006699');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Site buttons color
    $name = 'theme_epsilon/primarycolor';
    $title = get_string('primarycolor', 'theme_epsilon');
    $description = get_string('primarycolor_desc', 'theme_epsilon');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#25467a');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Hover color
    $name = 'theme_epsilon/hovercolor';
    $title = get_string('hovercolor', 'theme_epsilon');
    $description = get_string('hovercolor_desc', 'theme_epsilon');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#006699');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
