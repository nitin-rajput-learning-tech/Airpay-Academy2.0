<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add link to the management panel in admin navigation.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_emails_manage',
        get_string('pluginname', 'local_airpay_emails') . ' — Management',
        new moodle_url('/local/airpay_emails/manage.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_emails_preview',
        get_string('pluginname', 'local_airpay_emails') . ' — Preview',
        new moodle_url('/local/airpay_emails/preview.php'),
        'moodle/site:config'
    ));
}
