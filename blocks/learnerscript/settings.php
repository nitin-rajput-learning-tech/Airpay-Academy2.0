<?php
defined('MOODLE_INTERNAL') || die();
$ADMIN->add('modules', new admin_category('block_learnerscript', get_string('pluginname', 'block_learnerscript')));

$setting = new admin_settingpage('learnerscriptreports', get_string('reports', 'block_learnerscript'), 'moodle/site:config');

    $setting->add(new admin_setting_configcheckbox('block_learnerscript/sqlsecurity', get_string('sqlsecurity', 'block_learnerscript'), get_string('sqlsecurityinfo', 'block_learnerscript'), 1));
    $setting->add(new admin_setting_configcheckbox('block_learnerscript/exportfilesystem', get_string('exportfilesystem', 'block_learnerscript'), get_string('exportfilesystem', 'block_learnerscript'), 1));
    $setting->add(new admin_setting_configtext('block_learnerscript/exportfilesystempath', get_string('exportfilesystempath', 'block_learnerscript'), get_string('exportfilesystempathdesc', 'block_learnerscript'), 'learnerscript/reports', PARAM_URL, 40));
    $setting->add(new admin_setting_configcolourpicker('block_learnerscript/analytics_color', get_string('analytics_color', 'block_learnerscript'), get_string('analytics_color_desc', 'block_learnerscript'), '#FFFFFF'));
    $setting->add(new admin_setting_configstoredfile('block_learnerscript/logo', get_string('logo', 'block_learnerscript'), get_string('logo_desc', 'block_learnerscript'), 'logo', 0, array('maxfiles' => 1, 'accepted_types' => array('image'))));