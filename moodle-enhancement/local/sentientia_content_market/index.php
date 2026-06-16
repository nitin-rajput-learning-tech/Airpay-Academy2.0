<?php
/**
 * Content Marketplace browse page.
 *
 * Lists imported third-party catalog items for the current user's tenant.
 * Supports search, provider filter, content-type filter, and skill filter.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_content_market:view', $context);

// Feature flag gate — 403 when master switch is OFF.
if (!class_exists('\local_sentientia_platform\feature_flags')
    || !\local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')) {
    throw new moodle_exception('featureunavailable', 'local_sentientia_content_market');
}

// Derive tenant from current user's open_path.
$costcenterid = 0;
if (class_exists('\local_sentientia_platform\tenant')) {
    $costcenterid = \local_sentientia_platform\tenant::root_for_current_user();
}

// Input — all sanitised, no raw $_GET use.
$query        = optional_param('q',            '', PARAM_TEXT);
$provider     = optional_param('provider',     '', PARAM_ALPHANUMEXT);
$content_type = optional_param('content_type', '', PARAM_ALPHANUMEXT);
$level        = optional_param('level',        '', PARAM_ALPHANUMEXT);
$skill_id     = optional_param('skill_id',     0,  PARAM_INT);
$page         = optional_param('page',         1,  PARAM_INT);

if ($page < 1) {
    $page = 1;
}

$PAGE->set_url('/local/sentientia_content_market/index.php', [
    'q'            => $query,
    'provider'     => $provider,
    'content_type' => $content_type,
    'level'        => $level,
    'skill_id'     => $skill_id,
    'page'         => $page,
]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_sentientia_content_market'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_content_market'));

// Fetch results.
$aggregator = new \local_sentientia_content_market\market_aggregator();
$result     = $aggregator->search(
    $costcenterid,
    $query,
    $provider,
    $content_type,
    $level,
    $skill_id,
    $page,
    20
);

$total      = $result['total'];
$items      = $result['items'];
$page_size  = 20;
$total_pages = (int)ceil($total / $page_size);

// Build template context.
$providers_list = [];
foreach ($aggregator->get_all_providers() as $p) {
    $providers_list[] = [
        'key'     => $p->get_provider_key(),
        'label'   => $p->get_display_name(),
        'active'  => $provider === $p->get_provider_key(),
    ];
}

$items_ctx = [];
foreach ($items as $item) {
    $items_ctx[] = [
        'id'           => (int)$item->id,
        'title'        => format_string($item->title),
        'description'  => format_text($item->description ?? '', FORMAT_PLAIN),
        'provider'     => s($item->provider),
        'content_type' => s($item->content_type ?? ''),
        'level'        => s($item->level ?? ''),
        'language'     => s($item->language),
        'duration_mins'=> $item->duration_mins,
        'thumbnail_url'=> $item->thumbnail_url ? s($item->thumbnail_url) : null,
        'provider_url' => $item->provider_url ? s($item->provider_url) : null,
        'has_thumbnail'=> !empty($item->thumbnail_url),
        'has_url'      => !empty($item->provider_url),
        'is_free'      => $item->price_usd === null || $item->price_usd == 0,
        'price_usd'    => $item->price_usd !== null ? number_format((float)$item->price_usd, 2) : null,
    ];
}

$template_context = [
    'baseurl'         => (new moodle_url('/local/sentientia_content_market/index.php'))->out(false),
    'query'           => s($query),
    'has_query'       => $query !== '',
    'items'           => $items_ctx,
    'has_items'       => !empty($items_ctx),
    'total'           => $total,
    'page'            => $page,
    'total_pages'     => $total_pages,
    'has_prev'        => $page > 1,
    'has_next'        => $page < $total_pages,
    'prev_page'       => $page - 1,
    'next_page'       => $page + 1,
    'providers'       => $providers_list,
    'provider_filter' => s($provider),
    'level_filter'    => s($level),
    'type_filter'     => s($content_type),
    'can_manage'      => has_capability('local/sentientia_content_market:manageproviders', $context),
    'can_sync'        => has_capability('local/sentientia_content_market:syncproviders', $context),
    'sesskey'         => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_content_market/browse', $template_context);
echo $OUTPUT->footer();
