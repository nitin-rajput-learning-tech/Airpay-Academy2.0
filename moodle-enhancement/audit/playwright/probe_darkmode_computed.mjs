import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(180000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 180000 });

await page.goto(`${BASE}/local/airpay_courses/featured.php`,
    { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2000);

// Toggle dark mode body class.
await page.evaluate(() => {
    document.body.classList.add('dark-mode', 'ap-dark');
    document.documentElement.classList.add('dark-mode');
});
await page.waitForTimeout(500);

// Check computed styles of body + h2 + .ap-card.
// Walk up from h2 looking for the white-bg ancestor.
const ancestorBg = await page.evaluate(() => {
    const h2 = document.querySelector('h2');
    if (!h2) return null;
    let cur = h2.parentElement;
    const trail = [];
    while (cur && cur !== document.body) {
        const bg = getComputedStyle(cur).backgroundColor;
        trail.push({
            tag: cur.tagName,
            id: cur.id,
            cls: cur.className?.toString().slice(0, 60),
            bg,
        });
        if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {
            return trail;
        }
        cur = cur.parentElement;
    }
    return trail;
});
console.log('Ancestor trail: ' + JSON.stringify(ancestorBg, null, 2));

const info = await page.evaluate(() => {
    const body = document.body;
    const bodyStyle = getComputedStyle(body);
    const h2 = document.querySelector('h2');
    const h2Style = h2 ? getComputedStyle(h2) : null;
    const card = document.querySelector('.ap-card');
    const cardStyle = card ? getComputedStyle(card) : null;
    return {
        bodyBg: bodyStyle.backgroundColor,
        bodyColor: bodyStyle.color,
        bodyHasDarkMode: body.classList.contains('dark-mode'),
        htmlClass: document.documentElement.className,
        h2: h2 ? { color: h2Style.color, bg: h2Style.backgroundColor } : null,
        card: card ? { color: cardStyle.color, bg: cardStyle.backgroundColor } : null,
        cssVarPrimary: bodyStyle.getPropertyValue('--ap-color-text-primary').trim(),
        cssVarBg: bodyStyle.getPropertyValue('--ap-color-bg-body').trim(),
    };
});
console.log(JSON.stringify(info, null, 2));

await browser.close();
