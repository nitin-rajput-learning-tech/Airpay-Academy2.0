// Probe — capture full HTML of airpay profile page for Public user.
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(180000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'public.uat@airpay.test');
await page.fill('input[name="password"]', 'PublicUAT@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 180000 });

await page.goto(`${BASE}/local/airpay_users/profile.php`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2000);

const probe = await page.evaluate(() => {
    const html = document.documentElement.outerHTML;
    return {
        url: window.location.href,
        title: document.title,
        bodyclass: document.body.className,
        hasTenantFooter: html.includes('airpay-tenant-footer'),
        hasIndependentLearning: html.includes('independent learning hub'),
        hasStandardFooter: html.includes('standard_footer_html'),
        // Look for the footer tag
        footerHTML: document.querySelector('footer')?.outerHTML?.slice(0, 500) || 'no footer tag',
        pageBottom: html.slice(-1500),
    };
});

console.log('=== Profile page probe ===');
console.log('URL:', probe.url);
console.log('Title:', probe.title);
console.log('Body class:', probe.bodyclass);
console.log('hasTenantFooter (string match):', probe.hasTenantFooter);
console.log('hasIndependentLearning:', probe.hasIndependentLearning);
console.log('');
console.log('--- Footer tag content (first 500 chars) ---');
console.log(probe.footerHTML);
console.log('');
console.log('--- Last 1500 chars of HTML ---');
console.log(probe.pageBottom);

await browser.close();
