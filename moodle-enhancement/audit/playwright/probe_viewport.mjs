// Quick probe: dump actual rendering metrics for the dashboard +
// one of our admin pages so we can see if "the site looks zoomed"
// is real (root font-size too high, or DPR != 1, or zoom transform).

import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const ADMIN = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

// Login.
await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

const surfaces = ['/my/dashboard.php', '/local/airpay_roles/index.php',
    '/local/airpay_challenge/index.php'];
for (const url of surfaces) {
    await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    const m = await page.evaluate(() => ({
        url: location.href,
        viewport: { w: window.innerWidth, h: window.innerHeight },
        dpr: window.devicePixelRatio,
        rootFontSize: getComputedStyle(document.documentElement).fontSize,
        bodyFontSize: getComputedStyle(document.body).fontSize,
        bodyZoom: getComputedStyle(document.body).zoom,
        bodyTransform: getComputedStyle(document.body).transform,
        htmlZoom: getComputedStyle(document.documentElement).zoom,
        htmlTransform: getComputedStyle(document.documentElement).transform,
        h1Count: document.querySelectorAll('h1').length,
        h1FontSize: document.querySelector('h1') ? getComputedStyle(document.querySelector('h1')).fontSize : 'none',
        viewportMetaContent: document.querySelector('meta[name="viewport"]')?.content || 'none',
        bodyClasses: document.body.className.split(/\s+/).slice(0, 8),
    }));
    console.log(JSON.stringify(m, null, 2));
}

await browser.close();
