<?php
/**
 * Hook callback registrations for local_airpay_catalog.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_airpay_catalog\hook_callbacks::class, 'before_footer_html_generation'],
        'priority' => 100,
    ],
];
