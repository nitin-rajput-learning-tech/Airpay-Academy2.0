// Inspect axe contrast violations in detail.
import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';

const BASE = 'http://localhost:8080/moodle';
const ADMIN = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

for (const url of ['/local/airpay_users/bulk_csv.php',
                   '/local/airpay_programs/view.php?id=2&tab=overview',
                   '/local/airpay_roles/index.php',
                   '/local/airpay_manager/index.php',
                   '/local/airpay_skills/designation_matrix.php']) {
    console.log('\n=== ' + url + ' ===');
    await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    const axe = new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    const r = await axe.analyze();
    for (const v of r.violations.filter(x => x.id === 'color-contrast')) {
        console.log('-- color-contrast --');
        for (const node of v.nodes) {
            console.log('  target: ' + node.target);
            console.log('  html  : ' + node.html.replace(/\s+/g, ' ').slice(0, 130));
            for (const f of node.any) {
                if (f.id === 'color-contrast' && f.data) {
                    console.log('  ratio : ' + f.data.contrastRatio + ' (need '
                        + (f.data.expectedContrastRatio || '4.5') + ')'
                        + ' fg=' + f.data.fgColor + ' bg=' + f.data.bgColor
                        + ' size=' + f.data.fontSize + ' weight=' + f.data.fontWeight);
                }
            }
        }
    }
}
await browser.close();
