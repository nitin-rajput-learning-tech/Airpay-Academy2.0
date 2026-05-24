// Probe Moodle's filepicker DOM to find the right selectors for upload.
import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(90000);
const errs = [];
page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

await page.goto(`${BASE}/local/airpay_users/bulk_import.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(3000);

console.log('=== Before clicking the picker ===');
const beforeInputs = await page.$$eval('input[type="file"]',
    els => els.map(e => ({class: e.className, parent: e.parentElement?.className})));
console.log('input[type=file] elements: ' + JSON.stringify(beforeInputs));

const fpButton = await page.$('.filepicker-button, [class*="filepicker"] button, .filepicker .btn');
console.log('FP button: ' + (!!fpButton));
const fpButtonText = fpButton ? await fpButton.textContent() : null;
console.log('FP button text: ' + (fpButtonText ? fpButtonText.trim() : 'n/a'));

// Click the FP button to open the modal.
if (fpButton) {
    await fpButton.click();
    await page.waitForTimeout(2500);

    console.log('\n=== After clicking FP button ===');
    const afterInputs = await page.$$eval('input[type="file"]',
        els => els.map(e => ({
            class: e.className,
            id: e.id,
            name: e.name,
            visible: e.offsetParent !== null,
            parent: e.parentElement?.className?.slice(0, 60),
        })));
    console.log('input[type=file] elements: ' + JSON.stringify(afterInputs, null, 2));

    // Look for repository tabs.
    const tabs = await page.$$eval('.fp-repo, [class*="repo-"]',
        els => els.map(e => e.textContent.trim().slice(0, 50)).slice(0, 10));
    console.log('Repository tabs: ' + JSON.stringify(tabs));

    // Look for the upload form area.
    const uploadForms = await page.$$eval('form',
        els => els.filter(f => f.enctype === 'multipart/form-data')
            .map(f => ({action: f.action, id: f.id})));
    console.log('Multipart forms: ' + JSON.stringify(uploadForms));

    // Find the "Upload a file" tab/link and click it.
    const uploadTab = await page.evaluate(() => {
        const items = Array.from(document.querySelectorAll('a, button, li'));
        const found = items.find(e =>
            e.textContent.trim().toLowerCase().includes('upload a file')
            || e.textContent.trim().toLowerCase().includes('upload file'));
        if (found) found.click();
        return found ? { tag: found.tagName, text: found.textContent.trim().slice(0, 60) } : null;
    });
    console.log('Upload tab clicked: ' + JSON.stringify(uploadTab));

    await page.waitForTimeout(2000);

    console.log('\n=== After clicking "Upload a file" ===');
    const afterUpload = await page.$$eval('input[type="file"]',
        els => els.map(e => ({
            class: e.className,
            id: e.id,
            name: e.name,
            visible: e.offsetParent !== null,
        })));
    console.log('input[type=file] elements: ' + JSON.stringify(afterUpload, null, 2));

    // Look for any input via aria.
    const inputs = await page.$$eval('input',
        els => els.filter(e => e.type === 'file' || e.accept)
            .map(e => ({
                type: e.type, name: e.name, accept: e.accept,
                visible: e.offsetParent !== null,
            })));
    console.log('All file-like inputs: ' + JSON.stringify(inputs, null, 2));
}

await page.waitForTimeout(2000);
await browser.close();
