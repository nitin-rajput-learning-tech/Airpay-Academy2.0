// L-axis UAT: file uploads end-to-end via Moodle's repository_ajax API.
//
// Approach: drive the same HTTP pipeline that Moodle's FP YUI module uses,
// from inside Playwright (so we keep the real session). For each form:
//   1. Visit the page (Playwright login → session cookies set)
//   2. Extract draftitemid + sesskey + repo_id from the FP DOM
//   3. POST the file to /repository/repository_ajax.php?action=upload
//   4. POST the form with the draft itemid
//   5. Verify the resulting DB state (via grep on the response page)
//
// Tests all 5 file-upload surfaces:
//   - photo.php (filemanager)
//   - bulk_csv.php (filepicker)
//   - bulk_import.php (filepicker)
//   - enrol_csv.php (filepicker)
//   - import_template.php (filepicker)

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const FIXTURES = path.join(here, 'fixtures');
const BASE = 'http://localhost:8080/moodle';
const cases = [];
const record = (n, ok, d) => { cases.push({name:n, ok, detail:d}); console.log(`  ${ok?'✓':'✗'} ${n}${d?' — '+d:''}`); };

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(90000);
page.setDefaultTimeout(60000);

page.on('response', r => {
    if (r.status() >= 500) console.log(`  HTTP ${r.status()} ${r.url().slice(0, 120)}`);
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

/**
 * Upload a file to Moodle's draft area via repository_ajax.
 * @param {object} page  Playwright page (must be logged in)
 * @param {string} filepath  Local file path
 * @param {number} itemid    Draft itemid (from the form's hidden input)
 * @param {string} fpInputName  The form-element name (e.g. 'csvfile')
 * @returns {Promise<{ok:boolean, error?:string, file?:string}>}
 */
async function uploadFile(page, filepath, itemid, fpInputName, ctxId) {
    const fileBuf = await fs.readFile(filepath);
    const fileName = path.basename(filepath);
    const fileBytes = Array.from(fileBuf);

    return await page.evaluate(async (args) => {
        const { fileBytes, fileName, itemid, fpInputName, base, ctxId } = args;
        const sesskey = window.M?.cfg?.sesskey
            || document.querySelector('input[name="sesskey"]')?.value;
        if (!sesskey) return { ok: false, error: 'no sesskey' };

        // ctx_id: must match the page's context (system for site-level
        // forms; user-context for photo.php). The FP module reads it
        // from the page's filepicker config. Fall back to system (1).
        // Use the passed-in ctxId override if provided.
        const resolved_ctx = ctxId !== undefined ? String(ctxId)
            : (window.M?.cfg?.contextid || '1');

        const u8 = new Uint8Array(fileBytes);
        const file = new File([u8], fileName, { type: 'application/octet-stream' });

        // repo_id = 5 is the "upload" repository on this install.
        const fd = new FormData();
        fd.append('repo_upload_file', file);
        fd.append('repo_id', '5');
        fd.append('itemid', String(itemid));
        fd.append('sesskey', sesskey);
        fd.append('ctx_id', resolved_ctx);
        fd.append('savepath', '/');
        fd.append('title', fileName);
        fd.append('author', '');
        fd.append('license', 'unknown');

        const r = await fetch(base + '/repository/repository_ajax.php?action=upload', {
            method: 'POST',
            credentials: 'include',
            body: fd,
        });
        const text = await r.text();
        try {
            const j = JSON.parse(text);
            if (j.error) return { ok: false, error: j.error, raw: text.slice(0, 300) };
            return { ok: true, file: j.file || j.url || 'uploaded', raw: text.slice(0, 200) };
        } catch (e) {
            return { ok: false, error: 'parse: ' + e.message, raw: text.slice(0, 300) };
        }
    }, { fileBytes, fileName, itemid, fpInputName, base: BASE, ctxId });
}

/**
 * Extract a form's draftitemid (hidden input that the FP populates).
 */
async function getDraftItemId(page, fpInputName) {
    // The filepicker/filemanager creates a hidden input with the form
    // element's name (e.g. 'csvfile', 'jsonfile', 'newpicture') and a
    // value = the draft itemid (large integer). Search across the
    // whole document since the first <form> may be the navbar.
    return await page.evaluate((name) => {
        const h = document.querySelector(`input[type="hidden"][name="${name}"]`);
        if (!h || !h.value || !/^\d+$/.test(h.value)) return null;
        return parseInt(h.value, 10);
    }, fpInputName);
}

// ─────────────────────────────────────────────────────────────────
// UAT-L1.1 — bulk_csv.php (suspend/activate)
// ─────────────────────────────────────────────────────────────────
console.log('\n=== UAT-L1.1: bulk_csv.php upload+process ===');
{
    await page.goto(`${BASE}/local/airpay_users/bulk_csv.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    const itemid = await getDraftItemId(page, 'csvfile');
    record('UAT-L1.1.a draftitemid extracted', itemid !== null && itemid > 0,
        `itemid=${itemid}`);

    if (itemid) {
        const up = await uploadFile(page,
            path.join(FIXTURES, 'bulk-status.csv'), itemid, 'csvfile');
        record('UAT-L1.1.b File uploaded to draft area',
            up.ok, up.ok ? up.file : up.error);

        if (up.ok) {
            // Submit the form.
            await page.click('input[type="submit"][name="submitbutton"], button[type="submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);

            const summary = await page.evaluate(() => ({
                hasResultBlock: !!document.querySelector(
                    '[data-region="ap-bulk-csv-summary"], [data-region="ap-bulk-status-summary"], .ap-card'),
                hasCounts: /\d+ succeeded|\d+ skipped|\d+ failed/i.test(document.body.textContent),
                text: document.body.textContent.match(/Processed \d+ rows[^.]+\./)?.[0] || null,
            }));
            record('UAT-L1.1.c Result page rendered with summary',
                summary.hasCounts, summary.text || 'no counts found');
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// UAT-L1.2 — bulk_import.php (new users)
// ─────────────────────────────────────────────────────────────────
console.log('\n=== UAT-L1.2: bulk_import.php upload+process ===');
{
    await page.goto(`${BASE}/local/airpay_users/bulk_import.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    const itemid = await getDraftItemId(page, 'csvfile');
    record('UAT-L1.2.a draftitemid extracted', itemid !== null && itemid > 0,
        `itemid=${itemid}`);

    if (itemid) {
        const up = await uploadFile(page,
            path.join(FIXTURES, 'bulk-import-users.csv'), itemid, 'csvfile');
        record('UAT-L1.2.b File uploaded to draft area',
            up.ok, up.ok ? up.file : up.error);

        if (up.ok) {
            await page.click('input[type="submit"][name="submitbutton"], button[type="submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2500);

            const summary = await page.evaluate(() => ({
                hasCounts: /\d+ created|\d+ skipped|\d+ failed/i.test(document.body.textContent),
                text: document.body.textContent.match(/Processed \d+ rows[^.]+\./)?.[0]
                    || document.body.textContent.match(/\d+ created, \d+ skipped, \d+ failed/)?.[0]
                    || null,
            }));
            record('UAT-L1.2.c Result page shows succeeded/skipped/failed counts',
                summary.hasCounts, summary.text || 'no counts');
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// UAT-L1.3 — enrol_csv.php (mass-enrol)
// ─────────────────────────────────────────────────────────────────
console.log('\n=== UAT-L1.3: enrol_csv.php upload+process ===');
{
    await page.goto(`${BASE}/local/airpay_courses/enrol_csv.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    const itemid = await getDraftItemId(page, 'csvfile');
    record('UAT-L1.3.a draftitemid extracted', itemid !== null && itemid > 0,
        `itemid=${itemid}`);

    if (itemid) {
        const up = await uploadFile(page,
            path.join(FIXTURES, 'enrol-csv.csv'), itemid, 'csvfile');
        record('UAT-L1.3.b File uploaded to draft area',
            up.ok, up.ok ? up.file : up.error);

        if (up.ok) {
            await page.click('input[type="submit"][name="submitbutton"], button[type="submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2500);

            const summary = await page.evaluate(() => ({
                hasCounts: /\d+ enrolled|\d+ succeeded|\d+ skipped|\d+ failed/i.test(document.body.textContent),
                text: document.body.textContent.match(/Processed \d+ rows[^.]+\./)?.[0]
                    || document.body.textContent.match(/\d+ enrolled, \d+ skipped, \d+ failed/)?.[0]
                    || null,
            }));
            record('UAT-L1.3.c Result page shows enrol summary',
                summary.hasCounts, summary.text || 'no counts');
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// UAT-L1.4 — import_template.php (JSON eval template)
// ─────────────────────────────────────────────────────────────────
console.log('\n=== UAT-L1.4: import_template.php upload+process ===');
{
    await page.goto(`${BASE}/local/airpay_evaluation/import_template.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    const itemid = await getDraftItemId(page, 'jsonfile');
    record('UAT-L1.4.a draftitemid extracted', itemid !== null && itemid > 0,
        `itemid=${itemid}`);

    if (itemid) {
        const up = await uploadFile(page,
            path.join(FIXTURES, 'eval-template.json'), itemid, 'jsonfile');
        record('UAT-L1.4.b File uploaded to draft area',
            up.ok, up.ok ? up.file : up.error);

        if (up.ok) {
            await page.click('input[type="submit"][name="submitbutton"], button[type="submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2500);

            const ok = await page.evaluate(() => {
                const body = document.body.textContent;
                return /imported|template imported|created.*evaluation|UAT template/i
                    .test(body) || body.includes('UAT template');
            });
            record('UAT-L1.4.c Template imported as draft evaluation',
                ok, '');
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// UAT-L1.5 — photo.php (filemanager, not filepicker)
// ─────────────────────────────────────────────────────────────────
console.log('\n=== UAT-L1.5: photo.php upload+process ===');
{
    await page.goto(`${BASE}/local/airpay_users/photo.php?id=2`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    // photo.php uses 'filemanager' element which has its own itemid
    // semantics, but the draft area mechanism is the same. The hidden
    // input name is 'newpicture'.
    const itemid = await page.evaluate(() => {
        const hidden = document.querySelector('input[type="hidden"][name="newpicture"]');
        return hidden ? parseInt(hidden.value, 10) : null;
    });
    record('UAT-L1.5.a newpicture draftitemid extracted',
        itemid !== null && itemid > 0, `itemid=${itemid}`);

    if (itemid) {
        const up = await uploadFile(page,
            path.join(FIXTURES, 'test-avatar.png'), itemid, 'newpicture');
        record('UAT-L1.5.b PNG uploaded to draft area',
            up.ok, up.ok ? up.file : up.error);

        if (up.ok) {
            await page.click('input[type="submit"][name="submitbutton"], button[type="submit"]');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);

            // Should redirect to profile.php — confirm user.picture is set.
            const urlNow = page.url();
            const onProfile = urlNow.includes('profile.php');
            const notice = await page.evaluate(() => {
                const el = document.querySelector('.alert-success, .notification.success');
                return el ? el.textContent.trim().slice(0, 100) : null;
            });
            record('UAT-L1.5.c Redirected to profile + success notice',
                onProfile || (notice && /photo|updated/i.test(notice)),
                `url=${urlNow.slice(0, 80)} notice=${notice}`);
        }
    }
}

await browser.close();

const total = cases.length;
const passed = cases.filter(c => c.ok).length;
const failed = cases.filter(c => !c.ok);

console.log('\n' + '═'.repeat(60));
console.log(`L-axis File Uploads UAT: ${passed}/${total} cases pass`);
for (const f of failed) console.log(`  ✗ ${f.name} — ${f.detail}`);

process.exit(failed.length === 0 ? 0 : 1);
