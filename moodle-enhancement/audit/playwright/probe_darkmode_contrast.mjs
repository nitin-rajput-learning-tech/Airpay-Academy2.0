import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(90000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

await page.goto(`${BASE}/my/dashboard.php`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2000);

// Detect what triggers actual dark mode. Probe the topbar dark-mode button.
const darkBtn = await page.$('[data-action*="dark"], [data-action*="theme"], .ap-darkmode-toggle');
console.log('Dark toggle button: ' + (!!darkBtn));

// Check existing dark mode state.
const existingState = await page.evaluate(() => ({
    bodyClass: document.body.className,
    htmlAttr: document.documentElement.getAttribute('data-bs-theme'),
    lsKeys: Object.keys(localStorage),
}));
console.log('Before toggle: ' + JSON.stringify(existingState));

// Apply the dark mode the same way the UAT does.
await page.evaluate(() => {
    document.body.classList.add('ap-dark', 'dark-mode');
    document.documentElement.setAttribute('data-bs-theme', 'dark');
});
await page.waitForTimeout(500);

const afterState = await page.evaluate(() => ({
    bodyClass: document.body.className,
    bodyBg: getComputedStyle(document.body).backgroundColor,
    bodyColor: getComputedStyle(document.body).color,
}));
console.log('After toggle: ' + JSON.stringify(afterState));

// Run axe and look at specific violations.
const r = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
for (const v of r.violations.filter(x => x.impact === 'serious')) {
    console.log('\n-- ' + v.id + ' --');
    console.log('Help: ' + v.help);
    for (const node of v.nodes.slice(0, 3)) {
        console.log('  target: ' + JSON.stringify(node.target));
        console.log('  html  : ' + node.html.replace(/\s+/g, ' ').slice(0, 200));
        for (const f of node.any || []) {
            if (f.data) console.log('  data  : ' + JSON.stringify(f.data));
        }
    }
}

await browser.close();
