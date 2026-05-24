// Deep probe of one filepicker + one filemanager to find the right
// draftitemid hidden field name + the right repository ID for upload.
import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(90000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

for (const url of [
    '/local/airpay_users/bulk_csv.php',           // filepicker
    '/local/airpay_users/photo.php?id=2',         // filemanager
]) {
    console.log('\n=== ' + url + ' ===');
    await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    const info = await page.evaluate(() => {
        const hidden = Array.from(document.querySelectorAll('input[type="hidden"]'))
            .filter(h => h.value && /^\d{4,}$/.test(h.value))
            .map(h => ({
                name: h.name,
                value: h.value,
                id: h.id,
            }));
        // FP containers carry data attributes with repo info.
        const fps = Array.from(document.querySelectorAll(
            '.filepicker, [data-fieldtype="filepicker"], .filemanager, [data-fieldtype="filemanager"]'));
        const fpData = fps.map(fp => ({
            cls: fp.className,
            ds: Object.fromEntries(Object.entries(fp.dataset)),
        }));
        // Look for any element with data-fp-repo or similar.
        const repoEls = Array.from(document.querySelectorAll(
            '[data-fp-repo], [data-repo-id], [data-fp-itemid]'));
        const repos = repoEls.map(e => ({
            tag: e.tagName,
            cls: e.className.slice(0, 60),
            ds: Object.fromEntries(Object.entries(e.dataset)),
        }));
        // FP options from M.core_filepicker.
        const options = window.M && window.M.core_filepicker
            ? Object.keys(window.M.core_filepicker)
            : 'no M.core_filepicker';
        return { hidden, fpData, repos, options };
    });

    console.log('Hidden inputs (4+ digit values):');
    for (const h of info.hidden) console.log('  ', JSON.stringify(h));
    console.log('FP containers:');
    for (const f of info.fpData) console.log('  ', JSON.stringify(f));
    console.log('Repo elements:');
    for (const r of info.repos.slice(0, 5)) console.log('  ', JSON.stringify(r));
    console.log('M.core_filepicker:', info.options);
}

await browser.close();
