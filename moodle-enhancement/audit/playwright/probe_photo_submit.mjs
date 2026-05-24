// Debug the photo.php form submission specifically.
import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
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

await page.goto(`${BASE}/local/airpay_users/photo.php?id=2`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(3000);

// Inspect form structure.
const formInfo = await page.evaluate(() => {
    const forms = Array.from(document.querySelectorAll('form'));
    return forms.map(f => ({
        action: f.action,
        method: f.method,
        id: f.id,
        nFields: f.elements.length,
        hasSubmit: !!f.querySelector('[type="submit"]'),
        submitNames: Array.from(f.querySelectorAll('[type="submit"]'))
            .map(s => s.name + '=' + s.value).slice(0, 3),
    }));
});
console.log('Forms on page: ' + JSON.stringify(formInfo, null, 2));

// Find the photo form specifically.
const photoForm = await page.evaluate(() => {
    const f = document.querySelector('form[id*="airpay_users_photo_form"]')
        || Array.from(document.querySelectorAll('form'))
            .find(f => f.querySelector('input[name="newpicture"]'));
    if (!f) return null;
    return {
        id: f.id,
        action: f.action,
        submitButton: f.querySelector('[type="submit"]')?.name,
    };
});
console.log('Photo form: ' + JSON.stringify(photoForm));

// Pull session cookies + sesskey.
const sesskey = await page.evaluate(() => window.M?.cfg?.sesskey);
const ctxId = await page.evaluate(() => window.M?.cfg?.contextid);
console.log('sesskey: ' + sesskey + ' ctxId: ' + ctxId);

// Get the draft itemid.
const itemid = await page.evaluate(() => {
    const h = document.querySelector('input[type="hidden"][name="newpicture"]');
    return h ? parseInt(h.value, 10) : null;
});
console.log('itemid: ' + itemid);

// Upload the PNG.
const fileBuf = await fs.readFile(path.join(here, 'fixtures/test-avatar.png'));
const fileBytes = Array.from(fileBuf);

const up = await page.evaluate(async (args) => {
    const u8 = new Uint8Array(args.bytes);
    const file = new File([u8], 'test-avatar.png', { type: 'image/png' });
    const fd = new FormData();
    fd.append('repo_upload_file', file);
    fd.append('repo_id', '5');
    fd.append('itemid', String(args.itemid));
    fd.append('sesskey', args.sesskey);
    fd.append('ctx_id', String(args.ctxId));
    fd.append('savepath', '/');
    fd.append('title', 'test-avatar.png');
    fd.append('author', '');
    fd.append('license', 'unknown');
    const r = await fetch(args.base + '/repository/repository_ajax.php?action=upload', {
        method: 'POST', credentials: 'include', body: fd,
    });
    return { status: r.status, body: (await r.text()).slice(0, 400) };
}, { bytes: fileBytes, itemid, sesskey, ctxId, base: BASE });
console.log('Upload result: ' + JSON.stringify(up, null, 2));

// Inspect form fields before submit.
const formFields = await page.evaluate(() => {
    const form = Array.from(document.querySelectorAll('form'))
        .find(f => f.querySelector('input[name="newpicture"]'));
    if (!form) return null;
    return Array.from(form.elements).map(e => ({
        name: e.name, type: e.type,
        value: (e.value || '').slice(0, 60),
    })).filter(e => e.name);
});
console.log('Photo form fields: ' + JSON.stringify(formFields, null, 2));

// Try a more aggressive native submit (click submitbutton + wait for nav).
const navResult = await page.evaluate(() => new Promise(resolve => {
    const form = Array.from(document.querySelectorAll('form'))
        .find(f => f.querySelector('input[name="newpicture"]'));
    if (!form) return resolve({ error: 'no form' });
    // Native submit fires the actual browser submit event, which moodleform
    // is hardened against missing-token edge cases.
    form.querySelector('input[name="submitbutton"], button[name="submitbutton"]')?.click();
    setTimeout(() => resolve({ urlnow: location.href }), 6000);
}));
console.log('After native submit click: ' + JSON.stringify(navResult));
await page.waitForTimeout(2000);
console.log('Final url: ' + page.url());

await browser.close();
