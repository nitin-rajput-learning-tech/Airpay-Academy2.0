<?php
// Verify ADR-008 customer_brand resolver: same bundle shape pre/post
// migration, cache works, invalidation works, manifest endpoint still
// renders identical JSON.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

\core\session\manager::write_close();

use local_sentientia_platform\customer;

$pass = 0;
$fail = 0;
function expect($got, $desc) {
    global $pass, $fail;
    if ($got) { echo "  PASS — $desc\n"; $pass++; }
    else      { echo "  FAIL — $desc\n"; $fail++; }
}

echo "1. customer::branding(1) returns the same shape as before\n";
$brand = customer::branding(1);
expect($brand['name']         === 'Airpay Academy', 'name = Airpay Academy');
expect($brand['short_name']   === 'Academy', 'short_name = Academy');
expect($brand['theme_color']  === '#0066A7', 'theme_color = #0066A7');
expect($brand['bg_color']     === '#F2F4FB', 'bg_color = #F2F4FB');
expect($brand['lang']         === 'en', 'lang = en');
expect(str_contains($brand['icon_192_url'], '/local/sentientia_platform/pix/customer/1/icon-192.png'),
    'icon_192_url path correct');
expect(str_contains($brand['icon_512_url'], '/local/sentientia_platform/pix/customer/1/icon-512.png'),
    'icon_512_url path correct');
expect($brand['start_url']    === '/my/dashboard.php?utm_source=pwa_install', 'start_url correct');

echo "\n2. Phase 2 new keys\n";
expect(isset($brand['status_bar_style']), 'status_bar_style present');
expect(isset($brand['categories']), 'categories present');
expect(is_array($brand['categories']), 'categories is array');
expect(in_array('education', $brand['categories']), "categories includes 'education'");

echo "\n3. Cache hit on 2nd call (no DB roundtrip)\n";
$brand2 = customer::branding(1);
expect($brand === $brand2, '2nd call returns identical bundle');

echo "\n4. Unknown customer id falls back to default Airpay bundle\n";
$brand_unknown = customer::branding(99999);
expect($brand_unknown['name'] === 'Airpay Academy', 'unknown id -> Airpay fallback');

echo "\n5. invalidate_branding_cache() works\n";
customer::invalidate_branding_cache(1);
// Modify the DB row to a known canary value
global $DB;
$DB->set_field('local_sentientia_customer_brand', 'short_name', 'CANARY',
    ['customerid' => 1]);
$brand3 = customer::branding(1);
expect($brand3['short_name'] === 'CANARY', 'fresh fetch sees the DB change');

// Restore
$DB->set_field('local_sentientia_customer_brand', 'short_name', 'Academy',
    ['customerid' => 1]);
customer::invalidate_branding_cache(1);
$brand4 = customer::branding(1);
expect($brand4['short_name'] === 'Academy', 'after restore + invalidate, original returns');

echo "\n6. Manifest endpoint still returns valid JSON with Airpay branding\n";
$ch = curl_init($GLOBALS['CFG']->wwwroot . '/local/sentientia_pwa/manifest.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$body = curl_exec($ch);
$json = json_decode($body, true);
curl_close($ch);
expect(is_array($json), 'manifest.php returns valid JSON');
expect($json['name'] === 'Airpay Academy', "manifest.name === 'Airpay Academy'");
expect($json['theme_color'] === '#0066A7', "manifest.theme_color === '#0066A7'");
expect(count($json['icons'] ?? []) >= 2, 'manifest has >= 2 icons');

echo "\nSummary: $pass passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
