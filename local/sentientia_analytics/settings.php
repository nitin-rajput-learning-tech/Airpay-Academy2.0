<?php
/**
 * Admin settings for local_sentientia_analytics: the Training ROI assumptions.
 *
 * roi_calculator has always read these config names (roi_<key>) and fallen
 * back to its DEFAULT_* constants, and the ROI card has always told admins to
 * "adjust them in plugin settings", but there was no settings page until
 * 2026-11-19 (QW-ROI). The defaults here are those same constants, so saving
 * the page unchanged leaves every figure as it was.
 *
 * Values are site-wide. Per-customer assumptions are a follow-up.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The class_exists() guard: the top-level local/ copy of this plugin does not
// ship roi_calculator (grandfathered in tools/tree-drift-baseline.txt).
if ($hassiteconfig && class_exists('\local_sentientia_analytics\roi_calculator')) {
    $settings = new admin_settingpage(
        'local_sentientia_analytics',
        get_string('pluginname', 'local_sentientia_analytics')
    );

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_sentientia_analytics/roi_heading',
            get_string('roi_settings_heading', 'local_sentientia_analytics'),
            get_string('roi_settings_heading_desc', 'local_sentientia_analytics')
        ));

        $defaults = \local_sentientia_analytics\roi_calculator::defaults();
        foreach (\local_sentientia_analytics\roi_calculator::ASSUMPTIONS as $key => [$labelid, $unit]) {
            // A non-negative number with up to two decimals and no separators.
            // PARAM_FLOAT would refuse "2.50" (it compares the cleaned value as text).
            $settings->add(new admin_setting_configtext(
                'local_sentientia_analytics/roi_' . $key,
                get_string($labelid, 'local_sentientia_analytics'),
                get_string('roi_' . $key . '_desc', 'local_sentientia_analytics'),
                (string) $defaults[$key],
                '/^\d+(\.\d{1,2})?$/',
                12
            ));
        }
    }

    $ADMIN->add('localplugins', $settings);
}
