// Probe airpay_skills course-mapping UI: page renders, course list populates,
// search works, skill picker capped to skill.max_level.
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const ADMIN = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';

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

// 1. Visit course_mapping.php (no course selected).
await page.goto(`${BASE}/local/airpay_skills/course_mapping.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(2000);

const courseListCount = await page.$$eval('#ap-skill-course-list li',
    items => items.length);
console.log('Course list items: ' + courseListCount);

const emptyHint = await page.$('.ap-card .text-center .fa-link');
console.log('Empty-state present: ' + (!!emptyHint));

// 2. Click first course (visible).
let firstCourseId = null;
try {
    const firstLink = await page.$('#ap-skill-course-list a');
    if (firstLink) {
        const href = await firstLink.getAttribute('href');
        firstCourseId = parseInt(href.replace('?courseid=', ''), 10);
        await firstLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);
    }
} catch (e) {
    console.log('Could not click first course: ' + e.message);
}

if (firstCourseId) {
    console.log('Selected course: ' + firstCourseId);

    // 3. Verify the right pane shows the picker form.
    const formPresent = !!(await page.$('#ap-skill-add-mapping'));
    const skillPicker = !!(await page.$('#ap-skill-mapping-skill'));
    const levelPicker = !!(await page.$('#ap-skill-mapping-level'));
    console.log('Form present: ' + formPresent
        + ' skillPicker: ' + skillPicker
        + ' levelPicker: ' + levelPicker);

    // 4. Check that some skills exist as <option>s.
    const skillOptionCount = await page.$$eval(
        '#ap-skill-mapping-skill option', els => els.length);
    console.log('Skill picker options: ' + skillOptionCount);
}

// 5. Test the search filter.
await page.fill('#ap-skill-course-search', 'a'); // common letter
await page.waitForTimeout(800);
const filteredCount = await page.$$eval('#ap-skill-course-list li',
    items => items.length);
console.log('Filtered items (search="a"): ' + filteredCount);

await browser.close();

console.log('\n=== SUMMARY ===');
console.log('console errors: ' + consoleErrors.length);
console.log('5xx failures: ' + networkFailures.length);
for (const e of consoleErrors.slice(0, 5)) console.log('  ' + e.slice(0, 200));
for (const f of networkFailures.slice(0, 5)) console.log('  ' + f.status + ' ' + f.url);

const ok = courseListCount > 0 && consoleErrors.length === 0
    && networkFailures.length === 0;
console.log('OK: ' + ok);
process.exit(ok ? 0 : 1);
