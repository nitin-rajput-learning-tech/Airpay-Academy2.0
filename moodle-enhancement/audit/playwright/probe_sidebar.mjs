// Probe — dump dashboard HTML structure for the Public user.
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

console.log('Post-login URL:', page.url());

await page.goto(`${BASE}/my/dashboard.php`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2500);

const probe = await page.evaluate(() => {
    return {
        url: window.location.href,
        title: document.title,
        bodyclasses: document.body.className,
        haveApSidebar: !!document.querySelector('.ap-sidebar'),
        haveAside: document.querySelectorAll('aside').length,
        haveNavs: document.querySelectorAll('nav').length,
        firstNavHTML: document.querySelector('nav')?.outerHTML?.slice(0, 600) || 'no nav',
        firstAsideHTML: document.querySelector('aside')?.outerHTML?.slice(0, 600) || 'no aside',
        // Any element with "sidebar" in class
        sidebarLike: Array.from(document.querySelectorAll('[class*="sidebar"]'))
            .slice(0, 5)
            .map(el => ({ tag: el.tagName, cls: el.className, children: el.children.length })),
    };
});

console.log('\n=== DOM probe ===');
console.log(JSON.stringify(probe, null, 2));

await browser.close();
