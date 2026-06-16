<?php
/**
 * Cache definitions for local_sentientia_content_market.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // Provider adapter registry — list of available provider class names.
    // Short TTL: admin can add/remove providers without waiting hours.
    'provider_registry' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,   // 5 minutes
        'simplekeys'         => true,
        'staticacceleration' => true,
    ],

    // Per-tenant catalog item listing — main browse page result set.
    // Invalidated on every successful sync run.
    'catalog_listing' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600,   // 10 minutes
        'simplekeys' => true,
    ],

    // Per-item skill mapping results — avoids re-querying the join
    // for every card render on the browse page.
    'item_skills' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600,
        'simplekeys' => true,
    ],

];
