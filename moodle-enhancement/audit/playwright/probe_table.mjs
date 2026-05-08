// Probe whether new datatable mounts populate rows after the AMD attribute fix.
// Targets the four newly-shipped plugins' admin pages.
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const ADMIN = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';

const PAGES = [
    { url: '/local/airpay_roles/index.php',
      mounts: [
        { sel: '#airpay-roles-table', ws: 'list_roles' },
      ] },
    { url: '/local/airpay_roles/audit.php',
      mounts: [
        { sel: '[data-region="airpay-datatable"]', ws: 'audit' },
      ] },
    { url: '/local/airpay_challenge/index.php',
      mounts: [
        { sel: '#airpay-challenge-table', ws: 'list_challenges' },
      ] },
    { url: '/local/airpay_manager/requests.php',
      mounts: [
        { sel: '#ap-mgr-requests-table', ws: 'list_requests' },
      ] },
    { url: '/local/airpay_manager/allocations.php',
      mounts: [
        { sel: '#ap-mgr-alloc-table', ws: 'list_allocations' },
      ] },
    // designation_matrix renders statically (Mustache loop over rows), not via shared datatable.
];

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(60000);
page.setDefaultTimeout(60000);

const consoleErrors = [];
page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
});
const networkFailures = [];
page.on('response', resp => {
    if (resp.status() >= 500) {
        networkFailures.push({ url: resp.url(), status: resp.status() });
    }
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

const results = [];

for (const p of PAGES) {
    consoleErrors.length = 0;
    networkFailures.length = 0;
    console.log('\n=== ' + p.url + ' ===');
    await page.goto(`${BASE}${p.url}`, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(4000); // give AMD time to fetch

    for (const mount of p.mounts) {
        const el = await page.$(mount.sel);
        if (!el) {
            results.push({ url: p.url, sel: mount.sel, ok: false, reason: 'mount missing' });
            console.log('  ' + mount.sel + ' → MOUNT NOT FOUND');
            continue;
        }
        // Diagnostic: read the data-* attrs to confirm template rendered correctly
        const diag = await el.evaluate(node => ({
            wsName: node.getAttribute('data-ws-name'),
            endpoint: node.getAttribute('data-endpoint'),
            hasColumnsJson: !!node.getAttribute('data-columns-json'),
            hasColumns: !!node.getAttribute('data-columns'),
            columnsJsonLen: (node.getAttribute('data-columns-json') || '').length,
            extraArgsLen: (node.getAttribute('data-extra-args') || '').length,
            innerLen: node.innerHTML.length,
            classList: node.className,
            region: node.getAttribute('data-region'),
        }));
        console.log('  attrs: ws=' + diag.wsName + ' columnsLen=' + diag.columnsJsonLen
            + ' extraLen=' + diag.extraArgsLen + ' region=' + diag.region
            + ' inner=' + diag.innerLen);
        // Look for typical content signals: table rows OR a "no records" empty state.
        const innerHtml = await el.evaluate(node => node.innerHTML);
        const hasTable = /<table/.test(innerHtml);
        const hasRows = /<tr[\s>]/.test(innerHtml);
        const rowCount = (innerHtml.match(/<tr[\s>]/g) || []).length;
        const hasEmptyState = /no records|nothing to show|empty/i.test(innerHtml);
        const html = innerHtml.length;
        results.push({
            url: p.url, sel: mount.sel,
            html, hasTable, hasRows, rowCount, hasEmptyState,
            ok: hasTable && (hasRows || hasEmptyState),
        });
        console.log('  ' + mount.sel + ' html=' + html
            + ' table=' + hasTable + ' rows=' + rowCount
            + ' empty=' + hasEmptyState);
    }
    if (consoleErrors.length) {
        console.log('  CONSOLE ERRORS: ' + consoleErrors.length);
        for (const e of consoleErrors.slice(0, 5)) console.log('    ' + e.slice(0, 300));
    }
    if (networkFailures.length) {
        console.log('  5xx FAILURES: ' + networkFailures.length);
        for (const f of networkFailures.slice(0, 3)) console.log('    ' + f.status + ' ' + f.url);
    }
}

await browser.close();

console.log('\n=== SUMMARY ===');
const ok = results.filter(r => r.ok).length;
const total = results.length;
console.log(`${ok}/${total} mounts populated correctly`);
for (const r of results.filter(r => !r.ok)) {
    console.log('  FAIL: ' + r.url + ' ' + r.sel + ' reason=' + (r.reason || 'no rows / no empty state'));
}
process.exit(ok === total ? 0 : 1);
